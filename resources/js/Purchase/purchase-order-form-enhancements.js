document.addEventListener('DOMContentLoaded', () => {
    const poModal = document.getElementById('poModal');
    const poForm = document.getElementById('poForm');

    if (!poModal || !poForm) {
        return;
    }

    const hideField = (id) => {
        const field = document.getElementById(id);
        const group = field?.closest('.po-form-group');

        if (group) {
            group.classList.add('po-field-hidden');
        }

        return field;
    };

    const supplier = hideField('supplier_name');
    hideField('supplier_address_tel');
    hideField('terms');
    hideField('terms_of_payment');
    hideField('main_bus_no');
    hideField('main_employee');
    hideField('purpose');

    ['delivery_fee', 'discount', 'vat', 'gross_amount_display'].forEach((id) => {
        const field = document.getElementById(id);
        field?.closest('.po-total-row')?.classList.add('po-field-hidden');
    });

    if (supplier) {
        supplier.required = false;
        supplier.tabIndex = -1;
    }

    const requestInfo = document.querySelector('.po-request-info');
    const mainPrNo = document.getElementById('main_pr_no');

    if (requestInfo && mainPrNo) {
        requestInfo.classList.add('po-request-reference');

        if (!requestInfo.querySelector('.po-request-reference-heading')) {
            const heading = document.createElement('div');
            heading.className = 'po-request-reference-heading';
            heading.innerHTML = `
                <span class="po-form-section-icon"><i class="fa-solid fa-link"></i></span>
                <div>
                    <strong>Request Reference</strong>
                    <small>Automatically linked when this PO comes from a purchase request.</small>
                </div>
            `;
            requestInfo.prepend(heading);
        }
    }

    const formGrid = document.querySelector('.po-form-grid');
    if (formGrid && !formGrid.previousElementSibling?.classList.contains('po-form-section-heading')) {
        const heading = document.createElement('div');
        heading.className = 'po-form-section-heading';
        heading.innerHTML = `
            <span class="po-form-section-icon"><i class="fa-solid fa-file-invoice"></i></span>
            <div>
                <strong>Purchase Order Information</strong>
                <small>PO number and date are generated automatically. Choose the current procurement status.</small>
            </div>
        `;
        formGrid.before(heading);
    }

    const itemsSection = document.querySelector('.po-items-section');
    const itemsTitle = itemsSection?.querySelector('.po-items-title');
    if (itemsTitle) {
        itemsTitle.innerHTML = `
            <span class="po-form-section-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <span>
                <strong>Purchase Items</strong>
                <small>Add the parts or materials included in this purchase order.</small>
            </span>
        `;
    }

    const totalsBox = document.querySelector('.po-totals-box');
    const netAmount = document.getElementById('net_amount_display');
    const netRow = netAmount?.closest('.po-total-row');

    if (totalsBox && netRow) {
        totalsBox.classList.add('po-simple-total-box');
        const label = netRow.querySelector('label');
        if (label) {
            label.textContent = 'Total Amount';
        }
    }

    const bottomGrid = document.querySelector('.po-bottom-grid');
    if (bottomGrid) {
        bottomGrid.classList.add('po-simple-bottom');
    }

    const companyTitle = document.querySelector('.po-company-title');
    if (companyTitle) {
        const subtitle = companyTitle.querySelector('p');
        if (subtitle) {
            subtitle.textContent = 'PURCHASE ORDER FORM';
        }
    }

    const ensureHiddenDefaults = () => {
        if (supplier && !String(supplier.value || '').trim()) {
            supplier.value = 'N/A';
        }

        ['delivery_fee', 'discount', 'vat'].forEach((id) => {
            const field = document.getElementById(id);
            if (field && !String(field.value || '').trim()) {
                field.value = '0';
            }
        });
    };

    const syncRequestVisibility = () => {
        if (!requestInfo || !mainPrNo) {
            return;
        }

        requestInfo.classList.toggle('has-request-reference', Boolean(String(mainPrNo.value || '').trim()));
    };

    ensureHiddenDefaults();
    syncRequestVisibility();

    document.getElementById('openPoModal')?.addEventListener('click', () => {
        window.setTimeout(() => {
            ensureHiddenDefaults();
            syncRequestVisibility();
        }, 0);
    });

    poForm.addEventListener('reset', () => {
        window.setTimeout(() => {
            ensureHiddenDefaults();
            syncRequestVisibility();
        }, 0);
    });

    poForm.addEventListener('submit', ensureHiddenDefaults, true);

    mainPrNo?.addEventListener('input', syncRequestVisibility);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.open-view-po-modal, .open-edit-po-modal')) {
            return;
        }

        window.setTimeout(() => {
            ensureHiddenDefaults();
            syncRequestVisibility();
        }, 0);
    });

    const itemObserver = new MutationObserver(() => {
        document.querySelectorAll('.po-item-row').forEach((row) => {
            row.classList.add('po-item-row-redesigned');
        });
    });

    const itemsContainer = document.getElementById('poItemsContainer');
    if (itemsContainer) {
        itemObserver.observe(itemsContainer, { childList: true });
    }
});
