var productPrices = {};

document.addEventListener('DOMContentLoaded', function() {
    fetch('api_expense_handler.php?action=get_all_product_prices')
        .then(r => r.json())
        .then(prices => { productPrices = prices; });

    loadShops();

    const dateInput = document.getElementById('dateInput');
    if (dateInput) {
        dateInput.addEventListener('change', updateMonthFromDate);
    }
    const editDateInput = document.getElementById('editDateInput');
    if (editDateInput) {
        editDateInput.addEventListener('change', updateEditMonthFromDate);
    }

    $('input, select').on('input change', function() {
        $(this).removeClass('is-invalid');
    });

    $('#addModal').on('hidden.bs.modal', function() {
        resetAddForm();
    });
});

$('#expensesTable').DataTable({
    pageLength: 25,
    order: [[2, 'desc']],
    columnDefs: [
        { targets: [12], orderable: false }
    ]
});

// ========== SHOPS ==========

function loadShops() {
    fetch('api_expense_handler.php?action=get_shops')
        .then(r => r.json())
        .then(shops => {
            const select = document.getElementById('shopSelect');
            const editSelect = document.getElementById('editShopSelect');

            [select, editSelect].forEach(sel => {
                if (sel) {
                    sel.innerHTML = '<option value="">Select Shop</option>';
                    shops.forEach(shop => {
                        const opt = document.createElement('option');
                        opt.value = shop.id;
                        opt.textContent = shop.name;
                        opt.setAttribute('data-phone', shop.phone || '');
                        sel.appendChild(opt);
                    });
                }
            });
        })
        .catch(error => {
            showAlert('Failed to load shops: ' + error.message, 'danger');
        });
}

function updateShopDetails() {
    const shopSelect = document.getElementById('shopSelect');
    const phoneInput = document.getElementById('phoneInput');
    phoneInput.value = shopSelect.value
        ? (shopSelect.options[shopSelect.selectedIndex].getAttribute('data-phone') || '')
        : '';
}

function updateEditShopDetails() {
    const shopSelect = document.getElementById('editShopSelect');
    const phoneInput = document.getElementById('editPhoneInput');
    if (shopSelect && phoneInput) {
        phoneInput.value = shopSelect.value
            ? (shopSelect.options[shopSelect.selectedIndex].getAttribute('data-phone') || '')
            : '';
    }
}

// ========== CATEGORY -> PRODUCT ==========

function syncProducts(select, targetId) {
    const catId = select.value;
    const target = document.getElementById(targetId);
    target.innerHTML = '<option>Loading...</option>';
    fetch(`api_expense_handler.php?action=get_products&cat_id=${catId}`)
        .then(r => r.json())
        .then(products => {
            target.innerHTML = '<option value="">Select Product</option>';
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                const priceText = p.price ? ` - Rs. ${parseFloat(p.price).toFixed(2)}` : '';
                opt.textContent = p.name + (p.company ? ` (${p.company})` : '') + priceText;
                target.appendChild(opt);
            });
        });
}

function updateProductPrice() {
    const productSelect = document.getElementById('product_id_add');
    const unitPriceInput = document.getElementById('unit_price');
    const productId = productSelect.value;

    if (!productId) return;

    if (productPrices[productId]) {
        unitPriceInput.value = productPrices[productId];
        handleUnitPriceChange();
    } else {
        fetch(`api_expense_handler.php?action=get_product_price&id=${productId}`)
            .then(r => r.json())
            .then(product => {
                if (product.price) {
                    unitPriceInput.value = product.price;
                    handleUnitPriceChange();
                }
            });
    }
}

function updateEditProductPrice() {
    const productSelect = document.getElementById('product_id_edit');
    const unitPriceInput = document.getElementById('editUnitPrice');
    const productId = productSelect.value;

    if (!productId) return;

    if (productPrices[productId]) {
        unitPriceInput.value = productPrices[productId];
        handleEditUnitPriceChange();
    } else {
        fetch(`api_expense_handler.php?action=get_product_price&id=${productId}`)
            .then(r => r.json())
            .then(product => {
                if (product.price) {
                    unitPriceInput.value = product.price;
                    handleEditUnitPriceChange();
                }
            });
    }
}

// ========== QUANTITY / UNIT PRICE / TOTAL THREE-WAY SYNC ==========

let isUpdatingAdd = false;

function handleQuantityChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    document.getElementById('total_amount').value = (qty * price).toFixed(2);
    isUpdatingAdd = false;
}

function handleUnitPriceChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    document.getElementById('total_amount').value = (qty * price).toFixed(2);
    isUpdatingAdd = false;
}

function handleTotalAmountChange() {
    if (isUpdatingAdd) return;
    isUpdatingAdd = true;
    const qty = parseFloat(document.getElementById('quantity')?.value || 1);
    const total = parseFloat(document.getElementById('total_amount')?.value || 0);
    if (qty > 0) {
        document.getElementById('unit_price').value = (total / qty).toFixed(2);
    }
    isUpdatingAdd = false;
}

let isUpdatingEdit = false;

function handleEditQuantityChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const price = parseFloat(document.getElementById('editUnitPrice')?.value || 0);
    document.getElementById('editTotalAmount').value = (qty * price).toFixed(2);
    isUpdatingEdit = false;
}

function handleEditUnitPriceChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const price = parseFloat(document.getElementById('editUnitPrice')?.value || 0);
    document.getElementById('editTotalAmount').value = (qty * price).toFixed(2);
    isUpdatingEdit = false;
}

function handleEditTotalAmountChange() {
    if (isUpdatingEdit) return;
    isUpdatingEdit = true;
    const qty = parseFloat(document.getElementById('editQuantity')?.value || 1);
    const total = parseFloat(document.getElementById('editTotalAmount')?.value || 0);
    if (qty > 0) {
        document.getElementById('editUnitPrice').value = (total / qty).toFixed(2);
    }
    isUpdatingEdit = false;
}

// ========== MONTH DISPLAY (cosmetic only) ==========

function updateMonthFromDate() {
    // No visible month field on the Add modal - server derives month from date on save.
}

function updateEditMonthFromDate() {
    const dateInput = document.getElementById('editDateInput');
    const monthField = document.getElementById('editMonthField');
    if (dateInput && monthField && dateInput.value) {
        const date = new Date(dateInput.value);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        monthField.value = `${yyyy}-${mm}`;
    }
}

// ========== BILL NUMBER ==========

function getNextBillNumber() {
    fetch('api_expense_handler.php?action=get_next_bill')
        .then(r => r.json())
        .then(data => {
            if (data.bill_no) {
                document.querySelector('input[name="bill_no"]').value = data.bill_no;
            }
        });
}

// ========== FORM VALIDATION ==========

function validateForm() {
    const required = ['shop_id', 'date', 'category_id', 'product_id', 'unit_price'];
    let isValid = true;
    required.forEach(field => {
        const element = $(`#addForm [name="${field}"]`);
        if (!element.val()) {
            element.addClass('is-invalid');
            isValid = false;
        } else {
            element.removeClass('is-invalid');
        }
    });
    return isValid;
}

// ========== ADD / SAVE ==========

function saveExpense(callback) {
    if (!validateForm()) {
        showAlert('Please fill all required fields', 'warning');
        return;
    }
    const formData = $('#addForm').serializeArray();
    $.ajax({
        url: 'api_expense_handler.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                callback(response);
            } else {
                showAlert(response.message || 'Error saving expense', 'danger');
            }
        },
        error: function() {
            showAlert('Server error occurred', 'danger');
        }
    });
}

$('#saveAndContinueBtn').click(function() {
    saveExpense(function() {
        showAlert('Expense saved! Add another below.', 'success');
        resetAddForm();
    });
});

$('#saveAndExitBtn').click(function() {
    saveExpense(function() {
        showAlert('Expense saved successfully!', 'success');
        $('#addModal').modal('hide');
        setTimeout(() => location.reload(), 800);
    });
});

function resetAddForm() {
    const form = document.getElementById('addForm');
    form.reset();
    document.getElementById('product_id_add').innerHTML = '<option value="">Select Category First</option>';
    document.getElementById('quantity').value = 1;
    document.getElementById('unit_price').value = '';
    document.getElementById('total_amount').value = '';
    document.getElementById('dateInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('phoneInput').value = '';
    document.getElementById('shopSelect').value = '';
    getNextBillNumber();
}

// ========== EDIT ==========

function editExpense2(expense) {
    const form = document.getElementById('editForm');
    form.reset();

    form.querySelector('[name="id"]').value = expense.id;
    form.querySelector('[name="bill_no"]').value = expense.bill_no;
    form.querySelector('[name="date"]').value = expense.date.split(' ')[0];
    form.querySelector('[name="cn"]').value = expense.cn || '';

    document.getElementById('editQuantity').value = expense.quantity || 1;
    document.getElementById('editUnitPrice').value = expense.unit_price || expense.amount;
    handleEditQuantityChange();

    setTimeout(() => {
        const shopSelect = document.getElementById('editShopSelect');
        if (shopSelect && expense.shop_id) {
            shopSelect.value = expense.shop_id;
            updateEditShopDetails();
        }
    }, 100);

    if (expense.category_id) {
        const catSelect = document.querySelector('#editForm [name="category_id"]');
        catSelect.value = expense.category_id;
        syncProducts(catSelect, 'product_id_edit');

        setTimeout(() => {
            const productSelect = document.querySelector('#editForm [name="product_id"]');
            if (productSelect && expense.product_id) {
                productSelect.value = expense.product_id;
            }
        }, 500);
    }

    updateEditMonthFromDate();

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    try {
        const formData = new FormData(this);
        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        showAlert(result.message || 'Expense updated', result.success ? 'success' : 'danger');
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            setTimeout(() => location.reload(), 800);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

// ========== DELETE ==========

function deleteExpense2(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('confirmDelete').addEventListener('click', async function() {
    const id = document.getElementById('deleteId').value;
    if (!id) return;

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();

        showAlert(result.message || 'Expense deleted', 'success');
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        setTimeout(() => location.reload(), 800);
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

// ========== MARK AS PAID ==========

async function markAsUnpaid(id, billNo) {
    if (!confirm(`Mark bill ${billNo} as unpaid? This clears its cheque number and payment date.`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'mark_unpaid');
        formData.append('id', id);

        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();

        showAlert(result.message || 'Expense marked as unpaid', result.success ? 'success' : 'danger');
        if (result.success) {
            setTimeout(() => location.reload(), 800);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
}

function showMarkPaidModal(id, billNo, amount) {
    document.getElementById('markPaidId').value = id;
    document.getElementById('markPaidBillNo').textContent = billNo;
    document.getElementById('markPaidAmount').textContent = Number(amount).toFixed(2);
    document.getElementById('markPaidCn').value = '';
    document.getElementById('markPaidDate').value = new Date().toISOString().slice(0, 10);
}

document.getElementById('confirmMarkPaid').addEventListener('click', async function() {
    const id = document.getElementById('markPaidId').value;
    const cn = document.getElementById('markPaidCn').value.trim();
    const paymentDate = document.getElementById('markPaidDate').value;
    if (!id) return;

    if (!cn) {
        showAlert('Cheque number is required', 'warning');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'process_payment');
        formData.append('expense_ids[]', id);
        formData.append('cn', cn);
        formData.append('payment_date', paymentDate);

        const response = await fetch('api_expense_handler.php', { method: 'POST', body: formData });
        const result = await response.json();

        showAlert(result.message || 'Expense marked as paid', result.success ? 'success' : 'danger');
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('markPaidModal')).hide();
            setTimeout(() => location.reload(), 800);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

// ========== ALERTS ==========

function showAlert(msg, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `<div>${msg}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.getElementById('alert-placeholder').appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
}

// ========== PRINT ==========

function formatNumber(num) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num || 0);
}

function printSingle2(expense) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    const html = `
    <html>
    <head>
        <title>Invoice ${expense.bill_no}</title>
        <style>
            body { font-family: Arial; padding: 20px; }
            .invoice-box { border: 2px solid #000; padding: 20px; max-width: 800px; margin: 0 auto; }
            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #000; padding: 8px; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .signature { margin-top: 50px; display: flex; justify-content: space-between; }
            .total-row { font-weight: bold; background: #f0f0f0; }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <h2>Muhaddisa School of Science and Technology</h2>
                <h3>Expense Invoice</h3>
            </div>
            <table>
                <tr><td><strong>Bill No:</strong></td><td>${expense.bill_no}</td></tr>
                <tr><td><strong>Date:</strong></td><td>${new Date(expense.date).toLocaleDateString('en-GB')}</td></tr>
                <tr><td><strong>Shop:</strong></td><td>${expense.shop || ''}</td></tr>
                <tr><td><strong>Phone:</strong></td><td>${expense.phone || 'N/A'}</td></tr>
                <tr><td><strong>Cheque No:</strong></td><td>${expense.cn || '—'}</td></tr>
                <tr><td><strong>Category:</strong></td><td>${expense.category_name || ''}</td></tr>
                <tr><td colspan="2"><strong>Product Details:</strong></td></tr>
                <tr><td colspan="2">${expense.product_name || ''} ${expense.company ? '('+expense.company+')' : ''}</td></tr>
            </table>
            <table style="margin-top: 20px;">
                <tr><th>Quantity</th><th>Unit Price</th><th>Total Amount</th></tr>
                <tr class="total-row">
                    <td class="text-center">${expense.quantity || 1}</td>
                    <td class="text-right">Rs. ${formatNumber(expense.unit_price || expense.amount)}</td>
                    <td class="text-right"><strong>Rs. ${formatNumber(expense.amount)}</strong></td>
                </tr>
            </table>
            <div class="signature">
                <div>Accountant: ________________</div>
                <div>Authorized: ________________</div>
            </div>
            <div style="text-align:center; margin-top:20px;"><small>Printed: ${new Date().toLocaleString()}</small></div>
        </div>
    </body>
    </html>`;

    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

function printSummaryOnly() {
    const win = window.open('', '', 'width=900,height=700');
    const content = document.getElementById('summary-page').innerHTML;
    win.document.write(`
        <html><head><title>Summary</title>
        <style>
            body { font-family: Arial; margin: 30px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th, td { border: 1px solid #000; padding: 6px; }
            h5 { font-size: 16px; text-align: center; }
        </style>
        </head><body>
        ${content}
        </body></html>
    `);
    win.document.close();
    win.print();
}

function printReport() {
    const win = window.open('', '', 'width=900,height=700');
    win.document.write(`
        <html><head><title>Full Report</title>
        <style>
            body { font-family: Arial; margin: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 11px; }
            th, td { border: 1px solid #000; padding: 4px; }
        </style>
        </head><body>
        ${document.getElementById('printable').innerHTML}
        </body></html>
    `);
    win.document.close();
    win.print();
}
