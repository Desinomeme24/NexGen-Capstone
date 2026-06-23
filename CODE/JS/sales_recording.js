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

    const saleConfirmOverlay = document.getElementById('saleConfirmOverlay');
    const saleConfirmCancel = document.getElementById('saleConfirmCancel');
    const saleConfirmYes = document.getElementById('saleConfirmYes');

    const products = Array.isArray(window.products) ? window.products : [];

    if (saleForm) saleForm.noValidate = true;
    if (customerForm) customerForm.noValidate = true;

    function safeText(value) {
        return String(value ?? '');
    }

    function safeNumber(value, fallback = 0) {
        const num = Number(value);
        return Number.isFinite(num) ? num : fallback;
    }

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

        const accent = document.createElement('div');
        accent.className = 'nx-toast-accent';

        const content = document.createElement('div');
        content.className = 'nx-toast-content';

        const title = document.createElement('div');
        title.className = 'nx-toast-title';
        title.textContent = titleMap[type] || 'Message';

        const msg = document.createElement('div');
        msg.className = 'nx-toast-message';
        msg.textContent = safeText(message);

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'nx-toast-close';
        closeBtn.setAttribute('aria-label', 'Close notification');
        closeBtn.textContent = '×';

        content.appendChild(title);
        content.appendChild(msg);

        toast.appendChild(accent);
        toast.appendChild(content);
        toast.appendChild(closeBtn);

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

        closeBtn.addEventListener('click', removeToast);
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
        closeSaleConfirm();
        document.body.style.overflow = 'auto';
    }

    function openSaleConfirm() {
        if (!saleConfirmOverlay) return;
        saleConfirmOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSaleConfirm() {
        if (!saleConfirmOverlay) return;
        saleConfirmOverlay.classList.remove('show');

        if ((saleModal && saleModal.classList.contains('show')) || (customerModal && customerModal.classList.contains('show'))) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
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

    if (saleConfirmCancel) {
        saleConfirmCancel.addEventListener('click', function () {
            closeSaleConfirm();
        });
    }

    if (saleConfirmOverlay) {
        saleConfirmOverlay.addEventListener('click', function (e) {
            if (e.target === saleConfirmOverlay) {
                closeSaleConfirm();
            }
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
            if (saleConfirmOverlay && saleConfirmOverlay.classList.contains('show')) {
                closeSaleConfirm();
                return;
            }

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

    function buildProductSelect(selectEl) {
        selectEl.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select Product';
        selectEl.appendChild(defaultOption);

        products.forEach(product => {
            const option = document.createElement('option');
            option.value = safeText(product.id);
            option.dataset.price = String(safeNumber(product.selling_price));
            option.dataset.stock = String(safeNumber(product.stock_quantity));
            option.textContent = `${safeText(product.product_name)} (Stock: ${safeNumber(product.stock_quantity)} | ₱${safeNumber(product.selling_price).toFixed(2)})`;
            selectEl.appendChild(option);
        });
    }

    function addItemRow() {
        if (!itemsContainer) return;

        const row = document.createElement('div');
        row.className = 'item-row';

        const col1 = document.createElement('div');
        const productSelect = document.createElement('select');
        productSelect.name = 'product_id[]';
        productSelect.className = 'product-select';
        buildProductSelect(productSelect);
        col1.appendChild(productSelect);

        const col2 = document.createElement('div');
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.name = 'quantity[]';
        qtyInput.className = 'qty-input';
        qtyInput.min = '1';
        qtyInput.value = '1';
        col2.appendChild(qtyInput);

        const col3 = document.createElement('div');
        const priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.step = '0.01';
        priceInput.name = 'unit_price[]';
        priceInput.className = 'price-input';
        priceInput.min = '0';
        priceInput.value = '0.00';
        col3.appendChild(priceInput);

        const col4 = document.createElement('div');
        const subtotalInput = document.createElement('input');
        subtotalInput.type = 'text';
        subtotalInput.className = 'subtotal-input readonly-box';
        subtotalInput.value = '0.00';
        subtotalInput.readOnly = true;
        col4.appendChild(subtotalInput);

        const col5 = document.createElement('div');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'row-remove';
        removeBtn.textContent = '×';
        col5.appendChild(removeBtn);

        row.appendChild(col1);
        row.appendChild(col2);
        row.appendChild(col3);
        row.appendChild(col4);
        row.appendChild(col5);

        itemsContainer.appendChild(row);
        calculateGrandTotal();
    }

    function updatePrice(selectEl) {
        const row = selectEl.closest('.item-row');
        if (!row) return;

        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const price = selectedOption ? selectedOption.getAttribute('data-price') || 0 : 0;

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

    async function doSubmitSaleAjax() {
        if (!saleForm) return;

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
                showToast(data.message || 'Sale saved successfully.', 'success', 4200);

                if (saveSaleBtn) {
                    saveSaleBtn.textContent = 'Saved Successfully';
                }

                setTimeout(() => {
                    closeSaleModal();
                    resetSaleForm();
                }, 1100);

                setTimeout(() => {
                    window.location.href = '/NexGen/CODE/PHP/sale_view.php?id=' + data.sale_id;
                }, 2200);
            } else {
                showToast(data.message || 'Failed to save sale.', 'error', 4200);
            }
        } catch (error) {
            console.error('AJAX error:', error);
            showToast(error.message || 'An error occurred while saving the sale.', 'error', 4200);
        } finally {
            setTimeout(() => {
                if (saveSaleBtn) {
                    saveSaleBtn.disabled = false;
                    saveSaleBtn.textContent = 'Save Sale';
                }
            }, 1200);
        }
    }

    async function submitSaleAjax(e) {
        e.preventDefault();

        if (!saleForm) return;
        if (!validateSaleForm()) return;

        openSaleConfirm();
    }

    if (saleConfirmYes) {
        saleConfirmYes.addEventListener('click', async function () {
            closeSaleConfirm();
            await doSubmitSaleAjax();
        });
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
                    option.value = String(data.customer.id);
                    option.textContent = `${safeText(data.customer.customer_name)} (${safeText(data.customer.customer_code)})`;
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