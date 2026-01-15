<?php
require_once 'includes/init.php';

// معالجة البحث
$results = [];
$searchPerformed = false;

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $searchPerformed = true;
    
    $where = ["deleted_at IS NULL"];
    $params = [];
    
    // البحث النصي
    if (!empty($_GET['q'])) {
        $where[] = "(title LIKE ? OR organizing_dept LIKE ? OR notes LIKE ?)";
        $searchTerm = '%' . $_GET['q'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // الحالة
    if (!empty($_GET['status'])) {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
    }
    
    // نوع الفعالية
    if (!empty($_GET['location_type'])) {
        $where[] = "location_type = ?";
        $params[] = $_GET['location_type'];
    }
    
    // القاعة
    if (!empty($_GET['hall_id'])) {
        $where[] = "hall_id = ?";
        $params[] = $_GET['hall_id'];
    }
    
    // التاريخ من
    if (!empty($_GET['date_from'])) {
        $where[] = "start_date >= ?";
        $params[] = $_GET['date_from'];
    }
    
    // التاريخ إلى
    if (!empty($_GET['date_to'])) {
        $where[] = "start_date <= ?";
        $params[] = $_GET['date_to'];
    }
    
    // الفرز
    $orderBy = "start_date DESC";
    if (!empty($_GET['sort'])) {
        $sortOptions = [
            'date_asc' => 'start_date ASC',
            'date_desc' => 'start_date DESC',
            'title_asc' => 'title ASC',
            'title_desc' => 'title DESC',
            'created_asc' => 'created_at ASC',
            'created_desc' => 'created_at DESC'
        ];
        if (isset($sortOptions[$_GET['sort']])) {
            $orderBy = $sortOptions[$_GET['sort']];
        }
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT e.*, h.name as hall_name
        FROM events e
        LEFT JOIN halls h ON e.hall_id = h.id
        WHERE {$whereClause}
        ORDER BY {$orderBy}
    ");
    
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

// جلب القاعات للفلتر
$halls = $pdo->query("SELECT * FROM halls ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-black text-teal-900">البحث المتقدم</h2>
        <p class="text-teal-600 mt-1">ابحث في الفعاليات باستخدام فلاتر متعددة</p>
    </div>

    <!-- نموذج البحث -->
    <div class="shimal-card bg-white p-6 mb-8">
        <form method="GET" class="space-y-6">
            <input type="hidden" name="search" value="1">
            
            <!-- البحث النصي -->
            <div>
                <label class="block text-sm font-bold text-teal-900 mb-2">
                    <i class="fas fa-search ml-2"></i>
                    البحث النصي
                </label>
                <input 
                    type="text" 
                    name="q" 
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    placeholder="ابحث في العنوان، الجهة المنظمة، أو الملاحظات..."
                    class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold focus:ring-2 focus:ring-teal-500 focus:border-transparent"
                >
            </div>

            <!-- الفلاتر -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- الحالة -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">الحالة</label>
                    <select name="status" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                        <option value="">الكل</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>قيد المراجعة</option>
                        <option value="approved" <?= ($_GET['status'] ?? '') == 'approved' ? 'selected' : '' ?>>معتمد</option>
                        <option value="rejected" <?= ($_GET['status'] ?? '') == 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                    </select>
                </div>

                <!-- نوع الفعالية -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">نوع الفعالية</label>
                    <select name="location_type" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                        <option value="">الكل</option>
                        <option value="internal" <?= ($_GET['location_type'] ?? '') == 'internal' ? 'selected' : '' ?>>داخلية</option>
                        <option value="external" <?= ($_GET['location_type'] ?? '') == 'external' ? 'selected' : '' ?>>خارجية</option>
                    </select>
                </div>

                <!-- القاعة -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">القاعة</label>
                    <select name="hall_id" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                        <option value="">الكل</option>
                        <?php foreach ($halls as $hall): ?>
                            <option value="<?= $hall['id'] ?>" <?= ($_GET['hall_id'] ?? '') == $hall['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($hall['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- التاريخ من -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">من تاريخ</label>
                    <input 
                        type="date" 
                        name="date_from" 
                        value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                        class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold"
                    >
                </div>

                <!-- التاريخ إلى -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">إلى تاريخ</label>
                    <input 
                        type="date" 
                        name="date_to" 
                        value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                        class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold"
                    >
                </div>

                <!-- الفرز -->
                <div>
                    <label class="block text-xs font-bold text-teal-700 mb-2">الترتيب</label>
                    <select name="sort" class="w-full p-3 bg-teal-50 border border-teal-100 rounded-xl font-bold">
                        <option value="date_desc" <?= ($_GET['sort'] ?? '') == 'date_desc' ? 'selected' : '' ?>>الأحدث أولاً</option>
                        <option value="date_asc" <?= ($_GET['sort'] ?? '') == 'date_asc' ? 'selected' : '' ?>>الأقدم أولاً</option>
                        <option value="title_asc" <?= ($_GET['sort'] ?? '') == 'title_asc' ? 'selected' : '' ?>>العنوان (أ-ي)</option>
                        <option value="title_desc" <?= ($_GET['sort'] ?? '') == 'title_desc' ? 'selected' : '' ?>>العنوان (ي-أ)</option>
                    </select>
                </div>
            </div>

            <!-- أزرار -->
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-teal-500 text-white py-3 px-6 rounded-xl font-bold hover:bg-teal-600 transition">
                    <i class="fas fa-search ml-2"></i>
                    بحث
                </button>
                <a href="search.php" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition">
                    <i class="fas fa-redo ml-2"></i>
                    إعادة تعيين
                </a>
                <a href="index.php" class="px-6 py-3 bg-white border-2 border-teal-500 text-teal-500 rounded-xl font-bold hover:bg-teal-50 transition">
                    <i class="fas fa-times ml-2"></i>
                    إلغاء
                </a>
            </div>
        </form>
    </div>

    <!-- النتائج -->
    <?php if ($searchPerformed): ?>
        <div class="shimal-card bg-white p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-black text-teal-900">
                    النتائج: <?= count($results) ?> فعالية
                </h3>
                <?php if (!empty($results)): ?>
                    <a href="?export=csv&<?= http_build_query($_GET) ?>" class="text-sm px-4 py-2 bg-green-500 text-white rounded-lg font-bold hover:bg-green-600 transition">
                        <i class="fas fa-file-csv ml-2"></i>
                        تصدير CSV
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($results)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-search text-5xl text-gray-300 mb-4"></i>
                    <p class="font-bold text-gray-400">لم يتم العثور على نتائج</p>
                    <p class="text-sm text-gray-400 mt-2">جرب تعديل معايير البحث</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($results as $event): ?>
                        <div class="border-2 border-teal-100 rounded-xl p-4 hover:border-teal-300 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="text-lg font-black text-teal-900 mb-2">
                                        <?= htmlspecialchars($event['title']) ?>
                                    </h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <span class="text-gray-500">الجهة:</span>
                                            <strong class="mr-2"><?= htmlspecialchars($event['organizing_dept']) ?></strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">التاريخ:</span>
                                            <strong class="mr-2"><?= $event['start_date'] ?></strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">النوع:</span>
                                            <strong class="mr-2"><?= $event['location_type'] == 'internal' ? 'داخلية' : 'خارجية' ?></strong>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">الحالة:</span>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'approved' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700'
                                            ];
                                            $statusLabels = [
                                                'pending' => 'قيد المراجعة',
                                                'approved' => 'معتمد',
                                                'rejected' => 'مرفوض'
                                            ];
                                            ?>
                                            <span class="<?= $statusColors[$event['status']] ?> px-3 py-1 rounded-full text-xs font-bold mr-2">
                                                <?= $statusLabels[$event['status']] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="shimal-card bg-gradient-to-br from-teal-50 to-blue-50 p-12 text-center">
            <i class="fas fa-search text-6xl text-teal-300 mb-6"></i>
            <h3 class="text-2xl font-black text-teal-900 mb-3">ابدأ البحث</h3>
            <p class="text-teal-600">استخدم الفلاتر أعلاه للبحث عن الفعاليات</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
