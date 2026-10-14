# خطاها

همه از `Karnoweb\Meta\Exceptions\MetaException` ارث می‌برند، به‌جز اینکه خود `MetaException` از `RuntimeException` است.

## UnsavedModelException

مدل هنوز کلید پایدار ندارد، یا purge روی مدلی بدون کلید صدا زده شده است.

کار: اول مدل را ذخیره کنید. پکیج این کار را نمی‌کند.

## MetaTypeException

نوع اعلام‌شده با مقدار جور نیست، رشته عددی به‌جای int آمده، decimal نامعتبر یا float است، تاریخ `Y-m-d` نیست، JSON آرایه نیست یا از سقف بزرگ‌تر است، متن canonical برای decimal موجود نیست، یا `whereMeta` روی null/json استفاده شده است.

کار: decimal را رشته بدهید. float را به رشته تبدیل نکنید و همان float را پاس ندهید.

## MetaVersionException

- `versioned: false` روی کلید نسخه‌دار.
- `setMetaAt` در گذشته برای کلید عادی.
- `getMetaAt` روی کلید عادی.
- `update` یا `delete` الکوئنت روی یک ردیف `meta_versions`.

کار: mode را عوض نکنید. برای گذشته، اول در زمان حال ارتقا دهید. تاریخچه را با نسخه جدید یا tombstone جلو ببرید، نه با ویرایش ردیف قدیم.

## MetaQueryException

اپراتور خارج از فهرست، اپراتور نامعتبر برای آن نوع، بازه با دو نوع متفاوت، `LIKE` غیر UTF-8، fragment غیرقابل سریال‌سازی، یا JSON تودرتو روی SQLite.

کار: اپراتور را از `= != <> > >= < <=` انتخاب کنید. برای null و json از scope خودشان استفاده کنید. fragment تودرتو را روی MySQL یا PostgreSQL جستجو کنید.

## MetaException

بقیه موارد:

- branch روشن است و resolver نیست یا کلید نامعتبر داده است.
- کلید خالی، بلند، دارای کاراکتر کنترل، یا خارج از `$metaKeys`.
- شناسه مدل یا morph class خارج از حد طول.
- ساخت root بعد از برخورد یکتایی ممکن نشد.

کار: resolver را اصلاح کنید. کلید را کوتاه و ثابت کنید. این خطا را با fallback به branch خالی عوض نکنید.
