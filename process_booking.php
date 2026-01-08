<?php
require_once 'includes/init.php';
require_once 'includes/multi_day_helpers.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rate limiting للحجوزات - 3 طلبات في الساعة
    $rateLimit = check_rate_limit('booking', 3, 60);
    if (!$rateLimit['allowed']) {
        set_flash('error', $rateLimit['message']);
        header("Location: index.php?page=booking");
        exit();
    }

    // التحقق من CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
        header("Location: index.php?page=booking");
        exit();
    }
    
    // البيانات الأساسية
    $title = $_POST['title'] ?? '';
    $organizing_dept = $_POST['organizing_dept'] ?? '';
    $related_depts = $_POST['related_depts'] ?? '';
    $requester_mobile = $_POST['requester_mobile'] ?? '';
    $requester_email = $_POST['requester_email'] ?? '';
    $locationType = $_POST['locationType'] ?? 'internal';
    $notes = $_POST['notes'] ?? '';
    
    // معالجة بيانات الأيام المتعددة
    $multiDayData = process_multi_day_booking($_POST);
    
    // التحقق من أن هناك أيام محددة
    if (empty($multiDayData['event_days'])) {
        set_flash('error', 'يرجى تحديد أيام الفعالية');
        header("Location: index.php?page=booking");
        exit();
    }
    
    // التحقق من عدم اختيار الجمعة للفعاليات الداخلية
    if (!validate_no_friday_in_days($multiDayData['event_days'], $locationType)) {
        set_flash('error', 'لا يمكن حجز فعاليات داخلية يوم الجمعة');
        header("Location: index.php?page=booking");
        exit();
    }
    
    // التحقق من الأوقات للفعاليات الداخلية
    if (!validate_internal_times($multiDayData['event_days'], $locationType)) {
        set_flash('error', 'أوقات الفعاليات الداخلية يجب أن تكون من 8 صباحاً إلى 4 مساءً');
        header("Location: index.php?page=booking");
        exit();
    }

    // حقول مشتركة
    $attendees = 0;
    
    // حقول محددة حسب النوع
    $hall_id = null;
    $custom_hall_name = null;
    $req_audio = 0;
    $req_projector = 0;
    
    // ...
    if ($locationType == 'internal') {
        $hall_selection = $_POST['hall_selection_type'] ?? '1';
        if ($hall_selection === 'custom') {
            $hall_id = null;
            $custom_hall_name = $_POST['custom_hall_name'] ?? '';
        } else {
            $hall_id = (int)$hall_selection;
            $custom_hall_name = null;
        }
        $attendees = $_POST['attendees_internal'] ?? 0;
        $req_audio = isset($_POST['req_audio']) ? 1 : 0;
        $req_catering = isset($_POST['req_catering']) ? 1 : 0;
        $req_security = isset($_POST['req_security']) ? 1 : 0;
        $req_media = isset($_POST['req_media']) ? 1 : 0;
        $req_projector = isset($_POST['req_projector']) ? 1 : 0;

        // التحقق من تعارض المواعيد
        $checkSql = "SELECT id FROM events 
                     WHERE status != 'rejected' 
                     AND (hall_id = ? OR (custom_hall_name = ? AND custom_hall_name IS NOT NULL AND custom_hall_name != ''))
                     AND (
                        (start_date <= ? AND end_date >= ?)
                     )
                     AND (
                        (start_time <= ? AND end_time >= ?)
                     )";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$hall_id, $custom_hall_name, $endDate, $startDate, $endTime, $startTime]);
        if ($checkStmt->fetch()) {
            set_flash('error', 'عذراً! يوجد تعارض في المواعيد. المكان محجوز بالفعل في هذا الوقت.');
            header("Location: index.php?page=booking");
            exit();
        }

    } else {
        $extAddress = $_POST['extAddress'] ?? '';
        $attendees = $_POST['attendees_external'] ?? 0;
        $req_transport = isset($_POST['req_transport']) ? 1 : 0;
        $req_catering = 0;
        $req_security = 0;
        $req_media = 0;
        $req_projector = 0;
        $mkt_brochures = $_POST['mkt_brochures'] ?? 0;
        $mkt_gifts = $_POST['mkt_gifts'] ?? 0;
        $mkt_tools = $_POST['mkt_tools'] ?? 0;
        $estimated_budget = $_POST['estimated_budget'] ?? 0;
        $guest_list = $_POST['guest_list'] ?? '';
    }
    
    // توليد رمز التعديل الفريد
    $edit_token = generate_edit_token();
    
    // تحويل بيانات الأيام إلى JSON
    $event_days_json = json_encode($multiDayData['event_days'], JSON_UNESCAPED_UNICODE);

    try {
        $sql = "INSERT INTO events (
                    title, organizing_dept, related_depts, requester_mobile, requester_email, 
                    start_date, end_date, start_time, end_time, location_type, 
                    hall_id, custom_hall_name, req_audio, req_catering, req_security, req_media, req_projector,
                    external_address, req_transport, mkt_brochures, mkt_gifts, mkt_tools, estimated_budget, guest_list,
                    attendees_expected, notes, edit_token, status,
                    booking_type, unified_timing, event_days_json
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, 'pending',
                    ?, ?, ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, $organizing_dept, $related_depts, $requester_mobile, $requester_email,
            $multiDayData['first_date'], $multiDayData['last_date'], 
            $multiDayData['first_time'], $multiDayData['last_time'], 
            $locationType,
            $hall_id, $custom_hall_name, $req_audio, $req_catering, $req_security, $req_media, $req_projector,
            $extAddress, $req_transport, $mkt_brochures, $mkt_gifts, $mkt_tools, $estimated_budget, $guest_list,
            $attendees, $notes, $edit_token,
            $multiDayData['booking_type'], $multiDayData['unified_timing'], $event_days_json
        ]);
        
        $event_id = $pdo->lastInsertId();

        // حفظ أول نسخة (الإنشاء)
        require_once 'includes/versioning.php';
        save_event_version($event_id, 'create', 'إنشاء طلب جديد');
        
        // Audit log: new event created
        $logger = new AuditLogger($pdo);
        $logger->logEvent('create', $event_id, null, [
            'title' => $title,
            'organizing_dept' => $organizing_dept,
            'start_date' => $multiDayData['first_date'],
            'location_type' => $locationType,
            'booking_type' => $multiDayData['booking_type']
        ]);
        
        // حفظ رمز التعديل في الجلسة لعرضه للمستخدم
        $_SESSION['new_event_token'] = $edit_token;
        $_SESSION['new_event_id'] = $event_id;
        
        header("Location: index.php?page=success");
        exit();

    } catch (\PDOException $e) {
        // تسجيل الخطأ فقط وعدم عرض التفاصيل للمستخدم
        error_log("Database error in process_booking.php: " . $e->getMessage());
        set_flash('error', 'حدث خطأ أثناء حفظ الطلب. يرجى المحاولة مرة أخرى.');
        header("Location: index.php?page=booking");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
