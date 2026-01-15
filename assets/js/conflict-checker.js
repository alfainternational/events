/**
 * Real-time Conflict Checker
 * نظام التحقق من التداخل في الوقت الفعلي
 */

class ConflictChecker {
    constructor(options = {}) {
        this.options = {
            containerId: options.containerId || 'conflict-results',
            timelineId: options.timelineId || 'timeline-view',
            onConflict: options.onConflict || null,
            onClear: options.onClear || null,
            debounceDelay: options.debounceDelay || 500
        };

        this.debounceTimer = null;
        this.lastCheck = null;
        this.container = document.getElementById(this.options.containerId);
        this.timelineContainer = document.getElementById(this.options.timelineId);
    }

    /**
     * التحقق من التداخل
     */
    async check(data) {
        // Debounce للحد من الطلبات
        clearTimeout(this.debounceTimer);

        return new Promise((resolve) => {
            this.debounceTimer = setTimeout(async () => {
                this.showLoading();

                try {
                    const response = await fetch('api/check_conflict.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const result = await response.json();
                    this.lastCheck = result;

                    if (result.conflict) {
                        this.showConflict(result);
                        if (this.options.onConflict) {
                            this.options.onConflict(result);
                        }
                    } else {
                        this.showSuccess(result);
                        if (this.options.onClear) {
                            this.options.onClear(result);
                        }
                    }

                    // رسم Timeline إذا كان متوفراً
                    if (this.timelineContainer && result.available_slots) {
                        this.renderTimeline(result.available_slots);
                    }

                    resolve(result);

                } catch (error) {
                    console.error('Error checking conflict:', error);
                    this.showError();
                    resolve(null);
                }
            }, this.options.debounceDelay);
        });
    }

    /**
     * عرض حالة التحميل
     */
    showLoading() {
        if (!this.container) return;

        this.container.innerHTML = `
            <div class="flex items-center justify-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <i class="fas fa-spinner fa-spin text-blue-600 ml-2"></i>
                <span class="text-blue-700">جاري التحقق من التداخل...</span>
            </div>
        `;
    }

    /**
     * عرض حالة النجاح (لا يوجد تداخل)
     */
    showSuccess(result) {
        if (!this.container) return;

        this.container.innerHTML = `
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center mb-2">
                    <i class="fas fa-check-circle text-green-600 text-2xl ml-2"></i>
                    <span class="text-green-800 font-bold">${result.message}</span>
                </div>
                <p class="text-green-700 text-sm">جميع الأوقات المحددة متاحة للحجز</p>
            </div>
        `;
    }

    /**
     * عرض التداخل
     */
    showConflict(result) {
        if (!this.container) return;

        let html = `
            <div class="p-4 bg-red-50 border-2 border-red-300 rounded-lg">
                <div class="flex items-center mb-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl ml-2"></i>
                    <span class="text-red-800 font-bold text-lg">${result.message}</span>
                </div>

                <div class="space-y-3">
        `;

        result.conflicts.forEach(conflict => {
            html += `
                <div class="bg-white p-3 rounded border border-red-200">
                    <div class="font-semibold text-red-800 mb-1">
                        <i class="fas fa-calendar-alt ml-1"></i>
                        ${conflict.date_formatted}
                    </div>
                    <div class="text-sm text-gray-700 space-y-1">
                        <div>
                            <span class="font-medium">الوقت المطلوب:</span>
                            <span class="text-red-700">${conflict.requested_time}</span>
                        </div>
                        <div>
                            <span class="font-medium">يتداخل مع:</span>
                            <span class="text-red-700">${conflict.conflict_time}</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-2 bg-gray-50 p-2 rounded">
                            <i class="fas fa-info-circle ml-1"></i>
                            الفعالية المحجوزة: "${conflict.conflicting_event.title}"
                            في ${conflict.conflicting_event.hall_name}
                        </div>
                    </div>
                </div>
            `;
        });

        // الاقتراحات
        if (result.suggestions && result.suggestions.length > 0) {
            html += `
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                    <div class="font-semibold text-yellow-800 mb-2">
                        <i class="fas fa-lightbulb ml-1"></i>
                        حلول مقترحة:
                    </div>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                        ${result.suggestions.map(s => `<li>${s}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;

        this.container.innerHTML = html;
    }

    /**
     * عرض رسالة خطأ
     */
    showError() {
        if (!this.container) return;

        this.container.innerHTML = `
            <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl ml-2"></i>
                    <span class="text-red-800">حدث خطأ أثناء التحقق من التداخل. يرجى المحاولة مرة أخرى.</span>
                </div>
            </div>
        `;
    }

    /**
     * رسم Timeline للأوقات المتاحة والمحجوزة
     */
    renderTimeline(availableSlots) {
        if (!this.timelineContainer) return;

        let html = '<div class="space-y-4">';

        for (const [date, slots] of Object.entries(availableSlots)) {
            html += `
                <div class="timeline-day border rounded-lg p-4 bg-white shadow-sm">
                    <h4 class="font-bold text-gray-700 mb-3">
                        <i class="fas fa-calendar-day text-teal-600 ml-1"></i>
                        ${this.formatDateArabic(date)}
                    </h4>

                    <div class="relative">
                        <!-- Timeline Bar -->
                        <div class="h-12 bg-gray-200 rounded-full relative overflow-hidden">
                            ${this.renderTimeSlots(slots)}
                        </div>

                        <!-- Time Labels -->
                        <div class="flex justify-between text-xs text-gray-600 mt-2">
                            <span>8:00 ص</span>
                            <span>10:00 ص</span>
                            <span>12:00 م</span>
                            <span>2:00 م</span>
                            <span>4:00 م</span>
                        </div>
                    </div>

                    <!-- Available Slots List -->
                    <div class="mt-3 text-sm">
                        <div class="font-medium text-green-700 mb-1">الأوقات المتاحة:</div>
                        <div class="flex flex-wrap gap-2">
                            ${slots.map(slot => `
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs">
                                    ${this.formatTime(slot.start)} - ${this.formatTime(slot.end)}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        html += '</div>';
        this.timelineContainer.innerHTML = html;
    }

    /**
     * رسم الفترات على Timeline
     */
    renderTimeSlots(slots) {
        const dayStart = 8 * 60; // 8:00 AM in minutes
        const dayEnd = 16 * 60; // 4:00 PM in minutes
        const dayDuration = dayEnd - dayStart;

        let html = '';

        slots.forEach(slot => {
            const startMinutes = this.timeToMinutes(slot.start);
            const endMinutes = this.timeToMinutes(slot.end);

            const startPercent = ((startMinutes - dayStart) / dayDuration) * 100;
            const widthPercent = ((endMinutes - startMinutes) / dayDuration) * 100;

            html += `
                <div class="absolute h-full bg-green-400 hover:bg-green-500 transition-colors"
                     style="right: ${startPercent}%; width: ${widthPercent}%"
                     title="${this.formatTime(slot.start)} - ${this.formatTime(slot.end)}">
                </div>
            `;
        });

        return html;
    }

    /**
     * Helper Functions
     */
    timeToMinutes(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours);
        const period = h >= 12 ? 'م' : 'ص';
        const displayHour = h > 12 ? h - 12 : (h === 0 ? 12 : h);
        return `${displayHour}:${minutes} ${period}`;
    }

    formatDateArabic(dateStr) {
        const date = new Date(dateStr);
        const dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        const monthNames = [
            'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
        ];

        return `${dayNames[date.getDay()]} ${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
    }

    /**
     * مسح النتائج
     */
    clear() {
        if (this.container) {
            this.container.innerHTML = '';
        }
        if (this.timelineContainer) {
            this.timelineContainer.innerHTML = '';
        }
        this.lastCheck = null;
    }

    /**
     * الحصول على آخر نتيجة فحص
     */
    getLastCheck() {
        return this.lastCheck;
    }
}

// Export للاستخدام
window.ConflictChecker = ConflictChecker;
