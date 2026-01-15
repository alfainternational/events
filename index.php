<?php
require_once 'includes/init.php';
require_once 'includes/csrf.php';
require_once 'includes/messages.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
include 'includes/header.php';
display_flash_messages();

if ($page == 'success'):
    $edit_token = $_SESSION['new_event_token'] ?? null;
    unset($_SESSION['new_event_token']);
    if (!$edit_token) { header("Location: index.php"); exit(); }
?>
    <section class="max-w-3xl mx-auto">
        <div class="shimal-card p-8 md:p-12 text-center shadow-2xl border-t-8 border-teal-500">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check-circle text-5xl text-green-500"></i>
            </div>
            <h2 class="text-2xl md:text-3xl font-black text-teal-900 mb-4">تم إرسال الطلب بنجاح!</h2>
            <p class="text-teal-600 font-bold mb-10">طلبكم قيد المراجعة من قبل إدارة العلاقات العامة</p>
            
            <div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-6 md:p-8 mb-8 text-right">
                <div class="flex items-center gap-3 mb-4">
                    <i class="fas fa-key text-2xl text-amber-600"></i>
                    <h3 class="text-lg font-black text-amber-900">رمز التعديل الخاص بطلبك</h3>
                </div>
                <div class="bg-white p-6 rounded-xl mb-4 text-center border-2 border-dashed border-amber-200">
                    <code class="text-3xl md:text-4xl font-black text-teal-900 tracking-widest select-all"><?= htmlspecialchars($edit_token) ?></code>
                </div>
                <p class="text-sm font-bold text-amber-800 mb-2 italic">* احتفظ بهذا الرمز لتتمكن من تعديل طلبك لاحقاً.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="index.php" class="btn-primary w-full sm:w-auto">العودة للرئيسية</a>
                <a href="edit_booking.php" class="bg-gray-100 text-gray-700 px-8 py-3 rounded-xl font-black">تعديل الطلب</a>
            </div>
        </div>
    </section>

<?php elseif ($page == 'home'):
    $stmt = $pdo->query("SELECT e.*, h.name as hall_name FROM events e LEFT JOIN halls h ON e.hall_id = h.id WHERE e.status = 'approved' AND e.deleted_at IS NULL ORDER BY e.start_date ASC");
    $events = $stmt->fetchAll();
?>
    <div class="mb-10">
        <h2 class="text-3xl md:text-4xl font-black text-teal-950">الفعاليات القادمة</h2>
        <p class="text-teal-600 font-medium">الأنشطة المعتمدة في الكلية</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($events)): ?>
            <div class="col-span-full py-20 text-center opacity-40 italic font-black">لا توجد فعاليات مجدولة حالياً</div>
        <?php else: ?>
            <?php foreach ($events as $ev): ?>
                <div class="shimal-card p-6 flex flex-col hover:shadow-lg transition">
                    <div class="flex justify-between items-start mb-6">
                        <span class="bg-teal-50 text-teal-600 text-xs font-black px-3 py-1 rounded-full border border-teal-100 uppercase">
                            <?= $ev['location_type'] == 'internal' ? 'داخلية' : 'خارجية' ?>
                        </span>
                        <div class="text-right">
                            <span class="text-gray-400 text-[10px] font-bold block">التاريخ</span>
                            <span class="text-sm font-black text-teal-900"><?= $ev['start_date'] ?></span>
                        </div>
                    </div>
                    <h3 class="text-lg font-black text-teal-900 mb-8 leading-relaxed"><?= htmlspecialchars($ev['title']) ?></h3>
                    <div class="mt-auto pt-4 border-t border-teal-50 flex items-center gap-2 text-xs text-teal-500 font-bold">
                        <i class="fas fa-location-dot"></i>
                        <span><?= $ev['location_type'] == 'internal' ? ($ev['custom_hall_name'] ?: ($ev['hall_name'] ?: 'داخل الكلية')) : htmlspecialchars($ev['external_address']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($page == 'booking'): ?>
    <section class="max-w-4xl mx-auto">
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-center bg-teal-900 text-white p-5 rounded-3xl shadow-xl gap-4">
            <div class="flex items-center gap-4">
                <i class="fas fa-headset text-amber-400 text-3xl"></i>
                <div>
                    <p class="text-[10px] font-bold opacity-70 uppercase tracking-widest">للمساعدة تواصل مع العلاقات العامة</p>
                    <p class="text-xl font-black">0531987936</p>
                </div>
            </div>
            <a href="tel:0531987936" class="w-full sm:w-auto bg-amber-400 text-teal-950 px-8 py-3 rounded-2xl font-black text-center shadow-lg transition hover:scale-105">اتصل الآن</a>
        </div>

        <div class="shimal-card p-6 md:p-10 shadow-2xl">
            <h2 class="text-2xl md:text-3xl font-black text-teal-900 mb-10 flex items-center gap-4">
                <i class="fas fa-calendar-plus text-amber-400 text-4xl"></i>
                تخطيط فعالية جديدة
            </h2>

            <form action="process_booking.php" method="POST" class="space-y-10">
                <?php csrf_field(); ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">مسمى النشاط / الفعالية</label>
                        <input type="text" name="title" required class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهة المنظمة (القسم / الإدارة)</label>
                        <input type="text" name="organizing_dept" required placeholder="مثال: قسم التمريض" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-sm font-black text-teal-800 mb-2 uppercase">الجهات مشاركة</label>
                        <input type="text" name="related_depts" placeholder="مثال: شؤون الطلاب" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500 font-bold">
                    </div>
                </div>

                <div class="pt-8 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-clock text-teal-500"></i> تحديد أيام وأوقات الفعالية
                    </h3>
                    
                    <div class="mb-8">
                        <label class="block text-sm font-black text-teal-800 mb-4 uppercase">نوع الحجز</label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="bookingType" value="single_day" checked onchange="updateBookingUI()" class="peer sr-only">
                                <div class="p-4 bg-white border-2 border-teal-50 rounded-2xl peer-checked:bg-teal-500 peer-checked:text-white peer-checked:border-teal-500 transition font-black text-center shadow-sm">
                                    <i class="fas fa-calendar-day block text-2xl mb-2"></i>
                                    يوم واحد
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="bookingType" value="multiple_days" onchange="updateBookingUI()" class="peer sr-only">
                                <div class="p-4 bg-white border-2 border-teal-50 rounded-2xl peer-checked:bg-teal-500 peer-checked:text-white peer-checked:border-teal-500 transition font-black text-center shadow-sm">
                                    <i class="fas fa-calendar-week block text-2xl mb-2"></i>
                                    أيام متعددة
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="singleDaySection" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-black text-teal-800 mb-2">تاريخ الفعالية</label>
                                <input type="date" name="singleDate" class="w-full p-4 bg-teal-50/50 border border-teal-100 rounded-2xl font-bold">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-1 text-center">وقت البدء</label>
                                    <input type="time" name="singleStartTime" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-gray-400 mb-1 text-center">وقت الانتهاء</label>
                                    <input type="time" name="singleEndTime" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="multipleDaysSection" class="hidden space-y-8">
                        <div>
                            <label class="block text-sm font-black text-teal-800 mb-4 uppercase">نوع الأيام</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="daysType" value="consecutive" checked onchange="updateDaysTypeUI()" class="peer sr-only">
                                    <div class="p-3 bg-white border-2 border-teal-50 rounded-2xl peer-checked:bg-teal-100 peer-checked:border-teal-500 text-center font-bold text-sm transition">أيام متتالية</div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="daysType" value="separate" onchange="updateDaysTypeUI()" class="peer sr-only">
                                    <div class="p-3 bg-white border-2 border-teal-50 rounded-2xl peer-checked:bg-teal-100 peer-checked:border-teal-500 text-center font-bold text-sm transition">أيام متباعدة</div>
                                </label>
                            </div>
                        </div>

                        <div id="consecutiveDaysSection" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="date" name="consecutiveStartDate" placeholder="من تاريخ" class="p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                            <input type="date" name="consecutiveEndDate" placeholder="إلى تاريخ" class="p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        </div>

                        <div id="separateDaysSection" class="hidden space-y-3">
                            <div id="separateDatesContainer" class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="date" name="separateDate[]" class="flex-1 p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                                    <button type="button" onclick="addSeparateDate()" class="min-w-[48px] bg-teal-500 text-white rounded-xl"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-teal-50">
                            <label class="block text-sm font-black text-teal-800 mb-4 uppercase">نظام التوقيت</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="timingType" value="unified" checked onchange="updateTimingTypeUI()" class="peer sr-only">
                                    <div class="p-3 bg-white border-2 border-teal-50 rounded-xl peer-checked:bg-teal-100 text-center font-bold text-xs">توقيت موحد لكل الأيام</div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="timingType" value="different" onchange="updateTimingTypeUI()" class="peer sr-only">
                                    <div class="p-3 bg-white border-2 border-teal-50 rounded-xl peer-checked:bg-teal-100 text-center font-bold text-xs">توقيت مختلف لكل يوم</div>
                                </label>
                            </div>
                        </div>

                        <div id="unifiedTimingSection" class="grid grid-cols-2 gap-4">
                            <input type="time" name="unifiedStartTime" class="p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                            <input type="time" name="unifiedEndTime" class="p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        </div>
                        
                        <div id="differentTimingSection" class="hidden space-y-3" id="differentTimesContainer">
                            </div>
                    </div>
                </div>

                <div class="pt-8 border-t border-teal-50">
                    <h3 class="text-lg font-black text-teal-900 mb-6 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-teal-500"></i> الموقع والمتطلبات
                    </h3>
                    
                    <div class="flex gap-4 p-2 bg-teal-50 rounded-2xl mb-10">
                        <button type="button" onclick="setLoc('internal')" id="tab-int" class="flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all">فعالية داخلية</button>
                        <button type="button" onclick="setLoc('external')" id="tab-ext" class="flex-1 py-4 rounded-xl font-black text-teal-500 hover:text-teal-900 transition-all">فعالية خارجية</button>
                        <input type="hidden" name="locationType" id="locationType" value="internal">
                    </div>

                    <div id="loc-internal" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <select name="hall_selection_type" id="hall_selection_type" onchange="toggleHallNameInput()" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                                <option value="1">المسرح (قاعة الدلما رحمها الله)</option>
                                <option value="custom">قاعة أخرى (تحديد الاسم)</option>
                            </select>
                            <input type="text" id="custom_hall_input_div" name="custom_hall_name" placeholder="اسم القاعة بالتحديد" class="hidden w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                            <input type="number" name="attendees_internal" placeholder="العدد المتوقع للحضور" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        </div>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 text-center">
                            <label class="p-4 bg-teal-50/50 rounded-2xl border-2 border-transparent hover:border-teal-200 transition cursor-pointer">
                                <input type="checkbox" name="req_audio" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">أنظمة صوت</span>
                            </label>
                            <label class="p-4 bg-teal-50/50 rounded-2xl border-2 border-transparent hover:border-teal-200 transition cursor-pointer">
                                <input type="checkbox" name="req_catering" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">ضيافة</span>
                            </label>
                            <label class="p-4 bg-teal-50/50 rounded-2xl border-2 border-transparent hover:border-teal-200 transition cursor-pointer">
                                <input type="checkbox" name="req_security" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">أمن وتنظيم</span>
                            </label>
                            <label class="p-4 bg-teal-50/50 rounded-2xl border-2 border-transparent hover:border-teal-200 transition cursor-pointer">
                                <input type="checkbox" name="req_media" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">توثيق إعلامي</span>
                            </label>
                            <label class="p-4 bg-teal-50/50 rounded-2xl border-2 border-transparent hover:border-teal-200 transition cursor-pointer">
                                <input type="checkbox" name="req_projector" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">بروجيكتر</span>
                            </label>
                        </div>
                    </div>

                    <div id="loc-external" class="hidden space-y-6">
                        <input type="text" name="extAddress" placeholder="عنوان الفعالية / الجهة المستضيفة" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <input type="number" name="estimated_budget" placeholder="الميزانية التقديرية (ريال)" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                            <input type="number" name="attendees_external" placeholder="عدد الحضور المتوقع" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <label class="p-4 bg-amber-50 rounded-2xl border border-amber-100 cursor-pointer">
                                <input type="checkbox" name="req_transport" value="1" class="w-5 h-5 mb-2 block mx-auto">
                                <span class="text-[11px] font-black uppercase">مواصلات</span>
                            </label>
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[10px] font-bold block mb-1">بروشورات</span>
                                <input type="number" name="mkt_brochures" value="0" class="w-full p-1 text-center bg-white rounded border border-gray-200">
                            </div>
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[10px] font-bold block mb-1">هدايا</span>
                                <input type="number" name="mkt_gifts" value="0" class="w-full p-1 text-center bg-white rounded border border-gray-200">
                            </div>
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[10px] font-bold block mb-1">أدوات</span>
                                <input type="number" name="mkt_tools" value="0" class="w-full p-1 text-center bg-white rounded border border-gray-200">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-8 border-t border-teal-50">
                    <label class="block text-sm font-black text-teal-800 mb-2 uppercase">بيانات مقدم الطلب</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="tel" name="requester_mobile" required placeholder="رقم الجوال (05xxxxxxxx)" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                        <input type="email" name="requester_email" placeholder="البريد الإلكتروني (اختياري)" class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold">
                    </div>
                </div>

                <div class="pt-4">
                    <label class="block text-sm font-black text-teal-800 mb-2 uppercase">ملاحظات إضافية</label>
                    <textarea name="notes" rows="4" placeholder="أدخل أي طلبات خاصة أو ملاحظات هنا..." class="w-full p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold outline-none"></textarea>
                </div>

                <div class="flex flex-col md:flex-row justify-end gap-4 pt-10">
                    <button type="submit" class="btn-primary w-full md:w-auto px-16 shadow-xl shadow-teal-100">إرسال الطلب للعلاقات العامة</button>
                    <a href="index.php" class="py-4 text-center font-bold text-gray-400 px-8">إلغاء</a>
                </div>
            </form>
        </div>
    </section>

    <script>
        function updateBookingUI() {
            const val = document.querySelector('input[name="bookingType"]:checked').value;
            document.getElementById('singleDaySection').style.display = (val === 'single_day' ? 'block' : 'none');
            document.getElementById('multipleDaysSection').style.display = (val === 'multiple_days' ? 'block' : 'none');
        }
        function updateDaysTypeUI() {
            const val = document.querySelector('input[name="daysType"]:checked').value;
            document.getElementById('consecutiveDaysSection').style.display = (val === 'consecutive' ? 'grid' : 'none');
            document.getElementById('separateDaysSection').style.display = (val === 'separate' ? 'block' : 'none');
        }
        function updateTimingTypeUI() {
            const val = document.querySelector('input[name="timingType"]:checked').value;
            document.getElementById('unifiedTimingSection').style.display = (val === 'unified' ? 'grid' : 'none');
            document.getElementById('differentTimingSection').style.display = (val === 'different' ? 'block' : 'none');
        }
        function addSeparateDate() {
            const container = document.getElementById('separateDatesContainer');
            const div = document.createElement('div');
            div.className = "flex gap-2 mb-2";
            div.innerHTML = `<input type="date" name="separateDate[]" class="flex-1 p-4 bg-teal-50 border border-teal-100 rounded-2xl font-bold"><button type="button" onclick="this.parentElement.remove()" class="min-w-[48px] bg-red-100 text-red-500 rounded-xl"><i class="fas fa-minus"></i></button>`;
            container.appendChild(div);
        }
        function toggleHallNameInput() {
            const select = document.getElementById('hall_selection_type');
            document.getElementById('custom_hall_input_div').style.display = (select.value === 'custom' ? 'block' : 'none');
        }
        function setLoc(type) {
            document.getElementById('locationType').value = type;
            document.getElementById('loc-internal').style.display = (type === 'internal' ? 'block' : 'none');
            document.getElementById('loc-external').style.display = (type === 'external' ? 'block' : 'none');
            document.getElementById('tab-int').className = type === 'internal' ? "flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all" : "flex-1 py-4 rounded-xl font-black text-teal-500 transition-all";
            document.getElementById('tab-ext').className = type === 'external' ? "flex-1 py-4 rounded-xl font-black bg-white shadow-md text-teal-900 transition-all" : "flex-1 py-4 rounded-xl font-black text-teal-500 transition-all";
        }
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>