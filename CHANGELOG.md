# Changelog

## Unreleased

## 13.0.0 — 2026-10-14

- کلید branch روی MySQL با collation باینری ذخیره می‌شود. `Branch-A` و `branch-a` جدا هستند. فاصله و غیر NFC رد می‌شود.
- decimal دیگر float نمی‌پذیرد. مقدار canonical در `value_decimal_text` و ترتیب عددی در `value_decimal_sort` نگه داشته می‌شود تا SQLite دقت را به float تقلیل ندهد.
- برابری رشته از hash استفاده می‌کند تا ایندکس روی رشته ۵۱۲ کاراکتری، درج فارسی و ایموجی را نشکند. خود مقدار هم برای جلوگیری از برخورد hash چک می‌شود.
- `getMetas` / `Meta::getMany` و `metaHistoryPage` / `Meta::historyPage` اضافه شد.
- `purge` در هر connection یک تراکنش است و شناسه‌ها را ۵۰۰تایی chunk می‌کند.
- JSON تودرتو در SQLite به‌جای نتیجه خالی خطا می‌دهد.
- نصب‌هایی که migration اول را قبلاً اجرا کرده‌اند با `2020_01_01_090001_align_meta_exact_storage.php` ارتقا می‌یابند. دقت decimal ازدست‌رفته بازیابی نمی‌شود.

اولین پیاده‌سازی `karnoweb/laravel-meta`.

- جدول‌های `metas` و `meta_versions` با migration ثابت `2020_01_01_090000_create_meta_tables.php`.
- متادیتای نوع‌دار: null، string، int، decimal، bool، date، datetime، json.
- نسخه‌بندی فقط با `versioned: true`. به‌روزرسانی کلید عادی تاریخچه نمی‌سازد.
- جستجوی Eloquent نوع‌دار، `withMeta`، و `Meta::load`.
- Branch اختیاری با resolver کلاسی و رفتار fail-closed.
- پاکسازی صریح با `Meta::purge`. حذف نرم مدل متادیتا را نگه می‌دارد. `purge_on_force_delete` پیش‌فرض خاموش است.
