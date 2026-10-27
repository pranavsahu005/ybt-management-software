/**
 * YBT Management Software — Theme Manager
 * Supports Light, Dark, and System preference with local storage persistence
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'ybt_theme';
    const THEME_ATTR = 'data-theme';

    const ThemeManager = {
        init() {
            const saved = localStorage.getItem(STORAGE_KEY) || 'light';
            this.setTheme(saved, false);

            // Listen for system changes if system mode
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                    if (localStorage.getItem(STORAGE_KEY) === 'system') {
                        this.applyTheme(e.matches ? 'dark' : 'light');
                    }
                });
            }

            // Bind toggle buttons if present on page
            document.addEventListener('DOMContentLoaded', () => {
                this.bindButtons();
            });
        },

        getTheme() {
            return localStorage.getItem(STORAGE_KEY) || 'light';
        },

        setTheme(theme, save = true) {
            if (save) {
                localStorage.setItem(STORAGE_KEY, theme);
                document.cookie = STORAGE_KEY + "=" + encodeURIComponent(theme) + "; path=/; max-age=31536000; SameSite=Lax";
            }

            if (theme === 'system') {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                this.applyTheme(prefersDark ? 'dark' : 'light');
            } else {
                this.applyTheme(theme);
            }
            this.updateIcons();
        },

        applyTheme(theme) {
            document.documentElement.setAttribute(THEME_ATTR, theme);
            document.documentElement.style.colorScheme = theme;
        },

        toggle() {
            const current = this.getTheme();
            const next = current === 'dark' ? 'light' : 'dark';
            this.setTheme(next);
        },

        bindButtons() {
            const toggles = document.querySelectorAll('[data-action="toggle-theme"]');
            toggles.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggle();
                });
            });
            this.updateIcons();
        },

        updateIcons() {
            const current = document.documentElement.getAttribute(THEME_ATTR) || 'light';
            const icons = document.querySelectorAll('.theme-toggle-icon');
            icons.forEach(icon => {
                if (current === 'dark') {
                    icon.classList.remove('ti-moon');
                    icon.classList.add('ti-sun');
                } else {
                    icon.classList.remove('ti-sun');
                    icon.classList.add('ti-moon');
                }
            });
        }
    };

    window.ThemeManager = ThemeManager;
    ThemeManager.init();
})();
