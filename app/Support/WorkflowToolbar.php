<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkflowToolbar
{
    public static function build(string $type, Model $record): array
    {
        $currentStatus = self::normalizeStatus($type, self::statusValue($record->getAttribute('status')));
        $steps = self::stepsFor($type);

        if (in_array($currentStatus, ['rejected', 'cancelled'], true)) {
            $steps[] = [
                'status' => $currentStatus,
                'label' => $currentStatus === 'rejected' ? 'مرفوض' : 'ملغى',
                'icon' => $currentStatus === 'rejected' ? 'x-circle' : 'ban',
                'role' => $currentStatus === 'rejected' ? 'صاحب صلاحية الرفض' : 'صاحب صلاحية الإلغاء',
                'kind' => 'danger',
            ];
        }

        $history = self::historyFor($type, $record);
        $currentIndex = collect($steps)->search(fn (array $step) => $step['status'] === $currentStatus);
        $knownIndex = $currentIndex !== false;

        return collect($steps)->map(function (array $step, int $index) use ($record, $type, $history, $currentStatus, $currentIndex, $knownIndex) {
            $status = $step['status'];
            $historyRow = $history->get($status);

            $state = 'pending';
            if ($status === $currentStatus) {
                $state = in_array($currentStatus, ['rejected', 'cancelled'], true) ? 'danger' : 'current';
            } elseif ($historyRow) {
                $state = 'done';
            } elseif ($knownIndex && ! in_array($currentStatus, ['rejected', 'cancelled'], true) && $index < $currentIndex) {
                $state = 'done';
            }

            [$actor, $at, $note] = self::stepMeta($type, $record, $status, $historyRow);

            return array_merge($step, [
                'state' => $state,
                'actor' => $actor,
                'at' => $at,
                'note' => $note,
                'is_delivery' => ($step['kind'] ?? null) === 'delivery',
            ]);
        })->values()->all();
    }

    public static function title(string $type): string
    {
        return match ($type) {
            'special_cake' => 'مسار طلب الكيك',
            'showroom_sweets' => 'مسار طلب حلويات الفرع',
            'showroom_cake' => 'مسار طلب كيك الفرع',
            'order' => 'مسار الطلب',
            default => 'مسار حالة الطلب',
        };
    }

    public static function subtitle(string $type): string
    {
        return match ($type) {
            'special_cake' => 'أربع مراحل واضحة من المراجعة حتى اكتمال الطلب',
            'showroom_sweets' => 'أربع مراحل واضحة من المراجعة حتى استلام الفرع',
            'showroom_cake' => 'أربع مراحل واضحة من المراجعة حتى استلام الفرع',
            'order' => 'متابعة حالة الطلب من الإنشاء حتى الإكمال',
            default => 'متابعة حالة العملية خطوة بخطوة',
        };
    }

    private static function stepsFor(string $type): array
    {
        return match ($type) {
            'special_cake' => [
                ['status'=>'pending','label'=>'قيد المراجعة','icon'=>'search-check','role'=>'الفرع / المصنع','kind'=>'normal'],
                ['status'=>'in_progress','label'=>'قيد التنفيذ','icon'=>'chef','role'=>'فريق الإنتاج','kind'=>'normal'],
                ['status'=>'ready','label'=>'جاهز للاستلام','icon'=>'package-check','role'=>'الفرع / التسليم','kind'=>'normal'],
                ['status'=>'completed','label'=>'مكتمل','icon'=>'flag','role'=>'الفرع','kind'=>'normal'],
            ],
            'showroom_sweets' => [
                ['status'=>'pending','label'=>'قيد المراجعة','icon'=>'search-check','role'=>'الفرع / المصنع','kind'=>'normal'],
                ['status'=>'in_progress','label'=>'قيد التنفيذ','icon'=>'chef','role'=>'المصنع / الإنتاج','kind'=>'normal'],
                ['status'=>'ready','label'=>'جاهز للاستلام','icon'=>'package-check','role'=>'الفرع / التسليم','kind'=>'normal'],
                ['status'=>'completed','label'=>'مكتمل','icon'=>'flag','role'=>'الفرع','kind'=>'normal'],
            ],
            'showroom_cake' => [
                ['status'=>'pending','label'=>'قيد المراجعة','icon'=>'search-check','role'=>'الفرع / المصنع','kind'=>'normal'],
                ['status'=>'in_progress','label'=>'قيد التنفيذ','icon'=>'chef','role'=>'المصنع / الإنتاج','kind'=>'normal'],
                ['status'=>'ready','label'=>'جاهز للاستلام','icon'=>'package-check','role'=>'الفرع / التسليم','kind'=>'normal'],
                ['status'=>'completed','label'=>'مكتمل','icon'=>'flag','role'=>'الفرع','kind'=>'normal'],
            ],
            'order' => [
                ['status'=>'draft','label'=>'إنشاء الطلب','icon'=>'file-plus','role'=>'منشئ الطلب','kind'=>'normal'],
                ['status'=>'confirmed','label'=>'تأكيد الطلب','icon'=>'check-circle','role'=>'الكاشير / الفرع','kind'=>'normal'],
                ['status'=>'completed','label'=>'مكتمل','icon'=>'flag','role'=>'الفرع','kind'=>'normal'],
            ],
            default => [],
        };
    }

    private static function normalizeStatus(string $type, string $status): string
    {
        return match ($type) {
            'special_cake' => match ($status) {
                'draft',
                'pending_deposit',
                'deposit_paid',
                'pending_factory_review',
                'modification_requested' => 'pending',

                'accepted',
                'scheduled',
                'in_preparation',
                'decorating',
                'in_decoration',
                'quality_check',
                'delayed',
                'issue_open' => 'in_progress',

                'ready',
                'sent_to_branch',
                'dispatched_to_branch',
                'received_by_branch',
                'received_at_branch',
                'ready_for_customer',
                'ready_for_pickup' => 'ready',

                'delivered' => 'completed',

                'rejected',
                'canceled' => 'cancelled',

                default => $status,
            },
            'showroom_cake' => match ($status) {
                'draft',
                'submitted',
                'pending_factory_review',
                'modification_requested' => 'pending',

                'accepted',
                'scheduled',
                'in_preparation',
                'decorating',
                'quality_check' => 'in_progress',

                'ready_for_dispatch',
                'out_for_delivery',
                'sent_to_branch',
                'dispatched_to_branch',
                'received_by_branch',
                'received_at_branch',
                'ready_for_customer',
                'ready_for_pickup' => 'ready',

                'fulfilled',
                'delivered' => 'completed',

                'rejected',
                'canceled' => 'cancelled',

                default => $status,
            },
            'showroom_sweets' => match ($status) {
                'submitted' => 'pending',
                'ready_for_dispatch',
                'out_for_delivery' => 'ready',
                'received_at_branch',
                'fulfilled' => 'completed',
                'rejected',
                'canceled' => 'cancelled',
                default => $status,
            },
            'order' => match ($status) {
                'pending' => 'draft',
                'canceled' => 'cancelled',
                default => $status,
            },
            default => $status,
        };
    }

    private static function statusValue(mixed $status): string
    {
        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }

    private static function historyFor(string $type, Model $record): Collection
    {
        if (! in_array($type, ['special_cake', 'showroom_cake'], true) || ! method_exists($record, 'statusHistories')) {
            return collect();
        }

        try {
            $record->loadMissing('statusHistories.changedBy.employee');
            return $record->statusHistories
                ->sortBy('created_at')
                ->keyBy(fn ($row) => self::normalizeStatus($type, self::statusValue($row->to_status)));
        } catch (\Throwable) {
            return collect();
        }
    }

    private static function stepMeta(string $type, Model $record, string $status, mixed $historyRow): array
    {
        if ($historyRow) {
            return [
                self::userName($historyRow->changedBy ?? null),
                self::dateValue($historyRow->created_at ?? null),
                $historyRow->note ?? $historyRow->notes ?? null,
            ];
        }

        return match ($type) {
            'special_cake' => self::specialCakeMeta($record, $status),
            'showroom_sweets' => self::showroomSweetsMeta($record, $status),
            'showroom_cake' => self::showroomCakeMeta($record, $status),
            'order' => self::orderMeta($record, $status),
            default => [null, null, null],
        };
    }

    private static function specialCakeMeta(Model $record, string $status): array
    {
        return match ($status) {
            'pending' => [
                self::relationUserName($record, 'creator'),
                self::dateValue($record->created_at),
                null,
            ],
            'completed' => [
                null,
                self::dateValue($record->completed_at ?? null),
                null,
            ],
            default => [null, null, null],
        };
    }

    private static function showroomSweetsMeta(Model $record, string $status): array
    {
        return match ($status) {
            'pending' => [
                self::relationUserName($record, 'creator'),
                self::dateValue($record->submitted_at ?? $record->created_at),
                null,
            ],
            'in_progress' => [
                self::relationUserName($record, 'handledBy'),
                null,
                $record->factory_notes ?? null,
            ],
            'ready' => [
                null,
                self::statusValue($record->getAttribute('status')) === 'ready'
                    ? self::dateValue($record->updated_at)
                    : null,
                $record->factory_notes ?? null,
            ],
            'completed' => [
                self::relationUserName($record, 'receivedBy'),
                self::dateValue($record->received_at ?? $record->fulfilled_at ?? null),
                null,
            ],
            default => [null, null, null],
        };
    }

    private static function showroomCakeMeta(Model $record, string $status): array
    {
        return match ($status) {
            'pending' => [
                self::relationUserName($record, 'creator'),
                self::dateValue($record->submitted_at ?? $record->created_at),
                null,
            ],
            'in_progress' => [
                self::relationUserName($record, 'handledBy'),
                null,
                $record->factory_notes ?? null,
            ],
            'ready' => [
                null,
                self::statusValue($record->getAttribute('status')) === 'ready'
                    ? self::dateValue($record->updated_at)
                    : null,
                $record->factory_notes ?? null,
            ],
            'completed' => [
                self::relationUserName($record, 'handledBy'),
                self::dateValue($record->fulfilled_at ?? null),
                null,
            ],
            default => [null, null, null],
        };
    }

    private static function orderMeta(Model $record, string $status): array
    {
        return match ($status) {
            'draft' => [self::relationUserName($record, 'creator'), self::dateValue($record->created_at), null],
            'confirmed' => [null, self::dateValue($record->confirmed_at ?? null), null],
            'completed' => [null, self::dateValue($record->completed_at ?? null), null],
            'cancelled' => [null, self::dateValue($record->cancelled_at ?? null), $record->cancellation_reason ?? null],
            default => [null, null, null],
        };
    }

    private static function relationUserName(Model $record, string $relation): ?string
    {
        if (! method_exists($record, $relation)) {
            return null;
        }

        try {
            $record->loadMissing($relation . '.employee');
            return self::userName($record->getRelation($relation));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function userName(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->employee?->full_name ?? $user->name ?? $user->username ?? null;
    }

    private static function dateValue(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}