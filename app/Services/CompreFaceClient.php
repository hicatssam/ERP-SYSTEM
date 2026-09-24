<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CompreFaceClient
{
    public function configured(): bool
    {
        return $this->baseUrl() !== ''
            && $this->apiKey() !== '';
    }

    public function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'attendance-face.compreface.base_url'
            ),
            '/'
        );
    }

    public function apiKey(): string
    {
        return trim(
            (string) config(
                'attendance-face.compreface.api_key'
            )
        );
    }

    public function ping(): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $response = $this->request()
                ->get(
                    $this->baseUrl()
                    . '/api/v1/recognition/subjects/'
                );

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function enrollSubject(
        string $subject,
        array $images
    ): array {
        $this->assertConfigured();

        $images = array_values(
            array_filter(
                array_map(
                    fn ($image) =>
                        $this->normalizeBase64Image(
                            (string) $image
                        ),
                    $images
                )
            )
        );

        if (count($images) < 2) {
            throw ValidationException::withMessages([
                'images' =>
                    'يجب التقاط صورتين واضحتين على الأقل لتسجيل الوجه.',
            ]);
        }

        $imageIds = [];

        try {
            foreach ($images as $image) {
                $response = $this->request()
                    ->post(
                        $this->baseUrl()
                        . '/api/v1/recognition/faces?'
                        . http_build_query([
                            'subject' => $subject,
                            'det_prob_threshold' =>
                                $this->detectorThreshold(),
                        ]),
                        [
                            'file' => $image,
                        ]
                    );

                $this->ensureSuccessful(
                    $response,
                    'تعذر تسجيل صورة الوجه في CompreFace.'
                );

                $imageId = trim(
                    (string) $response->json(
                        'image_id'
                    )
                );

                if ($imageId !== '') {
                    $imageIds[] = $imageId;
                }
            }
        } catch (\Throwable $exception) {
            try {
                $this->deleteSubject(
                    $subject,
                    false
                );
            } catch (\Throwable) {
                // Best-effort rollback only.
            }

            if (
                $exception
                instanceof ValidationException
            ) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'images' =>
                    'تعذر إكمال تسجيل الوجه في CompreFace.',
            ]);
        }

        return [
            'subject' => $subject,
            'image_ids' => $imageIds,
            'examples_count' => count($images),
        ];
    }

    public function recognize(
        string $image,
        bool $withPose = false
    ): array {
        $this->assertConfigured();

        $image = $this->normalizeBase64Image(
            $image
        );

        $plugins = $withPose
            ? 'pose'
            : null;

        $query = [
            // 0 means no limit: return every detected face so Laravel can
            // reject frames containing more than one person.
            'limit' => 0,
            'prediction_count' => 1,
            'det_prob_threshold' =>
                $this->detectorThreshold(),
            'status' => 'false',
        ];

        if ($plugins) {
            $query['face_plugins'] =
                $plugins;
        }

        $response = $this->request()
            ->post(
                $this->baseUrl()
                . '/api/v1/recognition/recognize?'
                . http_build_query($query),
                [
                    'file' => $image,
                ]
            );

        $this->ensureSuccessful(
            $response,
            'تعذر التحقق من الوجه عبر CompreFace.'
        );

        $result = $response->json(
            'result',
            []
        );

        if (! is_array($result)) {
            $result = [];
        }

        if (count($result) !== 1) {
            throw ValidationException::withMessages([
                'face' => count($result) === 0
                    ? 'لم يتم اكتشاف وجه واضح أمام الكاميرا.'
                    : 'يجب أن يظهر وجه واحد فقط أمام الكاميرا.',
            ]);
        }

        $face = $result[0];

        $subjectData =
            data_get(
                $face,
                'subjects.0'
            );

        $subject = trim(
            (string) data_get(
                $subjectData,
                'subject'
            )
        );

        $similarity = (float) data_get(
            $subjectData,
            'similarity',
            0
        );

        return [
            'subject' =>
                $subject !== ''
                    ? $subject
                    : null,
            'similarity' =>
                $similarity,
            'box_probability' =>
                (float) data_get(
                    $face,
                    'box.probability',
                    0
                ),
            'pose' => [
                'pitch' => $this->floatOrNull(
                    data_get(
                        $face,
                        'pose.pitch'
                    )
                ),
                'roll' => $this->floatOrNull(
                    data_get(
                        $face,
                        'pose.roll'
                    )
                ),
                'yaw' => $this->floatOrNull(
                    data_get(
                        $face,
                        'pose.yaw'
                    )
                ),
            ],
        ];
    }

    public function deleteSubject(
        string $subject,
        bool $throwOnMissing = true
    ): void {
        $this->assertConfigured();

        $response = $this->request()
            ->delete(
                $this->baseUrl()
                . '/api/v1/recognition/subjects/'
                . rawurlencode($subject)
            );

        if (
            ! $throwOnMissing
            && $response->status() === 404
        ) {
            return;
        }

        $this->ensureSuccessful(
            $response,
            'تعذر حذف بيانات الوجه من CompreFace.'
        );
    }

    public function detectorThreshold(): float
    {
        return max(
            0.0,
            min(
                1.0,
                (float) config(
                    'attendance-face.compreface.det_prob_threshold',
                    0.80
                )
            )
        );
    }

    public function similarityThreshold(): float
    {
        return max(
            0.0,
            min(
                1.0,
                (float) config(
                    'attendance-face.compreface.similarity_threshold',
                    0.78
                )
            )
        );
    }

    private function request(): PendingRequest
    {
        return Http::timeout(
            max(
                3,
                (int) config(
                    'attendance-face.compreface.timeout_seconds',
                    12
                )
            )
        )
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-api-key' =>
                    $this->apiKey(),
            ]);
    }

    private function normalizeBase64Image(
        string $image
    ): string {
        $image = trim($image);

        if (
            preg_match(
                '/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/s',
                $image,
                $matches
            )
        ) {
            $image = $matches[1];
        }

        $decoded = base64_decode(
            $image,
            true
        );

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'image' =>
                    'بيانات صورة الوجه غير صالحة.',
            ]);
        }

        if (strlen($decoded) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'image' =>
                    'حجم صورة الوجه أكبر من الحد المسموح.',
            ]);
        }

        return base64_encode(
            $decoded
        );
    }

    private function ensureSuccessful(
        Response $response,
        string $fallbackMessage
    ): void {
        if ($response->successful()) {
            return;
        }

        $message = trim(
            (string) (
                $response->json('message')
                ?? $response->json('error')
                ?? ''
            )
        );

        throw ValidationException::withMessages([
            'compreface' =>
                $message !== ''
                    ? $message
                    : $fallbackMessage
                        . ' HTTP '
                        . $response->status(),
        ]);
    }

    private function assertConfigured(): void
    {
        if ($this->configured()) {
            return;
        }

        throw ValidationException::withMessages([
            'face_configuration' =>
                'CompreFace غير مهيأ بعد. أضف COMPREFACE_BASE_URL وCOMPREFACE_API_KEY.',
        ]);
    }

    private function floatOrNull(
        mixed $value
    ): ?float {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
