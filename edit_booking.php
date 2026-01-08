<?php
require_once 'includes/init.php';

$step = isset($_GET['step']) ? $_GET['step'] : 'enter_token';
$event = null;
$error = '';

// معالجة طلب التحقق من الرمز
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'verify_token') {
        $edit_token = trim($_POST['edit_token'] ?? '');
        
        if (empty($edit_token)) {
            $error = 'يرجى إدخال رمز التعديل';
        } else {
            // البحث عن الفعالية بالرمز
            $stmt = $pdo->prepare("SELECT * FROM events WHERE edit_token = ?");
            $stmt->execute([$edit_token]);
            $event = $stmt->fetch();
            
            if (!$event) {
                $error = 'رمز التعديل غير صحيح';
            } elseif ($event['status'] == 'rejected') {
                $error = 'لا يمكن تعديل طلب مرفوض';
            } else {
                // التحقق من الوقت المتبقي
                $can_edit = can_edit_event($event['start_date'], $event['start_time'], $pdo);
                
                if (!$can_edit['can_edit']) {
                    $error = $can_edit['message'];
                } else {
                    // حفظ معرف الطلب في الجلسة
                    $_SESSION['edit_event_id'] = $event['id'];
                    header("Location: edit_booking.php?step=edit");
                    exit();
                }
            }
        }
    } elseif ($_POST['action'] == 'update_event') {
        // التحقق من CSRF
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
            header("Location: edit_booking.php");
            exit();
        }
        
        // التحقق من وجود معرف الطلب في الجلسة
        if (!isset($_SESSION['edit_event_id'])) {
            header("Location: edit_booking.php");
            exit();
        }
        
        $event_id = $_SESSION['edit_event_id'];
        
        // جلب الطلب الحالي
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if (!$event) {
            set_flash('error', 'الطلب غير موجود');
            header("Location: edit_booking.php");
            exit();
        }
        
        // التحقق من الوقت المتبقي مرة أخرى
        $can_edit = can_edit_event($event['start_date'], $event['start_time'], $pdo);
        if (!$can_edit['can_edit']) {
            set_flash('error', $can_edit['message']);
            header("Location: edit_booking.php");
            exit();
        }
        
        // جمع البيانات
        require_once 'includes/multi_day_helpers.php';
        
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
            header("Location: edit_booking.php?step=edit");
            exit();
        }
        
        // التحقق من عدم اختيار الجمعة للفعاليات الداخلية
        if (!validate_no_friday_in_days($multiDayData['event_days'], $locationType)) {
            set_flash('error', 'لا يمكن حجز فعاليات داخلية يوم الجمعة');
            header("Location: edit_booking.php?step=edit");
            exit();
        }
        
        // التحقق من الأوقات للفعاليات الداخلية
        if (!validate_internal_times($multiDayData['event_days'], $locationType)) {
            set_flash('error', 'أوقات الفعاليات الداخلية يجب أن تكون من 8 صباحاً إلى 4 مساءً');
            header("Location: edit_booking.php?step=edit");
            exit();
        }
        
        // معالجة البيانات حسب نوع الفعالية
        $attendees = 0;
        $hall_id = null;
        $custom_hall_name = null;
        $extAddress = null;
        $req_audio = 0;
        $req_catering = 0;
        $req_security = 0;
        $req_media = 0;
        $req_projector = 0;
        $req_transport = 0;
        $mkt_brochures = 0;
        $mkt_gifts = 0;
        $mkt_tools = 0;
        $estimated_budget = 0;
        $guest_list = '';
        
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
        } else {
            $extAddress = $_POST['extAddress'] ?? '';
            $attendees = $_POST['attendees_external'] ?? 0;
            $req_transport = isset($_POST['req_transport']) ? 1 : 0;
            $mkt_brochures = $_POST['mkt_brochures'] ?? 0;
            $mkt_gifts = $_POST['mkt_gifts'] ?? 0;
            $mkt_tools = $_POST['mkt_tools'] ?? 0;
            $estimated_budget = $_POST['estimated_budget'] ?? 0;
            $guest_list = $_POST['guest_list'] ?? '';
        }
        
        try {
            // حفظ نسخة قبل التعديل
            require_once 'includes/versioning.php';
            save_event_version($event_id, 'update', 'تعديل من مقدم الطلب');
            
            // تحويل بيانات الأيام إلى JSON
            $event_days_json = json_encode($multiDayData['event_days'], JSON_UNESCAPED_UNICODE);
            
            // تحديث الطلب في قاعدة البيانات
            $sql = "UPDATE events SET 
                        title = ?, organizing_dept = ?, related_depts = ?, 
                        requester_mobile = ?, requester_email = ?,
                        start_date = ?, end_date = ?, start_time = ?, end_time = ?,
                        location_type = ?, hall_id = ?, custom_hall_name = ?,
                        req_audio = ?, req_catering = ?, req_security = ?, req_media = ?, req_projector = ?,
                        external_address = ?, req_transport = ?,
                        mkt_brochures = ?, mkt_gifts = ?, mkt_tools = ?, estimated_budget = ?, guest_list = ?,
                        attendees_expected = ?, notes = ?,
                        booking_type = ?, unified_timing = ?, event_days_json = ?,
                        status = 'pending'
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title, $organizing_dept, $related_depts,
                $requester_mobile, $requester_email,
                $multiDayData['first_date'], $multiDayData['last_date'], $multiDayData['first_time'], $multiDayData['last_time'],
                $locationType, $hall_id, $custom_hall_name,
                $req_audio, $req_catering, $req_security, $req_media, $req_projector,
                $extAddress, $req_transport,
                $mkt_brochures, $mkt_gifts, $mkt_tools, $estimated_budget, $guest_list,
                $attendees, $notes,
                $multiDayData['booking_type'], $multiDayData['unified_timing'], $event_days_json,
                $event_id
            ]);
            
            // Audit log: event updated
            $logger = new AuditLogger($pdo);
            $logger->logEvent('update', $event_id, 
                [
                    'title' => $event['title'],
                    'status' => $event['status'],
                    'start_date' => $event['start_date']
                ],
                [
                    'title' => $title,
                    'status' => 'pending',
                    'start_date' => $startDate
                ]
            );
            
            // حذف المعرف من الجلسة
            unset($_SESSION['edit_event_id']);
            
            // حفظ رسالة نجاح مع تنبيه الاتصال
            $_SESSION['edit_success'] = true;
            header("Location: edit_booking.php?step=success");
            exit();
            
        } catch (\PDOException $e) {
            error_log("Database error in edit_booking.php: " . $e->getMessage());
            set_flash('error', 'حدث خطأ أثناء حفظ التعديلات. يرجى المحاولة مرة أخرى.');
            header("Location: edit_booking.php?step=edit");
            exit();
        }
    }
}

// إذا كانت الخطوة edit، نجلب البيانات من الجلسة
if ($step == 'edit') {
    if (!isset($_SESSION['edit_event_id'])) {
        header("Location: edit_booking.php");
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT e.*, h.name as hall_name FROM events e LEFT JOIN halls h ON e.hall_id = h.id WHERE e.id = ?");
    $stmt->execute([$_SESSION['edit_event_id']]);
    $event = $stmt->fetch();
    
    if (!$event) {
        unset($_SESSION['edit_event_id']);
        header("Location: edit_booking.php");
        exit();
    }
}

include 'includes/header.php';
?>

<section class="max-w-4xl mx-auto">
    
    <?php if ($step == 'enter_token'): ?>
        <!-- نموذج إدخال رمز التعديل -->
        <div class="shimal-card bg-white p-10 shadow-2xl text-center">
            <i class="fas fa-key text-5xl text-teal-500 mb-6"></i>
            <h2 class="text-3xl font-black text-teal-900 mb-4">تعديل طلب فعالية</h2>
            <p class="text-teal-600 mb-8">أدخل رمز التعديل الخاص بطلبك للمتابعة</p>
            
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 font-bold">
                    <i class="fas fa-exclamation-circle ml-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="max-w-md mx-auto">
                <input type="hidden" name="action" value="verify_token">
                <input 
                    type="text" 
                    name="edit_token" 
                    placeholder="مثال: ABC12345" 
                    required 
                    maxlength="10"
                    class="w-full p-4 bg-teal-50 border-2 border-teal-100 rounded-2xl text-center font-black text-2xl tracking-widest uppercase outline-none focus:ring-2 focus:ring-teal-500 mb-6">
                <button type="submit" class="w-full btn-primary p-4 rounded-2xl font-black shadow-xl">
                    <i class="fas fa-arrow-left ml-2"></i>
                    متابعة
                </button>
                <a href="index.php" class="block text-sm text-teal-400 font-bold mt-4">العودة للرئيسية</a>
            </form>
        </div>
    
    <?php elseif ($step == 'edit' && $event): ?>
        <!-- نموذج التعديل -->
        <div class="shimal-card bg-white p-10 shadow-2xl">
            <h2 class="text-3xl font-black text-teal-900 mb-8 flex items-center gap-3">
                <i class="fas fa-edit text-accent text-4xl"></i>
                تعديل الطلب: <?= htmlspecialchars($event['title']) ?>
            </h2>
            
            <?php display_flash_messages(); ?>
            
            <form action="edit_booking.php" method="POST" class="space-y-10">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update_event">
                
                <!-- بيانات الفعالية - Same structure as booking form -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">مسمى النشاط / الفعالية</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($event['title']) ?>" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهة المنظمة</label>
                        <input type="text" name="organizing_dept" required value="<?= htmlspecialchars($event['organizing_dept']) ?>" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهات ذات العلاقة</label>
                        <input type="text" name="related_depts" value="<?= htmlspecialchars($event['related_depts']) ?>" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">تاريخ البدء والانتهاء</label>
                        <div class="flex flex-row-reverse gap-2">
                            <input type="date" name="startDate" required value="<?= $event['start_date'] ?>" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                            <input type="date" name="endDate" required value="<?= $event['end_date'] ?>" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">وقت الفعالية</label>
                        <div class="flex flex-row-reverse gap-2">
                            <input type="time" name="startTime" id="startTime" required value="<?= substr($event['start_time'], 0, 5) ?>" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                            <input type="time" name="endTime" id="endTime" required value="<?= substr($event['end_time'], 0, 5) ?>" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                        <p class="text-xs text-teal-600 mt-2 font-medium">
                            <i class="fas fa-calendar-check ml-1"></i>
                            الوقت المحدد سيطبق على جميع أيام الفعالية من تاريخ البدء حتى الانتهاء
                        </p>
                    </div>
                </div>
                
                <!-- بيانات التواصل -->
                <div class="pt-6 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6">بيانات مقدم الطلب</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-black text-teal-800 mb-2 uppercase">رقم الجوال</label>
                            <input type="tel" name="requester_mobile" required value="<?= htmlspecialchars($event['requester_mobile']) ?>" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-black text-teal-800 mb-2 uppercase">البريد الإلكتروني</label>
                            <input type="email" name="requester_email" value="<?= htmlspecialchars($event['requester_email']) ?>" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                    </div>
                </div>
                
                <!-- الموقع والمتطلبات -->
                <div class="pt-6 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6">الموقع والمتطلبات</h3>
                    <input type="hidden" name="locationType" id="locationType" value="<?= $event['location_type'] ?>">
                    <div class="flex gap-4 p-2 bg-teal-50 rounded-2xl mb-8">
                        <button type="button" onclick="setLoc('internal')" id="tab-int" class="flex-1 py-4 rounded-xl font-black <?= $event['location_type'] == 'internal' ? 'bg-white shadow-md text-teal-900' : 'text-teal-500' ?>">فعالية داخلية</button>
                        <button type="button" onclick="setLoc('external')" id="tab-ext" class="flex-1 py-4 rounded-xl font-black <?= $event['location_type'] == 'external' ? 'bg-white shadow-md text-teal-900' : 'text-teal-500' ?>">فعالية خارجية</button>
                    </div>
                    
                    <!-- خيارات الفعالية الداخلية -->
                    <div id="loc-internal" class="<?= $event['location_type'] != 'internal' ? 'hidden' : '' ?> space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">اختر القاعة</label>
                                <select name="hall_selection_type" id="hall_selection_type" onchange="toggleHallNameInput()" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                                    <option value="1" <?= $event['hall_id'] == 1 ? 'selected' : '' ?>>المسرح (قاعة الدلما رحمها الله)</option>
                                    <option value="custom" <?= $event['custom_hall_name'] ? 'selected' : '' ?>>قاعة أخرى</option>
                                </select>
                            </div>
                            <div id="custom_hall_input_div" class="<?= !$event['custom_hall_name'] ? 'hidden' : '' ?>">
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">اسم القاعة</label>
                                <input type="text" name="custom_hall_name" value="<?= htmlspecialchars($event['custom_hall_name']) ?>" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">العدد المتوقع</label>
                                <input type="number" name="attendees_internal" value="<?= $event['attendees_expected'] ?>" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_audio" value="1" <?= $event['req_audio'] ? 'checked' : '' ?> class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">أنظمة صوت</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_catering" value="1" <?= $event['req_catering'] ? 'checked' : '' ?> class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">ضيافة</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_security" value="1" <?= $event['req_security'] ? 'checked' : '' ?> class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">أمن</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_media" value="1" <?= $event['req_media'] ? 'checked' : '' ?> class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">توثيق إعلامي</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_projector" value="1" <?= $event['req_projector'] ? 'checked' : '' ?> class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">بروجيكتر</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- خيارات الفعالية الخارجية -->
                    <div id="loc-external" class="<?= $event['location_type'] != 'external' ? 'hidden' : '' ?> space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">عنوان الفعالية</label>
                            <input type="text" name="extAddress" value="<?= htmlspecialchars($event['external_address']) ?>" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">الميزانية (ريال)</label>
                                <input type="number" name="estimated_budget" value="<?= $event['estimated_budget'] ?>" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">عدد الحضور المتوقع</label>
                                <input type="number" name="attendees_external" value="<?= $event['attendees_expected'] ?>" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex flex-col items-center gap-3 p-4 bg-yellow-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-yellow-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_transport" value="1" <?= $event['req_transport'] ? 'checked' : '' ?> class="w-5 h-5 accent-yellow-600">
                                <span class="text-[10px] font-black uppercase">مواصلات</span>
                            </label>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">بروشورات</span>
                                <input type="number" name="mkt_brochures" value="<?= $event['mkt_brochures'] ?>" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">هدايا</span>
                                <input type="number" name="mkt_gifts" value="<?= $event['mkt_gifts'] ?>" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">أدوات</span>
                                <input type="number" name="mkt_tools" value="<?= $event['mkt_tools'] ?>" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">قائمة أهم الضيوف</label>
                            <textarea name="guest_list" rows="2" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none"><?= htmlspecialchars($event['guest_list']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="pt-6 border-t border-teal-50">
                    <label class="block text-sm font-black text-teal-800 mb-2 uppercase">ملاحظات إضافية</label>
                    <textarea name="notes" rows="3" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none focus:ring-2 focus:ring-teal-500"><?= htmlspecialchars($event['notes']) ?></textarea>
                </div>
                
                <div class="flex justify-end gap-4 pt-10">
                    <a href="index.php" class="px-8 py-4 font-bold text-teal-400">إلغاء</a>
                    <button type="submit" class="btn-primary px-16 py-4 rounded-2xl font-black shadow-xl shadow-teal-200">
                        <i class="fas fa-save ml-2"></i>
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
        
        <script>
            function toggleHallNameInput() {
                const select = document.getElementById('hall_selection_type');
                const customDiv = document.getElementById('custom_hall_input_div');
                if (select.value === 'custom') {
                    customDiv.classList.remove('hidden');
                } else {
                    customDiv.classList.add('hidden');
                }
            }

            function setLoc(type) {
                document.getElementById('locationType').value = type;
                const intBox = document.getElementById('loc-internal');
                const extBox = document.getElementById('loc-external');
                const tabInt = document.getElementById('tab-int');
                const tabExt = document.getElementById('tab-ext');

                if(type === 'internal') {
                    intBox.classList.remove('hidden');
                    extBox.classList.add('hidden');
                    tabInt.className = "flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all";
                    tabExt.className = "flex-1 py-4 rounded-xl font-black text-teal-500 hover:text-teal-900 transition-all";
                } else {
                    intBox.classList.add('hidden');
                    extBox.classList.remove('hidden');
                    tabExt.className = "flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all";
                    tabInt.className = "flex-1 py-4 rounded-xl font-black text-teal-500 hover:text-teal-900 transition-all";
                }
                
                // تطبيق قيود الوقت
                applyTimeRestrictions();
            }
        </script>
        
        <script>
            // تطبيق قيود الوقت للفعاليات الداخلية
            function applyTimeRestrictions() {
                const locationType = document.getElementById('locationType').value;
                const startTimeInput = document.getElementById('startTime');
                const endTimeInput = document.getElementById('endTime');
                
                if (locationType === 'internal') {
                    // تطبيق قيود الوقت (8 صباحاً - 4 مساءً)
                    startTimeInput.setAttribute('min', '08:00');
                    startTimeInput.setAttribute('max', '16:00');
                    endTimeInput.setAttribute('min', '08:00');
                    endTimeInput.setAttribute('max', '16:00');
                } else {
                    // إزالة القيود للفعاليات الخارجية
                    startTimeInput.removeAttribute('min');
                    startTimeInput.removeAttribute('max');
                    endTimeInput.removeAttribute('min');
                    endTimeInput.removeAttribute('max');
                }
            }
            
            // التحقق الفوري من صحة الوقت المدخل
            function validateTimeInput(input) {
                const locationType = document.getElementById('locationType').value;
                if (locationType !== 'internal') return true;
                
                const value = input.value;
                if (!value) return true;
                
                // التحقق من أن الوقت بين 08:00 و 16:00
                if (value < '08:00' || value > '16:00') {
                    alert('تنبيه: أوقات الفعاليات الداخلية يجب أن تكون من الساعة 8 صباحاً حتى 4 مساءً فقط');
                    input.value = '';
                    return false;
                }
                
                return true;
            }
            
            // التحقق من اختيار الجمعة للفعاليات الداخلية
            function checkFridayRestriction() {
                const locationType = document.getElementById('locationType').value;
                if (locationType !== 'internal') return;
                
                const startDateInput = document.querySelector('input[name="startDate"]');
                const endDateInput = document.querySelector('input[name="endDate"]');
                
                if (!startDateInput.value || !endDateInput.value) return;
                
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                // التحقق من كل يوم في النطاق
                for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
                    // 5 = الجمعة (0 = الأحد في JavaScript)
                    if (d.getDay() === 5) {
                        alert('تنبيه: لا يمكن حجز فعاليات داخلية يوم الجمعة. يرجى اختيار تواريخ أخرى.');
                        startDateInput.value = '';
                        endDateInput.value = '';
                        return false;
                    }
                }
                
                return true;
            }
            
            // تطبيق القيود عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', function() {
                applyTimeRestrictions();
                
                // إضافة مستمعات للأحداث
                const startDateInput = document.querySelector('input[name="startDate"]');
                const endDateInput = document.querySelector('input[name="endDate"]');
                const startTimeInput = document.getElementById('startTime');
                const endTimeInput = document.getElementById('endTime');
                
                if (startDateInput) {
                    startDateInput.addEventListener('change', checkFridayRestriction);
                }
                
                if (endDateInput) {
                    endDateInput.addEventListener('change', checkFridayRestriction);
                }
                
                // إضافة التحقق الفوري للأوقات
                if (startTimeInput) {
                    startTimeInput.addEventListener('change', function() {
                        validateTimeInput(this);
                    });
                    startTimeInput.addEventListener('blur', function() {
                        validateTimeInput(this);
                    });
                }
                
                if (endTimeInput) {
                    endTimeInput.addEventListener('change', function() {
                        validateTimeInput(this);
                    });
                    endTimeInput.addEventListener('blur', function() {
                        validateTimeInput(this);
                    });
                }
            });
        </script>
    
    <?php elseif ($step == 'success'): ?>
        <!-- صفحة نجاح التعديل -->
        <?php
        if (!isset($_SESSION['edit_success']) || !$_SESSION['edit_success']) {
            header("Location: edit_booking.php");
            exit();
        }
        unset($_SESSION['edit_success']);
        ?>
        <div class="shimal-card bg-white p-12 text-center shadow-2xl">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check-circle text-5xl text-green-500"></i>
            </div>
            <h2 class="text-3xl font-black text-teal-900 mb-3">تم حفظ التعديلات بنجاح!</h2>
            
            <!-- تنبيه مهم بضرورة الاتصال -->
            <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-8 my-8">
                <div class="flex items-center gap-3 mb-4 justify-center">
                    <i class="fas fa-phone-volume text-3xl text-red-600 animate-pulse"></i>
                    <h3 class="text-2xl font-black text-red-900">تنبيه مهم!</h3>
                </div>
                <p class="text-lg font-bold text-red-800 mb-4">
                    يرجى الاتصال فوراً بإدارة العلاقات العامة لإبلاغهم بأنك قمت بتحديث الطلب
                </p>
                <div class="bg-white p-6 rounded-xl">
                    <p class="text-sm font-bold text-red-600 mb-2">رقم الهاتف:</p>
                    <a href="tel:0531987936" class="text-4xl font-black text-red-900 hover:text-red-600 transition">
                        0531987936
                    </a>
                </div>
            </div>
            
            <p class="text-teal-600 font-bold mb-8">
                سيتم مراجعة التعديلات الجديدة من قبل الإدارة. حالة الطلب الآن: قيد المراجعة
            </p>
            
            <div class="flex gap-4 justify-center">
                <a href="tel:0531987936" class="px-8 py-3 bg-red-500 text-white rounded-xl font-black hover:bg-red-600 transition shadow-lg">
                    <i class="fas fa-phone ml-2"></i>
                    اتصل الآن
                </a>
                <a href="index.php" class="px-8 py-3 bg-white border-2 border-teal-100 text-teal-600 rounded-xl font-black hover:bg-teal-50 transition">
                    <i class="fas fa-home ml-2"></i>
                    العودة للرئيسية
                </a>
            </div>
        </div>
    <?php endif; ?>
    
</section>

<?php include 'includes/footer.php'; ?>
