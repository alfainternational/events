# 🔧 دليل إصلاح خطأ 500 - حل سريع

## 🔴 المشكلة
```
HTTP ERROR 500
events.cartnec.com is currently unable to handle this request
```

---

## ✅ الحل السريع (3 خطوات)

### الخطوة 1️⃣: تشخيص المشكلة

افتح في المتصفح:
```
https://events.cartnec.com/test_env.php
```

هذا الملف سيعرض لك:
- ✓ حالة PHP
- ✓ وجود ملف .env
- ✓ أذونات الملفات
- ✓ اتصال قاعدة البيانات
- ✓ الملفات المفقودة

**بناءً على النتيجة:**
- إذا ظهرت الصفحة → المشكلة في `includes/init.php`
- إذا لم تظهر → المشكلة في PHP أو إعدادات السيرفر

---

### الخطوة 2️⃣: إنشاء ملف .env في السيرفر

#### المسار الصحيح:
```
/home/u947172334/public_html/.env
```

أو حسب إعدادات cPanel:
```
/home/[cpanel_username]/public_html/.env
```

#### المحتوى الكامل لملف .env:

```env
# ===================================
# نظام إدارة الفعاليات - ملف الإعدادات
# ===================================

# إعدادات التطبيق
APP_NAME="نظام إدارة الفعاليات"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Riyadh

# إعدادات قاعدة البيانات
DB_HOST=localhost
DB_NAME=u947172334_events
DB_USERNAME=u947172334_events
DB_PASSWORD=U947172334_events
DB_CHARSET=utf8mb4

# إعدادات الأمان
SESSION_LIFETIME=120
COOKIE_SECURE=true
CSRF_TOKEN_LENGTH=32

# Rate Limiting
LOGIN_MAX_ATTEMPTS=5
LOGIN_WINDOW_MINUTES=15
BOOKING_MAX_ATTEMPTS=3
BOOKING_WINDOW_MINUTES=60

# إعدادات البريد الإلكتروني
MAIL_ENABLED=false
MAIL_FROM_ADDRESS=noreply@shmial.edu.sa
MAIL_FROM_NAME="نظام الفعاليات - كلية الشمال"
MAIL_ADMIN_EMAIL=admin@shmial.edu.sa

# المميزات
FEATURE_EDIT_ENABLED=true
FEATURE_EDIT_DEADLINE_HOURS=24
FEATURE_SOFT_DELETES=true
FEATURE_AUDIT_LOGGING=true
FEATURE_PWA=true
FEATURE_DARK_MODE=true
FEATURE_AUTO_SAVE=true

# Pagination
PAGINATION_DEFAULT=20
PAGINATION_MAX=100

# الرفع
UPLOAD_MAX_SIZE=5242880
```

#### كيفية إنشاء الملف عبر cPanel:

1. افتح **cPanel**
2. اذهب إلى **File Manager**
3. اذهب إلى مجلد `public_html`
4. انقر **+ File** أعلى اليسار
5. اكتب الاسم: `.env`
6. انقر **Create New File**
7. انقر بالزر الأيمن على `.env` → **Edit**
8. الصق المحتوى أعلاه
9. **Save Changes**

#### ضبط الأذونات (Permissions):

```bash
chmod 600 .env
```

أو عبر cPanel:
- انقر بالزر الأيمن على `.env` → **Change Permissions**
- الصق: `600`
- أو اختر: Owner: Read + Write فقط

---

### الخطوة 3️⃣: حل بديل مؤقت

إذا استمر الخطأ، استخدم النسخة الآمنة من init.php:

#### عبر SSH:
```bash
cd /home/u947172334/public_html/includes
mv init.php init.php.backup
cp init_safe.php init.php
```

#### عبر cPanel File Manager:
1. اذهب إلى `includes/`
2. أعد تسمية `init.php` إلى `init.php.backup`
3. انسخ `init_safe.php` وسمّه `init.php`

---

## 🐛 الأسباب المحتملة والحلول

### السبب 1: ملف .env غير موجود

**الأعراض:**
```
Warning: .env file not found
```

**الحل:**
أنشئ ملف `.env` كما في الخطوة 2 أعلاه

---

### السبب 2: مشكلة في security_headers.php

**الأعراض:**
- الصفحة بيضاء فارغة
- خطأ 500 مباشرة

**الحل:**
```bash
# تعطيل مؤقت
mv includes/security_headers.php includes/security_headers.php.disabled
```

ثم في `includes/init.php` علّق السطر:
```php
// require_once __DIR__ . '/security_headers.php';
```

---

### السبب 3: مشكلة في env_loader.php

**الأعراض:**
```
Fatal error: Class 'EnvLoader' not found
```

**الحل:**
تأكد من وجود الملف:
```
/home/u947172334/public_html/includes/env_loader.php
```

---

### السبب 4: قاعدة البيانات غير متصلة

**الأعراض:**
```
Database connection failed
```

**الحل:**
1. تحقق من بيانات الاتصال في `.env`
2. تأكد من:
   - DB_HOST=localhost (صحيح غالباً)
   - DB_NAME=u947172334_events
   - DB_USERNAME=u947172334_events
   - DB_PASSWORD=U947172334_events

3. اختبر الاتصال في cPanel:
   - اذهب إلى **phpMyAdmin**
   - تأكد من وجود قاعدة البيانات `u947172334_events`

---

### السبب 5: مشكلة في الأذونات

**الأعراض:**
```
Permission denied
```

**الحل:**
```bash
# الأذونات الصحيحة
chmod 755 /home/u947172334/public_html/
chmod 644 /home/u947172334/public_html/*.php
chmod 600 /home/u947172334/public_html/.env
chmod 755 /home/u947172334/public_html/includes/
chmod 644 /home/u947172334/public_html/includes/*.php
```

---

## 🔍 التشخيص المتقدم

### عرض الأخطاء مؤقتاً:

في `index.php` أضف في أول سطر:
```php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

⚠️ **مهم:** احذف هذه الأسطر بعد حل المشكلة!

---

### فحص Error Log:

#### عبر cPanel:
1. اذهب إلى **Metrics** → **Errors**
2. أو افتح الملف:
   ```
   /home/u947172334/public_html/error_log
   ```

#### عبر SSH:
```bash
tail -50 /home/u947172334/public_html/error_log
```

---

## ✅ الحل النهائي الشامل

إذا جربت كل شيء ولم يعمل، استخدم هذا الحل:

### 1. أنشئ ملف index_test.php:

```php
<?php
// اختبار بسيط جداً
echo "PHP يعمل!<br>";
echo "Server: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "Path: " . __DIR__;
?>
```

افتح: `https://events.cartnec.com/index_test.php`

- إذا عمل → المشكلة في الكود
- إذا لم يعمل → المشكلة في إعدادات السيرفر

### 2. إذا كانت المشكلة في الكود:

قم بتعطيل الملفات الجديدة واحداً تلو الآخر:

```bash
# 1. تعطيل security_headers
mv includes/security_headers.php includes/security_headers.php.disabled

# 2. تعطيل env_loader
mv includes/env_loader.php includes/env_loader.php.disabled

# 3. استخدام db.php القديم
# (لديك نسخة احتياطية من Git)
```

---

## 📞 الدعم الفني

إذا استمرت المشكلة، أرسل لي:

1. نتيجة `test_env.php`
2. محتوى `error_log`
3. لقطة شاشة من cPanel File Manager توضح الملفات الموجودة

---

## 📚 ملاحظات مهمة

### للإنتاج (Production):
في `.env` يجب أن يكون:
```env
APP_ENV=production
APP_DEBUG=false
COOKIE_SECURE=true
```

### للتطوير (Development):
```env
APP_ENV=development
APP_DEBUG=true
COOKIE_SECURE=false
```

---

## 🎯 الخلاصة

**الترتيب الصحيح للحل:**

1. ✅ افتح `test_env.php` للتشخيص
2. ✅ أنشئ ملف `.env` في المسار الصحيح
3. ✅ اضبط الأذونات 600
4. ✅ إذا استمر الخطأ، استخدم `init_safe.php`
5. ✅ افحص `error_log`

**مدة الحل المتوقعة:** 5-10 دقائق

---

تاريخ الإنشاء: 2026-01-15
الإصدار: 1.0
