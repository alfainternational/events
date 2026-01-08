<?php
require_once 'includes/init.php';

// حماية الصفحة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// معالجة التصدير
if (isset($_GET['export'])) {
    require_once 'includes/export.php';
    $filters = [];
    
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    if (isset($_GET['type'])) $filters['location_type'] = $_GET['type'];
    if (isset($_GET['from'])) $filters['date_from'] = $_GET['from'];
    if (isset($_GET['to'])) $filters['date_to'] = $_GET['to'];
    
    switch ($_GET['export']) {
        case 'csv':
            export_to_csv($filters);
            break;
        case 'excel':
            export_to_excel($filters);
            break;
        case 'pdf':
            export_to_pdf($filters);
            break;
    }
}

// إحصائيات الطلبات حسب الحالة
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM events 
    WHERE deleted_at IS NULL 
    GROUP BY status
");
$statusStats = [];
while ($row = $stmt->fetch()) {
    $statusStats[$row['status']] = $row['count'];
}

// إحصائيات حسب نوع الفعالية
$stmt = $pdo->query("
    SELECT location_type, COUNT(*) as count 
    FROM events 
    WHERE deleted_at IS NULL 
    GROUP BY location_type
");
$locationStats = [];
while ($row = $stmt->fetch()) {
    $locationStats[$row['location_type']] = $row['count'];
}

// إحصائيات شهرية (آخر 12 شهر)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(start_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM events
    WHERE deleted_at IS NULL 
    AND start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthlyStats = $stmt->fetchAll();

// أكثر القاعات استخداماً
$stmt = $pdo->query("
    SELECT 
        COALESCE(h.name, e.custom_hall_name, 'غير محدد') as hall_name,
        COUNT(*) as count
    FROM events e
    LEFT JOIN halls h ON e.hall_id = h.id
    WHERE e.deleted_at IS NULL 
    AND e.location_type = 'internal'
    GROUP BY hall_name
    ORDER BY count DESC
    LIMIT 5
");
$hallStats = $stmt->fetchAll();

// متوسط وقت الموافقة
$stmt = $pdo->query("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours
    FROM events
    WHERE status = 'approved'
    AND updated_at IS NOT NULL
");
$avgApprovalTime = $stmt->fetch()['avg_hours'] ?? 0;

// إحصائيات عامة
$totalEvents = array_sum($statusStats);
$approvedEvents = $statusStats['approved'] ?? 0;
$pendingEvents = $statusStats['pending'] ?? 0;
$rejectedEvents = $statusStats['rejected'] ?? 0;
$approvalRate = $totalEvents > 0 ? round(($approvedEvents / $totalEvents) * 100, 1) : 0;

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-black text-teal-900">لوحة الإحصائيات</h2>
            <p class="text-teal-600 mt-1">تحليل شامل للفعاليات والطلبات</p>
        </div>
        <div class="flex gap-3">
            <a href="?export=csv" class="px-6 py-3 bg-green-500 text-white rounded-xl font-bold hover:bg-green-600 transition">
                <i class="fas fa-file-csv ml-2"></i>
                تصدير CSV
            </a>
            <a href="admin.php" class="px-6 py-3 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 transition">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة
            </a>
        </div>
    </div>

    <!-- البطاقات الإحصائية -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="shimal-card bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-sm opacity-90">إجمالي الطلبات</p>
                    <h3 class="text-4xl font-black mt-2"><?= number_format($totalEvents) ?></h3>
                </div>
                <i class="fas fa-calendar-alt text-3xl opacity-50"></i>
            </div>
        </div>

        <div class="shimal-card bg-gradient-to-br from-green-500 to-green-600 text-white p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-sm opacity-90">الفعاليات المعتمدة</p>
                    <h3 class="text-4xl font-black mt-2"><?= number_format($approvedEvents) ?></h3>
                </div>
                <i class="fas fa-check-circle text-3xl opacity-50"></i>
            </div>
            <div class="text-sm opacity-90">نسبة القبول: <?= $approvalRate ?>%</div>
        </div>

        <div class="shimal-card bg-gradient-to-br from-yellow-500 to-yellow-600 text-white p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-sm opacity-90">قيد المراجعة</p>
                    <h3 class="text-4xl font-black mt-2"><?= number_format($pendingEvents) ?></h3>
                </div>
                <i class="fas fa-clock text-3xl opacity-50"></i>
            </div>
        </div>

        <div class="shimal-card bg-gradient-to-br from-red-500 to-red-600 text-white p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-sm opacity-90">المرفوضة</p>
                    <h3 class="text-4xl font-black mt-2"><?= number_format($rejectedEvents) ?></h3>
                </div>
                <i class="fas fa-times-circle text-3xl opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- الرسوم البيانية -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- توزيع الحالات -->
        <div class="shimal-card bg-white p-6">
            <h3 class="text-lg font-black text-teal-900 mb-6">توزيع الطلبات حسب الحالة</h3>
            <canvas id="statusChart" class="max-h-64"></canvas>
        </div>

        <!-- نوع الفعالية -->
        <div class="shimal-card bg-white p-6">
            <h3 class="text-lg font-black text-teal-900 mb-6">نوع الفعاليات</h3>
            <canvas id="locationChart" class="max-h-64"></canvas>
        </div>

        <!-- الطلبات الشهرية -->
        <div class="shimal-card bg-white p-6 md:col-span-2">
            <h3 class="text-lg font-black text-teal-900 mb-6">الطلبات الشهرية (آخر 12 شهر)</h3>
            <canvas id="monthlyChart" class="max-h-64"></canvas>
        </div>
    </div>

    <!-- القاعات والأداء -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- أكثر القاعات استخداماً -->
        <div class="shimal-card bg-white p-6">
            <h3 class="text-lg font-black text-teal-900 mb-6">أكثر القاعات استخداماً</h3>
            <div class="space-y-4">
                <?php foreach ($hallStats as $index => $hall): ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-teal-900"><?= htmlspecialchars($hall['hall_name']) ?></span>
                            <span class="text-teal-600 font-bold"><?= $hall['count'] ?> فعالية</span>
                        </div>
                        <div class="w-full bg-teal-100 rounded-full h-3">
                            <?php $percentage = ($hall['count'] / $hallStats[0]['count']) * 100; ?>
                            <div class="bg-teal-500 h-3 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- مؤشرات الأداء -->
        <div class="shimal-card bg-white p-6">
            <h3 class="text-lg font-black text-teal-900 mb-6">مؤشرات الأداء</h3>
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-hourglass-half text-2xl text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">متوسط وقت الموافقة</p>
                        <p class="text-2xl font-black text-teal-900"><?= round($avgApprovalTime, 1) ?> ساعة</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-percentage text-2xl text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">نسبة القبول</p>
                        <p class="text-2xl font-black text-teal-900"><?= $approvalRate ?>%</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-building text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">فعاليات داخلية</p>
                        <p class="text-2xl font-black text-teal-900"><?= $locationStats['internal'] ?? 0 ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-map-marker-alt text-2xl text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">فعاليات خارجية</p>
                        <p class="text-2xl font-black text-teal-900"><?= $locationStats['external'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js من CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// توزيع الحالات
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['معتمد', 'قيد المراجعة', 'مرفوض'],
        datasets: [{
            data: [<?= $approvedEvents ?>, <?= $pendingEvents ?>, <?= $rejectedEvents ?>],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// نوع الفعالية
const locationCtx = document.getElementById('locationChart').getContext('2d');
new Chart(locationCtx, {
    type: 'pie',
    data: {
        labels: ['داخلية', 'خارجية'],
        datasets: [{
            data: [<?= $locationStats['internal'] ?? 0 ?>, <?= $locationStats['external'] ?? 0 ?>],
            backgroundColor: ['#3b82f6', '#f97316']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// الطلبات الشهرية
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: [<?php foreach ($monthlyStats as $m) echo "'" . $m['month'] . "',"; ?>],
        datasets: [{
            label: 'عدد الطلبات',
            data: [<?php foreach ($monthlyStats as $m) echo $m['count'] . ','; ?>],
            borderColor: '#14b8a6',
            backgroundColor: 'rgba(20, 184, 166, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
