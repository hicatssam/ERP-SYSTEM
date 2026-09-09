<?php

namespace App\Notifications;

use App\Models\RestaurantTableSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RestaurantTableSessionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly RestaurantTableSession $session,
        private readonly string $event
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $session = $this->session->loadMissing([
            'table.area',
            'location',
            'openedBy.employee',
            'closedBy.employee',
        ]);

        $tableName = $session->table?->displayName() ?? 'طاولة';
        $areaName = $session->table?->area?->name;
        $locationName = $session->location?->name ?? 'الفرع';

        $isOpened = $this->event === 'opened';

        $actor = $isOpened
            ? $session->openedBy
            : $session->closedBy;

        $actorName = $actor?->display_name ?? 'مستخدم نظام';

        return [
            'fingerprint' => 'restaurant_table_session_' . $this->event . '_' . $session->id,
            'type' => 'restaurant_table_session',
            'title' => $isOpened
                ? 'تم فتح جلسة طاولة'
                : 'تم إغلاق جلسة طاولة',
            'message' => trim(
                $locationName
                . ' — '
                . ($areaName ? $areaName . ' — ' : '')
                . $tableName
                . ' — بواسطة '
                . $actorName
            ),
            'restaurant_table_session_id' => $session->id,
            'restaurant_table_id' => $session->restaurant_table_id,
            'location_id' => $session->location_id,
            'event' => $this->event,
            'url' => '/restaurant/tables?location_id=' . $session->location_id,
            'priority' => 'medium',
        ];
    }
}
