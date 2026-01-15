<?php
/**
 * صفحة إعادة تعيين كلمة المرور
 * يتم الوصول إليها عبر رابط من البريد الإلكتروني
 */

require_once 'includes/init.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// التحقق من صحة الرمز
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT prt.*, u.username, u.full_name 
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? AND prt.used = FALSE AND prt.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();
    
    if (!$reset_data) {
        $error = 'الرابط غير صحيح أو منتهي الصلاحية';
    }
} else {
    $error = 'رمز إعادة التعيين غير موجود';
}

// معالجة إعادة التعيين
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $submit_token = $_POST['token'] ?? '';
    
    if ($submit_token !== $token) {
        $error = 'خطأ في التحقق';
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = 'جميع الحقول مطلوبة';
    } elseif ($new_password !== $confirm_password) {
        $error = 'كلمة المرور وتأكيدها غير متطابقين';
    } elseif (strlen($new_password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        // تحديث كلمة المرور
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, last_password_change = NOW() WHERE id = ?");
        
        if ($stmt->execute([$password_hash, $reset_data['user_id']])) {
            // تعليم الرمز كمستخدم
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = true;
        } else {
            $error = 'حدث خطأ أثناء تحديث كلمة المرور';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-teal-50 via-sky-50 to-cyan-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto">
        <?php if ($success): ?>
            <!-- نجاح إعادة التعيين -->
            <div class="shimal-card bg-white p-8 shadow-xl text-center">
                <div class="mb-6">
                    <i class="fas fa-check-circle text-6xl text-green-500"></i>
                </div>
                <h1 class="text-3xl font-black text-teal-900 mb-4">
                    تم تغيير كلمة المرور بنجاح!
                </h1>
                <p class="text-gray-600 mb-6">
                    يمكنك الآن تسجيل الدخول باستخدام كلمة المرور الجديدة
                </p>
                <a href="login.php" class="btn-primary px-8 py-3 rounded-xl font-black shadow-lg inline-block">
                    <i class="fas fa-sign-in-alt ml-2"></i>
                    تسجيل الدخول
                </a>
            </div>
        <?php elseif (!empty($error)): ?>
            <!-- خطأ -->
            <div class="shimal-card bg-white p-8 shadow-xl text-center">
                <div class="mb-6">
                    <i class="fas fa-exclamation-circle text-6xl text-red-500"></i>
                </div>
                <h1 class="text-3xl font-black text-teal-900 mb-4">
                    خطأ في إعادة تعيين كلمة المرور
                </h1>
                <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
                <a href="login.php" class="btn-secondary px-8 py-3 rounded-xl font-black inline-block">
                    <i class="fas fa-arrow-right ml-2"></i>
                    العودة لتسجيل الدخول
                </a>
            </div>
        <?php else: ?>
            <!-- نموذج إعادة التعيين -->
            <div class="shimal-card bg-white p-8 shadow-xl">
                <div class="text-center mb-8">
                    <i class="fas fa-key text-5xl text-teal-500 mb-4"></i>
                    <h1 class="text-3xl font-black text-teal-900 mb-2">
                        إعادة تعيين كلمة المرور
                    </h1>
                    <p class="text-gray-600">
                        مرحباً <strong><?= htmlspecialchars($reset_data['full_name'] ?? $reset_data['username']) ?></strong>
                    </p>
                    <p class="text-sm text-gray-500 mt-2">
                        الرجاء إدخال كلمة المرور الجديدة
                    </p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">
                                <i class="fas fa-lock ml-1 text-teal-500"></i>
                                كلمة المرور الجديدة
                            </label>
                            <input type="password" name="new_password" minlength="6" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200">
                            <p class="text-sm text-gray-500 mt-1">يجب أن تكون 6 أحرف على الأقل</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">
                                <i class="fas fa-check-circle ml-1 text-teal-500"></i>
                                تأكيد كلمة المرور
                            </label>
                            <input type="password" name="confirm_password" minlength="6" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring focus:ring-teal-200">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full btn-primary py-3 rounded-xl font-black shadow-lg">
                        <i class="fas fa-shield-alt ml-2"></i>
                        تغيير كلمة المرور
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <a href="login.php" class="text-teal-600 hover:text-teal-800 text-sm">
                        <i class="fas fa-arrow-right ml-1"></i>
                        العودة لتسجيل الدخول
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
