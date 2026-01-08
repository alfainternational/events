/**
 * Auto-save System - النظام التلقائي لحفظ المسودات
 * يحفظ بيانات النماذج في LocalStorage تلقائياً
 */

class AutoSave {
    constructor(formId, storageKey, intervalMs = 30000) {
        this.form = document.getElementById(formId);
        this.storageKey = storageKey;
        this.intervalMs = intervalMs;
        this.autosaveTimer = null;

        if (this.form) {
            this.init();
        }
    }

    init() {
        // استرجاع البيانات المحفوظة عند تحميل الصفحة
        this.restoreDraft();

        // حفظ تلقائي كل intervalMs
        this.startAutosave();

        // حفظ عند تغيير أي حقل
        this.form.addEventListener('input', () => this.saveDraft());

        // حذف المسودة عند الإرسال الناجح
        this.form.addEventListener('submit', () => this.clearDraft());

        // إضافة زر لحذف المسودة
        this.addClearButton();
    }

    saveDraft() {
        const formData = new FormData(this.form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            // تجاهل CSRF token و actionلا حفظها
            if (key === 'csrf_token' || key === 'action') continue;

            data[key] = value;
        }

        localStorage.setItem(this.storageKey, JSON.stringify({
            data: data,
            savedAt: new Date().toISOString()
        }));

        this.showSaveIndicator();
    }

    restoreDraft() {
        const saved = localStorage.getItem(this.storageKey);

        if (!saved) return;

        try {
            const { data, savedAt } = JSON.parse(saved);
            const savedDate = new Date(savedAt);
            const hoursSince = (new Date() - savedDate) / 1000 / 60 / 60;

            // إذا مر أكثر من 24 ساعة، احذف المسودة
            if (hoursSince > 24) {
                this.clearDraft();
                return;
            }

            // استرجاع البيانات
            let restored = 0;
            for (let [key, value] of Object.entries(data)) {
                const field = this.form.elements[key];
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = value === '1' || value === 'on';
                    } else if (field.type === 'radio') {
                        const radio = this.form.querySelector(`input[name="${key}"][value="${value}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        field.value = value;
                    }
                    restored++;
                }
            }

            if (restored > 0) {
                this.showRestoreNotification(savedDate);
            }

        } catch (e) {
            console.error('Failed to restore draft:', e);
            this.clearDraft();
        }
    }

    clearDraft() {
        localStorage.removeItem(this.storageKey);
        this.hideClearButton();
    }

    startAutosave() {
        this.autosaveTimer = setInterval(() => this.saveDraft(), this.intervalMs);
    }

    stopAutosave() {
        if (this.autosaveTimer) {
            clearInterval(this.autosaveTimer);
        }
    }

    showSaveIndicator() {
        // إظهار مؤشر "تم الحفظ"
        let indicator = document.getElementById('autosave-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autosave-indicator';
            indicator.style.cssText = 'position: fixed; top: 20px; left: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 50px; font-size: 14px; z-index: 9999; opacity: 0; transition: opacity 0.3s;';
            indicator.innerHTML = '<i class="fas fa-check-circle"></i> تم الحفظ تلقائياً';
            document.body.appendChild(indicator);
        }

        indicator.style.opacity = '1';
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 2000);
    }

    showRestoreNotification(savedDate) {
        const timeAgo = this.timeAgo(savedDate);
        const notification = document.createElement('div');
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; left: 20px; max-width: 500px; margin: 0 auto; background: #fbbf24; color: #78350f; padding: 15px 20px; border-radius: 12px; font-size: 14px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="font-size: 20px;"></i>
                <div style="flex: 1;">
                    <strong>تم استرجاع مسودة محفوظة</strong>
                    <div style="font-size: 12px; margin-top: 5px;">آخر حفظ: ${timeAgo}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
        `;
        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);
    }

    addClearButton() {
        if (localStorage.getItem(this.storageKey)) {
            let btn = document.getElementById('clear-draft-btn');
            if (!btn) {
                btn = document.createElement('button');
                btn.id = 'clear-draft-btn';
                btn.type = 'button';
                btn.className = 'text-sm text-red-500 hover:text-red-700 font-bold mt-2';
                btn.innerHTML = '<i class="fas fa-trash ml-1"></i> مسح المسودة';
                btn.onclick = () => {
                    if (confirm('هل أنت متأكد من حذف المسودة المحفوظة؟')) {
                        this.clearDraft();
                        this.form.reset();
                        location.reload();
                    }
                };
                this.form.insertAdjacentElement('beforebegin', btn);
            }
        }
    }

    hideClearButton() {
        const btn = document.getElementById('clear-draft-btn');
        if (btn) btn.remove();
    }

    timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        const intervals = {
            'سنة': 31536000,
            'شهر': 2592000,
            'أسبوع': 604800,
            'يوم': 86400,
            'ساعة': 3600,
            'دقيقة': 60
        };

        for (let [name, value] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / value);
            if (interval >= 1) {
                return `قبل ${interval} ${name}`;
            }
        }

        return 'منذ لحظات';
    }
}

// تفعيل Auto-save على نموذج الحجز
if (document.getElementById('booking-form')) {
    new AutoSave('booking-form', 'booking_draft', 30000); // حفظ كل 30 ثانية
}

// تفعيل Auto-save على نموذج التعديل
if (document.getElementById('edit-form')) {
    new AutoSave('edit-form', 'edit_draft', 30000);
}
