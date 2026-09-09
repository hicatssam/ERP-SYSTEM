<?php

namespace App\Services;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendancePunch;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AttendancePunchIngestService
{
    public function __construct(
        private readonly AttendancePunchProcessor $processor
    ) {
    }

    public function ingestBatch(
        AttendanceDevice $device,
        array $items
    ): array {
        $log = AttendanceDeviceSyncLog::create([
            'attendance_device_id' => $device->id,
            'direction' => 'push',
            'status' => 'running',
            'received_count' => count($items),
            'processed_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ]);

        $created = 0;
        $duplicates = 0;
        $processed = 0;
        $unmapped = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $normalized = $this->normalize($item);

                $fingerprint = hash(
                    'sha256',
                    implode('|', [
                        $device->id,
                        $normalized['device_user_id'],
                        $normalized['punch_at']->toIso8601String(),
                        $normalized['punch_type'],
                        $normalized['external_id'] ?? '',
                    ])
                );

                $punch = AttendancePunch::query()
                    ->where('fingerprint', $fingerprint)
                    ->first();

                if ($punch) {
                    $duplicates++;
                    continue;
                }

                $punch = AttendancePunch::create([
                    'attendance_device_id' => $device->id,
                    'device_user_id' => $normalized['device_user_id'],
                    'external_id' => $normalized['external_id'],
                    'punch_at' => $normalized['punch_at'],
                    'punch_type' => $normalized['punch_type'],
                    'status' => 'pending',
                    'fingerprint' => $fingerprint,
                    'raw_payload' => $item,
                ]);

                $created++;

                $result = $this->processor->process($punch);

                if ($result === 'processed') {
                    $processed++;
                } elseif ($result === 'unmapped') {
                    $unmapped++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $device->update([
            'last_seen_at' => now(),
            'last_sync_at' => now(),
        ]);

        $status = $failed > 0
            ? ($processed > 0 ? 'partial' : 'error')
            : 'success';

        $log->update([
            'status' => $status,
            'processed_count' => $processed,
            'failed_count' => $failed,
            'finished_at' => now(),
            'message' => sprintf(
                'created=%d, duplicates=%d, processed=%d, unmapped=%d, failed=%d',
                $created,
                $duplicates,
                $processed,
                $unmapped,
                $failed
            ),
            'metadata' => [
                'created' => $created,
                'duplicates' => $duplicates,
                'unmapped' => $unmapped,
            ],
        ]);

        return [
            'received' => count($items),
            'created' => $created,
            'duplicates' => $duplicates,
            'processed' => $processed,
            'unmapped' => $unmapped,
            'failed' => $failed,
        ];
    }

    private function normalize(array $item): array
    {
        $deviceUserId = Arr::first([
            $item['device_user_id'] ?? null,
            $item['user_id'] ?? null,
            $item['pin'] ?? null,
            $item['uid'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $timestamp = Arr::first([
            $item['punch_at'] ?? null,
            $item['timestamp'] ?? null,
            $item['time'] ?? null,
            $item['att_time'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (! $deviceUserId || ! $timestamp) {
            throw ValidationException::withMessages([
                'punch' => 'كل بصمة تحتاج device_user_id و punch_at.',
            ]);
        }

        $type = strtolower((string) (
            $item['punch_type']
            ?? $item['type']
            ?? $item['state']
            ?? 'unknown'
        ));

        $type = match ($type) {
            '0', 'checkin', 'check_in', 'entry', 'in' => 'in',
            '1', 'checkout', 'check_out', 'exit', 'out' => 'out',
            'breakin', 'break_in' => 'break_in',
            'breakout', 'break_out' => 'break_out',
            default => 'unknown',
        };

        return [
            'device_user_id' => (string) $deviceUserId,
            'external_id' => isset($item['external_id'])
                ? (string) $item['external_id']
                : (isset($item['id']) ? (string) $item['id'] : null),
            'punch_at' => Carbon::parse($timestamp),
            'punch_type' => $type,
        ];
    }
}
