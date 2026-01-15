<?php
require_once 'includes/init.php';
require_once 'includes/rbac.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// معالجة العمليات (تأكيد، حذف، تحديث الإعدادات)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // التحقق من CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
        header("Location: admin.php");
        exit();
    }
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'approve' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            // جلب البيانات القديمة للتسجيل
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $oldData = $stmt->fetch();
            
            // حفظ نسخة قبل التغيير
            require_once 'includes/versioning.php';
            save_event_version($id, 'approve', 'تم قبول الطلب');
            
            $pdo->prepare("UPDATE events SET status = 'approved' WHERE id = ?")->execute([$id]);
            
            // Audit log
            $logger = new AuditLogger($pdo);
            $logger->logEvent('approve', $id, 
                ['status' => $oldData['status'] ?? 'pending'], 
                ['status' => 'approved']
            );
            
            // إرسال بريد إلكتروني
            if (!empty($oldData['requester_email'])) {
                require_once 'includes/mailer.php';
                send_email_template('event_approved', 
                    $oldData['requester_email'],
                    $oldData['organizing_dept'],
                    [
                        'title' => $oldData['title'],
                        'organizing_dept' => $oldData['organizing_dept'],
                        'start_date' => $oldData['start_date'],
                        'requester_name' => $oldData['organizing_dept']
                    ]
                );
            }
            
            set_flash('success', 'تم قبول الطلب بنجاح');
        } 
        elseif ($action == 'reject' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $oldData = $stmt->fetch();
            
            $pdo->prepare("UPDATE events SET status = 'rejected' WHERE id = ?")->execute([$id]);
            
            // Audit log
            $logger = new AuditLogger($pdo);
            $logger->logEvent('reject', $id, 
                ['status' => $oldData['status'] ?? 'pending'], 
                ['status' => 'rejected']
            );
            
            // إرسال بريد إلكتروني
            if (!empty($oldData['requester_email'])) {
                require_once 'includes/mailer.php';
                send_email_template('event_rejected',
                    $oldData['requester_email'],
                    $oldData['organizing_dept'],
                    [
                        'title' => $oldData['title'],
                        'organizing_dept' => $oldData['organizing_dept'],
                        'requester_name' => $oldData['organizing_dept']
                    ]
                );
            }
            
            set_flash('success', 'تم رفض الطلب');
        }
        elseif ($action == 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            // حفظ البيانات قبل الحذف (المنطقي)
            $stmt = $pdo->prepare("SELECT title, status FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $eventData = $stmt->fetch();
            
            // Soft Delete - تعيين deleted_at بدلاً من الحذف الفعلي
            $pdo->prepare("UPDATE events SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            
            // Audit log
            $logger = new AuditLogger($pdo);
            $logger->logEvent('soft_delete', $id, $eventData, null);
            
            set_flash('success', 'تم نقل الطلب إلى سلة المحذوفات');
        }
        elseif ($action == 'update_settings') {
            $deadline_hours = (int)$_POST['edit_deadline_hours'];
            if ($deadline_hours > 0) {
                // جلب القيمة القديمة
                $oldValue = get_system_setting('edit_deadline_hours', 1, $pdo);
                
                update_system_setting('edit_deadline_hours', $deadline_hours, $pdo);
                
                // Audit log
                $logger = new AuditLogger($pdo);
                $logger->logSetting('update', 'edit_deadline_hours', $oldValue, $deadline_hours);
                
                set_flash('success', 'تم تحديث الإعدادات بنجاح');
            }
        }
        
        header("Location: admin.php");
        exit();
    }
}

// خروج
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$stmt = $pdo->query("SELECT e.*, h.name as hall_name FROM events e LEFT JOIN halls h ON e.hall_id = h.id WHERE e.deleted_at IS NULL ORDER BY e.created_at DESC");
$events = $stmt->fetchAll();

// جلب الإعدادات
$deadline_hours = get_system_setting('edit_deadline_hours', 1, $pdo);

$current_tab = $_GET['tab'] ?? 'events';

include 'includes/header.php';
?>

<div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6 mb-10">
    <h2 class="text-2xl md:text-3xl font-black text-teal-900 border-r-4 border-teal-500 pr-4">لوحة تحكم الإدارة</h2>
    
    <div class="flex flex-wrap gap-3 items-center">
         <div class="bg-white px-4 py-2 rounded-2xl shadow-sm border border-teal-50 flex-1 md:flex-none">
            <span class="text-[9px] font-bold text-teal-400 block uppercase">المسؤول الحالي</span>
            <span class="font-black text-teal-900 text-sm"><?= $_SESSION['username'] ?></span>
         </div>
         
         <div class="flex gap-2 w-full md:w-auto">
             <a href="admin_profile.php" class="flex-1 md:flex-none bg-teal-500 text-white p-3 rounded-2xl font-bold hover:bg-teal-600 transition flex items-center justify-center gap-2" title="الملف الشخصي">
                <i class="fas fa-user-circle"></i> <span class="hidden sm:inline">الملف الشخصي</span>
             </a>
             
             <?php if (isSuperAdmin($pdo, $_SESSION['user_id'])): ?>
                 <a href="admin_users.php" class="bg-purple-500 text-white p-3 rounded-2xl font-bold hover:bg-purple-600 transition flex items-center justify-center gap-2" title="إدارة المستخدمين">
                    <i class="fas fa-users-cog"></i>
                 </a>
                 <a href="admin_settings.php" class="bg-blue-500 text-white p-3 rounded-2xl font-bold hover:bg-blue-600 transition flex items-center justify-center gap-2" title="الإعدادات المتقدمة">
                    <i class="fas fa-cogs"></i>
                 </a>
             <?php endif; ?>
             
             <a href="admin.php?logout=1" class="bg-red-500 text-white p-3 rounded-2xl font-bold hover:bg-red-600 transition flex items-center justify-center gap-2">
                <i class="fas fa-power-off"></i>
             </a>
         </div>
    </div>
</div>

<?php display_flash_messages(); ?>

<div class="flex gap-3 mb-8 overflow-x-auto pb-2 no-scrollbar">
    <a href="admin.php?tab=events" class="whitespace-nowrap px-6 py-3 rounded-xl font-bold <?= $current_tab == 'events' ? 'bg-teal-500 text-white shadow-lg' : 'bg-white text-teal-600 border border-teal-50' ?> transition">
        <i class="fas fa-calendar-alt ml-2"></i> الفعاليات
    </a>
    <a href="admin.php?tab=settings" class="whitespace-nowrap px-6 py-3 rounded-xl font-bold <?= $current_tab == 'settings' ? 'bg-teal-500 text-white shadow-lg' : 'bg-white text-teal-600 border border-teal-50' ?> transition">
        <i class="fas fa-cog ml-2"></i> الإعدادات
    </a>
    <a href="statistics.php" class="whitespace-nowrap px-6 py-3 rounded-xl font-bold bg-white text-teal-600 border border-teal-50 transition">
        <i class="fas fa-chart-line ml-2"></i> الإحصائيات
    </a>
    <a href="trash.php" class="whitespace-nowrap px-6 py-3 rounded-xl font-bold bg-white text-teal-600 border border-teal-50 transition">
        <i class="fas fa-trash ml-2"></i> السلة
    </a>
    <a href="audit_logs.php" class="whitespace-nowrap px-6 py-3 rounded-xl font-bold bg-white text-teal-600 border border-teal-50 transition">
        <i class="fas fa-history ml-2"></i> المراجعة
    </a>
</div>

<?php if ($current_tab == 'settings'): ?>
    <div class="shimal-card bg-white p-6 md:p-10 shadow-xl">
        <h3 class="text-xl md:text-2xl font-black text-teal-900 mb-6">
            <i class="fas fa-sliders-h ml-2 text-teal-500"></i> إعدادات النظام
        </h3>
        
        <form method="POST" class="max-w-2xl">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_settings">
            
            <div class="mb-6">
                <label class="block text-sm font-black text-teal-800 mb-3 uppercase">
                    الحد الأدنى للساعات قبل الفعالية للسماح بالتعديل
                </label>
                <div class="flex items-center gap-4">
                    <input 
                        type="number" 
                        name="edit_deadline_hours" 
                        value="<?= $deadline_hours ?>" 
                        min="1" 
                        required
                        class="w-24 md:w-32 p-3 md:p-4 bg-teal-50 border-2 border-teal-100 rounded-xl font-black text-center text-xl md:text-2xl outline-none focus:ring-2 focus:ring-teal-500">
                    <span class="text-teal-600 font-bold">ساعة</span>
                </div>
                <p class="text-xs text-teal-500 mt-2 leading-relaxed">
                    لن يتمكن المستخدمون من تعديل طلباتهم إذا كان الوقت المتبقي قبل بدء الفعالية أقل من هذا العدد
                </p>
            </div>
            
            <button type="submit" class="w-full md:w-auto btn-primary px-8 py-4 rounded-xl font-black shadow-lg">
                <i class="fas fa-save ml-2"></i> حفظ الإعدادات
            </button>
        </form>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 gap-6">
        <?php if (empty($events)): ?>
            <div class="py-20 text-center shimal-card bg-white border-dashed border-2 border-teal-100 opacity-50">
                <i class="fas fa-inbox text-5xl mb-4 text-teal-200"></i>
                <p class="font-bold text-teal-800">لا يوجد طلبات فعاليات حالياً</p>
            </div>
        <?php else: ?>
            <?php foreach ($events as $ev): ?>
                <div class="shimal-card bg-white relative overflow-hidden transition hover:shadow-2xl group">
                    <div class="absolute right-0 top-0 bottom-0 w-1.5 <?= $ev['status'] == 'approved' ? 'bg-teal-500' : ($ev['status'] == 'pending' ? 'bg-yellow-400' : 'bg-red-500') ?>"></div>
                    
                    <div class="p-5 md:p-8">
                        <div class="flex flex-col lg:flex-row gap-6 lg:gap-10">
                            
                            <div class="flex-1 space-y-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="px-2.5 py-1 rounded-lg text-[9px] font-black <?= $ev['location_type'] == 'internal' ? 'bg-teal-50 text-teal-600' : 'bg-yellow-50 text-yellow-600' ?> uppercase tracking-wider">
                                        <?= $ev['location_type'] == 'internal' ? 'منظم داخلية' : 'منظم خارجية' ?>
                                    </span>
                                    <span class="text-[10px] font-bold text-teal-400 border-r border-teal-100 pr-3"><?= $ev['created_at'] ?></span>
                                    
                                    <?php if ($ev['status'] == 'approved'): ?>
                                        <span class="bg-teal-500/10 text-teal-600 text-[9px] font-black px-2 py-1 rounded">معتمد</span>
                                    <?php elseif ($ev['status'] == 'rejected'): ?>
                                        <span class="bg-red-500/10 text-red-600 text-[9px] font-black px-2 py-1 rounded">مرفوض</span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="text-xl md:text-2xl font-black text-teal-900 leading-tight">
                                    <?= htmlspecialchars($ev['title']) ?>
                                </h3>
                                
                                <div class="flex items-start gap-2 text-xs text-teal-600 font-bold">
                                    <i class="fas fa-map-marker-alt mt-1 opacity-70"></i>
                                    <span class="leading-relaxed"><?= $ev['location_type'] == 'internal' ? ($ev['custom_hall_name'] ? htmlspecialchars($ev['custom_hall_name']) : ($ev['hall_name'] ?: 'داخل الكلية')) : htmlspecialchars($ev['external_address']) ?></span>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                    <div class="text-[11px] font-bold text-teal-700 bg-teal-50/50 p-2 rounded-lg"><i class="fas fa-building ml-2 opacity-50"></i> القسم: <?= htmlspecialchars($ev['organizing_dept']) ?></div>
                                    <div class="text-[11px] font-bold text-teal-700 bg-teal-50/50 p-2 rounded-lg"><i class="fas fa-users-rectangle ml-2 opacity-50"></i> علاقة بـ: <?= htmlspecialchars($ev['related_depts'] ?: 'لا يوجد') ?></div>
                                </div>
                            </div>

                            <div class="flex-1 lg:max-w-xs bg-teal-50/30 p-5 rounded-2xl border border-teal-50 space-y-3">
                                <h4 class="text-[10px] font-black text-teal-900 uppercase border-b border-teal-100 pb-2 mb-3">التواصل والتوقيت</h4>
                                <div class="space-y-2 text-[11px] font-bold">
                                    <div class="flex justify-between">
                                        <span class="text-teal-400">الجوال:</span> 
                                        <a href="tel:<?= $ev['requester_mobile'] ?>" class="text-teal-900 underline decoration-teal-200"><?= $ev['requester_mobile'] ?></a>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-teal-400">الإيميل:</span> 
                                        <span class="text-teal-900 truncate ml-2 max-w-[120px]"><?= $ev['requester_email'] ?: '-' ?></span>
                                    </div>
                                    <div class="pt-2 mt-2 border-t border-teal-100/50">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-teal-400">بداية:</span> 
                                            <span class="text-teal-900"><?= $ev['start_date'] ?> <span class="bg-white px-1.5 py-0.5 rounded text-[9px]"><?= substr($ev['start_time'],0,5) ?></span></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-teal-400">نهاية:</span> 
                                            <span class="text-teal-900"><?= $ev['end_date'] ?> <span class="bg-white px-1.5 py-0.5 rounded text-[9px]"><?= substr($ev['end_time'],0,5) ?></span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col justify-between gap-6 w-full lg:w-48">
                                <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                                    <div class="p-3 bg-white rounded-xl border border-teal-50 shadow-sm flex items-center justify-between">
                                        <span class="text-[9px] font-black text-teal-400">الحضور</span>
                                        <span class="text-xs font-black text-teal-900"><?= $ev['attendees_expected'] ?></span>
                                    </div>
                                    
                                    <div class="p-2 bg-white rounded-xl border border-teal-50 flex flex-wrap gap-2 justify-center">
                                        <?php if($ev['location_type'] == 'internal'): ?>
                                            <i class="fas fa-volume-up <?= $ev['req_audio'] ? 'text-teal-500' : 'text-gray-200' ?>" title="صوت"></i>
                                            <i class="fas fa-coffee <?= $ev['req_catering'] ? 'text-teal-500' : 'text-gray-200' ?>" title="ضيافة"></i>
                                            <i class="fas fa-shield-alt <?= $ev['req_security'] ? 'text-teal-500' : 'text-gray-200' ?>" title="أمن"></i>
                                            <i class="fas fa-camera <?= $ev['req_media'] ? 'text-teal-500' : 'text-gray-200' ?>" title="إعلام"></i>
                                            <i class="fas fa-video <?= $ev['req_projector'] ? 'text-teal-500' : 'text-gray-200' ?>" title="بروجيكتر"></i>
                                        <?php else: ?>
                                            <i class="fas fa-bus <?= $ev['req_transport'] ? 'text-teal-500' : 'text-gray-200' ?>" title="نقل"></i>
                                            <span class="text-[9px] font-black text-teal-600"><?= $ev['estimated_budget'] ?> ر.س</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                     <?php if ($ev['status'] == 'pending'): ?>
                                         <form method="POST" class="flex-1">
                                             <?php csrf_field(); ?>
                                             <input type="hidden" name="action" value="approve">
                                             <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                             <button type="submit" class="w-full bg-teal-500 text-white py-3.5 rounded-xl font-black text-sm hover:bg-teal-600 transition shadow-lg shadow-teal-100 active:scale-95">قبول</button>
                                         </form>
                                     <?php endif; ?>
                                     
                                     <form method="POST" class="<?= $ev['status'] == 'pending' ? 'px-1' : 'w-full' ?>">
                                         <?php csrf_field(); ?>
                                         <input type="hidden" name="action" value="delete">
                                         <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                         <button type="submit" class="w-full bg-red-50 text-red-500 py-3.5 px-5 rounded-xl font-bold hover:bg-red-500 hover:text-white transition active:scale-95">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </form>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col md:flex-row gap-4 items-start md:items-center justify-between border-t border-teal-50 pt-4">
                            <?php if(!empty($ev['notes'])): ?>
                                <div class="bg-yellow-50/50 p-3 rounded-xl border border-yellow-100 flex-1 w-full">
                                    <p class="text-[10px] font-black text-yellow-900 mb-1"><i class="fas fa-sticky-note ml-1"></i> ملاحظات:</p>
                                    <p class="text-[10px] font-bold text-yellow-800 italic leading-relaxed"><?= nl2br(htmlspecialchars($ev['notes'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($ev['edit_token']): ?>
                                <div class="bg-gray-50 px-4 py-2 rounded-xl border border-gray-100 w-full md:w-auto text-center md:text-right">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase ml-2">رمز التعديل:</span>
                                    <code class="text-xs font-black text-teal-900 tracking-widest"><?= htmlspecialchars($ev['edit_token']) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>