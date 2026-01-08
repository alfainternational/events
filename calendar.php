<?php
require_once 'includes/init.php';

// جلب الفعاليات المعتمدة للتقويم
$stmt = $pdo->query("
    SELECT e.*, h.name as hall_name
    FROM events e
    LEFT JOIN halls h ON e.hall_id = h.id
    WHERE e.status = 'approved' AND e.deleted_at IS NULL
    ORDER BY e.start_date ASC
");
$events = $stmt->fetchAll();

// تحويل الفعاليات لصيغة FullCalendar
$calendarEvents = [];
foreach ($events as $event) {
    $calendarEvents[] = [
        'id' => $event['id'],
        'title' => $event['title'],
        'start' => $event['start_date'] . 'T' . $event['start_time'],
        'end' => $event['end_date'] . 'T' . $event['end_time'],
        'backgroundColor' => $event['location_type'] == 'internal' ? '#14b8a6' : '#f97316',
        'borderColor' => $event['location_type'] == 'internal' ? '#0f766e' : '#ea580c',
        'extendedProps' => [
            'organizing_dept' => $event['organizing_dept'],
            'location_type' => $event['location_type'],
            'hall_name' => $event['hall_name'] ?? $event['custom_hall_name'] ?? 'N/A',
            'attendees' => $event['attendees_expected']
        ]
    ];
}

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-black text-teal-900">تقويم الفعاليات</h2>
            <p class="text-teal-600 mt-1">عرض جميع الفعاليات المعتمدة في تقويم تفاعلي</p>
        </div>
        <div class="flex gap-3">
            <a href="search.php" class="px-6 py-3 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 transition">
                <i class="fas fa-search ml-2"></i>
                بحث متقدم
            </a>
            <a href="index.php" class="px-6 py-3 bg-white border-2 border-teal-500 text-teal-500 rounded-xl font-bold hover:bg-teal-50 transition">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة
            </a>
        </div>
    </div>

    <!-- المؤشرات -->
    <div class="flex gap-4 mb-6 flex-wrap">
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded" style="background-color: #14b8a6;"></div>
            <span class="text-sm font-bold">فعاليات داخلية</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded" style="background-color: #f97316;"></div>
            <span class="text-sm font-bold">فعاليات خارجية</span>
        </div>
    </div>

    <!-- التقويم -->
    <div class="shimal-card bg-white p-6">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal لعرض تفاصيل الفعالية -->
<div id="eventModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
    <div class="bg-white rounded-2xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-6">
            <h3 id="modalTitle" class="text-2xl font-black text-teal-900"></h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
        </div>
        <div id="modalContent" class="space-y-4"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeModal()" class="px-6 py-3 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 transition">
                إغلاق
            </button>
        </div>
    </div>
</div>

<!-- FullCalendar من CDN -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ar',
        direction: 'rtl',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'اليوم',
            month: 'شهر',
            week: 'أسبوع',
            day: 'يوم',
            list: 'قائمة'
        },
        events: <?= json_encode($calendarEvents, JSON_UNESCAPED_UNICODE) ?>,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            showEventDetails(info.event);
        },
        eventDidMount: function(info) {
            info.el.style.cursor = 'pointer';
        },
        height: 'auto',
        views: {
            dayGridMonth: {
                titleFormat: { year: 'numeric', month: 'long' }
            }
        },
        // إعدادات responsive للموبايل
        windowResize: function(view) {
            if (window.innerWidth < 768) {
                calendar.changeView('listMonth');
            }
        },
        // تحسين العرض على الشاشات الصغيرة
        contentHeight: 'auto',
        aspectRatio: window.innerWidth < 768 ? 1 : 1.35
    });
    
    // تغيير العرض تلقائياً على الموبايل
    if (window.innerWidth < 768) {
        calendar.changeView('listMonth');
    }
    
    calendar.render();
});

function showEventDetails(event) {
    const modal = document.getElementById('eventModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    
    title.textContent = event.title;
    
    const startDate = new Date(event.start);
    const endDate = new Date(event.end);
    
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">الجهة المنظمة</p>
                <p class="font-bold">${event.extendedProps.organizing_dept}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">نوع الفعالية</p>
                <p class="font-bold">${event.extendedProps.location_type == 'internal' ? 'داخلية' : 'خارجية'}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">تاريخ البدء</p>
                <p class="font-bold">${startDate.toLocaleDateString('ar-SA')} ${startDate.toLocaleTimeString('ar-SA', {hour: '2-digit', minute: '2-digit'})}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">تاريخ الانتهاء</p>
                <p class="font-bold">${endDate.toLocaleDateString('ar-SA')} ${endDate.toLocaleTimeString('ar-SA', {hour: '2-digit', minute: '2-digit'})}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">المكان</p>
                <p class="font-bold">${event.extendedProps.hall_name}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">عدد الحضور المتوقع</p>
                <p class="font-bold">${event.extendedProps.attendees || 'غير محدد'}</p>
            </div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('eventModal').style.display = 'none';
}

// إغلاق عند الضغط على الخلفية
document.getElementById('eventModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
