/**
 * YBT Management Software — Tables & Filtering JS
 * Handles table sorting, instant search, select-all, and pagination helpers
 */
(function() {
    'use strict';

    const TableManager = {
        init() {
            this.initSelectAll();
            this.initTableFilter();
        },

        initSelectAll() {
            document.querySelectorAll('[data-select-all]').forEach(masterCheckbox => {
                const targetSelector = masterCheckbox.getAttribute('data-select-all');
                masterCheckbox.addEventListener('change', () => {
                    const checkboxes = document.querySelectorAll(targetSelector);
                    checkboxes.forEach(cb => {
                        cb.checked = masterCheckbox.checked;
                        cb.dispatchEvent(new Event('change'));
                    });
                    this.updateBatchActions();
                });
            });

            document.addEventListener('change', (e) => {
                if (e.target.matches('.row-select-checkbox')) {
                    this.updateBatchActions();
                }
            });
        },

        updateBatchActions() {
            const checkedCount = document.querySelectorAll('.row-select-checkbox:checked').length;
            const batchContainer = document.querySelector('.batch-actions-bar');
            const countDisplay = document.querySelector('.batch-selected-count');

            if (batchContainer) {
                if (checkedCount > 0) {
                    batchContainer.classList.add('active');
                    if (countDisplay) countDisplay.textContent = `${checkedCount} selected`;
                } else {
                    batchContainer.classList.remove('active');
                }
            }
        },

        initTableFilter() {
            const filterInputs = document.querySelectorAll('[data-table-filter]');
            filterInputs.forEach(input => {
                const targetTable = document.querySelector(input.getAttribute('data-table-filter'));
                if (!targetTable) return;

                input.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase().trim();
                    const rows = targetTable.querySelectorAll('tbody tr:not(.no-filter)');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            });
        }
    };

    window.TableManager = TableManager;
    document.addEventListener('DOMContentLoaded', () => TableManager.init());
})();
