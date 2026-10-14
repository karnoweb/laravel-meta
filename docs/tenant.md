# Tenant

پیش‌فرض `meta.tenant.enabled` برابر `false` است. همه ردیف‌ها با `tenant_key=''` نوشته و خوانده می‌شوند.

## روشن کردن

Resolver باید کلاس قابل ساخت از container باشد و شیء برگشتی invokable. رشته غیرخالی تا ۶۴ کاراکتر برگرداند. closure داخل config نگذارید؛ `config:cache` آن را نگه نمی‌دارد.

```php
namespace App\Meta;

class TenantResolver
{
    public function __invoke(): string
    {
        return (string) auth()->user()->tenant_id;
    }
}
```

```php
// config/meta.php
return [
    'tenant' => [
        'enabled' => true,
        'resolver' => App\Meta\TenantResolver::class,
    ],
];
```

اگر tenant روشن باشد و resolver نباشد، کلاس ساخته نشود، invokable نباشد، یا کلید خالی/بلند برگرداند، عملیات با `MetaException` متوقف می‌شود. به tenant عمومی برنمی‌گردد.

پکیج به `current_club_id`، پکیج tenant، یا شعبه خاصی وصل نیست. هر بار از container کلاس را می‌سازد. در worker، resolver باید tenant همان job را برگرداند. کش بارگذاری‌شده با عوض شدن کلید tenant باطل می‌شود.

## دامنه

فیلتر tenant روی نوشتن، خواندن، تاریخچه، حذف، eager load، جستجو و purge اعمال می‌شود. دو tenant می‌توانند برای یک مدل و یک کلید دو root داشته باشند. حذف در tenant A ردیف tenant B را پاک نمی‌کند.

## پاکسازی

`Meta::purge($model)` و `Meta::purgeMany($models)` فقط tenant جاری و connection مدل را پاک می‌کنند. اول نسخه‌ها با query builder حذف می‌شوند، بعد rootها. این حذف سراسری بین tenantها نیست.

`deleteMeta` روی کلید نسخه‌دار tombstone است و purge نیست.

پکیج تعیین نمی‌کند کدام کاربر اجازه دیدن مدل را دارد. جداسازی tenant جایگزین authorization نیست.
