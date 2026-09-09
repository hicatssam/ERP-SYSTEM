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
            'special_cake' => 'من إنشاء الطلب حتى الإنتاج والتزيين والجودة والتوصيل والاستلام',
            'showroom_sweets' => 'من إرسال الفرع حتى تجهيز المصنع والتوصيل واستلام الفرع',
            'showroom_cake' => 'إرسال الفرع، مراجعة المصنع، التجهيز، التوصيل ثم تأكيد استلام الفرع',
            'order' => 'متابعة حالة الطلب من الإنشاء حتى الإكمال',
            default => 'متابعة حالة العملية خطوة بخطوة',
        };
    }

    private static function stepsFor(string $type): array
    {
        return match ($type) {
            'special_cake' => [
                ['status'=>'draft','label'=>'إنشاء الطلب','icon'=>'file-plus','role'=>'الفرع / الكاشير','kind'=>'normal'],
                ['status'=>'pending_factory_review','label'=>'مراجعة المصنع','icon'=>'factory','role'=>'مدير المصنع','kind'=>'normal'],
                ['status'=>'accepted','label'=>'قبول الطلب','icon'=>'check-circle','role'=>'مدير المصنع','kind'=>'normal'],
                ['status'=>'scheduled','label'=>'جدولة الإنتاج','icon'=>'calendar','role'=>'مدير المصنع','kind'=>'normal'],
                ['status'=>'in_preparation','label'=>'التحضير','icon'=>'chef','role'=>'موظف الإنتاج','kind'=>'normal'],
                ['status'=>'decorating','label'=>'تزيين الكيك','icon'=>'cake','role'=>'مصمم الكيك','kind'=>'normal'],
                ['status'=>'quality_check','label'=>'فحص الجودة','icon'=>'search-check','role'=>'مراقب الجودة','kind'=>'normal'],
                ['status'=>'ready','label'=>'جاهز للتوصيل','icon'=>'package-check','role'=>'بانتظار موظف التوصيل','kind'=>'delivery'],
                ['status'=>'sent_to_branch','label'=>'خرج للتوصيل','icon'=>'truck','role'=>'موظف التوصيل','kind'=>'delivery'],
                ['status'=>'received_by_branch','label'=>'استلمه الفرع','icon'=>'store-check','role'=>'مدير / موظف الفرع','kind'=>'delivery'],
                ['status'=>'ready_for_customer','label'=>'جاهز للعميل','icon'=>'user-check','role'=>'الفرع','kind'=>'normal'],
                ['status'=>'completed','label'=>'مكتمل','icon'=>'flag','role'=>'الفرع','kind'=>'normal'],
            ],
            'showroom_sweets' => [
                ['status'=>'submitted','label'=>'إرسال الطلب','icon'=>'send','role'=>'الفرع','kind'=>'normal'],
                ['status'=>'in_progress','label'=>'قيد التجهيز','icon'=>'chef','role'=>'المصنع / الإنتاج','kind'=>'normal'],
                ['status'=>'ready_for_dispatch','label'=>'جاهز للتوصيل','icon'=>'package-check','role'=>'بانتظار موظف التوصيل','kind'=>'delivery'],
                ['status'=>'out_for_delivery','label'=>'خرج للتوصيل','icon'=>'truck','role'=>'موظف التوصيل','kind'=>'delivery'],
                ['status'=>'received_at_branch','label'=>'استلمه الفرع','icon'=>'store-check','role'=>'مدير / موظف الفرع','kind'=>'delivery'],
            ],
            'showroom_cake' => [
                ['status'=>'submitted','label'=>'إرسال الطلب','icon'=>'send','role'=>'مدير / موظف الفرع','kind'=>'normal'],
                ['status'=>'pending_factory_review','label'=>'مراجعة المصنع','icon'=>'factory','role'=>'مدير المصنع','kind'=>'normal'],
                ['status'=>'accepted','label'=>'قبول الطلب','icon'=>'check-circle','role'=>'مدير المصنع','kind'=>'normal'],
                ['status'=>'in_preparation','label'=>'قيد التجهيز','icon'=>'chef','role'=>'موظف الإنتاج','kind'=>'normal'],
                ['status'=>'ready','label'=>'جاهز للتوصيل','icon'=>'package-check','role'=>'بانتظار موظف التوصيل','kind'=>'delivery'],
                ['status'=>'sent_to_branch','label'=>'خرج للتوصيل','icon'=>'truck','role'=>'موظف التوصيل','kind'=>'delivery'],
                ['status'=>'received_by_branch','label'=>'استلمه الفرع','icon'=>'store-check','role'=>'مدير / موظف الفرع','kind'=>'delivery'],
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
                'in_decoration' => 'decorating',
                'dispatched_to_branch' => 'sent_to_branch',
                'received_at_branch' => 'received_by_branch',
                'ready_for_pickup' => 'ready_for_customer',
                'delivered' => 'completed',
                default => $status,
            },
            'showroom_cake' => match ($status) {
                'in_progress' => 'in_preparation',
                'ready_for_dispatch' => 'ready',
                'out_for_delivery' => 'sent_to_branch',
                'dispatched_to_branch' => 'sent_to_branch',
                'received_at_branch' => 'received_by_branch',
                'fulfilled' => 'completed',
                'delivered' => 'completed',
                'canceled' => 'cancelled',
                default => $status,
            },
            'showroom_sweets' => match ($status) {
                'fulfilled' => 'received_at_branch',
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
            'draft' => [self::relationUserName($record, 'creator'), self::dateValue($record->created_at), null],
            'scheduled' => [null, self::dateValue($record->scheduled_at ?? null), null],
            'completed' => [null, self::dateValue($record->completed_at ?? null), null],
            default => [null, null, null],
        };
    }

    private static function showroomSweetsMeta(Model $record, string $status): array
    {
        return match ($status) {
            'submitted' => [self::relationUserName($record, 'creator'), self::dateValue($record->submitted_at ?? $record->created_at), null],
            'in_progress' => [self::relationUserName($record, 'handledBy'), null, $record->factory_notes ?? null],
            'ready_for_dispatch' => [null, self::statusValue($record->getAttribute('status')) === 'ready_for_dispatch' ? self::dateValue($record->updated_at) : null, $record->factory_notes ?? null],
            'out_for_delivery' => [self::relationUserName($record, 'dispatchedBy'), self::dateValue($record->dispatched_at ?? null), null],
            'received_at_branch' => [self::relationUserName($record, 'receivedBy'), self::dateValue($record->received_at ?? $record->fulfilled_at ?? null), null],
            default => [null, null, null],
        };
    }

    private static function showroomCakeMeta(Model $record, string $status): array
    {
        $relation = match ($status) {
            'submitted' => 'creator',
            'sent_to_branch' => 'dispatchedBy',
            'received_by_branch' => 'receivedBy',
            default => null,
        };

        $date = match ($status) {
            'submitted' => $record->submitted_at ?? $record->created_at,
            'sent_to_branch' => $record->dispatched_at ?? null,
            'received_by_branch' => $record->received_at ?? null,
            'completed' => $record->completed_at ?? null,
            default => null,
        };

        return [$relation ? self::relationUserName($record, $relation) : null, self::dateValue($date), null];
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