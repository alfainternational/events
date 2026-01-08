<?php
/**
 * صفحة إعدادات النظام المتقدمة
 * يمكن للمدير الرئيسي فقط الوصول إلى هذه الصفحة
 */

require_once 'includes/init.php';
require_once 'includes/rbac.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من أن المستخدم هو السوبر أدمن
if (!isSuperAdmin($pdo, $_SESSION['user_id'])) {
    set_flash('error', 'ليس لديك الصلاحيات الكافية للوصول إلى هذه الصفحة');
    header("Location: admin.php");
    exit();
}

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // التحقق من CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
        header("Location: admin_settings.php");
        exit();
    }
    
    $action = $_POST['action'];
    
    if ($action == 'update_email_settings') {
        // تحديث إعدادات البريد الإلكتروني
        $email_enabled = isset($_POST['email_enabled']) ? 'true' : 'false';
        $email_from_address = sanitize_input($_POST['email_from_address'] ?? '');
        $email_from_name = sanitize_input($_POST['email_from_name'] ?? '');
        $email_admin_email = sanitize_input($_POST['email_admin_email'] ?? '');
        
        $settings = [
            'email_enabled' => $email_enabled,
            'email_from_address' => $email_from_address,
            'email_from_name' => $email_from_name,
            'email_admin_email' => $email_admin_email
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        set_flash('success', 'تم تحديث إعدادات البريد الإلكتروني بنجاح');
        header("Location: admin_settings.php");
        exit();
    }
    
    if ($action == 'update_cron_settings') {
        // تحديث إعدادات Cron
        $cron_enabled = isset($_POST['cron_enabled']) ? 'true' : 'false';
        $cron_interval_minutes = (int)($_POST['cron_interval_minutes'] ?? 30);
        $cron_batch_size = (int)($_POST['cron_batch_size'] ?? 20);
        
        if ($cron_interval_minutes < 1) $cron_interval_minutes = 30;
        if ($cron_batch_size < 1) $cron_batch_size = 20;
        
        $settings = [
            'cron_enabled' => $cron_enabled,
            'cron_interval_minutes' => $cron_interval_minutes,
            'cron_batch_size' => $cron_batch_size
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        set_flash('success', 'تم تحديث إعدادات المهام المجدولة بنجاح');
        header("Location: admin_settings.php");
        exit();
    }
}

// جلب الإعدادات الحالية
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-teal-50 via-sky-50 to-cyan-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-black text-teal-900 mb-2">
                    <i class="fas fa-cogs ml-2 text-teal-500"></i>
                    الإعدادات المتقدمة
                </h1>
                <p class="text-gray-600">إدارة إعدادات النظام والبريد الإلكتروني والمهام المجدولة</p>
            </div>
            <a href="admin.php" class="btn-secondary px-6 py-3 rounded-xl font-bold">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة للوحة التحكم
            </a>
        </div>

        <?php display_flash_messages(); ?>

        <!-- إعدادات البريد الإلكتروني -->
        <div class="shimal-card bg-white p-8 mb-8 shadow-xl">
            <h2 class="text-2xl font-black text-teal-900 mb-6 border-b-4 border-teal-500 pb-3">
                <i class="fas fa-envelope ml-2 text-teal-500"></i>
                إعدادات البريد الإلكتروني
            </h2>
            
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_email_settings">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- تفعيل البريد -->
                    <div class="md:col-span-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="email_enabled" 
                                   <?= ($settings['email_enabled'] ?? 'false') == 'true' ? 'checked' : '' ?>
                                   class="w-6 h-6 text-teal-600 rounded">
                            <span class="mr-3 text-lg font-bold text-gray-700">تفعيل نظام البريد الإلكتروني</span>
                        </label>
                        <p class="text-sm text-gray-500 mr-9 mt-1">عند التفعيل، سيتم إرسال إشعارات عبر البريد الإلكتروني</p>
                    </div>
                    
                    <!-- عنوان المرسل -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-at ml-1 text-teal-500"></i>
                            عنوان البريد الإلكتروني للمرسل
                        </label>
                        <input type="email" name="email_from_address" 
                               value="<?= htmlspecialchars($settings['email_from_address'] ?? '') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                               required>
                    </div>
                    
                    <!-- اسم المرسل -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-signature ml-1 text-teal-500"></i>
                            اسم المرسل
                        </label>
                        <input type="text" name="email_from_name" 
                               value="<?= htmlspecialchars($settings['email_from_name'] ?? '') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                               required>
                    </div>
                    
                    <!-- بريد المدير -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-user-shield ml-1 text-teal-500"></i>
                            البريد الإلكتروني للمدير (لاستقبال الإشعارات المهمة)
                        </label>
                        <input type="email" name="email_admin_email" 
                               value="<?= htmlspecialchars($settings['email_admin_email'] ?? '') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                               required>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="btn-primary px-8 py-3 rounded-xl font-black shadow-lg">
                        <i class="fas fa-save ml-2"></i>
                        حفظ إعدادات البريد
                    </button>
                </div>
            </form>
        </div>

        <!-- إعدادات Cron -->
        <div class="shimal-card bg-white p-8 shadow-xl">
            <h2 class="text-2xl font-black text-teal-900 mb-6 border-b-4 border-teal-500 pb-3">
                <i class="fas fa-clock ml-2 text-teal-500"></i>
                إعدادات المهام المجدولة (Cron Jobs)
            </h2>
            
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_cron_settings">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- تفعيل Cron -->
                    <div class="md:col-span-2">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="cron_enabled" 
                                   <?= ($settings['cron_enabled'] ?? 'false') == 'true' ? 'checked' : '' ?>
                                   class="w-6 h-6 text-teal-600 rounded">
                            <span class="mr-3 text-lg font-bold text-gray-700">تفعيل المهام المجدولة</span>
                        </label>
                        <p class="text-sm text-gray-500 mr-9 mt-1">المهام المجدولة تُستخدم لإرسال البريد الإلكتروني بشكل دوري</p>
                    </div>
                    
                    <!-- تكرار التشغيل -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-redo ml-1 text-teal-500"></i>
                            تكرار التشغيل (بالدقائق)
                        </label>
                        <input type="number" name="cron_interval_minutes" min="1" max="1440"
                               value="<?= htmlspecialchars($settings['cron_interval_minutes'] ?? '30') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                               required>
                        <p class="text-sm text-gray-500 mt-1">كل كم دقيقة يتم تشغيل المهام المجدولة</p>
                    </div>
                    
                    <!-- حجم الدفعة -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">
                            <i class="fas fa-layer-group ml-1 text-teal-500"></i>
                            حجم دفعة الإرسال
                        </label>
                        <input type="number" name="cron_batch_size" min="1" max="100"
                               value="<?= htmlspecialchars($settings['cron_batch_size'] ?? '20') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                               required>
                        <p class="text-sm text-gray-500 mt-1">عدد الرسائل التي تُرسل في كل دفعة</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="btn-primary px-8 py-3 rounded-xl font-black shadow-lg">
                        <i class="fas fa-save ml-2"></i>
                        حفظ إعدادات Cron
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
