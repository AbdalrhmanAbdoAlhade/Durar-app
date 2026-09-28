<div align="center">

# 💎 Durar Store API
### منصة تجارة إلكترونية ومزادات متكاملة — Laravel Back-End

**RESTful API متكاملة لمتجر إلكتروني تشمل المنتجات، السلة، الطلبات، الكوبونات، المزادات، التقييمات، الدفع عبر EdfaPay، وإشعارات Firebase.**

![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-Auth-000000?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

[المميزات](#-المميزات) • [التقنيات](#️-التقنيات-المستخدمة) • [التثبيت](#-التثبيت-والتشغيل) • [توثيق الـ API](#-توثيق-الـ-api) • [المطور](#️-المطور)

</div>

---

## 📖 نبذة عن المشروع

**Durar App** هو الـ Back-End الخاص بمنصة **Durar Store** — متجر إلكتروني عربي متكامل مبني بـ **Laravel 13**.

يغطي النظام دورة التجارة الإلكترونية كاملة:
من تصفح المنتجات والأقسام ← إضافة للسلة ← تطبيق كوبونات الخصم ← إتمام الطلب ← الدفع الإلكتروني ← التقييم،
بالإضافة إلى **نظام مزادات (Auctions)** كامل بالمزايدات والإغلاق والتحويل لطلب، ولوحة تحكم **Admin** كاملة لإدارة كل شيء.

> 📬 ملف Postman جاهز للتجربة: `Durar-Store-API.postman_collection.json`

---

## ✨ المميزات

| المجال | التفاصيل |
|---|---|
| 🔐 **المصادقة والصلاحيات** | تسجيل / دخول / خروج بـ Laravel Sanctum + أدوار عبر Spatie Permission (`admin`, `customer`) |
| 📦 **المنتجات والأقسام** | CRUD كامل، صور متعددة للمنتج، واجهات Public للزوار + واجهات Admin للإدارة |
| 🛒 **السلة** | سلة للزوار (Session-based) والمستخدمين: إضافة، تعديل كمية، حذف، تفريغ |
| 🎟️ **الكوبونات** | إنشاء كوبونات، فحص صلاحيتها (`coupons/check`)، ربطها بأقسام معينة |
| 🧾 **الطلبات** | Checkout من السلة، Checkout للفائز بالمزاد، تتبع الطلبات، تحديث الحالة (Admin) |
| 🔨 **المزادات** | إنشاء مزاد، عرض المزايدات، المزايدة، إغلاق المزاد، تحويل الفائز لطلب |
| ⭐ **التقييمات** | تقييم المنتجات (للمستخدمين المسجلين)، مراجعة واعتماد التقييمات من Admin |
| 🖼️ **البانرات** | بانرات الواجهة الرئيسية مع إدارة كاملة من Admin |
| 💳 **المدفوعات** | تكامل مع **EdfaPay** + Webhook لاستقبال نتيجة الدفع |
| 🔔 **الإشعارات** | تخزين FCM Tokens عبر Firebase (`kreait/firebase-php`) للإشعارات اللحظية |
| 👤 **المستخدمين** | بيانات المستخدم + رقم الهاتف + FCM Tokens |

---

## 🛠️ التقنيات المستخدمة

| التقنية | الإصدار / الاستخدام |
|---|---|
| **Laravel Framework** | `^13.17` |
| **PHP** | `^8.3` |
| **Laravel Sanctum** | `^4.0` — مصادقة API بالـ Tokens |
| **Spatie Laravel Permission** | `^8.3` — الأدوار والصلاحيات |
| **Kreait Firebase PHP** | `^8.5` — إشعارات FCM |
| **EdfaPay** | بوابة الدفع + Webhook |
| **MySQL** | قاعدة البيانات الرئيسية |
| **Pest PHP** | `^4.7` — الاختبارات |
| **Laravel Pail / Pint** | السجلات وتنسيق الكود |
| **Vite + TailwindCSS 4** | الواجهات والأصول |

### 🗂️ الموديلات الأساسية

`User` • `Category` • `Product` • `ProductImage` • `Cart` • `CartItem` • `Coupon` • `Banner` • `Review` • `Auction` • `AuctionBid` • `AuctionImage` • `Order` • `OrderItem` • `Payment` • `FcmToken`

---

## 📁 هيكل المشروع

```
Durar-app/
├── app/
│   ├── Http/Controllers/Api/   # Auth, Product, Category, Cart, Order,
│   │                           # Coupon, Auction, Review, Banner,
│   │                           # Payment, FcmToken
│   ├── Models/                 # 16 Model
│   ├── Services/               # Business Logic (Payment, FCM, ...)
│   └── Console/
├── routes/
│   ├── api.php                 # كل endpoints الخاصة بالـ API
│   ├── web.php
│   └── console.php
├── database/migrations/        # 22 Migration
├── Durar-Store-API.postman_collection.json
├── composer.json
└── package.json
```

---

## 🚀 التثبيت والتشغيل

### المتطلبات

- PHP `>= 8.3`
- Composer
- MySQL
- Node.js + NPM

### 1️⃣ استنساخ المشروع

```bash
git clone <repo-url>
cd Durar-app
```

### 2️⃣ تثبيت الاعتماديات

```bash
composer install
npm install
```

### 3️⃣ إعداد ملف البيئة

```bash
cp .env.example .env
php artisan key:generate
```

عدّل بيانات الاتصال في `.env`:

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

# Firebase (FCM)
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
```

### 4️⃣ الهجرات والبيانات الأولية

```bash
php artisan migrate --seed
```

### 5️⃣ التشغيل

```bash
# الطريقة المختصرة (سيرفر + queue + vite)
composer dev

# أو كل أمر لوحده
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

### 6️⃣ البناء للإنتاج

```bash
npm run build
php artisan config:cache
php artisan route:cache
```

---

## 📚 توثيق الـ API

Base URL: `http://localhost:8000/api`

### 🔐 المصادقة — `auth/`

| Method | Endpoint | الوصف | صلاحية |
|---|---|---|---|
| `POST` | `/auth/register` | تسجيل مستخدم جديد | Public |
| `POST` | `/auth/login` | تسجيل الدخول | Public |
| `POST` | `/auth/logout` | تسجيل الخروج | Auth |
| `GET` | `/auth/me` | بيانات المستخدم الحالي | Auth |

### 🏪 الواجهة العامة (زوار + عملاء)

| Method | Endpoint | الوصف |
|---|---|---|
| `GET` | `/categories` | كل الأقسام |
| `GET` | `/categories/{category}` | قسم واحد |
| `GET` | `/products` | كل المنتجات (مع فلترة/بحث) |
| `GET` | `/products/{product}` | منتج واحد |
| `GET` | `/products/{product}/reviews` | تقييمات منتج |
| `GET` | `/banners` | البانرات النشطة |
| `GET` | `/auctions` | المزادات النشطة |
| `GET` | `/auctions/{auction}` | تفاصيل مزاد |
| `GET` | `/auctions/{auction}/bids` | مزايدات مزاد |
| `GET` | `/cart` | عرض السلة |
| `POST` | `/cart/items` | إضافة عنصر للسلة |
| `PUT` | `/cart/items/{item}` | تعديل كمية عنصر |
| `DELETE` | `/cart/items/{item}` | حذف عنصر |
| `DELETE` | `/cart` | تفريغ السلة |
| `POST` | `/coupons/check` | فحص كوبون خصم |
| `POST` | `/payments/edfapay/webhook` | Webhook بوابة الدفع (EdfaPay) |

### 👤 العملاء (مسجلين) — `auth:sanctum`

| Method | Endpoint | الوصف |
|---|---|---|
| `POST` | `/products/{product}/reviews` | إضافة تقييم |
| `POST` | `/auctions/{auction}/bids` | المزايدة على مزاد |
| `POST` | `/auctions/{auction}/checkout` | الفائز يحوّل المزاد لطلب |
| `GET` | `/orders` | طلباتي |
| `GET` | `/orders/{order}` | تفاصيل طلب |
| `POST` | `/orders/checkout` | إتمام طلب من السلة |
| `GET` | `/orders/{order}/payment` | بيانات دفع طلب |
| `POST` | `/fcm-tokens` | تسجيل FCM Token |
| `DELETE` | `/fcm-tokens` | حذف FCM Token |

### 🛡️ الإدارة — `admin/*` (دور `admin`)

| Method | Endpoint | الوصف |
|---|---|---|
| `GET/POST` | `/admin/categories` | عرض / إنشاء الأقسام |
| `PUT/DELETE` | `/admin/categories/{category}` | تعديل / حذف قسم |
| `GET/POST` | `/admin/products` | عرض / إنشاء المنتجات |
| `PUT/DELETE` | `/admin/products/{product}` | تعديل / حذف منتج |
| `DELETE` | `/admin/products/{product}/images/{image}` | حذف صورة منتج |
| `GET/POST` | `/admin/coupons` | عرض / إنشاء الكوبونات |
| `GET/PUT/DELETE` | `/admin/coupons/{coupon}` | عرض / تعديل / حذف كوبون |
| `GET/POST` | `/admin/banners` | عرض / إنشاء البانرات |
| `PUT/DELETE` | `/admin/banners/{banner}` | تعديل / حذف بانر |
| `GET` | `/admin/reviews/pending` | التقييمات المعلقة |
| `POST` | `/admin/reviews/{review}/approve` | اعتماد تقييم |
| `DELETE` | `/admin/reviews/{review}` | حذف تقييم |
| `GET/POST` | `/admin/auctions` | عرض / إنشاء المزادات |
| `PUT/DELETE` | `/admin/auctions/{auction}` | تعديل / حذف مزاد |
| `POST` | `/admin/auctions/{auction}/close` | إغلاق مزاد |
| `GET` | `/admin/orders` | كل الطلبات |
| `PUT` | `/admin/orders/{order}/status` | تحديث حالة طلب |

> 🧪 للتجربة السريعة استورد ملف `Durar-Store-API.postman_collection.json` في Postman.

---

## 🧪 الاختبارات

```bash
php artisan test
# أو
composer test
```

المشروع يستخدم **Pest PHP** مع إضافة Laravel.

---

## 🤝 المساهمة

المساهمات مرحب بها:

1. اعمل Fork للمشروع
2. أنشئ فرعًا للميزة (`git checkout -b feature/new-feature`)
3. اعمل Commit (`git commit -m "Add new feature"`)
4. ادفع الفرع (`git push origin feature/new-feature`)
5. افتح Pull Request

الكود منسق بـ **Laravel Pint**:

```bash
./vendor/bin/pint
```

---

## 📄 الرخصة

هذا المشروع مرخص تحت رخصة [MIT](https://opensource.org/licenses/MIT).

---

## 🧑‍💻 المطور

<div align="center">

# Abdalrhman AbdoAlhade
### Senior Back-End Systems Engineer

*Building scalable systems*

[![Portfolio](https://img.shields.io/badge/Portfolio-000000?style=for-the-badge&logo=vercel&logoColor=white)](https://abdalrhman-abdo-alhade.vercel.app/)
[![Email](https://img.shields.io/badge/Gmail-EA4335?style=for-the-badge&logo=gmail&logoColor=white)](mailto:abdo.king22227@gmail.com)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/AbdalrhmanAbdoAlhade)
[![WhatsApp](https://img.shields.io/badge/WhatsApp-25D366?style=for-the-badge&logo=whatsapp&logoColor=white)](https://wa.me/201023402756)

![Profile Views](https://komarev.com/ghpvc/?username=AbdalrhmanAbdoAlhade&color=0e75b6&style=flat&label=Profile+Views)

**Durar Store API** — صُمم وطُوّر بواسطة **Abdalrhman AbdoAlhade**

</div>
