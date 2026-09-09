<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Location;
use App\Models\RestaurantArea;
use App\Models\RestaurantTable;
use App\Models\RestaurantTableSession;
use App\Models\User;
use App\Notifications\RestaurantTableSessionNotification;
use Tests\TestCase;

class RestaurantTableSessionNotificationTest extends TestCase
{
    public function test_open_notification_is_arabic_and_uses_employee_full_name(): void
    {
        $employee = new Employee(['full_name' => 'أحمد محمود']);
        $user = new User(['username' => 'admin']);
        $user->id = 15;
        $user->setRelation('employee', $employee);

        $area = new RestaurantArea(['name' => 'الصالة الرئيسية']);
        $table = new RestaurantTable(['name' => 'طاولة العائلة', 'code' => 'T-01']);
        $table->id = 7;
        $table->setRelation('area', $area);

        $location = new Location(['name' => 'فرع المدينة']);
        $location->id = 3;

        $session = new RestaurantTableSession([
            'restaurant_table_id' => 7,
            'location_id' => 3,
            'opened_by' => 15,
            'status' => 'open',
            'guest_count' => 4,
        ]);
        $session->id = 22;
        $session->setRelation('table', $table);
        $session->setRelation('location', $location);
        $session->setRelation('openedBy', $user);
        $session->setRelation('closedBy', null);

        $payload = (new RestaurantTableSessionNotification($session, 'opened'))
            ->toDatabase($user);

        $this->assertSame('تم فتح جلسة طاولة', $payload['title']);
        $this->assertStringContainsString('أحمد محمود', $payload['message']);
        $this->assertStringNotContainsString('admin', $payload['message']);
        $this->assertSame('/restaurant/tables?location_id=3', $payload['url']);
    }

    public function test_close_notification_has_an_arabic_title(): void
    {
        $employee = new Employee(['full_name' => 'سارة علي']);
        $user = new User(['username' => 'cashier']);
        $user->id = 18;
        $user->setRelation('employee', $employee);

        $table = new RestaurantTable(['name' => 'طاولة 5', 'code' => 'T-05']);
        $location = new Location(['name' => 'فرع السوق']);
        $session = new RestaurantTableSession([
            'restaurant_table_id' => 5,
            'location_id' => 4,
            'closed_by' => 18,
            'status' => 'closed',
        ]);
        $session->id = 23;
        $session->setRelation('table', $table);
        $session->setRelation('location', $location);
        $session->setRelation('openedBy', null);
        $session->setRelation('closedBy', $user);

        $payload = (new RestaurantTableSessionNotification($session, 'closed'))
            ->toDatabase($user);

        $this->assertSame('تم إغلاق جلسة طاولة', $payload['title']);
        $this->assertStringContainsString('سارة علي', $payload['message']);
        $this->assertStringNotContainsString('cashier', $payload['message']);
    }
}
