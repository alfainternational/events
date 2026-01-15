/**
 * Enhanced Date Picker with Multi-Select & Conflict Detection
 * نظام محسّن لاختيار التواريخ مع دعم الأيام المتباعدة والكشف عن التداخل
 */

class EnhancedDatePicker {
    constructor(options = {}) {
        this.options = {
            containerId: options.containerId || 'datepicker-container',
            selectedDatesField: options.selectedDatesField || 'selected_dates',
            locationType: options.locationType || 'internal',
            hallId: options.hallId || null,
            customHallName: options.customHallName || null,
            onDateSelect: options.onDateSelect || null,
            onDateDeselect: options.onDateDeselect || null,
            minDate: options.minDate || new Date(),
            maxMonthsAhead: options.maxMonthsAhead || 6,
            disableFridaysForInternal: options.disableFridaysForInternal !== false
        };

        this.selectedDates = [];
        this.bookedDates = {}; // {date: [{start_time, end_time, title}]}
        this.currentMonth = new Date();
        this.container = document.getElementById(this.options.containerId);

        if (!this.container) {
            console.error('Container not found:', this.options.containerId);
            return;
        }

        this.init();
    }

    async init() {
        await this.loadBookedDates();
        this.render();
        this.attachEvents();
    }

    /**
     * تحميل الأيام المحجوزة من السيرفر
     */
    async loadBookedDates() {
        try {
            const response = await fetch('api/get_booked_dates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    location_type: this.options.locationType,
                    hall_id: this.options.hallId,
                    custom_hall_name: this.options.customHallName,
                    month: this.currentMonth.toISOString().slice(0, 7)
                })
            });

            if (response.ok) {
                const data = await response.json();
                this.bookedDates = data.booked_dates || {};
            }
        } catch (error) {
            console.error('Error loading booked dates:', error);
        }
    }

    /**
     * رسم التقويم
     */
    render() {
        const month = this.currentMonth.getMonth();
        const year = this.currentMonth.getFullYear();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        // رؤوس الأيام (عربي)
        const dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        const monthNames = [
            'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
        ];

        let html = `
            <div class="datepicker-enhanced" dir="rtl">
                <!-- Header -->
                <div class="datepicker-header bg-gradient-to-r from-cyan-600 to-teal-600 text-white p-4 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <button type="button" class="datepicker-prev-month p-2 hover:bg-white/20 rounded transition">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <h3 class="text-xl font-bold">${monthNames[month]} ${year}</h3>
                        <button type="button" class="datepicker-next-month p-2 hover:bg-white/20 rounded transition">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="datepicker-body bg-white p-4 rounded-b-lg shadow-lg">
                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        ${dayNames.map(day => `
                            <div class="text-center font-semibold text-gray-600 text-sm py-2">
                                ${day}
                            </div>
                        `).join('')}
                    </div>

                    <!-- Days Grid -->
                    <div class="grid grid-cols-7 gap-1">
                        ${this.renderDays(year, month, daysInMonth, startingDayOfWeek)}
                    </div>
                </div>

                <!-- Selected Dates Summary -->
                <div class="datepicker-summary mt-4 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-bold text-gray-700 mb-2">
                        <i class="fas fa-calendar-check text-teal-600"></i>
                        الأيام المختارة: <span class="selected-count badge bg-teal-600 text-white px-2 py-1 rounded">${this.selectedDates.length}</span>
                    </h4>
                    <div class="selected-dates-list space-y-1">
                        ${this.renderSelectedDatesList()}
                    </div>
                </div>

                <!-- Legend -->
                <div class="datepicker-legend mt-3 flex flex-wrap gap-3 text-sm">
                    <div class="flex items-center gap-1">
                        <span class="w-4 h-4 rounded bg-teal-600"></span>
                        <span>محدد</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-4 h-4 rounded bg-red-500"></span>
                        <span>محجوز كلياً</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-4 h-4 rounded bg-yellow-500"></span>
                        <span>محجوز جزئياً</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-4 h-4 rounded bg-gray-300"></span>
                        <span>غير متاح</span>
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
    }

    /**
     * رسم أيام الشهر
     */
    renderDays(year, month, daysInMonth, startingDayOfWeek) {
        let daysHTML = '';

        // خلايا فارغة قبل بداية الشهر
        for (let i = 0; i < startingDayOfWeek; i++) {
            daysHTML += '<div class="datepicker-day-empty"></div>';
        }

        // أيام الشهر
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateStr = this.formatDate(date);
            const dayOfWeek = date.getDay();

            // تحديد الحالة
            const isSelected = this.selectedDates.includes(dateStr);
            const isPast = date < new Date().setHours(0, 0, 0, 0);
            const isFriday = dayOfWeek === 5;
            const isDisabled = isPast ||
                             (this.options.locationType === 'internal' &&
                              this.options.disableFridaysForInternal && isFriday);

            const bookedStatus = this.getBookedStatus(dateStr);

            let classes = 'datepicker-day cursor-pointer p-3 text-center rounded-lg border transition-all hover:shadow-md relative';
            let icon = '';
            let title = day;

            if (isDisabled) {
                classes += ' bg-gray-200 text-gray-400 cursor-not-allowed hover:shadow-none';
            } else if (isSelected) {
                classes += ' bg-teal-600 text-white font-bold ring-2 ring-teal-400';
                icon = '<i class="fas fa-check absolute top-1 left-1 text-xs"></i>';
            } else if (bookedStatus === 'full') {
                classes += ' bg-red-100 text-red-800 border-red-300';
                title = `${day}\n🔴 محجوز`;
            } else if (bookedStatus === 'partial') {
                classes += ' bg-yellow-100 text-yellow-800 border-yellow-300';
                title = `${day}\n🟡 محجوز جزئياً`;
            } else {
                classes += ' bg-white hover:bg-teal-50 border-gray-200';
            }

            daysHTML += `
                <div class="${classes}"
                     data-date="${dateStr}"
                     data-disabled="${isDisabled}"
                     title="${this.getDateTooltip(dateStr, bookedStatus)}">
                    ${icon}
                    <span class="block">${day}</span>
                </div>
            `;
        }

        return daysHTML;
    }

    /**
     * الحصول على حالة الحجز لتاريخ معين
     */
    getBookedStatus(dateStr) {
        if (!this.bookedDates[dateStr]) return 'available';

        const bookings = this.bookedDates[dateStr];

        // التحقق إذا كان محجوز كامل اليوم (8 صباحاً - 4 مساءً للداخلي)
        if (this.options.locationType === 'internal') {
            const totalMinutes = bookings.reduce((sum, booking) => {
                const start = this.timeToMinutes(booking.start_time);
                const end = this.timeToMinutes(booking.end_time);
                return sum + (end - start);
            }, 0);

            // إذا كان المجموع 8 ساعات أو أكثر، اعتبره محجوز كلياً
            if (totalMinutes >= 480) return 'full';
        }

        return 'partial';
    }

    /**
     * رسم قائمة التواريخ المختارة
     */
    renderSelectedDatesList() {
        if (this.selectedDates.length === 0) {
            return '<p class="text-gray-500 text-sm">لم يتم اختيار أي تاريخ بعد</p>';
        }

        return this.selectedDates
            .sort()
            .map(dateStr => {
                const date = new Date(dateStr);
                const formatted = this.formatDateArabic(date);
                return `
                    <div class="flex justify-between items-center bg-white p-2 rounded border">
                        <span><i class="fas fa-calendar-day text-teal-600 ml-2"></i>${formatted}</span>
                        <button type="button"
                                class="remove-date text-red-600 hover:text-red-800"
                                data-date="${dateStr}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            })
            .join('');
    }

    /**
     * ربط الأحداث
     */
    attachEvents() {
        // Navigation buttons
        this.container.querySelector('.datepicker-prev-month')?.addEventListener('click', () => {
            this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
            this.init();
        });

        this.container.querySelector('.datepicker-next-month')?.addEventListener('click', () => {
            this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
            this.init();
        });

        // Day selection
        this.container.querySelectorAll('.datepicker-day').forEach(dayEl => {
            dayEl.addEventListener('click', (e) => {
                const isDisabled = dayEl.getAttribute('data-disabled') === 'true';
                if (isDisabled) return;

                const dateStr = dayEl.getAttribute('data-date');
                this.toggleDate(dateStr);
            });
        });

        // Remove date buttons
        this.container.querySelectorAll('.remove-date').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const dateStr = btn.getAttribute('data-date');
                this.toggleDate(dateStr);
            });
        });
    }

    /**
     * تبديل اختيار تاريخ
     */
    toggleDate(dateStr) {
        const index = this.selectedDates.indexOf(dateStr);

        if (index > -1) {
            // إزالة التحديد
            this.selectedDates.splice(index, 1);
            if (this.options.onDateDeselect) {
                this.options.onDateDeselect(dateStr);
            }
        } else {
            // إضافة التحديد
            this.selectedDates.push(dateStr);
            if (this.options.onDateSelect) {
                this.options.onDateSelect(dateStr);
            }
        }

        // تحديث الحقل المخفي
        this.updateHiddenField();

        // إعادة الرسم
        this.render();
        this.attachEvents();
    }

    /**
     * تحديث الحقل المخفي
     */
    updateHiddenField() {
        const field = document.getElementById(this.options.selectedDatesField);
        if (field) {
            field.value = JSON.stringify(this.selectedDates);
        }
    }

    /**
     * Helper functions
     */
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    formatDateArabic(date) {
        const dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        const monthNames = [
            'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
        ];

        return `${dayNames[date.getDay()]} ${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
    }

    timeToMinutes(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    getDateTooltip(dateStr, bookedStatus) {
        if (bookedStatus === 'available') {
            return 'متاح للحجز - انقر للتحديد';
        }

        if (!this.bookedDates[dateStr]) return '';

        const bookings = this.bookedDates[dateStr];
        let tooltip = 'حجوزات موجودة:\n';
        bookings.forEach(booking => {
            tooltip += `• ${booking.start_time} - ${booking.end_time}: ${booking.title}\n`;
        });

        return tooltip;
    }

    /**
     * API Methods
     */
    getSelectedDates() {
        return [...this.selectedDates];
    }

    setSelectedDates(dates) {
        this.selectedDates = [...dates];
        this.updateHiddenField();
        this.render();
        this.attachEvents();
    }

    clearSelection() {
        this.selectedDates = [];
        this.updateHiddenField();
        this.render();
        this.attachEvents();
    }
}

// Export للاستخدام
window.EnhancedDatePicker = EnhancedDatePicker;
