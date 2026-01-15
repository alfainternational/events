/**
 * Dark Mode System - نظام الوضع الليلي
 */

class DarkMode {
    constructor() {
        this.darkModeEnabled = localStorage.getItem('darkMode') === 'enabled';
        this.init();
    }

    init() {
        // تطبيق الوضع المحفوظ
        if (this.darkModeEnabled) {
            this.enable();
        }

        // إضافة زر التبديل
        this.addToggleButton();
    }

    enable() {
        document.documentElement.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'enabled');
        this.darkModeEnabled = true;
        this.updateToggleButton();
    }

    disable() {
        document.documentElement.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'disabled');
        this.darkModeEnabled = false;
        this.updateToggleButton();
    }

    toggle() {
        if (this.darkModeEnabled) {
            this.disable();
        } else {
            this.enable();
        }
    }

    addToggleButton() {
        const btn = document.createElement('button');
        btn.id = 'dark-mode-toggle';
        btn.innerHTML = '<i class="fas fa-moon"></i>';
        btn.style.cssText = 'position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px; border-radius: 50%; background: #14b8a6; color: white; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; transition: all 0.3s; display: flex; align-items: center; justify-content: center; font-size: 20px;';
        btn.addEventListener('click', () => this.toggle());
        btn.addEventListener('mouseenter', () => {
            btn.style.transform = 'scale(1.1)';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = 'scale(1)';
        });

        document.body.appendChild(btn);
        this.updateToggleButton();
    }

    updateToggleButton() {
        const btn = document.getElementById('dark-mode-toggle');
        if (btn) {
            btn.innerHTML = this.darkModeEnabled ?
                '<i class="fas fa-sun"></i>' :
                '<i class="fas fa-moon"></i>';
            btn.title = this.darkModeEnabled ? 'تفعيل الوضع النهاري' : 'تفعيل الوضع الليلي';
        }
    }
}

// تفعيل Dark Mode
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new DarkMode());
} else {
    new DarkMode();
}
