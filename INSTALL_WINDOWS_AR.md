# تشغيل المشروع على Windows وWampServer

هذه الحزمة لا تحتوي مجلد `vendor`، لذلك يجب تنزيل اعتماديات Laravel قبل تشغيل أي أمر Artisan.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan system:audit-logic
php artisan serve --host=0.0.0.0 --port=8000
```

إذا كان لديك ملف `.env` جاهز وقاعدة MySQL موجودة، لا تنسخ `.env.example` فوقه ولا تنشئ مفتاحًا جديدًا؛ احتفظ بالمفتاح الحالي وشغّل `composer install` ثم بقية أوامر الفحص.

