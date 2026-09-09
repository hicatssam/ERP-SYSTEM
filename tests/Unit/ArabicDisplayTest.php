<?php

namespace Tests\Unit;

use App\Support\ArabicDisplay;
use App\Models\Employee;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArabicDisplayTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function test_raw_statuses_are_rendered_in_arabic(string $status, string $expected): void
    {
        $this->assertSame($expected, ArabicDisplay::status($status));
    }

    public static function statusProvider(): array
    {
        return [
            ['draft', 'مسودة'],
            ['posted', 'مرحّلة'],
            ['partially_received', 'مستلمة جزئيًا'],
            ['pending_dispatch', 'بانتظار الإرسال'],
            ['unknown_status', 'حالة غير معرّفة'],
        ];
    }

    public function test_roles_and_currencies_are_rendered_in_arabic(): void
    {
        $this->assertSame('مدير النظام', ArabicDisplay::role('Admin'));
        $this->assertSame('شيكل', ArabicDisplay::currency('ILS'));
        $this->assertSame('عملة غير معرّفة', ArabicDisplay::currency('XYZ'));
    }

    public function test_user_display_name_uses_linked_employee_full_name_and_never_username(): void
    {
        $user = new User(['username' => 'admin']);
        $user->id = 7;
        $user->setRelation('employee', new Employee(['full_name' => 'لؤي سام']));

        $this->assertSame('لؤي سام', $user->display_name);

        $user->setRelation('employee', null);
        $this->assertSame('مستخدم رقم 7', $user->display_name);
    }
}
