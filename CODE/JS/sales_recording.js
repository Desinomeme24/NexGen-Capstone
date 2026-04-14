document.addEventListener('DOMContentLoaded', function () {
    const saleModal = document.getElementById('saleModal');
    const customerModal = document.getElementById('customerModal');
    const openCustomerBtn = document.getElementById('openCustomerBtn');
    const closeCustomerBtn = document.getElementById('closeCustomerBtn');

    const itemsContainer = document.getElementById('itemsContainer');
    const grandTotalEl = document.getElementById('grandTotal');
    const saleForm = document.getElementById('saleForm');
    const customerForm = document.getElementById('customerForm');
    const saveSaleBtn = document.getElementById('saveSaleBtn');
    const saveCustomerBtn = document.getElementById('saveCustomerBtn');
    const customerSelect = document.getElementById('customerSelect');
    const paymentStatus = document.getElementById('paymentStatus');
    const orderStatus = document.getElementById('orderStatus');
    const amountPaidGroup = document.getElementById('amountPaidGroup');
    const dueDateGroup = document.getElementById('dueDateGroup');
    const amountPaidInput = document.getElementById('amountPaidInput');
    const dueDateInput = document.getElementById('dueDateInput');

    const paymentStatusContext = document.getElementById('paymentStatusContext');
    const phoneGroup = document.getElementById('phoneGroup');
    const emailGroup = document.getElementById('emailGroup');
    const addressGroup = document.getElementById('addressGroup');
    const phoneField = document.getElementById('phoneField');
    const emailField = document.getElementById('emailField');
    const addressField = document.getElementById('addressField');

    const products = window.products || [];

    if (saleForm) saleForm.noValidate = true;
    if (customerForm) customerForm.noValidate = true;

    function getToastRoot() {
        let root = document.getElementById('nxToastRoot');
        if (!root) {
            root = document.createElement('div');
            root.id = 'nxToastRoot';
            root.className = 'nx-toast-root';
            document.body.appendChild(root);
        }
        return root;
    }

    function showToast(message, type = 'info', duration = 3200) {
        const root = getToastRoot();
        const toast = document.createElement('div');
        toast.className = `nx-toast ${type}`;

        const titleMap = {
            success: 'Success',
            error: 'Attention',
            warning: 'Notice',
            info: 'Message'
        };

        toast.innerHTML = `
            <div class="nx-toast-accent"></div>
            <div class="nx-toast-content">
                <div class="nx-toast-title">${titleMap[type] || 'Message'}</div>
                <div class="nx-toast-message">${message}</div>
            </div>
            <button type="button" class="nx-toast-close" aria-label="Close notification">&times;</button>
        `;

        root.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        const removeToast = () => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 220);
        };

        const closeBtn = toast.querySelector('.nx-toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', removeToast);
        }

        setTimeout(removeToast, duration);
    }

    function openSaleModal() {
        if (!saleModal) return;
        saleModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSaleModal() {
        if (!saleModal) return;
        saleModal.classList.remove('show');
        if (customerModal) customerModal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function applyCustomerFieldRules() {
        if (!paymentStatus) return;

        const status = paymentStatus.value;

        if (paymentStatusContext) {
            paymentStatusContext.value = status;
        }

        const isPaid = status === 'Paid';

        if (phoneGroup) phoneGroup.style.display = isPaid ? 'none' : 'block';
        if (emailGroup) emailGroup.style.display = isPaid ? 'none' : 'block';
        if (addressGroup) addressGroup.style.display = isPaid ? 'none' : 'block';

        if (phoneField && isPaid) phoneField.value = '';
        if (emailField && isPaid) emailField.value = '';
        if (addressField && isPaid) addressField.value = '';
    }

    function syncOrderStatusWithPayment() {
        if (!paymentStatus || !orderStatus) return;

        const status = paymentStatus.value;
        orderStatus.value = status === 'Paid' ? 'Fulfilled' : 'Pending';
    }

    function openCustomerModal() {
        if (!customerModal) {
            showToast('Customer modal not found.', 'error');
            return;
        }

        applyCustomerFieldRules();
        customerModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeCustomerModal() {
        if (!customerModal) return;
        customerModal.classList.remove('show');

        if (saleModal && saleModal.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
    }

    if (openCustomerBtn) {
        openCustomerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openCustomerModal();
        });
    }

    if (closeCustomerBtn) {
        closeCustomerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeCustomerModal();
        });
    }

    if (saleModal) {
        saleModal.addEventListener('click', function (e) {
            if (e.target === saleModal) {
                closeSaleModal();
            }
        });
    }

    if (customerModal) {
        customerModal.addEventListener('click', function (e) {
            if (e.target === customerModal) {
                closeCustomerModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (customerModal && customerModal.classList.contains('show')) {
                closeCustomerModal();
                return;
            }

            if (saleModal && saleModal.classList.contains('show')) {
                closeSaleModal();
            }
        }
    });

    function formatMoney(value) {
        return Number(value).toFixed(2);
    }

    function getProductOptions() {
        let options = '<option value="">Select Product</option>';

        products.forEach(product => {
            options += `
                <option value="${product.id}" data-price="${product.selling_price}" data-stock="${product.stock_quantity}">
                    ${product.product_name} (Stock: ${product.stock_quantity} | ₱${parseFloat(product.selling_price).toFixed(2)})
                </option>
            `;
        });

        return options;
    }

    function addItemRow() {
        if (!itemsContainer) return;

        const row = document.createElement('div');
        row.className = 'item-row';
        row.innerHTML = `
            <div>
                <select name="product_id[]" class="product-select">
                    ${getProductOptions()}
                </select>
            </div>
            <div>
                <input type="number" name="quantity[]" class="qty-input" min="1" value="1">
            </div>
            <div>
                <input type="number" step="0.01" name="unit_price[]" class="price-input" min="0" value="0.00">
            </div>
            <div>
                <input type="text" class="subtotal-input readonly-box" value="0.00" readonly>
            </div>
            <div>
                <button type="button" class="row-remove">×</button>
            </div>
        `;

        itemsContainer.appendChild(row);
        calculateGrandTotal();
    }

    function updatePrice(selectEl) {
        const row = selectEl.closest('.item-row');
        if (!row) return;

        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const price = selectedOption.getAttribute('data-price') || 0;

        row.querySelector('.price-input').value = parseFloat(price).toFixed(2);
        calculateRow(row);
    }

    function calculateRow(rowOrElement) {
        const row = rowOrElement.closest ? rowOrElement.closest('.item-row') : rowOrElement;
        if (!row) return;

        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const subtotal = qty * price;

        row.querySelector('.subtotal-input').value = formatMoney(subtotal);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let total = 0;

        document.querySelectorAll('.subtotal-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        if (grandTotalEl) {
            grandTotalEl.textContent = formatMoney(total);
        }

        togglePaymentFields();
    }

    function removeRow(button) {
        const rows = document.querySelectorAll('.item-row');

        if (rows.length <= 1) {
            showToast('At least one item row is required.', 'warning');
            return;
        }

        const row = button.closest('.item-row');
        if (row) {
            row.remove();
            calculateGrandTotal();
        }
    }

    function resetSaleForm() {
        if (!saleForm) return;

        saleForm.reset();

        if (itemsContainer) {
            itemsContainer.innerHTML = '';
            addItemRow();
        }

        if (grandTotalEl) {
            grandTotalEl.textContent = '0.00';
        }

        applyCustomerFieldRules();
        syncOrderStatusWithPayment();
        togglePaymentFields();
    }

    function getGrandTotalValue() {
        return parseFloat((grandTotalEl?.textContent || '0').replace(/,/g, '')) || 0;
    }

    function togglePaymentFields() {
        if (!paymentStatus) return;

        const status = paymentStatus.value;
        const total = getGrandTotalValue();

        if (status === 'Paid') {
            if (amountPaidGroup) amountPaidGroup.style.display = 'block';
            if (dueDateGroup) dueDateGroup.style.display = 'none';
            if (amountPaidInput) amountPaidInput.value = total > 0 ? formatMoney(total) : '0.00';
            if (dueDateInput) dueDateInput.value = '';
        } else if (status === 'Unpaid') {
            if (amountPaidGroup) amountPaidGroup.style.display = 'block';
            if (dueDateGroup) dueDateGroup.style.display = 'block';
            if (amountPaidInput) amountPaidInput.value = '0.00';
        } else if (status === 'Partially Paid') {
            if (amountPaidGroup) amountPaidGroup.style.display = 'block';
            if (dueDateGroup) dueDateGroup.style.display = 'block';

            const current = parseFloat(amountPaidInput?.value || '0') || 0;
            if (current >= total && total > 0 && amountPaidInput) {
                amountPaidInput.value = '0.00';
            }
        }

        applyCustomerFieldRules();
        syncOrderStatusWithPayment();
    }

    function validateSaleForm() {
        if (!saleForm) return false;

        const payment = paymentStatus ? paymentStatus.value : 'Paid';
        const total = getGrandTotalValue();
        const amountPaid = parseFloat(amountPaidInput?.value || '0') || 0;

        if (total <= 0) {
            showToast('Please add at least one product with a valid amount.', 'warning');
            return false;
        }

        const rows = Array.from(document.querySelectorAll('.item-row'));
        if (!rows.length) {
            showToast('Please add at least one product item.', 'warning');
            return false;
        }

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const productSelect = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.qty-input');
            const priceInput = row.querySelector('.price-input');

            if (!productSelect || !productSelect.value) {
                showToast(`Please select a product for item ${i + 1}.`, 'warning');
                return false;
            }

            const qty = parseFloat(qtyInput?.value || '0') || 0;
            const price = parseFloat(priceInput?.value || '0') || 0;
            const stock = parseFloat(productSelect.options[productSelect.selectedIndex]?.getAttribute('data-stock') || '0') || 0;
            const productName = productSelect.options[productSelect.selectedIndex]?.textContent || `item ${i + 1}`;

            if (qty <= 0) {
                showToast(`Quantity must be greater than zero for item ${i + 1}.`, 'warning');
                return false;
            }

            if (price < 0) {
                showToast(`Unit price cannot be negative for item ${i + 1}.`, 'warning');
                return false;
            }

            if (qty > stock) {
                showToast(`Insufficient stock for ${productName}.`, 'error');
                return false;
            }
        }

        if ((payment === 'Unpaid' || payment === 'Partially Paid') && (!customerSelect || !customerSelect.value)) {
            showToast('Please select or add a customer for unpaid or partially paid sales.', 'warning');
            return false;
        }

        if ((payment === 'Unpaid' || payment === 'Partially Paid') && !dueDateInput?.value) {
            showToast('Please select a due date for unpaid or partially paid sales.', 'warning');
            return false;
        }

        if (payment === 'Paid') {
            if (Math.abs(amountPaid - total) > 0.009) {
                showToast('For paid sales, the amount paid must match the grand total.', 'warning');
                return false;
            }
        }

        if (payment === 'Unpaid') {
            if (amountPaid > 0) {
                showToast('For unpaid sales, amount paid must stay at 0.00.', 'warning');
                return false;
            }
        }

        if (payment === 'Partially Paid') {
            if (amountPaid <= 0 || amountPaid >= total) {
                showToast('For partially paid sales, amount paid must be more than 0 and less than the grand total.', 'warning');
                return false;
            }
        }

        syncOrderStatusWithPayment();
        return true;
    }

    function validateCustomerForm() {
        if (!customerForm) return false;

        const status = paymentStatus ? paymentStatus.value : 'Paid';
        const customerNameField = document.getElementById('customerNameField');

        if (!customerNameField || !customerNameField.value.trim()) {
            showToast('Please enter the customer name.', 'warning');
            return false;
        }

        if (status === 'Unpaid' || status === 'Partially Paid') {
            if (!phoneField?.value.trim()) {
                showToast('Phone is required for unpaid or partially paid customers.', 'warning');
                return false;
            }

            if (!emailField?.value.trim()) {
                showToast('Email is required for unpaid or partially paid customers.', 'warning');
                return false;
            }

            if (!addressField?.value.trim()) {
                showToast('Address is required for unpaid or partially paid customers.', 'warning');
                return false;
            }
        }

        return true;
    }

    async function submitSaleAjax(e) {
        e.preventDefault();

        if (!saleForm) return;
        if (!validateSaleForm()) return;

        syncOrderStatusWithPayment();

        const formData = new FormData(saleForm);

        if (saveSaleBtn) {
            saveSaleBtn.disabled = true;
            saveSaleBtn.textContent = 'Saving...';
        }

        try {
            const response = await fetch('/NexGen/CODE/PHP/process_sale_ajax.php', {
                method: 'POST',
                body: formData
            });

            const rawText = await response.text();
            let data;

            try {
                data = JSON.parse(rawText);
            } catch (jsonError) {
                console.error('Invalid JSON response:', rawText);
                throw new Error('Server returned an invalid response.');
            }

            if (data.success) {
                showToast(data.message || 'Sale saved successfully.', 'success');
                closeSaleModal();
                resetSaleForm();

                setTimeout(() => {
                    window.location.href = '/NexGen/CODE/PHP/sale_view.php?id=' + data.sale_id;
                }, 900);
            } else {
                showToast(data.message || 'Failed to save sale.', 'error');
            }
        } catch (error) {
            console.error('AJAX error:', error);
            showToast(error.message || 'An error occurred while saving the sale.', 'error');
        } finally {
            if (saveSaleBtn) {
                saveSaleBtn.disabled = false;
                saveSaleBtn.textContent = 'Save Sale';
            }
        }
    }

    async function submitCustomerAjax(e) {
        e.preventDefault();

        if (!customerForm) {
            showToast('Customer form not found.', 'error');
            return;
        }

        if (!validateCustomerForm()) return;

        const formData = new FormData(customerForm);

        if (saveCustomerBtn) {
            saveCustomerBtn.disabled = true;
            saveCustomerBtn.textContent = 'Saving...';
        }

        try {
            const response = await fetch('/NexGen/CODE/PHP/customer_save.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const rawText = await response.text();
            let data;

            try {
                data = JSON.parse(rawText);
            } catch (jsonError) {
                console.error('Invalid JSON response:', rawText);
                throw new Error('Server returned an invalid response while saving customer.');
            }

            if (data.success) {
                if (!customerSelect) {
                    throw new Error('Customer dropdown not found.');
                }

                const existingOption = Array.from(customerSelect.options).find(
                    option => option.value === String(data.customer.id)
                );

                if (!existingOption) {
                    const option = document.createElement('option');
                    option.value = data.customer.id;
                    option.textContent = `${data.customer.customer_name} (${data.customer.customer_code})`;
                    customerSelect.appendChild(option);
                }

                customerSelect.value = String(data.customer.id);

                customerForm.reset();
                closeCustomerModal();
                showToast(data.message || 'Customer added successfully.', 'success');
            } else {
                showToast(data.message || 'Failed to add customer.', 'error');
            }
        } catch (error) {
            console.error('Customer AJAX error:', error);
            showToast(error.message || 'An error occurred while saving the customer.', 'error');
        } finally {
            if (saveCustomerBtn) {
                saveCustomerBtn.disabled = false;
                saveCustomerBtn.textContent = 'Save Customer';
            }
        }
    }

    if (itemsContainer) {
        itemsContainer.addEventListener('change', function (e) {
            if (e.target.classList.contains('product-select')) {
                updatePrice(e.target);
            }
        });

        itemsContainer.addEventListener('input', function (e) {
            if (
                e.target.classList.contains('qty-input') ||
                e.target.classList.contains('price-input')
            ) {
                calculateRow(e.target);
            }
        });

        itemsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('row-remove')) {
                removeRow(e.target);
            }
        });
    }

    if (saleForm) {
        saleForm.addEventListener('submit', submitSaleAjax);
    }

    if (customerForm) {
        customerForm.addEventListener('submit', submitCustomerAjax);
    }

    if (paymentStatus) {
        paymentStatus.addEventListener('change', function () {
            togglePaymentFields();
            applyCustomerFieldRules();
            syncOrderStatusWithPayment();
        });
    }

    if (amountPaidInput) {
        amountPaidInput.addEventListener('input', function () {
            const status = paymentStatus ? paymentStatus.value : 'Paid';
            if (!orderStatus) return;

            orderStatus.value = status === 'Paid' ? 'Fulfilled' : 'Pending';
        });
    }

    window.openSaleModal = openSaleModal;
    window.closeSaleModal = closeSaleModal;
    window.openCustomerModal = openCustomerModal;
    window.closeCustomerModal = closeCustomerModal;
    window.addItemRow = addItemRow;
    window.updatePrice = updatePrice;
    window.calculateRow = calculateRow;
    window.removeRow = removeRow;

    addItemRow();
    applyCustomerFieldRules();
    syncOrderStatusWithPayment();
    togglePaymentFields();

    if (window.initialPopup && window.initialPopup.message) {
        showToast(window.initialPopup.message, window.initialPopup.type || 'info');
    }
});