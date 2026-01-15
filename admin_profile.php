<?php
/**
 * صفحة الملف الشخصي للأدمن
 * يمكن لأي أدمن تعديل بياناته الشخصية
 */

require_once 'includes/init.php';
require_once 'includes/rbac.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب بيانات المستخدم الحالي
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'خطأ في جلب بيانات المستخدم');
    header("Location: admin.php");
    exit();
}

// جلب الأدوار والصلاحيات
$user_roles = getUserRoles($pdo, $user_id);
$user_permissions = getUserPermissions($pdo, $user_id);

// معالجة التحديث
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
        header("Location: admin_profile.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_profile') {
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        
        if (empty($full_name) || empty($username)) {
            set_flash('error', 'الاسم الكامل واسم المستخدم مطلوبان');
        } else {
            // التحقق من عدم تكرار اسم المستخدم
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                set_flash('error', 'اسم المستخدم موجود بالفعل');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $email, $username, $user_id])) {
                    set_flash('success', 'تم تحديث بياناتك الشخصية بنجاح');
                    
                    // تحديث البيانات المعروضة
                    $user['full_name'] = $full_name;
                    $user['email'] = $email;
                    $user['username'] = $username;
                } else {
                    set_flash('error', 'حدث خطأ أثناء التحديث');
                }
            }
        }
    }
    
    if ($action == 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            set_flash('error', 'جميع حقول كلمة المرور مطلوبة');
        } elseif ($new_password !== $confirm_password) {
            set_flash('error', 'كلمة المرور الجديدة وتأكيدها غير متطابقين');
        } elseif (strlen($new_password) < 6) {
            set_flash('error', 'كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            set_flash('error', 'كلمة المرور الحالية غير صحيحة');
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, last_password_change = NOW() WHERE id = ?");
            if ($stmt->execute([$password_hash, $user_id])) {
                set_flash('success', 'تم تغيير كلمة المرور بنجاح');
            } else {
                set_flash('error', 'حدث خطأ أثناء تغيير كلمة المرور');
            }
        }
    }
    
    header("Location: admin_profile.php");
    exit();
}

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-teal-50 via-sky-50 to-cyan-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-black text-teal-900 mb-2">
                    <i class="fas fa-user-circle ml-2 text-teal-500"></i>
                    الملف الشخصي
                </h1>
                <p class="text-gray-600">إدارة بياناتك الشخصية وكلمة المرور</p>
            </div>
            <a href="admin.php" class="btn-secondary px-6 py-3 rounded-xl font-bold">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة
            </a>
        </div>

        <?php display_flash_messages(); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- معلومات المستخدم -->
            <div class="lg:col-span-1">
                <div class="shimal-card bg-white p-6 shadow-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-circle text-8xl text-teal-500"></i>
                    </div>
                    <h2 class="text-2xl font-black text-teal-900 mb-2">
                        <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
                    </h2>
                    <p class="text-gray-600 mb-4">@<?= htmlspecialchars($user['username']) ?></p>
                    
                    <?php if ($user['is_super_admin']): ?>
                        <div class="inline-block px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full font-bold mb-4">
                            <i class="fas fa-crown ml-1"></i>
                            مدير رئيسي
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-right mt-6 pt-6 border-t border-gray-200">
                        <p class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-calendar-alt ml-1 text-teal-500"></i>
                            تاريخ التسجيل: <?= date('Y/m/d', strtotime($user['created_at'])) ?>
                        </p>
                        <?php if ($user['last_password_change']): ?>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-key ml-1 text-teal-500"></i>
                                آخر تغيير لكلمة المرور: <?= date('Y/m/d', strtotime($user['last_password_change'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- الأدوار -->
                    <div class="mt-6 pt-6 border-t border-gray-200 text-right">
                        <h3 class="font-bold text-gray-700 mb-3">
                            <i class="fas fa-user-tag ml-1 text-teal-500"></i>
                            الأدوار
                        </h3>
                        <?php foreach ($user_roles as $role): ?>
                            <span class="inline-block px-3 py-1 bg-teal-100 text-teal-800 rounded-full text-sm font-bold mb-2">
                                <?= htmlspecialchars($role['display_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- نماذج التحديث -->
            <div class="lg:col-span-2 space-y-6">
                <!-- تحديث البيانات الشخصية -->
                <div class="shimal-card bg-white p-8 shadow-xl">
                    <h2 class="text-2xl font-black text-teal-900 mb-6 border-b-4 border-teal-500 pb-3">
                        <i class="fas fa-edit ml-2 text-teal-500"></i>
                        تحديث البيانات الشخصية
                    </h2>
                    
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-user ml-1 text-teal-500"></i>
                                    اسم المستخدم
                                </label>
                                <input type="text" name="username" 
                                       value="<?= htmlspecialchars($user['username']) ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                                       required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-id-card ml-1 text-teal-500"></i>
                                    الاسم الكامل
                                </label>
                                <input type="text" name="full_name" 
                                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200"
                                       required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-envelope ml-1 text-teal-500"></i>
                                    البريد الإلكتروني
                                </label>
                                <input type="email" name="email" 
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200">
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="btn-primary px-8 py-3 rounded-xl font-black shadow-lg">
                                <i class="fas fa-save ml-2"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>

                <!-- تغيير كلمة المرور -->
                <div class="shimal-card bg-white p-8 shadow-xl">
                    <h2 class="text-2xl font-black text-teal-900 mb-6 border-b-4 border-red-500 pb-3">
                        <i class="fas fa-lock ml-2 text-red-500"></i>
                        تغيير كلمة المرور
                    </h2>
                    
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-key ml-1 text-red-500"></i>
                                    كلمة المرور الحالية
                                </label>
                                <input type="password" name="current_password" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-red-500 focus:ring focus:ring-red-200"
                                       required>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-unlock-alt ml-1 text-red-500"></i>
                                    كلمة المرور الجديدة
                                </label>
                                <input type="password" name="new_password" minlength="6"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-red-500 focus:ring focus:ring-red-200"
                                       required>
                                <p class="text-sm text-gray-500 mt-1">يجب أن تكون 6 أحرف على الأقل</p>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">
                                    <i class="fas fa-check-circle ml-1 text-red-500"></i>
                                    تأكيد كلمة المرور الجديدة
                                </label>
                                <input type="password" name="confirm_password" minlength="6"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-red-500 focus:ring focus:ring-red-200"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="bg-red-500 text-white px-8 py-3 rounded-xl font-black shadow-lg hover:bg-red-600 transition">
                                <i class="fas fa-shield-alt ml-2"></i>
                                تغيير كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>

                <!-- الصلاحيات -->
                <div class="shimal-card bg-white p-8 shadow-xl">
                    <h2 class="text-2xl font-black text-teal-900 mb-6 border-b-4 border-purple-500 pb-3">
                        <i class="fas fa-shield-alt ml-2 text-purple-500"></i>
                        الصلاحيات الممنوحة لك
                    </h2>
                    
                    <?php if (empty($user_permissions)): ?>
                        <p class="text-gray-500 text-center py-8">لا توجد صلاحيات محددة</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            $grouped_permissions = [];
                            foreach ($user_permissions as $perm) {
                                $grouped_permissions[$perm['module']][] = $perm;
                            }
                            ?>
                            
                            <?php foreach ($grouped_permissions as $module => $permissions): ?>
                                <div class="border-2 border-purple-200 rounded-xl p-4">
                                    <h3 class="font-black text-purple-900 mb-3 capitalize">
                                        <?= htmlspecialchars($module) ?>
                                    </h3>
                                    <ul class="space-y-2">
                                        <?php foreach ($permissions as $perm): ?>
                                            <li class="text-sm text-gray-700">
                                                <i class="fas fa-check text-green-500 ml-1"></i>
                                                <?= htmlspecialchars($perm['display_name']) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
