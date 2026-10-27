/**
 * YBT Management Software — Dynamic Forms & Line Item Calculator
 * Powers invoices, quotations, and expense item calculations
 */
(function() {
    'use strict';

    const InvoiceForm = {
        init() {
            const table = document.querySelector('#line-items-table');
            if (!table) return;

            this.table = table;
            this.tbody = table.querySelector('tbody');
            this.rowTemplate = document.querySelector('#line-item-template');
            
            this.bindEvents();
            this.recalculate();
        },

        bindEvents() {
            // Add item button
            const addBtn = document.querySelector('#add-item-btn');
            if (addBtn) {
                addBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.addRow();
                });
            }

            // Remove item
            this.table.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.remove-row-btn');
                if (removeBtn) {
                    e.preventDefault();
                    const rows = this.tbody.querySelectorAll('.line-item-row');
                    if (rows.length > 1) {
                        removeBtn.closest('tr').remove();
                        this.recalculate();
                    } else {
                        if (window.App) App.toast('An invoice must have at least one line item', 'warning');
                    }
                }
            });

            // Input change listener for live calculation
            this.table.addEventListener('input', (e) => {
                if (e.target.matches('.item-qty, .item-price, .item-discount, .item-tax-rate')) {
                    this.calculateRow(e.target.closest('tr'));
                    this.recalculate();
                }
            });

            // Product selector dropdown auto-fill
            this.table.addEventListener('change', (e) => {
                if (e.target.matches('.item-select-product')) {
                    const row = e.target.closest('tr');
                    const opt = e.target.selectedOptions[0];
                    if (opt && opt.value) {
                        const price = opt.getAttribute('data-price') || 0;
                        const tax = opt.getAttribute('data-tax') || 18;
                        const desc = opt.getAttribute('data-description') || '';

                        const nameInput = row.querySelector('.item-name');
                        const priceInput = row.querySelector('.item-price');
                        const taxInput = row.querySelector('.item-tax-rate');
                        const descInput = row.querySelector('.item-description');

                        if (nameInput) nameInput.value = opt.getAttribute('data-name') || opt.text;
                        if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
                        if (taxInput) taxInput.value = parseFloat(tax).toFixed(2);
                        if (descInput && !descInput.value) descInput.value = desc;

                        this.calculateRow(row);
                        this.recalculate();
                    }
                }
            });

            // Overall discount & round-off
            const overallDiscount = document.querySelector('#invoice-overall-discount');
            if (overallDiscount) {
                overallDiscount.addEventListener('input', () => this.recalculate());
            }
        },

        addRow() {
            let newRow;
            if (this.rowTemplate) {
                const clone = this.rowTemplate.content.cloneNode(true);
                newRow = clone.querySelector('tr');
            } else {
                // Fallback default row
                newRow = document.createElement('tr');
                newRow.className = 'line-item-row';
                const idx = this.tbody.querySelectorAll('.line-item-row').length;
                newRow.innerHTML = `
                    <td>
                        <input type="text" name="items[${idx}][name]" class="form-input item-name" placeholder="Item/Service name" required>
                        <textarea name="items[${idx}][description]" class="form-input form-textarea-sm item-description mt-1" rows="1" placeholder="Description (optional)"></textarea>
                    </td>
                    <td><input type="text" name="items[${idx}][hsn]" class="form-input form-input-sm" placeholder="HSN/SAC"></td>
                    <td style="width: 100px;"><input type="number" name="items[${idx}][quantity]" class="form-input item-qty" value="1" min="0.01" step="any" required></td>
                    <td style="width: 140px;"><input type="number" name="items[${idx}][unit_price]" class="form-input item-price" value="0.00" min="0" step="0.01" required></td>
                    <td style="width: 110px;"><input type="number" name="items[${idx}][tax_rate]" class="form-input item-tax-rate" value="18" min="0" step="0.5"></td>
                    <td style="width: 130px;" class="text-right"><strong class="item-total-display">0.00</strong></td>
                    <td style="width: 50px;" class="text-center">
                        <button type="button" class="btn btn-icon btn-ghost text-danger remove-row-btn" title="Remove row">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                `;
            }

            this.tbody.appendChild(newRow);
            this.recalculate();
        },

        calculateRow(row) {
            const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
            const taxRate = parseFloat(row.querySelector('.item-tax-rate')?.value) || 0;
            const discount = parseFloat(row.querySelector('.item-discount')?.value) || 0;

            const baseAmount = qty * price;
            const afterDiscount = Math.max(0, baseAmount - discount);
            const taxAmount = (afterDiscount * taxRate) / 100;
            const rowTotal = afterDiscount + taxAmount;

            const totalDisplay = row.querySelector('.item-total-display');
            if (totalDisplay) {
                totalDisplay.textContent = rowTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            row.dataset.baseAmount = baseAmount;
            row.dataset.afterDiscount = afterDiscount;
            row.dataset.taxAmount = taxAmount;
            row.dataset.rowTotal = rowTotal;
        },

        recalculate() {
            let subtotal = 0;
            let totalTax = 0;
            let totalDiscount = 0;

            this.tbody.querySelectorAll('.line-item-row').forEach(row => {
                this.calculateRow(row);
                subtotal += parseFloat(row.dataset.afterDiscount) || 0;
                totalTax += parseFloat(row.dataset.taxAmount) || 0;
            });

            const overallDiscountInput = document.querySelector('#invoice-overall-discount');
            const overallDiscount = overallDiscountInput ? (parseFloat(overallDiscountInput.value) || 0) : 0;

            const netSubtotal = Math.max(0, subtotal - overallDiscount);
            const grandTotal = netSubtotal + totalTax;

            // Update DOM displays
            const subtotalEl = document.querySelector('#display-subtotal');
            const taxEl = document.querySelector('#display-tax');
            const grandTotalEl = document.querySelector('#display-grand-total');

            if (subtotalEl) subtotalEl.textContent = '₹' + subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (taxEl) taxEl.textContent = '₹' + totalTax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (grandTotalEl) grandTotalEl.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const grandTotalInput = document.querySelector('#input-grand-total');
            if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
        }
    };

    window.InvoiceForm = InvoiceForm;
    document.addEventListener('DOMContentLoaded', () => InvoiceForm.init());
})();
