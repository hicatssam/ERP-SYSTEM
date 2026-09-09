# التشغيل الأول على Windows وWAMP

نفّذ الأوامر داخل مجلد المشروع بالترتيب:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan system:audit-logic
php artisan serve --host=0.0.0.0 --port=8000
```

ملف `.env.example` مضبوط افتراضيًا على MySQL وقاعدة `dahab_sweets` والمستخدم `root` دون كلمة مرور. عدّل القيم قبل `migrate` إذا كانت إعدادات WAMP مختلفة.

الحزمة تتضمن ملفات `.gitignore` التي تحفظ مجلدات:

- `storage/framework/views`
- `storage/framework/sessions`
- `storage/framework/cache`
- `bootstrap/cache`

لذلك لا يجب إنشاء مسار View cache يدويًا بعد فك هذه النسخة.
