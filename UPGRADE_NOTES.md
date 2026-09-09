# Dahab Smart ERP — تعليمات التحديث

## الميزات المضافة

### 1. قسم طلبات المعرض (Showroom Cake Requests)
قسم جديد يسمح لكل فرع/معرض بتقديم طلب كيك للمصنع.

### 2. حقل نوع الصورة على الكيك (image_cover_type)
إضافة حقل في نموذج طلب الكيك لتحديد نوع الصورة المطبوعة.

### 3. إشعارات الجرس (Bell Notifications)
نظام الإشعارات يعمل تلقائياً — يُحدّث عدد الإشعارات كل 30 ثانية،
وعند النقر على الجرس تظهر آخر 8 إشعارات غير مقروءة.

### 4. طرق الدفع الإلكترونية
إضافة: **Pal Pay** و **Bank of Palestine** و **Jawwal Pay** و **نقداً**
كخيارات في حقل "طريقة التحويل" عند إنشاء طلب كيك.

### 5. خانة الخصم (Discount)
إضافة حقول الخصم (نسبة مئوية أو مبلغ ثابت) مع حساب الصافي تلقائياً.

---

## خطوات التثبيت (مرة واحدة فقط)

```bash
# 1. تشغيل المهاجرات الجديدة
php artisan migrate

# 2. إضافة طرق الدفع الجديدة للقاعدة
php artisan db:seed --class=ElectronicPaymentMethodSeeder

# 3. تحديث الأصول (إن وجد)
npm run build
```

---

## الملفات الجديدة

| الملف | الوصف |
|-------|-------|
| `app/Enums/ElectronicPaymentMethod.php` | Enum لطرق الدفع |
| `app/Enums/ShowroomCakeRequestStatus.php` | Enum لحالات طلبات المعرض |
| `app/Models/ShowroomCakeRequest.php` | موديل طلبات المعرض |
| `app/Models/ShowroomCakeRequestItem.php` | موديل أصناف الطلب |
| `app/Http/Controllers/Sales/ShowroomCakeRequestController.php` | كونترولر طلبات المعرض |
| `resources/views/sales/showroom-cake-requests/` | واجهات طلبات المعرض |
| `database/migrations/2026_08_05_000001_*` | حقول جديدة على special_cake_orders |
| `database/migrations/2026_08_05_000002_*` | جدول showroom_cake_requests |
| `database/seeders/ElectronicPaymentMethodSeeder.php` | Seeder لطرق الدفع |

## الملفات المعدّلة

| الملف | التعديل |
|-------|---------|
| `app/Models/SpecialCakeOrder.php` | إضافة الحقول الجديدة لـ fillable و casts |
| `app/Services/SpecialCakes/SpecialCakeOrderService.php` | دعم الخصم وطريقة الدفع ونوع الصورة |
| `app/Http/Controllers/Sales/SpecialCakeOrderController.php` | معالجة الحقول الجديدة عند الحفظ |
| `resources/views/sales/cake-orders/create.blade.php` | نموذج كامل مع الخصم والدفع والصور |
| `resources/views/sales/cake-orders/show.blade.php` | عرض الحقول الجديدة والصور |
| `routes/web.php` | إضافة routes لطلبات المعرض |
| `resources/views/layouts/app.blade.php` | إضافة رابط "طلبات المعرض" في الشريط الجانبي |
| `database/seeders/DatabaseSeeder.php` | إضافة Pal Pay و Bank of Palestine و Jawwal Pay |
