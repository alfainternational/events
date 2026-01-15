# 🎓 نظام إدارة الفعاليات - كلية الشمال للتمريض

نظام متكامل واحترافي لإدارة حجوزات الفعاليات الداخلية والخارجية مع ميزات متقدمة.

[![Version](https://img.shields.io/badge/version-2.1.0-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)

---

## ✨ المميزات الرئيسية

### للمستخدمين
- ✅ حجز فعاليات بواجهة سهلة وبصرية
- ✅ اختيار أيام متعددة (متباعدة أو متتالية)
- ✅ فحص فوري للتداخل الزمني مع عرض visual timeline
- ✅ تعديل الحجز برمز فريد خلال 24 ساعة
- ✅ رسائل تأكيد واضحة وإشعارات فورية

### للمسؤولين
- ✅ لوحة تحكم شاملة لإدارة الطلبات
- ✅ موافقة/رفض سريع مع ذكر الأسباب
- ✅ سجل مراجعة كامل (Audit Log)
- ✅ إحصائيات وتقارير مفصلة

### الأمان والأداء
- ✅ حماية شاملة: CSRF, Rate Limiting, SQL Injection Prevention
- ✅ Security Headers محسّنة
- ✅ Database Indexes للأداء الأمثل
- ✅ AJAX للتحديثات الفورية بدون إعادة تحميل

---

## 📖 الوثائق

- **[دليل الترقية الشامل](UPGRADE_GUIDE.md)** - كيفية تطبيق الترقية واستخدام جميع المميزات
- **[سجل التغييرات](CHANGELOG.md)** - جميع التحديثات والتحسينات
- **[دليل Migrations](migrations/README.md)** - إدارة قاعدة البيانات

---

## 🚀 البدء السريع

```bash
# 1. استيراد قاعدة البيانات
mysql -u root -p shimal_events < db.sql

# 2. تشغيل Migrations
cd migrations && php run_migrations.php

# 3. إعداد .env
cp .env.example .env
nano .env  # تحديث البيانات

# 4. الدخول للنظام
URL: https://your-domain.com
Username: admin
Password: PR123
```

⚠️ **غيّر كلمة المرور فوراً بعد الدخول الأول!**

---

## 🔧 المتطلبات

- PHP 7.4+
- MySQL 5.7+ أو MariaDB 10.2+
- Apache/Nginx مع mod_rewrite

---

## 📊 الإصدار الحالي: v2.1.0

### ما الجديد؟

✨ **نظام Calendar محسّن** - اختيار تواريخ متعددة بصرياً
✨ **فحص تداخل فوري** - AJAX Conflict Checker مع Timeline
✨ **Inline Validation** - تحقق فوري من صحة البيانات
✨ **UI Components** - Dialogs, Toasts, Loading States
🔒 **أمان محسّن** - Security Headers, .env Protection
🗄️ **قاعدة بيانات محدّثة** - حقول جديدة + Indexes

[اقرأ سجل التغييرات الكامل](CHANGELOG.md)

---

## 📞 الدعم

- **البريد**: it@shmial.edu.sa
- **الهاتف**: 0531987936

---

<div align="center">
  <strong>صُنع بـ ❤️ في كلية الشمال للتمريض</strong>
  <br>
  <sub>Version 2.1.0 | 2026</sub>
</div>