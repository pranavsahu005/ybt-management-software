/**
 * YBT Management Software — Core Application JS
 * Handles sidebar, mobile drawer, dropdowns, modals, toasts, tooltips
 */
(function() {
    'use strict';

    const App = {
        init() {
            this.initSidebar();
            this.initDropdowns();
            this.initModals();
            this.initAlerts();
            this.initGlobalSearch();
        },

        /* ---------- Sidebar & Mobile Drawer ---------- */
        initSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('[data-action="toggle-sidebar"]');
            const mobileToggleBtn = document.querySelector('[data-action="toggle-mobile-sidebar"]');
            const overlay = document.querySelector('.mobile-overlay');

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    sidebar.classList.toggle('collapsed');
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('ybt_sidebar_collapsed', isCollapsed ? '1' : '0');
                });

                // Restore state on desktop
                if (window.innerWidth > 1024) {
                    if (localStorage.getItem('ybt_sidebar_collapsed') === '1') {
                        sidebar.classList.add('collapsed');
                    }
                }
            }

            if (mobileToggleBtn && sidebar && overlay) {
                mobileToggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('active');
                });

                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        },

        /* ---------- Dropdowns ---------- */
        initDropdowns() {
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('[data-toggle="dropdown"]');
                const openDropdowns = document.querySelectorAll('.dropdown.open');

                if (trigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = trigger.closest('.dropdown');
                    const isOpen = dropdown.classList.contains('open');

                    // Close all others
                    openDropdowns.forEach(d => {
                        if (d !== dropdown) d.classList.remove('open');
                    });

                    dropdown.classList.toggle('open', !isOpen);
                } else if (!e.target.closest('.dropdown-menu')) {
                    // Click outside closes dropdowns
                    openDropdowns.forEach(d => d.classList.remove('open'));
                }
            });
        },

        /* ---------- Modals ---------- */
        initModals() {
            // Open modal triggers
            document.addEventListener('click', (e) => {
                const openBtn = e.target.closest('[data-modal-target]');
                if (openBtn) {
                    e.preventDefault();
                    const modalId = openBtn.getAttribute('data-modal-target');
                    App.openModal(modalId);
                }

                const closeBtn = e.target.closest('[data-modal-close]');
                if (closeBtn) {
                    e.preventDefault();
                    const modal = closeBtn.closest('.modal-backdrop');
                    if (modal) {
                        App.closeModal(modal.id);
                    }
                }
            });

            // Close on backdrop click
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal-backdrop')) {
                    App.closeModal(e.target.id);
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const activeModal = document.querySelector('.modal-backdrop.active');
                    if (activeModal) {
                        App.closeModal(activeModal.id);
                    }
                }
            });
        },

        openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
                if (firstInput) setTimeout(() => firstInput.focus(), 100);
            }
        },

        closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                if (!document.querySelector('.modal-backdrop.active')) {
                    document.body.style.overflow = '';
                }
            }
        },

        /* ---------- Toast Notifications ---------- */
        toast(message, type = 'info', title = '', duration = 4000) {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }

            const icons = {
                success: 'ti-circle-check',
                error: 'ti-alert-circle',
                warning: 'ti-alert-triangle',
                info: 'ti-info-circle'
            };

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="ti ${icons[type] || 'ti-info-circle'} toast-icon"></i>
                <div class="toast-body">
                    ${title ? `<div class="toast-title">${title}</div>` : ''}
                    <div class="toast-message">${message}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Close">&times;</button>
            `;

            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.classList.add('toast-fade-out');
                setTimeout(() => toast.remove(), 300);
            });

            container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.add('toast-fade-out');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, duration);
            }
        },

        /* ---------- Auto-dismiss Flash Alerts ---------- */
        initAlerts() {
            document.querySelectorAll('.flash-dismissible').forEach(alert => {
                const btn = alert.querySelector('.flash-close');
                if (btn) {
                    btn.addEventListener('click', () => alert.remove());
                }
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.transition = 'opacity 0.3s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, 5000);
            });
        },

        /* ---------- Global Search ---------- */
        initGlobalSearch() {
            const input = document.querySelector('#global-search-input');
            const results = document.querySelector('#global-search-results');
            if (!input || !results) return;

            let debounceTimer;
            input.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                const query = e.target.value.trim();
                if (query.length < 2) {
                    results.style.display = 'none';
                    results.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`${window.APP_URL || ''}/api/search.php?q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (!data.results || data.results.length === 0) {
                                results.innerHTML = '<div class="search-empty">No results found</div>';
                            } else {
                                results.innerHTML = data.results.map(item => `
                                    <a href="${item.url}" class="search-result-item">
                                        <div class="search-item-type">${item.type}</div>
                                        <div class="search-item-title">${item.title}</div>
                                        <div class="search-item-desc">${item.subtitle || ''}</div>
                                    </a>
                                `).join('');
                            }
                            results.style.display = 'block';
                        })
                        .catch(() => {
                            results.style.display = 'none';
                        });
                }, 300);
            });

            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !results.contains(e.target)) {
                    results.style.display = 'none';
                }
            });
        },

        /* ---------- Confirm Action Helper ---------- */
        confirmAction(message, onConfirm) {
            if (confirm(message)) {
                onConfirm();
            }
        }
    };

    window.App = App;
    document.addEventListener('DOMContentLoaded', () => App.init());
})();
