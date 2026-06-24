# iH4x OS — Notemaker Pro

A premium, production-ready, self-hosted note-taking application designed for speed, flexibility, and bilingual users. It fully supports both English (LTR) and Arabic (RTL) with seamless interface toggling and intelligent layout adjustments.

---

## Features

- **Bi-directional Layout Support (RTL & LTR)**: Native support for English and Arabic. Auto-flips sidebars, editors, outlines, and menus.
- **Rich Text Editor**: Powered by modern block-based formatting, lists, tables, todo checkboxes, divide lines, callouts, and quote blocks.
- **Smart Views**: Instantly access *All Notes*, *Pinned*, *Favorites*, *Archive*, and *Trash*.
- **Folders & Organization**: Dynamic folder tree supporting nested folders, customized icons, and color coding.
- **Security & Versioning**: Complete history logs, auto-saving, version recovery, and robust server directory protection.
- **Self-Hosted**: Designed to run locally on your own machine over XAMPP or in LAN environments.

---

## Installation Guide (XAMPP / Local Server)

### Prerequisites

1. **XAMPP** installed with PHP 8.0+ and Apache.
2. **SQLite3 PHP Extension** enabled (typically enabled by default in modern XAMPP installations).

### Setup Instructions

1. Clone or download this repository into your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\notemaker`).
2. Rename `config.sample.php` to `config.php` and configure if necessary (see [Configuration](#configuration) below).
3. Start Apache from the XAMPP Control Panel.
4. Navigate to `http://localhost/notemaker` in your web browser.
5. The application will automatically detect that no user accounts exist and redirect you to `setup.php` to create the admin/owner account and name your workspace.

---

## Configuration

The application's basic settings are managed in `config.php`:

```php
<?php
define('WORKSPACE_NAME', 'iH4x OS');
define('APP_URL', 'http://localhost/notemaker'); // Set to your local or LAN domain
define('AUTH_ENABLED', true); // Toggle login screen
define('DEFAULT_ROLE', 'user');
```

Additional security configurations are pre-installed via `.htaccess` in the root and `/data` folders to block direct HTTP access to the SQLite database and log directories.

---

## Bilingual Support (English & Arabic)

This application has been meticulously polished for Arabic and English:
- **Automatic Direction Toggling**: Switching languages changes the page direction (`dir="rtl"` or `dir="ltr"`).
- **Logical CSS Rules**: Layout margins, paddings, and borders adapt dynamically using CSS logical properties (`margin-inline-start`, etc.) instead of hardcoded left/right values.
- **Localized UI**: Key elements including toolbars, menus, profile panel, setup steps, and server validation errors are completely translated.

---

## Licensing

Licensed under the **GNU General Public License v3.0 (GPL-3.0)**. See the `LICENSE` file for details.

---

# iH4x OS — مفكرة المحترفين

تطبيق تدوين ملاحظات متميز وجاهز للإنتاج والاستخدام الشخصي، مصمم للسرعة والمرونة والمستخدمين ثنائيي اللغة. يدعم التطبيق اللغتين الإنجليزية (من اليسار إلى اليمين) والعربية (من اليمين إلى اليسار) بشكل كامل مع إمكانية تبديل الواجهة وتعديل التخطيط بمرونة.

---

## الميزات

- **دعم التخطيط ثنائي الاتجاه (RTL & LTR)**: دعم كامل للغتين العربية والإنجليزية مع إمكانية قلب القوائم الجانبية، والمحررين، والخطوط العريضة، والقوائم تلقائياً.
- **محرر نصوص غني**: مدعوم بتنسيق كتل حديث، قوائم، جداول، مربعات تحديد المهام، خطوط فاصلة، وتنبيهات مخصصة واقتباسات.
- **العروض الذكية**: وصول فوري إلى *كل الملاحظات*، *المثبتة*، *المفضلة*، *الأرشيف*، و*المهملات*.
- **تنظيم المجلدات**: هيكل مجلدات شجري يدعم المجلدات المتداخلة، الأيقونات المخصصة، والترميز اللوني.
- **الأمان والنسخ**: سجلات محفوظات كاملة، حفظ تلقائي، استرداد النسخ، وحماية قوية للمجلدات على الخادم.
- **استضافة ذاتية**: مصمم للعمل محلياً على جهازك الخاص عبر XAMPP أو في بيئات الشبكة المحلية (LAN).

---

## دليل التثبيت (XAMPP / خادم محلي)

### المتطلبات الأساسية

1. تثبيت **XAMPP** مع PHP 8.0+ و Apache.
2. تفعيل **SQLite3 PHP Extension** (مفعل تلقائياً في إصدارات XAMPP الحديثة).

### خطوات التثبيت

1. انسخ أو قم بتنزيل هذا المستودع في مجلد `htdocs` الخاص بـ XAMPP (على سبيل المثال: `C:\xampp\htdocs\notemaker`).
2. أعد تسمية `config.sample.php` إلى `config.php` واضبط الإعدادات إذا لزم الأمر (انظر قسم [التهيئة](#التهيئة) أدناه).
3. قم بتشغيل Apache من لوحة تحكم XAMPP.
4. افتح الرابط التالي في متصفحك: `http://localhost/notemaker`.
5. سيتعرف التطبيق تلقائياً على عدم وجود حسابات مستخدمين وسيقوم بتوجيهك إلى صفحة الإعداد `setup.php` لإنشاء حساب المالك/المدير وتسمية مساحة العمل الخاصة بك.

---

## التهيئة

يتم التحكم في الإعدادات الأساسية للتطبيق في ملف `config.php`:

```php
<?php
define('WORKSPACE_NAME', 'iH4x OS');
define('APP_URL', 'http://localhost/notemaker'); // اضبطه على النطاق المحلي أو شبكة LAN الخاصة بك
define('AUTH_ENABLED', true); // تبديل شاشة تسجيل الدخول
define('DEFAULT_ROLE', 'user');
```

تم دمج ملفات الحماية `.htaccess` مسبقاً في المجلد الرئيسي ومجلد `/data` لمنع الوصول المباشر عبر بروتوكول HTTP لقاعدة بيانات SQLite ومجلدات السجلات.

---

## الترخيص

مرخص بموجب رخصة **GNU General Public License v3.0 (GPL-3.0)**. راجع ملف `LICENSE` للحصول على التفاصيل.
