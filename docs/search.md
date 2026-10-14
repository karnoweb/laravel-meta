# جستجو

همه scopeها روی مدلی که `HasMeta` دارد کار می‌کنند. شرط با `whereExists` به مدل وصل می‌شود. سطر محصول تکرار نمی‌شود و `paginate` تعداد را از روی join متادیتا باد نمی‌کند.

اپراتورهای مجاز: `= != <> > >= < <=`. اپراتور دیگر `MetaQueryException` می‌دهد. مقایسه ترتیبی فقط برای `int` و `decimal` و `date` و `datetime` است.

مقدار و اپراتور و الگو bind می‌شوند. نام کلید شناسه SQL نیست.

## scopeها

```php
Product::query()->whereMeta('color', '=', 'red');
Product::query()->orWhereMeta('color', '=', 'blue');
Product::query()->whereMeta('count', '>=', 10);
Product::query()->whereMeta('amount', '=', '10.20', type: 'decimal');
Product::query()->whereMetaIn('count', [10, 20]);
Product::query()->whereMetaBetween('count', 10, 20);
Product::query()->whereMetaLike('color', '%red%');
Product::query()->whereMetaNull('note');
Product::query()->whereMetaNotNull('note');
Product::query()->whereHasMeta('color');
Product::query()->whereDoesntHaveMeta('color');
Product::query()->whereMetaJsonContains('tags', 'vip');
Product::query()->whereMetaAt('price', '=', '100.00', '2027-01-10 00:00:00', type: 'decimal');
```

| scope | نتیجه |
| --- | --- |
| `whereMeta` | مدل‌هایی که مقدار جاری همان نوع و اپراتور را دارند |
| `orWhereMeta` | همان شرط داخل `orWhere` |
| `whereMetaIn` | عضویت؛ چند نوع در یک آرایه جداگانه گروه می‌شوند |
| `whereMetaBetween` | بازه شامل دو سر، فقط نوع‌های ترتیبی |
| `whereMetaLike` | فقط رشته؛ الگو bind می‌شود |
| `whereMetaNull` | فقط null صریح، نه کلید غایب |
| `whereMetaNotNull` | ردیف موجود با نوع غیر null و بدون tombstone |
| `whereHasMeta` | کلید موجود است، حتی اگر مقدار `false` یا `null` باشد |
| `whereDoesntHaveMeta` | کلید نیست یا tombstone جاری است |
| `whereMetaJsonContains` | عضو JSON با `whereJsonContains` لاراول |
| `whereMetaAt` | فقط کلید نسخه‌دار، در همان لحظه |

## نوع‌ها در جستجو

- رشته `'10'` با عدد `10` یکی نیست.
- `false` با `0` و `'0'` یکی نیست.
- `!=` فقط بین ردیف‌های موجود از همان نوع است. کلید غایب را «نابرابر» حساب نمی‌کند.
- `whereMetaIn([])` هیچ مدلی برنمی‌گرداند.
- `null` داخل `whereMetaIn` فقط ردیف null صریح را می‌گیرد. اگر `type` اعلام‌شده با null جور نباشد خطا می‌دهد.
- بازه برعکس (`max < min`) نتیجه خالی است و خطا نیست.
- دو سر بازه با نوع متفاوت `MetaQueryException` می‌دهند.
- null و json را با `whereMeta` مقایسه نکنید. برای آن‌ها scope مخصوص است.

## تاریخ و نسخه

`whereMeta('price', '=', '100.00', type: 'decimal')` قیمت تاریخی را پیدا نمی‌کند اگر مقدار جاری چیز دیگری باشد. `whereMetaAt` همان تاریخ را روی کلید نسخه‌دار می‌بیند و کلید عادی را وارد نتیجه نمی‌کند.

نسخه آینده تا `effective_at` در جستجوی جاری نیست. اگر تنها نسخه در آینده باشد، `whereHasMeta` فعلاً آن کلید را موجود نمی‌داند.

## JSON و حروف

عضو سطح‌اول آرایه در SQLite، MySQL و PostgreSQL قابل جستجو است. fragment تودرتو در SQLite خطا می‌دهد. در MySQL و PostgreSQL جستجو می‌شود. نتیجه خالی به‌جای این خطا برنمی‌گردد.

برابری رشته با hash مقدار و سپس خود رشته انجام می‌شود و به حروف حساس است. `LIKE` به collation دیتابیس بستگی دارد. SQLite برای `LIKE` روی ASCII معمولاً به بزرگی و کوچکی حرف حساس نیست. این رفتار را برای بقیه درایورها یکسان فرض نکنید.

## ایمنی

کلید، اپراتور، نوع، الگو و مقدار قبل از اجرا بررسی می‌شوند. تزریق SQL از طریق این آرگومان‌ها به شناسه خام تبدیل نمی‌شود. پکیج همچنان مجوز دسترسی کاربر به خود مدل را اعمال نمی‌کند.
