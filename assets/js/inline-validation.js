/**
 * Inline Form Validation System
 * نظام التحقق الفوري من النماذج
 */

class InlineValidator {
    constructor(formSelector, options = {}) {
        this.form = document.querySelector(formSelector);
        if (!this.form) {
            console.error('Form not found:', formSelector);
            return;
        }

        this.options = {
            validateOnBlur: options.validateOnBlur !== false,
            validateOnInput: options.validateOnInput !== false,
            showSuccessIcon: options.showSuccessIcon !== false,
            debounceDelay: options.debounceDelay || 300,
            customValidators: options.customValidators || {}
        };

        this.debounceTimers = {};
        this.validationResults = {};

        this.init();
    }

    init() {
        // إضافة event listeners
        this.form.querySelectorAll('input, textarea, select').forEach(field => {
            if (this.options.validateOnBlur) {
                field.addEventListener('blur', () => this.validateField(field));
            }

            if (this.options.validateOnInput) {
                field.addEventListener('input', () => this.debouncedValidate(field));
            }

            // إزالة رسائل الخطأ عند التركيز
            field.addEventListener('focus', () => this.clearFieldError(field));
        });

        // منع الإرسال إذا كان هناك أخطاء
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showFormError('يرجى تصحيح الأخطاء في النموذج');
                // التمرير للخطأ الأول
                const firstError = this.form.querySelector('.validation-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    /**
     * التحقق مع debounce
     */
    debouncedValidate(field) {
        const fieldName = field.name || field.id;

        clearTimeout(this.debounceTimers[fieldName]);

        this.debounceTimers[fieldName] = setTimeout(() => {
            this.validateField(field);
        }, this.options.debounceDelay);
    }

    /**
     * التحقق من حقل واحد
     */
    validateField(field) {
        const fieldName = field.name || field.id;
        const value = field.value.trim();
        const rules = this.getValidationRules(field);

        // مسح الأخطاء السابقة
        this.clearFieldError(field);

        // التحقق من كل قاعدة
        for (const rule of rules) {
            const result = this.applyRule(value, rule, field);

            if (!result.valid) {
                this.showFieldError(field, result.message);
                this.validationResults[fieldName] = false;
                return false;
            }
        }

        // عرض علامة النجاح
        if (this.options.showSuccessIcon && value) {
            this.showFieldSuccess(field);
        }

        this.validationResults[fieldName] = true;
        return true;
    }

    /**
     * التحقق من النموذج كاملاً
     */
    validateForm() {
        let isValid = true;

        this.form.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.hasAttribute('required') || field.value) {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            }
        });

        return isValid;
    }

    /**
     * الحصول على قواعد التحقق من الحقل
     */
    getValidationRules(field) {
        const rules = [];

        // Required
        if (field.hasAttribute('required')) {
            rules.push({ type: 'required', message: 'هذا الحقل مطلوب' });
        }

        // Email
        if (field.type === 'email') {
            rules.push({ type: 'email', message: 'البريد الإلكتروني غير صحيح' });
        }

        // Saudi Mobile
        if (field.hasAttribute('data-saudi-mobile')) {
            rules.push({ type: 'saudi-mobile', message: 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام' });
        }

        // Min length
        if (field.hasAttribute('minlength')) {
            const min = parseInt(field.getAttribute('minlength'));
            rules.push({ type: 'minlength', value: min, message: `يجب أن يكون على الأقل ${min} أحرف` });
        }

        // Max length
        if (field.hasAttribute('maxlength')) {
            const max = parseInt(field.getAttribute('maxlength'));
            rules.push({ type: 'maxlength', value: max, message: `يجب أن لا يتجاوز ${max} أحرف` });
        }

        // Pattern
        if (field.hasAttribute('pattern')) {
            const pattern = field.getAttribute('pattern');
            rules.push({ type: 'pattern', value: pattern, message: 'التنسيق غير صحيح' });
        }

        // Custom validators
        const customValidator = field.getAttribute('data-validator');
        if (customValidator && this.options.customValidators[customValidator]) {
            rules.push({ type: 'custom', validator: customValidator });
        }

        return rules;
    }

    /**
     * تطبيق قاعدة التحقق
     */
    applyRule(value, rule, field) {
        switch (rule.type) {
            case 'required':
                return {
                    valid: value.length > 0,
                    message: rule.message
                };

            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return {
                    valid: !value || emailRegex.test(value),
                    message: rule.message
                };

            case 'saudi-mobile':
                const mobileRegex = /^05[0-9]{8}$/;
                return {
                    valid: !value || mobileRegex.test(value.replace(/\s/g, '')),
                    message: rule.message
                };

            case 'minlength':
                return {
                    valid: value.length >= rule.value,
                    message: rule.message
                };

            case 'maxlength':
                return {
                    valid: value.length <= rule.value,
                    message: rule.message
                };

            case 'pattern':
                const regex = new RegExp(rule.value);
                return {
                    valid: regex.test(value),
                    message: rule.message
                };

            case 'custom':
                return this.options.customValidators[rule.validator](value, field);

            default:
                return { valid: true };
        }
    }

    /**
     * عرض خطأ الحقل
     */
    showFieldError(field, message) {
        field.classList.add('validation-error', 'border-red-500');
        field.classList.remove('validation-success', 'border-green-500');

        const errorEl = document.createElement('div');
        errorEl.className = 'validation-message text-red-600 text-sm mt-1 flex items-center';
        errorEl.innerHTML = `
            <i class="fas fa-exclamation-circle ml-1"></i>
            <span>${message}</span>
        `;

        // إدراج بعد الحقل
        const parent = field.parentElement;
        const existingError = parent.querySelector('.validation-message');
        if (existingError) {
            existingError.remove();
        }
        field.insertAdjacentElement('afterend', errorEl);
    }

    /**
     * عرض علامة النجاح
     */
    showFieldSuccess(field) {
        field.classList.add('validation-success', 'border-green-500');
        field.classList.remove('validation-error', 'border-red-500');

        const successEl = document.createElement('div');
        successEl.className = 'validation-message text-green-600 text-sm mt-1 flex items-center';
        successEl.innerHTML = `
            <i class="fas fa-check-circle ml-1"></i>
            <span>صحيح</span>
        `;

        const parent = field.parentElement;
        const existingMsg = parent.querySelector('.validation-message');
        if (existingMsg) {
            existingMsg.remove();
        }
        field.insertAdjacentElement('afterend', successEl);
    }

    /**
     * مسح أخطاء الحقل
     */
    clearFieldError(field) {
        field.classList.remove('validation-error', 'validation-success', 'border-red-500', 'border-green-500');

        const parent = field.parentElement;
        const existingMsg = parent.querySelector('.validation-message');
        if (existingMsg) {
            existingMsg.remove();
        }
    }

    /**
     * عرض خطأ عام للنموذج
     */
    showFormError(message) {
        let errorContainer = this.form.querySelector('.form-error-container');

        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'form-error-container';
            this.form.insertBefore(errorContainer, this.form.firstChild);
        }

        errorContainer.innerHTML = `
            <div class="bg-red-50 border-r-4 border-red-500 p-4 mb-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl ml-2"></i>
                    <div>
                        <h4 class="text-red-800 font-bold">خطأ في النموذج</h4>
                        <p class="text-red-700 text-sm">${message}</p>
                    </div>
                </div>
            </div>
        `;

        // إزالة الرسالة بعد 5 ثواني
        setTimeout(() => {
            if (errorContainer) {
                errorContainer.innerHTML = '';
            }
        }, 5000);
    }

    /**
     * API Methods
     */
    isValid() {
        return this.validateForm();
    }

    reset() {
        this.form.querySelectorAll('input, textarea, select').forEach(field => {
            this.clearFieldError(field);
        });
        this.validationResults = {};

        const errorContainer = this.form.querySelector('.form-error-container');
        if (errorContainer) {
            errorContainer.innerHTML = '';
        }
    }
}

// Export
window.InlineValidator = InlineValidator;

// Custom Validators Library
window.CustomValidators = {
    // التحقق من أن التاريخ في المستقبل
    futureDate: (value, field) => {
        if (!value) return { valid: true };

        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return {
            valid: selectedDate >= today,
            message: 'التاريخ يجب أن يكون في المستقبل'
        };
    },

    // التحقق من أن نهاية التاريخ بعد البداية
    endDateAfterStart: (value, field) => {
        if (!value) return { valid: true };

        const startDateField = document.querySelector('[name="start_date"], [name="startDate"]');
        if (!startDateField || !startDateField.value) return { valid: true };

        const startDate = new Date(startDateField.value);
        const endDate = new Date(value);

        return {
            valid: endDate >= startDate,
            message: 'تاريخ الانتهاء يجب أن يكون بعد أو يساوي تاريخ البدء'
        };
    },

    // التحقق من أن وقت الانتهاء بعد البداية
    endTimeAfterStart: (value, field) => {
        if (!value) return { valid: true };

        const startTimeField = document.querySelector('[name="start_time"], [name="startTime"]');
        if (!startTimeField || !startTimeField.value) return { valid: true };

        return {
            valid: value > startTimeField.value,
            message: 'وقت الانتهاء يجب أن يكون بعد وقت البدء'
        };
    },

    // التحقق من أوقات الفعاليات الداخلية
    internalEventTime: (value, field) => {
        if (!value) return { valid: true };

        const [hours, minutes] = value.split(':').map(Number);
        const totalMinutes = hours * 60 + minutes;

        // من 8:00 صباحاً (480 دقيقة) إلى 4:00 مساءً (960 دقيقة)
        const isValid = totalMinutes >= 480 && totalMinutes <= 960;

        return {
            valid: isValid,
            message: 'أوقات الفعاليات الداخلية من 8 صباحاً حتى 4 مساءً'
        };
    },

    // التحقق من رقم موجب
    positiveNumber: (value, field) => {
        if (!value) return { valid: true };

        const number = parseFloat(value);

        return {
            valid: !isNaN(number) && number >= 0,
            message: 'يجب أن يكون رقماً موجباً'
        };
    }
};
