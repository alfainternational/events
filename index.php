<?php
require_once 'includes/init.php';
require_once 'includes/csrf.php';
require_once 'includes/messages.php';

// تحديد الصفحة الحالية (home هو الافتراض)
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

include 'includes/header.php';

// عرض رسائل Flash
display_flash_messages();

if ($page == 'success'):
    // صفحة النجاح بعد إنشاء الطلب
    $edit_token = $_SESSION['new_event_token'] ?? null;
    $event_id = $_SESSION['new_event_id'] ?? null;
    
    unset($_SESSION['new_event_token'], $_SESSION['new_event_id']);
    
    if (!$edit_token):
        header("Location: index.php");
        exit();
    endif;
?>
    <section class="max-w-3xl mx-auto">
        <div class="shimal-card bg-white p-12 text-center shadow-2xl">
            <div class="mb-8">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-5xl text-green-500"></i>
                </div>
                <h2 class="text-3xl font-black text-teal-900 mb-3">تم إرسال الطلب بنجاح!</h2>
                <p class="text-teal-600 font-bold">طلبكم قيد المراجعة من قبل إدارة العلاقات العامة</p>
            </div>
            
            <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-8 mb-8">
                <div class="flex items-center gap-3 mb-4 justify-center">
                    <i class="fas fa-key text-3xl text-yellow-600"></i>
                    <h3 class="text-xl font-black text-yellow-900">رمز التعديل الخاص بطلبك</h3>
                </div>
                <div class="bg-white p-6 rounded-xl mb-4">
                    <code class="text-4xl font-black text-teal-900 tracking-widest select-all"><?= htmlspecialchars($edit_token) ?></code>
                </div>
                <p class="text-sm font-bold text-yellow-800 mb-2">
                    <i class="fas fa-exclamation-triangle ml-1"></i>
                    احفظ هذا الرمز في مكان آمن!
                </p>
                <p class="text-xs text-yellow-700 leading-relaxed">
                    يمكنك استخدام هذا الرمز لتعديل طلبك في أي وقت قبل بدء الفعالية بساعة واحدة على الأقل.
                    هذا الرمز خاص بطلبك فقط ولن يُعرض مرة أخرى.
                </p>
            </div>
            
            <div class="flex gap-4 justify-center">
                <a href="edit_booking.php" class="px-8 py-3 bg-teal-500 text-white rounded-xl font-black hover:bg-teal-600 transition shadow-lg">
                    <i class="fas fa-edit ml-2"></i>
                    تعديل الطلب
                </a>
                <a href="index.php" class="px-8 py-3 bg-white border-2 border-teal-100 text-teal-600 rounded-xl font-black hover:bg-teal-50 transition">
                    <i class="fas fa-home ml-2"></i>
                    العودة للرئيسية
                </a>
            </div>
        </div>
    </section>

<?php elseif ($page == 'home'):
    // جلب الفعاليات من قاعدة البيانات
    $stmt = $pdo->query("SELECT e.*, h.name as hall_name FROM events e LEFT JOIN halls h ON e.hall_id = h.id WHERE e.status = 'approved' ORDER BY e.start_date ASC");
    $events = $stmt->fetchAll();
?>
    <!-- PAGE: HOME (TIMELINE) -->
    <section id="page-home" class="animate-in fade-in duration-500">
        <div class="mb-12">
            <h2 class="text-4xl font-black text-teal-950 mb-2">الفعاليات القادمة</h2>
            <p class="text-teal-600 font-medium italic">قائمة الأنشطة المعتمدة في الكلية</p>
        </div>

        <div id="events-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (empty($events)): ?>
                <div class="col-span-full py-20 text-center opacity-30">
                    <i class="fas fa-calendar-times text-6xl mb-4"></i>
                    <p class="font-black">لا توجد فعاليات مجدولة حالياً</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <div class="shimal-card bg-white p-8 flex flex-col">
                        <div class="flex justify-between items-start mb-6">
                            <span class="bg-teal-50 text-teal-600 text-[10px] font-black px-4 py-1.5 rounded-full border border-teal-100 uppercase">
                                <?= $ev['location_type'] == 'internal' ? 'داخلية' : 'خارجية' ?>
                            </span>
                            <div class="text-right">
                                <span class="text-teal-300 text-[10px] font-bold block">التاريخ</span>
                                <span class="text-xs font-black text-teal-900"><?= $ev['start_date'] ?></span>
                            </div>
                        </div>
                        
                        <h3 class="text-lg font-black text-teal-900 mb-4 leading-relaxed"><?= htmlspecialchars($ev['title']) ?></h3>
                        
                        <div class="flex items-center gap-3 text-xs text-teal-500 font-bold mb-8">
                            <i class="fas fa-map-location-dot"></i>
                            <span><?= $ev['location_type'] == 'internal' ? ($ev['custom_hall_name'] ? htmlspecialchars($ev['custom_hall_name']) : ($ev['hall_name'] ?: 'داخل الكلية')) : htmlspecialchars($ev['external_address']) ?></span>
                        </div>

                        <div class="mt-auto pt-4 border-t border-teal-50 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-teal-500 flex items-center gap-1">
                                <i class="fas fa-check-double"></i> تم التأكيد
                            </span>
                            <i class="fas fa-chevron-left text-[10px] text-teal-200"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

<?php elseif ($page == 'booking'): ?>
    <!-- PAGE: BOOKING FORM -->
    <section id="page-booking" class="max-w-4xl mx-auto">
        
        <!-- روابط التواصل السريع -->
        <div class="mb-6 flex justify-between items-center bg-teal-900 text-white p-4 rounded-3xl shadow-xl">
            <div class="flex items-center gap-3">
                <i class="fas fa-headset text-accent text-xl"></i>
                <div>
                    <p class="text-[10px] font-bold opacity-70 uppercase">تحتاج مساعدة؟ تواصل مع العلاقات العامة</p>
                    <p class="text-lg font-black">0531987936</p>
                </div>
            </div>
            <a href="tel:0531987936" class="bg-accent text-teal-950 px-6 py-2 rounded-xl font-black text-sm hover:scale-105 transition">اتصل الآن</a>
        </div>

        <div class="shimal-card bg-white p-10 shadow-2xl overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-teal-50 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            
            <h2 class="text-3xl font-black text-teal-900 mb-8 flex items-center gap-3">
                <i class="fas fa-calendar-plus text-accent text-4xl"></i>
                تخطيط فعالية جديدة
            </h2>

            <form action="process_booking.php" method="POST" class="space-y-10 relative z-10">
                <?php csrf_field(); ?>
                <!-- بيانات الفعالية الأساسية -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">مسمى النشاط / الفعالية</label>
                        <input type="text" name="title" required class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهة المنظمة (القسم / الإدارة)</label>
                        <input type="text" name="organizing_dept" required placeholder="مثال: قسم التمريض" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهات ذات العلاقة</label>
                        <input type="text" name="related_depts" placeholder="مثال: شؤون الطلاب، المكتبة" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    
                    <!-- نظام الحجز المرن -->
                    <div class="col-span-2 pt-6 border-t border-teal-50">
                        <h3 class="text-lg font-black text-teal-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-teal-500"></i> تحديد أيام وأوقات الفعالية
                        </h3>
                        
                        <!-- Step 1: نوع الحجز -->
                        <div class="mb-6">
                            <label class="block text-sm font-black text-teal-800 mb-3 uppercase">نوع الحجز</label>
                            <div class="flex gap-4">
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="bookingType" value="single_day" checked onchange="updateBookingUI()" class="peer sr-only">
                                    <div class="p-4 bg-teal-50/50 border-2 border-teal-100 rounded-2xl peer-checked:bg-teal-500 peer-checked:text-white peer-checked:border-teal-500 transition font-bold text-center">
                                        <i class="fas fa-calendar-day text-2xl mb-2"></i>
                                        <p>يوم واحد</p>
                                    </div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="bookingType" value="multiple_days" onchange="updateBookingUI()" class="peer sr-only">
                                    <div class="p-4 bg-teal-50/50 border-2 border-teal-100 rounded-2xl peer-checked:bg-teal-500 peer-checked:text-white peer-checked:border-teal-500 transition font-bold text-center">
                                        <i class="fas fa-calendar-week text-2xl mb-2"></i>
                                        <p>أيام متعددة</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- يوم واحد -->
                        <div id="singleDaySection" class="space-y-4">
                            <div>
                                <label class="block text-sm font-black text-teal-800 mb-2 uppercase">تاريخ الفعالية</label>
                                <input type="date" name="singleDate" id="singleDate" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-black text-teal-800 mb-2 uppercase">وقت الفعالية (من - إلى)</label>
                                <div class="flex flex-row-reverse gap-2">
                                    <input type="time" name="singleStartTime" id="singleStartTime" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                    <input type="time" name="singleEndTime" id="singleEndTime" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                </div>
                            </div>
                        </div>
                        
                        <!-- أيام متعددة -->
                        <div id="multipleDaysSection" class="hidden space-y-6">
                            <!-- Step 2: نوع الأيام -->
                            <div>
                                <label class="block text-sm font-black text-teal-800 mb-3 uppercase">نوع الأيام</label>
                                <div class="flex gap-4">
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="daysType" value="consecutive" checked onchange="updateDaysTypeUI()" class="peer sr-only">
                                        <div class="p-3 bg-teal-50/50 border-2 border-teal-100 rounded-xl peer-checked:bg-teal-100 peer-checked:border-teal-400 transition font-bold text-center text-sm">
                                            <i class="fas fa-arrows-alt-h ml-1"></i> أيام متتالية
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="daysType" value="separate" onchange="updateDaysTypeUI()" class="peer sr-only">
                                        <div class="p-3 bg-teal-50/50 border-2 border-teal-100 rounded-xl peer-checked:bg-teal-100 peer-checked:border-teal-400 transition font-bold text-center text-sm">
                                            <i class="fas fa-th ml-1"></i> أيام متباعدة
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- أيام متتالية -->
                            <div id="consecutiveDaysSection">
                                <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الفترة الزمنية</label>
                                <div class="flex flex-row-reverse gap-2">
                                    <input type="date" name="consecutiveStartDate" id="consecutiveStartDate" placeholder="تاريخ البدء" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                    <input type="date" name="consecutiveEndDate" id="consecutiveEndDate" placeholder="تاريخ الانتهاء" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                </div>
                            </div>
                            
                            <!-- أيام متباعدة -->
                            <div id="separateDaysSection" class="hidden">
                                <label class="block text-sm font-black text-teal-800 mb-2 uppercase">اختر الأيام</label>
                                <div class="space-y-2" id="separateDatesContainer">
                                    <div class="flex gap-2">
                                        <input type="date" name="separateDate[]" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none separate-date-input">
                                        <button type="button" onclick="addSeparateDate()" class="px-4 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" onclick="addSeparateDate()" class="mt-2 text-sm text-teal-600 font-bold hover:text-teal-700">
                                    <i class="fas fa-plus-circle ml-1"></i> إضافة يوم آخر
                                </button>
                            </div>
                            
                            <!-- Step 3: نوع التوقيت -->
                            <div>
                                <label class="block text-sm font-black text-teal-800 mb-3 uppercase">التوقيت</label>
                                <div class="flex gap-4">
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="timingType" value="unified" checked onchange="updateTimingTypeUI()" class="peer sr-only">
                                        <div class="p-3 bg-teal-50/50 border-2 border-teal-100 rounded-xl peer-checked:bg-teal-100 peer-checked:border-teal-400 transition font-bold text-center text-sm">
                                            <i class="fas fa-clock ml-1"></i> نفس الوقت لجميع الأيام
                                        </div>
                                    </label>
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="timingType" value="different" onchange="updateTimingTypeUI()" class="peer sr-only">
                                        <div class="p-3 bg-teal-50/50 border-2 border-teal-100 rounded-xl peer-checked:bg-teal-100 peer-checked:border-teal-400 transition font-bold text-center text-sm">
                                            <i class="fas fa-tasks ml-1"></i> وقت مختلف لكل يوم
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- توقيت موحد -->
                            <div id="unifiedTimingSection">
                                <label class="block text-sm font-black text-teal-800 mb-2 uppercase">وقت الفعالية (من - إلى)</label>
                                <div class="flex flex-row-reverse gap-2">
                                    <input type="time" name="unifiedStartTime" id="unifiedStartTime" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                    <input type="time" name="unifiedEndTime" id="unifiedEndTime" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                                </div>
                            </div>
                            
                            <!-- توقيت مختلف -->
                            <div id="differentTimingSection" class="hidden">
                                <label class="block text-sm font-black text-teal-800 mb-3 uppercase">أوقات كل يوم</label>
                                <div id="differentTimesContainer" class="space-y-3">
                                    <!-- سيتم ملؤها ديناميكياً بـ JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <!-- بيانات التواصل -->
                <div class="pt-6 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-address-book text-teal-500"></i> بيانات مقدم الطلب
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-black text-teal-800 mb-2 uppercase">رقم الجوال</label>
                            <input type="tel" name="requester_mobile" required placeholder="05xxxxxxxx" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-black text-teal-800 mb-2 uppercase">البريد الإلكتروني</label>
                            <input type="email" name="requester_email" placeholder="example@north.edu.sa" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                    </div>
                </div>

                <!-- الموقع والخيارات المنفصلة -->
                <div class="pt-6 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-teal-500"></i> الموقع والمتطلبات
                    </h3>
                    
                    <div class="flex gap-4 p-2 bg-teal-50 rounded-2xl mb-8">
                        <button type="button" onclick="setLoc('internal')" id="tab-int" class="flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all">فعالية داخلية</button>
                        <button type="button" onclick="setLoc('external')" id="tab-ext" class="flex-1 py-4 rounded-xl font-black text-teal-500 hover:text-teal-900 transition-all">فعالية خارجية</button>
                        <input type="hidden" name="locationType" id="locationType" value="internal">
                    </div>

                    <!-- خيارات الفعالية الداخلية -->
                    <div id="loc-internal" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">اختر القاعة / المسرح</label>
                                <select name="hall_selection_type" id="hall_selection_type" onchange="toggleHallNameInput()" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                                    <option value="1">المسرح (قاعة الدلما رحمها الله)</option>
                                    <option value="custom">قاعة أخرى (تحديد الاسم)</option>
                                </select>
                            </div>
                            <div id="custom_hall_input_div" class="hidden">
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">اسم القاعة بالتحديد</label>
                                <input type="text" name="custom_hall_name" placeholder="أدخل اسم القاعة هنا..." class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">العدد المتوقع</label>
                                <input type="number" name="attendees_internal" placeholder="0" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_audio" value="1" class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">أنظمة صوت</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_catering" value="1" class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">ضيافة</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_security" value="1" class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">أمن وتنظيم</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_media" value="1" class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">توثيق إعلامي</span>
                            </label>
                            <label class="flex flex-col items-center gap-3 p-4 bg-teal-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-teal-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_projector" value="1" class="w-5 h-5 accent-teal-600">
                                <span class="text-[10px] font-black uppercase">بروجيكتر</span>
                            </label>
                        </div>
                    </div>

                    <!-- خيارات الفعالية الخارجية -->
                    <div id="loc-external" class="hidden space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">عنوان الفعالية / الجهة المستضيفة</label>
                            <input type="text" name="extAddress" placeholder="أدخل العنوان التفصيلي..." class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">الميزانية التقديرية (ريال)</label>
                                <input type="number" name="estimated_budget" placeholder="0" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">عدد الحضور المتوقع</label>
                                <input type="number" name="attendees_external" placeholder="0" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex flex-col items-center gap-3 p-4 bg-yellow-50/50 rounded-2xl cursor-pointer hover:bg-white border border-transparent hover:border-yellow-100 transition shadow-sm text-center">
                                <input type="checkbox" name="req_transport" value="1" class="w-5 h-5 accent-yellow-600">
                                <span class="text-[10px] font-black uppercase">مواصلات</span>
                            </label>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">بروشورات</span>
                                <input type="number" name="mkt_brochures" value="0" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">هدايا</span>
                                <input type="number" name="mkt_gifts" value="0" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                            <div class="bg-yellow-50/30 p-4 rounded-2xl flex flex-col items-center gap-2">
                                <span class="text-[10px] font-black uppercase text-yellow-800">أدوات</span>
                                <input type="number" name="mkt_tools" value="0" min="0" class="w-16 p-1 text-center rounded-lg border border-yellow-100 text-xs font-bold">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-teal-400 mb-2 uppercase">قائمة أهم الضيوف (إن وجد)</label>
                            <textarea name="guest_list" rows="2" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-teal-50">
                    <label class="block text-sm font-black text-teal-800 mb-2 uppercase">ملاحظات إضافية</label>
                    <textarea name="notes" rows="3" placeholder="أدخل أي ملاحظات أو طلبات خاصة هنا..." class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none focus:ring-2 focus:ring-teal-500"></textarea>
                </div>

                <div class="flex justify-end gap-4 pt-10">
                    <a href="index.php" class="px-8 py-4 font-bold text-teal-400">إلغاء</a>
                    <button type="submit" class="btn-primary px-16 py-4 rounded-2xl font-black shadow-xl shadow-teal-200">إرسال الطلب للعلاقات العامة</button>
                </div>
            </form>
        </div>
    </section>

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
        
        // === نظام الحجز المرن ===
        
        // تحديث واجهة المستخدم بناءً على نوع الحجز
        function updateBookingUI() {
            const bookingType = document.querySelector('input[name="bookingType"]:checked').value;
            const singleDay = document.getElementById('singleDaySection');
            const multipleDays = document.getElementById('multipleDaysSection');
            
            if (bookingType === 'single_day') {
                singleDay.classList.remove('hidden');
                multipleDays.classList.add('hidden');
            } else {
                singleDay.classList.add('hidden');
                multipleDays.classList.remove('hidden');
                updateDaysTypeUI();
            }
        }
        
        // تحديث واجهة نوع الأيام
        function updateDaysTypeUI() {
            const daysType = document.querySelector('input[name="daysType"]:checked')?.value || 'consecutive';
            const consecutive = document.getElementById('consecutiveDaysSection');
            const separate = document.getElementById('separateDaysSection');
            
            if (daysType === 'consecutive') {
                consecutive.classList.remove('hidden');
                separate.classList.add('hidden');
            } else {
                consecutive.classList.add('hidden');
                separate.classList.remove('hidden');
            }
            
            // تحديث أوقات كل يوم إذا كان التوقيت مختلف
            if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                generateDifferentTimesInputs();
            }
        }
        
        // تحديث واجهة نوع التوقيت
        function updateTimingTypeUI() {
            const timingType = document.querySelector('input[name="timingType"]:checked')?.value || 'unified';
            const unified = document.getElementById('unifiedTimingSection');
            const different = document.getElementById('differentTimingSection');
            
            if (timingType === 'unified') {
                unified.classList.remove('hidden');
                different.classList.add('hidden');
            } else {
                unified.classList.add('hidden');
                different.classList.remove('hidden');
                generateDifferentTimesInputs();
            }
        }
        
        // إضافة يوم جديد للأيام المتباعدة
        function addSeparateDate() {
            const container = document.getElementById('separateDatesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'flex gap-2';
            newRow.innerHTML = `
                <input type="date" name="separateDate[]" class="flex-1 p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold outline-none separate-date-input">
                <button type="button" onclick="removeSeparateDate(this)" class="px-4 bg-red-500 text-white rounded-xl font-bold hover:bg-red-600">
                    <i class="fas fa-minus"></i>
                </button>
            `;
            container.appendChild(newRow);
            
            // إضافة مستمع للتحديث التلقائي
            const newInput = newRow.querySelector('.separate-date-input');
            newInput.addEventListener('change', function() {
                if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                    generateDifferentTimesInputs();
                }
            });
            
            // تحديث أوقات كل يوم إذا كان التوقيت مختلف
            if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                generateDifferentTimesInputs();
            }
        }
        
        // حذف يوم من الأيام المتباعدة
        function removeSeparateDate(button) {
            button.parentElement.remove();
            if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                generateDifferentTimesInputs();
            }
        }
        
        // توليد حقول الأوقات المختلفة لكل يوم
        function generateDifferentTimesInputs() {
            const container = document.getElementById('differentTimesContainer');
            const daysType = document.querySelector('input[name="daysType"]:checked')?.value || 'consecutive';
            
            container.innerHTML = '';
            
            let dates = [];
            
            if (daysType === 'consecutive') {
                const startDate = document.getElementById('consecutiveStartDate').value;
                const endDate = document.getElementById('consecutiveEndDate').value;
                
                if (startDate && endDate) {
                    dates = getDateRangeDates(startDate, endDate);
                }
            } else {
                // أيام متباعدة - جمع جميع التواريخ المدخلة
                const dateInputs = document.querySelectorAll('input[name="separateDate[]"]');
                const tempDates = [];
                dateInputs.forEach(input => {
                    if (input.value) {
                        tempDates.push(input.value);
                    }
                });
                // إزالة المكررات وترتيب
                dates = [...new Set(tempDates)].sort();
            }
            
            if (dates.length === 0) {
                container.innerHTML = '<p class="text-sm text-teal-400">اختر التواريخ أولاً</p>';
                return;
            }
            
            dates.forEach((date, index) => {
                const dayName = getDayName(date);
                const row = document.createElement('div');
                row.className = 'grid grid-cols-2 md:grid-cols-4 gap-3 p-3 bg-teal-50 rounded-xl';
                row.innerHTML = `
                    <div class="col-span-2 flex items-center">
                        <span class="font-bold text-teal-900">${dayName} - ${formatDateArabic(date)}</span>
                        <input type="hidden" name="dayTime[${index}][date]" value="${date}">
                    </div>
                    <div>
                        <input type="time" name="dayTime[${index}][start]" placeholder="من" class="w-full p-2 bg-white border border-teal-100 rounded-lg font-bold text-sm" required>
                    </div>
                    <div>
                        <input type="time" name="dayTime[${index}][end]" placeholder="إلى" class="w-full p-2 bg-white border border-teal-100 rounded-lg font-bold text-sm" required>
                    </div>
                `;
                container.appendChild(row);
            });
        }
        
        // الحصول على نطاق التواريخ
        function getDateRangeDates(startDate, endDate) {
            const dates = [];
            const current = new Date(startDate);
            const end = new Date(endDate);
            
            while (current <= end) {
                dates.push(current.toISOString().split('T')[0]);
                current.setDate(current.getDate() + 1);
            }
            
            return dates;
        }
        
        // الحصول على اسم اليوم بالعربية
        function getDayName(dateStr) {
            const days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
            const date = new Date(dateStr);
            return days[date.getDay()];
        }
        
        // تنسيق التاريخ بالعربية
        function formatDateArabic(dateStr) {
            const date = new Date(dateStr);
            return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
        }
        
        // إضافة مستمعات للأحداث عند تغيير التواريخ
        document.addEventListener('DOMContentLoaded', function() {
            // عند تغيير تواريخ الأيام المتتالية
            const consecutiveStartDate = document.getElementById('consecutiveStartDate');
            const consecutiveEndDate = document.getElementById('consecutiveEndDate');
            
            if (consecutiveStartDate) {
                consecutiveStartDate.addEventListener('change', function() {
                    if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                        generateDifferentTimesInputs();
                    }
                });
            }
            
            if (consecutiveEndDate) {
                consecutiveEndDate.addEventListener('change', function() {
                    if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                        generateDifferentTimesInputs();
                    }
                });
            }
            
            // إضافة مستمع للتاريخ الأول في الأيام المتباعدة
            const firstSeparateDate = document.querySelector('input[name="separateDate[]"]');
            if (firstSeparateDate) {
                firstSeparateDate.addEventListener('change', function() {
                    if (document.querySelector('input[name="timingType"]:checked')?.value === 'different') {
                        generateDifferentTimesInputs();
                    }
                });
            }
        });
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
        
        // تطبيق الإعدادات عند تحميل الصفحة
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
<?php endif; ?>

<?php
include 'includes/footer.php';
?>
