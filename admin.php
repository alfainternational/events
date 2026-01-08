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

<div class="flex justify-between items-center mb-10">
    <h2 class="text-3xl font-black text-teal-900 border-r-4 border-teal-500 pr-4">لوحة تحكم الإدارة</h2>
    <div class="flex gap-4">
         <div class="bg-white px-6 py-2 rounded-2xl shadow-sm border border-teal-50">
            <span class="text-[10px] font-bold text-teal-400 block uppercase">المسؤول الحالي</span>
            <span class="font-black text-teal-900"><?= $_SESSION['username'] ?></span>
         </div>
         
         <!-- قائمة الأدمن -->
         <a href="admin_profile.php" class="bg-teal-500 text-white px-6 py-2 rounded-2xl font-bold hover:bg-teal-600 transition flex items-center gap-2" title="الملف الشخصي">
            <i class="fas fa-user-circle"></i> الملف الشخصي
         </a>
         
         <?php if (isSuperAdmin($pdo, $_SESSION['user_id'])): ?>
             <a href="admin_users.php" class="bg-purple-500 text-white px-6 py-2 rounded-2xl font-bold hover:bg-purple-600 transition flex items-center gap-2" title="إدارة المستخدمين">
                <i class="fas fa-users-cog"></i> المستخدمين
             </a>
             <a href="admin_settings.php" class="bg-blue-500 text-white px-6 py-2 rounded-2xl font-bold hover:bg-blue-600 transition flex items-center gap-2" title="الإعدادات المتقدمة">
                <i class="fas fa-cogs"></i> إعدادات متقدمة
             </a>
         <?php endif; ?>
         
         <a href="admin.php?logout=1" class="bg-red-500 text-white px-6 py-2 rounded-2xl font-bold hover:bg-red-600 transition flex items-center gap-2">
            <i class="fas fa-power-off"></i> خروج
         </a>
    </div>
</div>

<?php display_flash_messages(); ?>

<!-- Tabs -->
<div class="flex gap-4 mb-8 flex-wrap">
    <a href="admin.php?tab=events" class="px-6 py-3 rounded-xl font-bold <?= $current_tab == 'events' ? 'bg-teal-500 text-white' : 'bg-white text-teal-600' ?> transition">
        <i class="fas fa-calendar-alt ml-2"></i>
        الفعاليات
    </a>
    <a href="admin.php?tab=settings" class="px-6 py-3 rounded-xl font-bold <?= $current_tab == 'settings' ? 'bg-teal-500 text-white' : 'bg-white text-teal-600' ?> transition">
        <i class="fas fa-cog ml-2"></i>
        الإعدادات
    </a>
    <a href="statistics.php" class="px-6 py-3 rounded-xl font-bold bg-white text-teal-600 transition">
        <i class="fas fa-chart-line ml-2"></i>
        الإحصائيات
    </a>
    <a href="trash.php" class="px-6 py-3 rounded-xl font-bold bg-white text-teal-600 transition">
        <i class="fas fa-trash ml-2"></i>
        سلة المحذوفات
    </a>
    <a href="audit_logs.php" class="px-6 py-3 rounded-xl font-bold bg-white text-teal-600 transition">
        <i class="fas fa-history ml-2"></i>
        سجل المراجعة
    </a>
</div>

<?php if ($current_tab == 'settings'): ?>
    <!-- لوحة الإعدادات -->
    <div class="shimal-card bg-white p-10 shadow-xl">
        <h3 class="text-2xl font-black text-teal-900 mb-6">
            <i class="fas fa-sliders-h ml-2 text-teal-500"></i>
            إعدادات النظام
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
                        class="w-32 p-4 bg-teal-50 border-2 border-teal-100 rounded-xl font-black text-center text-2xl outline-none focus:ring-2 focus:ring-teal-500">
                    <span class="text-teal-600 font-bold">ساعة</span>
                </div>
                <p class="text-sm text-teal-500 mt-2">
                    لن يتمكن المستخدمون من تعديل طلباتهم إذا كان الوقت المتبقي قبل بدء الفعالية أقل من هذا العدد
                </p>
            </div>
            
            <button type="submit" class="btn-primary px-8 py-3 rounded-xl font-black shadow-lg">
                <i class="fas fa-save ml-2"></i>
                حفظ الإعدادات
            </button>
        </form>
    </div>
<?php else: ?>
    <!-- لوحة الفعاليات -->

<div class="grid grid-cols-1 gap-8">
    <?php if (empty($events)): ?>
        <div class="py-20 text-center opacity-30 shimal-card bg-white">
            <i class="fas fa-inbox text-5xl mb-4"></i>
            <p class="font-bold">لا يوجد طلبات فعاليات حالياً</p>
        </div>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
            <div class="shimal-card bg-white p-8 relative overflow-hidden">
                <!-- شريط الحالة الجانبي -->
                <div class="absolute right-0 top-0 bottom-0 w-2 <?= $ev['status'] == 'approved' ? 'bg-teal-500' : ($ev['status'] == 'pending' ? 'bg-yellow-400' : 'bg-red-500') ?>"></div>
                
                <div class="flex flex-col md:flex-row justify-between gap-8">
                    <!-- القسم الأول: معلومات الفعالية والجهة -->
                    <div class="flex-1 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $ev['location_type'] == 'internal' ? 'bg-teal-50 text-teal-600' : 'bg-yellow-50 text-yellow-600' ?> uppercase">
                                <?= $ev['location_type'] == 'internal' ? 'منظم داخلية' : 'منظم خارجية' ?>
                            </span>
                            <span class="text-xs font-bold text-teal-400"><?= $ev['created_at'] ?></span>
                        </div>
                        <h3 class="text-2xl font-black text-teal-900"><?= htmlspecialchars($ev['title']) ?></h3>
                        <div class="flex items-center gap-3 text-xs text-teal-500 font-bold mb-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= $ev['location_type'] == 'internal' ? ($ev['custom_hall_name'] ? htmlspecialchars($ev['custom_hall_name']) : ($ev['hall_name'] ?: 'داخل الكلية')) : htmlspecialchars($ev['external_address']) ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm font-bold">
                            <div class="text-teal-700"><i class="fas fa-building ml-2 opacity-50"></i> القسم: <?= htmlspecialchars($ev['organizing_dept']) ?></div>
                            <div class="text-teal-700"><i class="fas fa-users-rectangle ml-2 opacity-50"></i> علاقة بـ: <?= htmlspecialchars($ev['related_depts'] ?: 'لا يوجد') ?></div>
                        </div>
                    </div>

                    <!-- القسم الثاني: بيانات التواصل والتوقيت -->
                    <div class="flex-1 bg-teal-50/30 p-6 rounded-3xl border border-teal-50 space-y-4">
                        <h4 class="text-xs font-black text-teal-900 uppercase mb-2">بيانات التواصل والتوقيت</h4>
                        <div class="grid grid-cols-1 gap-2 text-xs font-bold">
                            <div class="flex justify-between"><span>الجوال:</span> <span class="text-teal-900"><?= $ev['requester_mobile'] ?></span></div>
                            <div class="flex justify-between"><span>الإيميل:</span> <span class="text-teal-900"><?= $ev['requester_email'] ?: '-' ?></span></div>
                            <hr class="border-teal-100 my-2">
                            <div class="flex justify-between"><span>بداية:</span> <span class="text-teal-900"><?= $ev['start_date'] ?> (<?= substr($ev['start_time'],0,5) ?>)</span></div>
                            <div class="flex justify-between"><span>نهاية:</span> <span class="text-teal-900"><?= $ev['end_date'] ?> (<?= substr($ev['end_time'],0,5) ?>)</span></div>
                        </div>
                    </div>

                    <!-- القسم الثالث: المتطلبات والتحكم -->
                    <div class="w-full md:w-64 space-y-6">
                        <div class="bg-white p-4 rounded-2xl border border-teal-50">
                            <p class="text-[10px] font-black text-teal-950 mb-3 border-b border-teal-50 pb-2">تفاصيل الخدمة:</p>
                            <div class="grid grid-cols-2 gap-y-2 text-[10px] font-bold text-teal-800">
                                <div><i class="fas fa-users ml-1"></i> <?= $ev['attendees_expected'] ?></div>
                                <?php if($ev['location_type'] == 'internal'): ?>
                                    <div><i class="fas <?= $ev['req_audio'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> صوت</div>
                                    <div><i class="fas <?= $ev['req_catering'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> ضيافة</div>
                                    <div><i class="fas <?= $ev['req_security'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> أمن</div>
                                    <div><i class="fas <?= $ev['req_media'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> إعلام</div>
                                    <div><i class="fas <?= $ev['req_projector'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> بروجيكتر</div>
                                <?php else: ?>
                                    <div><i class="fas <?= $ev['req_transport'] ? 'fa-check-circle text-teal-500' : 'fa-times-circle' ?> ml-1"></i> نقل</div>
                                    <div><i class="fas fa-money-bill ml-1"></i> <?= $ev['estimated_budget'] ?> ر.س</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if(!empty($ev['notes'])): ?>
                            <div class="bg-yellow-50/50 p-4 rounded-2xl border border-yellow-100 mt-4">
                                <p class="text-[10px] font-black text-yellow-900 mb-1 leading-tight"><i class="fas fa-sticky-note ml-1"></i> ملاحظات إضافية:</p>
                                <p class="text-[10px] font-bold text-yellow-800 italic"><?= nl2br(htmlspecialchars($ev['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex gap-2">
                             <?php if ($ev['status'] == 'pending'): ?>
                                 <form method="POST" class="flex-1">
                                     <?php csrf_field(); ?>
                                     <input type="hidden" name="action" value="approve">
                                     <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                     <button type="submit" class="w-full bg-teal-500 text-white py-3 rounded-xl font-bold text-center hover:bg-teal-600 transition shadow-lg shadow-teal-100">قبول</button>
                                 </form>
                             <?php endif; ?>
                             <form method="POST" class="px-4">
                                 <?php csrf_field(); ?>
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                 <button type="submit" class="bg-red-50 text-red-500 py-3 px-4 rounded-xl font-bold text-center hover:bg-red-500 hover:text-white transition">
                                     <i class="fas fa-trash"></i>
                                 </button>
                             </form>
                        </div>
                        
                        <!-- عرض رمز التعديل -->
                        <?php if ($ev['edit_token']): ?>
                            <div class="mt-4 pt-4 border-t border-teal-50">
                                <p class="text-[9px] font-bold text-teal-400 uppercase mb-1">رمز التعديل</p>
                                <code class="text-xs font-black text-teal-900 bg-teal-50 px-2 py-1 rounded"><?= htmlspecialchars($ev['edit_token']) ?></code>
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
