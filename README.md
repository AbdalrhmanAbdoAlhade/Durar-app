# 💎 Durar Store API

### منصة تجارة إلكترونية ومزادات متكاملة — Laravel REST API

**Back-End متكامل لمتجر متخصص في الحلي · الأحجار الكريمة · النيازك**

يشمل: المنتجات · السلة (ضيف + مستخدم) · الكوبونات · الطلبات · المزادات · التقييمات · الدفع عبر EdfaPay · إشعارات FCM · رسائل WhatsApp · حجز المخزون · لوحة Admin كاملة.

> 📬 Postman Collection جاهزة: `Durar-Store-API.postman_collection.json`

---

## 📑 المحتويات

- [المميزات](#-المميزات)
- [التقنيات](#️-التقنيات)
- [التثبيت والتشغيل](#-التثبيت-والتشغيل)
- [توثيق الـ API](#-توثيق-الـ-api)
- [نظام حجز المخزون](#-نظام-حجز-المخزون-stock-reservation)
- [المهام المجدولة](#-المهام-المجدولة-scheduler)
- [سلة الزائر والموبايل](#-سلة-الزائر-والموبايل)
- [تكامل WhatsApp](#-تكامل-whatsapp)
- [هيكل المشروع](#-هيكل-المشروع)
- [ملاحظات أمنية](#-ملاحظات-أمنية)

---

## ✨ المميزات

| المجال | التفاصيل |
|--------|----------|
| 🔐 **المصادقة** | تسجيل / دخول / خروج بـ Laravel Sanctum + أدوار Spatie (`admin`, `customer`) |
| 👤 **الملف الشخصي** | تحديث البيانات + رفع صورة شخصية (WebP) |
| 📦 **المنتجات والأقسام** | CRUD كامل، صور متعددة، فلترة وبحث |
| 🛒 **السلة** | للزوار (Session / `X-Device-Id`) والمستخدمين المسجلين — دمج تلقائي بعد Login/Register |
| 🎟️ **الكوبونات** | على مستوى الأقسام + حد استخدام عام + حد لكل مستخدم (`usage_limit_per_user`) |
| 🧾 **الطلبات** | Checkout من السلة · Checkout فائز المزاد · تتبع الحالة · إعادة محاولة الدفع |
| 📦 **حجز المخزون** | Reservation مؤقت (15 دقيقة) — الخصم الفعلي عند نجاح الدفع فقط |
| 🔨 **المزادات** | إنشاء · مزايدة · إغلاق تلقائي بالـ Scheduler · تحويل الفائز لطلب |
| ⭐ **التقييمات** | تقييم المنتجات + اعتماد Admin |
| 🖼️ **البانرات** | بانرات الواجهة الرئيسية مع إدارة كاملة |
| 💳 **المدفوعات** | تكامل EdfaPay + Webhook مع إعادة تحقق من البوابة |
| 🔔 **الإشعارات** | Firebase FCM + رسائل WhatsApp للأحداث المهمة (ترحيب بعد التسجيل وغيره) |
| 📰 **المقالات** | محتوى عام + إدارة من Admin |

---

## 🛠️ التقنيات

| التقنية | الاستخدام |
|---------|-----------|
| Laravel 11+ | إطار العمل (API فقط) |
| PHP ^8.3 | لغة البرمجة |
| Laravel Sanctum | مصادقة API Tokens |
| Spatie Permission | الأدوار والصلاحيات |
| Kreait Firebase PHP | إشعارات FCM |
| EdfaPay | بوابة الدفع + Webhook |
| WhatsApp Gateway | إرسال رسائل واتساب (session: `durar`) |
| MySQL | قاعدة البيانات |
| Pest PHP | الاختبارات |

### الموديلات الأساسية

`User` · `Category` · `Product` · `ProductImage` · `Cart` · `CartItem` · `Coupon` · `Banner` · `Review` · `Auction` · `AuctionBid` · `AuctionImage` · `Order` · `OrderItem` · `Payment` · `StockReservation` · `FcmToken` · `Article`

---

## 🚀 التثبيت والتشغيل

### المتطلبات

- PHP >= 8.3
- Composer
- MySQL
- Node.js + NPM (اختياري للأصول)

### 1) استنساخ المشروع

```bash
git clone https://github.com/AbdalrhmanAbdoAlhade/Durar-app.git
cd Durar-app
```

### 2) تثبيت الاعتماديات

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 3) إعداد `.env`

```env
APP_NAME="Durar Store"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=durar_app
DB_USERNAME=root
DB_PASSWORD=

# EdfaPay
EDFAPAY_MERCHANT_ID=
EDFAPAY_PASSWORD=
EDFAPAY_CALLBACK_URL=${APP_URL}/api/payments/edfapay/webhook
EDFAPAY_RETURN_URL=${APP_URL}/payment/return

# Firebase (FCM)
FIREBASE_CREDENTIALS=storage/firebase-credentials.json

# Frontend (لروابط التقييم وغيرها)
FRONTEND_URL=https://your-frontend.com
```

> `APP_NAME` بيتستخدم في رسائل الواتساب (الترحيب والـ closings)، فاكتب اسم البراند الصح.

### 4) الهجرات والبيانات

```bash
php artisan migrate --seed
php artisan storage:link
```

### 5) التشغيل

```bash
# سيرفر التطوير
php artisan serve

# Scheduler (مهم للمزادات وحجوزات المخزون)
php artisan schedule:work
```

في الإنتاج استخدم Cron:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

بعد أي تعديل في الكود أو الـ config على السيرفر:

```bash
php artisan optimize:clear
```

---

## 📚 توثيق الـ API

**Base URL:** `https://api.example.com/api`

### 🔐 المصادقة — `auth/`

| Method | Endpoint | الوصف | الصلاحية |
|--------|----------|-------|----------|
| POST | `/auth/register` | تسجيل مستخدم جديد + رسالة ترحيب واتساب | Public |
| POST | `/auth/login` | تسجيل الدخول | Public |
| POST | `/auth/logout` | تسجيل الخروج (حذف التوكن الحالي) | Auth |
| GET | `/auth/me` | بيانات المستخدم الحالي + الأدوار | Auth |
| PUT | `/auth/profile` | تحديث الملف الشخصي + الصورة | Auth |

- بعد **Register / Login** بيتم دمج سلة الزائر في سلة المستخدم تلقائياً (ويب أو موبايل).
- للموبايل: أرسل `X-Device-Id` في الـ Headers عند Login/Register.
- رسالة الواتساب بتتبعت **بعد إرسال الرد** للعميل، فمفيش تأخير على الـ register حتى لو خدمة واتساب بطيئة أو واقعة.

**مثال Register:**

```http
POST /api/auth/register
Content-Type: application/json
X-Device-Id: 550e8400-e29b-41d4-a716-446655440000

{
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "phone": "01012345678",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**الرد (201):**

```json
{
  "success": true,
  "message": "Registered successfully",
  "data": {
    "user": { "id": 1, "name": "Ahmed Ali", "email": "ahmed@example.com" },
    "token": "1|xxxxxxxxxxxxxxxx"
  }
}
```

> شكل الـ envelope النهائي بيعتمد على `ApiResponseTrait` في المشروع.

---

### 🏪 الواجهة العامة

| Method | Endpoint | الوصف |
|--------|----------|-------|
| GET | `/categories` | كل الأقسام |
| GET | `/categories/{id}` | قسم واحد |
| GET | `/products` | المنتجات (فلترة / بحث) |
| GET | `/products/{id}` | منتج واحد |
| GET | `/products/{id}/reviews` | تقييمات منتج |
| GET | `/banners` | البانرات النشطة |
| GET | `/auctions` | المزادات |
| GET | `/auctions/{id}` | تفاصيل مزاد |
| GET | `/auctions/{id}/bids` | مزايدات مزاد |
| GET | `/articles` | المقالات |
| GET | `/articles/{slug}` | مقال واحد |
| POST | `/coupons/check` | فحص صلاحية كوبون |

---

### 🛒 السلة — `cart/`

تعمل للزائر والمستخدم المسجّل.

| Method | Endpoint | الوصف |
|--------|----------|-------|
| GET | `/cart` | عرض السلة |
| POST | `/cart/items` | إضافة منتج |
| PUT | `/cart/items/{id}` | تعديل الكمية |
| DELETE | `/cart/items/{id}` | حذف عنصر |
| DELETE | `/cart` | تفريغ السلة |

**Header للموبايل (زائر):**

```text
X-Device-Id: 550e8400-e29b-41d4-a716-446655440000
```

---

### 🧾 الطلبات والدفع (Auth)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/orders/checkout` | إنشاء طلب من السلة + رابط دفع |
| POST | `/auctions/{id}/checkout` | طلب لفائز المزاد |
| GET | `/orders` | طلبات المستخدم |
| GET | `/orders/{id}` | تفاصيل طلب |
| GET | `/orders/{id}/payment` | حالة الدفع |
| POST | `/orders/{id}/retry-payment` | إعادة محاولة الدفع |

**Webhook (Public — من EdfaPay):**

```text
POST /payments/edfapay/webhook
```

---

### 🔨 المزادات (Auth للمزايدة)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/auctions/{id}/bids` | وضع مزايدة |

> لا يمكن المزايدة إذا كنت أعلى مزايد حالياً.

---

### ⭐ أخرى (Auth)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/products/{id}/reviews` | إضافة تقييم |
| POST | `/fcm-tokens` | تسجيل توكن FCM |
| DELETE | `/fcm-tokens` | حذف توكن FCM |

---

### 🛠️ Admin (`auth:sanctum` + `role:admin`)

| المجموعة | Endpoints |
|----------|-----------|
| الأقسام | `GET/POST /admin/categories` · `POST/DELETE /admin/categories/{id}` |
| المنتجات | `GET/POST /admin/products` · `POST/DELETE /admin/products/{id}` · `PATCH .../rare` |
| الكوبونات | CRUD كامل `/admin/coupons` |
| البانرات | CRUD `/admin/banners` |
| التقييمات | `GET /admin/reviews/pending` · `POST .../approve` · `DELETE` |
| المقالات | CRUD `/admin/articles` |
| المزادات | CRUD + `POST /admin/auctions/{id}/close` |
| الطلبات | `GET /admin/orders` · `PUT /admin/orders/{id}/status` |

**حالات الطلب:** `pending` · `processing` · `shipped` · `delivered` · `cancelled`

---

## 📦 نظام حجز المخزون (Stock Reservation)

```text
Checkout
   │
   ├─► إنشاء Order (pending / unpaid)
   ├─► إنشاء StockReservation (status: reserved, 15 دقيقة)
   └─► فتح Payment (pending)
          │
          ├─► Webhook SUCCESS
          │      ├─► payment_status = paid
          │      ├─► reservation → committed
          │      └─► product.quantity -= reserved qty
          │
          ├─► Webhook FAILED
          │      ├─► payment_status = failed
          │      └─► reservation → released
          │
          └─► انتهاء المهلة (Scheduler كل 5 دقائق)
                 ├─► reservation → released
                 └─► order → cancelled (إن لزم)
```

عند إلغاء الطلب من Admin: يُسترجع المخزون تلقائياً من الحجوزات `committed`.

---

## ⏰ المهام المجدولة (Scheduler)

| الأمر | التكرار | الوظيفة |
|-------|---------|---------|
| `auctions:close-expired` | كل دقيقة | إغلاق المزادات المنتهية وتحديد الفائز |
| `reservations:release-expired` | كل 5 دقائق | تحرير حجوزات المخزون المنتهية |

تأكد إن الـ Scheduler شغال (`schedule:work` للتطوير، Cron للإنتاج).

---

## 🛒 سلة الزائر والموبايل

| الحالة | المعرّف المستخدم |
|--------|------------------|
| مستخدم مسجّل (Bearer Token) | `user_id` |
| زائر موبايل | Header: `X-Device-Id` أو `X-Cart-Token` |
| زائر ويب | Laravel Session ID |

الـ `CartService::resolveGuestId($request)` هي المسؤولة عن تحديد معرّف الزائر من الطلب، وبعدها `mergeGuestCartIntoUser()` بتدمج السلة في حساب المستخدم بعد Login/Register.

---

## 💬 تكامل WhatsApp

الإرسال عن طريق `App\Services\WhatsAppService` على بوابة واتساب خارجية (session: `durar`).

| الميثود | الوظيفة |
|---------|---------|
| `accountCreated(User $user)` | رسالة ترحيب بعد إنشاء الحساب — لا ترمي Exception أبداً وتسجل الفشل في اللوج |
| `sendMessage($to, $message, $vary = true)` | إرسال عام، بيرجّع array فيها `success` و `message` و `response` |

**تفاصيل مهمة:**

- **تنظيف الأرقام:** تحويل الصيغة المحلية المصرية (`010...`) للصيغة الدولية (`2010...`)، وإزالة `00`، وإصلاح حالة الصفر الزيادة (`200...`)، والتحقق من الطول (10–15 رقم).
- **تأخير عشوائي:** من 0.3 إلى 1.2 ثانية قبل كل إرسال لتقليل خطر الحظر.
- **تنويع الرسائل (Anti-Ban):** عند `$vary = true` بيتم تغيير شكل الرسالة (ترحيب/ختام عشوائي، مرادفات، تعديلات شكلية). أسماء البراند فيها مأخوذة من `config('app.name')`.
- **رسائل المعاملات (ترحيب / OTP / حالة الطلب):** الأفضل `vary = false` عشان النص يفضل ثابت وواضح.
- **عدم تعطيل الـ API:** الإرسال في الـ register بيتم بعد الرد (`afterResponse()`).
- **التشخيص:** أي فشل بيتسجل في `storage/logs/laravel.log` (الحالة + الـ body من البوابة).

```bash
tail -f storage/logs/laravel.log
```

---

## 📁 هيكل المشروع

```text
Durar-app/
├── app/
│   ├── Console/Commands/      # CloseExpiredAuctions, ReleaseExpiredReservations
│   ├── Http/
│   │   ├── Controllers/Api/   # Auth, Product, Cart, Order, Auction, Payment, ...
│   │   ├── Requests/          # LoginRequest, RegisterRequest, ...
│   │   └── Resources/         # UserResource, ...
│   ├── Models/                # 17+ Model بما فيها StockReservation
│   ├── Services/              # Cart, Order, Auction, Coupon, EdfaPay, WhatsApp, FCM
│   └── Traits/                # ImageConverterTrait, HasTranslations
├── routes/
│   ├── api.php
│   └── console.php            # Scheduler
├── database/migrations/
└── Durar-Store-API.postman_collection.json
```

---

## 🔒 ملاحظات أمنية

- Webhook الدفع لا يثق في الـ payload وحده — يتم إعادة التحقق من حالة المعاملة عبر EdfaPay.
- صلاحيات Admin محمية بـ `role:admin` (Spatie).
- الكوبونات محدودة لكل مستخدم عبر جدول `coupon_user`.
- فشل واتساب أو FCM لا يكسر الـ request الأساسي (try/catch + تسجيل في اللوج).
- يُفضّل تفعيل **Rate Limiting** على `auth` و `bids` في الإنتاج.
- لا ترفع `storage/firebase-credentials.json` ولا ملف `.env` على Git.

---

## 👨‍💻 المطور

**Abdalrhman AbdoAlhade**
Senior Back-End Systems Engineer

[Portfolio](https://abdalrhman-abdo-alhade.vercel.app) · [GitHub](https://github.com/AbdalrhmanAbdoAlhade) · [LinkedIn](https://www.linkedin.com/in/abdalrhman-abdalnabe)

---

## 📄 الترخيص

Private — جميع الحقوق محفوظة © Durar Store
