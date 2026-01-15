<?php
require_once 'includes/init.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// معالجة الفلاتر
$filters = [];
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
}
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}
if (isset($_GET['resource_type']) && !empty($_GET['resource_type'])) {
    $filters['resource_type'] = $_GET['resource_type'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Pagination
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// جلب السجلات
$logger = new AuditLogger($pdo);
$logs = $logger->getLogs($filters, $perPage, $offset);
$totalLogs = $logger->countLogs($filters);
$totalPages = ceil($totalLogs / $perPage);

// جلب قائمة المستخدمين للفلتر
$stmt = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username");
$users = $stmt->fetchAll();

// قائمة الإجراءات المتاحة
$actions = ['approve', 'reject', 'delete', 'update', 'create', 'login_success', 'login_failed'];
$resourceTypes = ['event', 'user', 'setting'];

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-black text-teal-900">سجل المراجعة</h2>
            <p class="text-teal-600 mt-1">عرض جميع العمليات والتغييرات في النظام</p>
        </div>
        <a href="admin.php" class="px-6 py-3 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 transition">
            <i class="fas fa-arrow-right ml-2"></i>
            العودة للوحة الأدمن
        </a>
    </div>

    <!-- الفلاتر -->
    <div class="shimal-card bg-white p-6 mb-6">
        <h3 class="text-lg font-black text-teal-900 mb-4">
            <i class="fas fa-filter ml-2"></i>
            تصفية السجلات
        </h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-teal-700 mb-2">المستخدم</label>
                <select name="user_id" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                    <option value="">الكل</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= isset($filters['user_id']) && $filters['user_id'] == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['username']) ?> - <?= htmlspecialchars($user['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-teal-700 mb-2">الإجراء</label>
                <select name="action" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                    <option value="">الكل</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= $action ?>" <?= isset($filters['action']) && $filters['action'] == $action ? 'selected' : '' ?>>
                            <?= htmlspecialchars($action) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-teal-700 mb-2">نوع المورد</label>
                <select name="resource_type" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                    <option value="">الكل</option>
                    <?php foreach ($resourceTypes as $type): ?>
                        <option value="<?= $type ?>" <?= isset($filters['resource_type']) && $filters['resource_type'] == $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-teal-700 mb-2">من تاريخ</label>
                <input type="date" name="date_from" value="<?= $filters['date_from'] ?? '' ?>" 
                       class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-teal-700 mb-2">إلى تاريخ</label>
                <input type="date" name="date_to" value="<?= $filters['date_to'] ?? '' ?>" 
                       class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
            </div>
            
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-teal-500 text-white py-3 px-6 rounded-xl font-bold hover:bg-teal-600 transition">
                    <i class="fas fa-search ml-2"></i>
                    بحث
                </button>
                <a href="audit_logs.php" class="bg-gray-100 text-gray-600 py-3 px-6 rounded-xl font-bold hover:bg-gray-200 transition">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- النتائج -->
    <div class="shimal-card bg-white p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-black text-teal-900">
                النتائج: <?= number_format($totalLogs) ?> سجل
            </h3>
            <div class="text-sm text-teal-600">
                صفحة <?= $page ?> من <?= $totalPages ?>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div class="text-center py-12 text-teal-400">
                <i class="fas fa-inbox text-5xl mb-4"></i>
                <p class="font-bold">لا توجد سجلات</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-teal-100">
                            <th class="text-right p-3 font-black text-teal-900 text-xs">الوقت</th>
                            <th class="text-right p-3 font-black text-teal-900 text-xs">المستخدم</th>
                            <th class="text-right p-3 font-black text-teal-900 text-xs">الإجراء</th>
                            <th class="text-right p-3 font-black text-teal-900 text-xs">المورد</th>
                            <th class="text-right p-3 font-black text-teal-900 text-xs">IP</th>
                            <th class="text-right p-3 font-black text-teal-900 text-xs">التفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b border-teal-50 hover:bg-teal-50/50 transition">
                                <td class="p-3 text-sm">
                                    <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="p-3 text-sm font-bold">
                                    <?= $log['username'] ? htmlspecialchars($log['username']) : '<span class="text-gray-400">غير معروف</span>' ?>
                                </td>
                                <td class="p-3">
                                    <?php
                                    $actionColors = [
                                        'approve' => 'bg-green-100 text-green-700',
                                        'reject' => 'bg-red-100 text-red-700',
                                        'delete' => 'bg-red-100 text-red-700',
                                        'update' => 'bg-blue-100 text-blue-700',
                                        'create' => 'bg-teal-100 text-teal-700',
                                        'login_success' => 'bg-green-100 text-green-700',
                                        'login_failed' => 'bg-yellow-100 text-yellow-700'
                                    ];
                                    $color = $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="<?= $color ?> px-3 py-1 rounded-full text-xs font-bold">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-sm">
                                    <?= htmlspecialchars($log['resource_type']) ?>
                                    <?= $log['resource_id'] ? '#' . $log['resource_id'] : '' ?>
                                </td>
                                <td class="p-3 text-xs text-gray-500 font-mono">
                                    <?= htmlspecialchars($log['ip_address']) ?>
                                </td>
                                <td class="p-3">
                                    <?php if ($log['old_value'] || $log['new_value']): ?>
                                        <button onclick="showDetails(<?= $log['id'] ?>)" 
                                                class="text-teal-500 hover:text-teal-700 text-xs font-bold">
                                            <i class="fas fa-eye ml-1"></i>
                                            عرض
                                        </button>
                                        <div id="details-<?= $log['id'] ?>" class="hidden mt-2 p-3 bg-gray-50 rounded text-xs">
                                            <?php if ($log['old_value']): ?>
                                                <div class="mb-2">
                                                    <strong>القديم:</strong>
                                                    <pre class="mt-1 text-xs overflow-auto"><?= htmlspecialchars($log['old_value']) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($log['new_value']): ?>
                                                <div>
                                                    <strong>الجديد:</strong>
                                                    <pre class="mt-1 text-xs overflow-auto"><?= htmlspecialchars($log['new_value']) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="flex justify-center gap-2 mt-6">
                    <?php if ($page > 1): ?>
                        <a href="?p=<?= $page - 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" 
                           class="px-4 py-2 bg-teal-100 text-teal-700 rounded-lg font-bold hover:bg-teal-200 transition">
                            السابق
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?p=<?= $i ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" 
                           class="px-4 py-2 <?= $i == $page ? 'bg-teal-500 text-white' : 'bg-teal-100 text-teal-700' ?> rounded-lg font-bold hover:bg-teal-200 transition">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?p=<?= $page + 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" 
                           class="px-4 py-2 bg-teal-100 text-teal-700 rounded-lg font-bold hover:bg-teal-200 transition">
                            التالي
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function showDetails(id) {
    const element = document.getElementById('details-' + id);
    element.classList.toggle('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
