# Changelog

## [Unreleased]

اولین پیاده‌سازی `karnoweb/laravel-meta`.

- جدول‌های `metas` و `meta_versions` با migration ثابت `2020_01_01_090000_create_meta_tables.php`.
- متادیتای نوع‌دار: null، string، int، decimal، bool، date، datetime، json.
- نسخه‌بندی فقط با `versioned: true`. به‌روزرسانی کلید عادی تاریخچه نمی‌سازد.
- جستجوی Eloquent نوع‌دار، `withMeta`، و `Meta::load`.
- Tenant اختیاری با resolver کلاسی و رفتار fail-closed.
- پاکسازی صریح با `Meta::purge`. حذف نرم مدل متادیتا را نگه می‌دارد. `purge_on_force_delete` پیش‌فرض خاموش است.

برچسب پیشنهادی پس از سبز شدن jobهای MySQL و PostgreSQL: `0.1.0`.
