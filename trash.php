<?php
require_once 'includes/init.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// جلب السجلات المحذوفة فقط
$stmt = $pdo->query("
    SELECT e.*, h.name as hall_name 
    FROM events e 
    LEFT JOIN halls h ON e.hall_id = h.id 
    WHERE e.deleted_at IS NOT NULL 
    ORDER BY e.deleted_at DESC
");
$deleted_events = $stmt->fetchAll();

// معالجة الاستعادة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان');
        header("Location: trash.php");
        exit();
    }
    
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action == 'restore' && $id > 0) {
        // استعادة السجل
        $stmt = $pdo->prepare("UPDATE events SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        // Audit log
        $logger = new AuditLogger($pdo);
        $logger->logEvent('restore', $id, null, ['restored' => true]);
        
        set_flash('success', 'تم استعادة الطلب بنجاح');
    } elseif ($action == 'permanent_delete' && $id > 0) {
        // حذف نهائي
        $stmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);
        
        // Audit log
        $logger = new AuditLogger($pdo);
        $logger->logEvent('permanent_delete', $id, ['title' => $event['title']], null);
        
        set_flash('success', 'تم الحذف النهائي');
    } elseif ($action == 'empty_trash') {
        // إفراغ سلة المحذوفات (حذف جميع السجلات المحذوفة)
        $stmt = $pdo->prepare("DELETE FROM events WHERE deleted_at IS NOT NULL");
        $count = $stmt->execute();
        
        // Audit log
        $logger = new AuditLogger($pdo);
        $logger->log('empty_trash', 'event', null, null, ['count' => $count]);
        
        set_flash('success', "تم حذف {$count} سجل نهائياً");
    }
    
    header("Location: trash.php");
    exit();
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-black text-teal-900">سلة المحذوفات</h2>
            <p class="text-teal-600 mt-1">الطلبات المحذوفة يمكن استعادتها أو حذفها نهائياً</p>
        </div>
        <div class="flex gap-3">
            <?php if (!empty($deleted_events)): ?>
                <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف جميع السجلات نهائياً؟')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="empty_trash">
                    <button type="submit" class="px-6 py-3 bg-red-500 text-white rounded-xl font-bold hover:bg-red-600 transition">
                        <i class="fas fa-trash ml-2"></i>
                        إفراغ السلة
                    </button>
                </form>
            <?php endif; ?>
            <a href="admin.php" class="px-6 py-3 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 transition">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة
            </a>
        </div>
    </div>

    <?php display_flash_messages(); ?>

    <?php if (empty($deleted_events)): ?>
        <div class="shimal-card bg-white p-12 text-center">
            <i class="fas fa-trash text-5xl text-gray-300 mb-4"></i>
            <p class="font-bold text-gray-400">سلة المحذوفات فارغة</p>
        </div>
    <?php else: ?>
        <div class="grid gap-6">
            <?php foreach ($deleted_events as $ev): ?>
                <div class="shimal-card bg-white p-6 border-r-4 border-red-500">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-xl font-black text-gray-700 mb-2">
                                <?= htmlspecialchars($ev['title']) ?>
                            </h3>
                            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                                <div>
                                    <span class="text-gray-500">الجهة المنظمة:</span>
                                    <strong class="mr-2"><?= htmlspecialchars($ev['organizing_dept']) ?></strong>
                                </div>
                                <div>
                                    <span class="text-gray-500">التاريخ:</span>
                                    <strong class="mr-2"><?= $ev['start_date'] ?></strong>
                                </div>
                                <div>
                                    <span class="text-gray-500">حُذف في:</span>
                                    <strong class="mr-2 text-red-600"><?= date('Y-m-d H:i', strtotime($ev['deleted_at'])) ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-xl font-bold hover:bg-green-600 transition">
                                    <i class="fas fa-undo ml-2"></i>
                                    استعادة
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف النهائي؟ لن يمكن التراجع!')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="permanent_delete">
                                <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-xl font-bold hover:bg-red-600 transition">
                                    <i class="fas fa-trash-alt ml-2"></i>
                                    حذف نهائي
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
