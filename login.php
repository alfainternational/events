<?php
require_once 'includes/init.php';

// Rate limiting للتسجيل الدخول
$rateLimit = check_rate_limit('login', 5, 15); // 5 محاولات كل 15 دقيقة
if (!$rateLimit['allowed']) {
    $error = $rateLimit['message'];
}

if (isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = "خطأ في التحقق من الأمان. يرجى إعادة المحاولة.";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Audit log: successful login
            $logger = new AuditLogger($pdo);
            $logger->logLogin($username, true, $user['id']);
            
            // Reset rate limit on successful login
            reset_rate_limit('login');
            
            // Regenerate session ID على  تسجيل الدخول
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // تحديث last_login في قاعدة البيانات
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $stmt->execute([RateLimiter::getIdentifier(), $user['id']]);
            
            header("Location: admin.php");
            exit();
        } else {
            // Audit log: failed login
            $logger = new AuditLogger($pdo);
            $logger->logLogin($username, false, $user['id'] ?? null);
            
            $error = "اسم المستخدم أو كلمة المرور غير صحيحة.";
        }
    }
}

include 'includes/header.php';
?>

<section class="max-w-md mx-auto py-20">
    <div class="shimal-card bg-white p-10 text-center shadow-2xl">
        <i class="fas fa-shield-halved text-5xl text-teal-500 mb-6"></i>
        <h2 class="text-2xl font-black text-teal-900 mb-2">دخول الإدارة</h2>
        <p class="text-teal-600 text-sm mb-8">خاص بموظفي قسم العلاقات العامة</p>
        
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-500 p-3 rounded-xl mb-4 text-xs font-bold"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <?php csrf_field(); ?>
            <input type="text" name="username" placeholder="اسم المستخدم" required class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl text-center font-bold outline-none focus:ring-2 focus:ring-teal-500">
            <input type="password" name="password" placeholder="كلمة المرور" required class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl text-center font-bold outline-none focus:ring-2 focus:ring-teal-500">
            <button type="submit" class="w-full btn-primary p-4 rounded-2xl font-black">تسجيل الدخول</button>
            <a href="index.php" class="block text-xs text-teal-400 font-bold mt-4 underline">العودة للرئيسية</a>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
