/**
 * UI Components Library
 * مكتبة مكونات واجهة المستخدم (Dialogs, Loading States, Tooltips, etc.)
 */

// ====== Confirmation Dialog ======
class ConfirmDialog {
    static show(options = {}) {
        return new Promise((resolve) => {
            const defaults = {
                title: 'تأكيد العملية',
                message: 'هل أنت متأكد من المتابعة؟',
                confirmText: 'نعم، متأكد',
                cancelText: 'إلغاء',
                type: 'warning', // warning, danger, info, success
                showIcon: true,
                confirmButtonClass: '',
                cancelButtonClass: ''
            };

            const config = { ...defaults, ...options };

            // Icons
            const icons = {
                warning: '<i class="fas fa-exclamation-triangle text-yellow-500 text-5xl"></i>',
                danger: '<i class="fas fa-exclamation-circle text-red-500 text-5xl"></i>',
                info: '<i class="fas fa-info-circle text-blue-500 text-5xl"></i>',
                success: '<i class="fas fa-check-circle text-green-500 text-5xl"></i>'
            };

            // Button colors
            const buttonColors = {
                warning: 'bg-yellow-600 hover:bg-yellow-700',
                danger: 'bg-red-600 hover:bg-red-700',
                info: 'bg-blue-600 hover:bg-blue-700',
                success: 'bg-green-600 hover:bg-green-700'
            };

            // Create dialog HTML
            const dialogHTML = `
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm animate-fade-in"
                     id="confirm-dialog">
                    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full mx-4 animate-scale-in">
                        <!-- Content -->
                        <div class="p-6 text-center">
                            ${config.showIcon ? `<div class="mb-4">${icons[config.type]}</div>` : ''}

                            <h3 class="text-xl font-bold text-gray-800 mb-2">${config.title}</h3>
                            <p class="text-gray-600 mb-6">${config.message}</p>

                            <!-- Buttons -->
                            <div class="flex gap-3 justify-center">
                                <button type="button"
                                        id="confirm-dialog-cancel"
                                        class="px-6 py-2 rounded-lg font-medium transition-colors
                                               bg-gray-200 hover:bg-gray-300 text-gray-800
                                               ${config.cancelButtonClass}">
                                    ${config.cancelText}
                                </button>
                                <button type="button"
                                        id="confirm-dialog-confirm"
                                        class="px-6 py-2 rounded-lg font-medium text-white transition-colors
                                               ${config.confirmButtonClass || buttonColors[config.type]}">
                                    ${config.confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Insert into DOM
            document.body.insertAdjacentHTML('beforeend', dialogHTML);

            const dialog = document.getElementById('confirm-dialog');
            const confirmBtn = document.getElementById('confirm-dialog-confirm');
            const cancelBtn = document.getElementById('confirm-dialog-cancel');

            // Event handlers
            const cleanup = () => {
                dialog.classList.add('animate-fade-out');
                setTimeout(() => dialog.remove(), 200);
            };

            confirmBtn.addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            cancelBtn.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            // Close on backdrop click
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) {
                    cleanup();
                    resolve(false);
                }
            });

            // ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    cleanup();
                    resolve(false);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }
}

// ====== Loading Spinner ======
class LoadingSpinner {
    static show(message = 'جاري التحميل...', containerId = null) {
        const id = 'loading-spinner-' + Date.now();

        const spinnerHTML = `
            <div class="loading-spinner-overlay fixed inset-0 z-50 flex items-center justify-center
                        bg-black bg-opacity-30 backdrop-blur-sm" id="${id}">
                <div class="bg-white rounded-lg shadow-xl p-6 flex flex-col items-center">
                    <div class="relative">
                        <div class="w-16 h-16 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-calendar-check text-teal-600 text-xl"></i>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-700 font-medium">${message}</p>
                </div>
            </div>
        `;

        if (containerId) {
            const container = document.getElementById(containerId);
            if (container) {
                container.insertAdjacentHTML('beforeend', spinnerHTML);
            } else {
                document.body.insertAdjacentHTML('beforeend', spinnerHTML);
            }
        } else {
            document.body.insertAdjacentHTML('beforeend', spinnerHTML);
        }

        return id;
    }

    static hide(spinnerId) {
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.classList.add('animate-fade-out');
            setTimeout(() => spinner.remove(), 200);
        }
    }

    static hideAll() {
        document.querySelectorAll('.loading-spinner-overlay').forEach(spinner => {
            spinner.classList.add('animate-fade-out');
            setTimeout(() => spinner.remove(), 200);
        });
    }
}

// ====== Toast Notifications ======
class Toast {
    static show(message, type = 'info', duration = 4000) {
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-times-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };

        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };

        const id = 'toast-' + Date.now();

        const toastHTML = `
            <div class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 animate-slide-down" id="${id}">
                <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[300px]">
                    <span class="text-xl">${icons[type]}</span>
                    <span class="flex-1">${message}</span>
                    <button onclick="document.getElementById('${id}').remove()"
                            class="hover:bg-white/20 rounded p-1 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', toastHTML);

        // Auto-remove
        if (duration > 0) {
            setTimeout(() => {
                const toast = document.getElementById(id);
                if (toast) {
                    toast.classList.add('animate-slide-up');
                    setTimeout(() => toast.remove(), 200);
                }
            }, duration);
        }

        return id;
    }

    static success(message, duration) {
        return this.show(message, 'success', duration);
    }

    static error(message, duration) {
        return this.show(message, 'error', duration);
    }

    static warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    static info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// ====== Tooltip ======
class Tooltip {
    static init() {
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                const text = element.getAttribute('data-tooltip');
                const position = element.getAttribute('data-tooltip-position') || 'top';

                const tooltipId = 'tooltip-' + Date.now();

                const tooltipHTML = `
                    <div class="fixed z-50 bg-gray-900 text-white text-sm px-3 py-2 rounded shadow-lg max-w-xs"
                         id="${tooltipId}">
                        ${text}
                        <div class="tooltip-arrow"></div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', tooltipHTML);

                const tooltip = document.getElementById(tooltipId);
                const rect = element.getBoundingClientRect();

                // Position
                switch (position) {
                    case 'top':
                        tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
                        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
                        break;
                    case 'bottom':
                        tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
                        tooltip.style.top = rect.bottom + 8 + 'px';
                        break;
                    case 'left':
                        tooltip.style.left = rect.left - tooltip.offsetWidth - 8 + 'px';
                        tooltip.style.top = rect.top + rect.height / 2 - tooltip.offsetHeight / 2 + 'px';
                        break;
                    case 'right':
                        tooltip.style.left = rect.right + 8 + 'px';
                        tooltip.style.top = rect.top + rect.height / 2 - tooltip.offsetHeight / 2 + 'px';
                        break;
                }

                // Remove on mouse leave
                element.addEventListener('mouseleave', () => {
                    tooltip.remove();
                }, { once: true });
            });
        });
    }
}

// ====== Progress Bar ======
class ProgressBar {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            total: options.total || 100,
            current: options.current || 0,
            showPercentage: options.showPercentage !== false,
            showLabel: options.showLabel !== false,
            color: options.color || 'teal'
        };

        this.render();
    }

    render() {
        const percentage = Math.min(100, Math.round((this.options.current / this.options.total) * 100));

        const colors = {
            teal: 'bg-teal-600',
            blue: 'bg-blue-600',
            green: 'bg-green-600',
            red: 'bg-red-600',
            yellow: 'bg-yellow-600'
        };

        this.container.innerHTML = `
            <div class="w-full">
                ${this.options.showLabel ? `
                    <div class="flex justify-between mb-1 text-sm">
                        <span class="text-gray-700">التقدم</span>
                        ${this.options.showPercentage ? `<span class="font-bold text-gray-900">${percentage}%</span>` : ''}
                    </div>
                ` : ''}
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="${colors[this.options.color]} h-full rounded-full transition-all duration-500 ease-out"
                         style="width: ${percentage}%"></div>
                </div>
            </div>
        `;
    }

    update(current) {
        this.options.current = current;
        this.render();
    }

    setTotal(total) {
        this.options.total = total;
        this.render();
    }
}

// ====== Skeleton Screen ======
class SkeletonScreen {
    static show(containerId, type = 'card') {
        const container = document.getElementById(containerId);
        if (!container) return;

        const templates = {
            card: `
                <div class="animate-pulse space-y-4">
                    <div class="h-48 bg-gray-300 rounded"></div>
                    <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-300 rounded"></div>
                    <div class="h-4 bg-gray-300 rounded w-5/6"></div>
                </div>
            `,
            list: `
                <div class="animate-pulse space-y-3">
                    ${Array(5).fill(0).map(() => `
                        <div class="flex space-x-4 p-3">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                                <div class="h-3 bg-gray-300 rounded"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `,
            table: `
                <div class="animate-pulse space-y-3">
                    <div class="h-12 bg-gray-300 rounded"></div>
                    ${Array(5).fill(0).map(() => `
                        <div class="h-8 bg-gray-200 rounded"></div>
                    `).join('')}
                </div>
            `
        };

        container.innerHTML = templates[type] || templates.card;
    }

    static hide(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = '';
        }
    }
}

// Export
window.ConfirmDialog = ConfirmDialog;
window.LoadingSpinner = LoadingSpinner;
window.Toast = Toast;
window.Tooltip = Tooltip;
window.ProgressBar = ProgressBar;
window.SkeletonScreen = SkeletonScreen;

// Initialize tooltips on page load
document.addEventListener('DOMContentLoaded', () => {
    Tooltip.init();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes fade-out {
        from { opacity: 1; }
        to { opacity: 0; }
    }

    @keyframes scale-in {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    @keyframes slide-down {
        from { transform: translate(-50%, -100%); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }

    @keyframes slide-up {
        from { transform: translate(-50%, 0); opacity: 1; }
        to { transform: translate(-50%, -100%); opacity: 0; }
    }

    .animate-fade-in { animation: fade-in 0.2s ease-out; }
    .animate-fade-out { animation: fade-out 0.2s ease-out; }
    .animate-scale-in { animation: scale-in 0.3s ease-out; }
    .animate-slide-down { animation: slide-down 0.3s ease-out; }
    .animate-slide-up { animation: slide-up 0.3s ease-out; }
`;
document.head.appendChild(style);
