# مستندات

این پوشه رفتار پیاده‌شده را توضیح می‌دهد. اگر کدی برای یک قابلیت وجود ندارد، اینجا هم وعده داده نمی‌شود.

| سند | موضوع |
| --- | --- |
| [installation.md](installation.md) | نصب، migrate، config cache |
| [usage.md](usage.md) | Trait، Facade، UUID، صفحه‌بندی |
| [types.md](types.md) | نوع‌ها و تفاوت مقدارها |
| [versioning.md](versioning.md) | تاریخچه، آینده، گذشته، tombstone |
| [search.md](search.md) | scopeهای جستجو |
| [performance.md](performance.md) | تعداد کوئری، ایندکس، بنچمارک |
| [tenant.md](tenant.md) | جداسازی tenant |
| [rules.md](rules.md) | قفل، اتمی بودن، حذف |
| [errors.md](errors.md) | Exceptionها |
| [testing.md](testing.md) | اجرای تست و بنچمارک |
| [scenarios](scenarios/01-basic-meta.md) | ۲۴ سناریوی عملیاتی |

## واژه‌نامه

- **root:** یک ردیف `metas` برای یک tenant، یک مدل، و یک کلید.
- **version:** یک ردیف `meta_versions`. فقط کلید نسخه‌دار این ردیف را دارد.
- **tombstone:** نسخه با `is_deleted=1`. کلید از آن لحظه غایب است و به مقدار قبلی برنمی‌گردد.
- **tenant key:** رشته حداکثر ۶۴ کاراکتر. وقتی tenant خاموش است مقدارش خالی است.
- **decimal:** رشته دهدهی با مقیاس ۱۰، نه float.
