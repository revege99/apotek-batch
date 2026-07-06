

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.store('loadingState', {
        active: document.readyState !== 'complete',
        message: 'Memuat halaman...',
        pendingRequests: 0,

        show(message = 'Memproses data...') {
            this.message = message;
            this.active = true;
        },

        hide() {
            if (this.pendingRequests > 0) {
                return;
            }

            this.active = false;
        },

        begin(message = 'Memuat data...') {
            this.pendingRequests += 1;
            this.show(message);
        },

        end() {
            this.pendingRequests = Math.max(0, this.pendingRequests - 1);

            if (this.pendingRequests === 0) {
                this.hide();
            }
        },
    });

    window.apotikLoading = {
        show(message) {
            Alpine.store('loadingState').show(message);
        },
        hide() {
            Alpine.store('loadingState').hide();
        },
        begin(message) {
            Alpine.store('loadingState').begin(message);
        },
        end() {
            Alpine.store('loadingState').end();
        },
    };

    const originalFetch = window.fetch.bind(window);

    window.fetch = async (...args) => {
        const [resource, options = {}] = args;
        const headers = new Headers(options?.headers ?? {});
        const shouldSkipLoading = headers.get('X-Apotik-Silent-Loading') === 'true';
        const method = String(options?.method ?? 'GET').toUpperCase();

        if (! shouldSkipLoading) {
            window.apotikLoading.begin(method === 'GET' ? 'Memuat data...' : 'Menyimpan data...');
        }

        try {
            return await originalFetch(resource, options);
        } finally {
            if (! shouldSkipLoading) {
                window.apotikLoading.end();
            }
        }
    };

    const originalXhrOpen = XMLHttpRequest.prototype.open;
    const originalXhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        this._apotikLoadingMethod = String(method ?? 'GET').toUpperCase();
        return originalXhrOpen.call(this, method, url, async, user, password);
    };

    XMLHttpRequest.prototype.send = function(body) {
        window.apotikLoading.begin(this._apotikLoadingMethod === 'GET' ? 'Memuat data...' : 'Menyimpan data...');

        this.addEventListener('loadend', () => {
            window.apotikLoading.end();
        }, { once: true });

        return originalXhrSend.call(this, body);
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || form.dataset.noLoading === 'true') {
            return;
        }

        const submitter = event.submitter;
        const formMethod = String(submitter?.getAttribute('formmethod') ?? form.getAttribute('method') ?? 'GET').toUpperCase();
        const message = formMethod === 'GET' ? 'Memuat data...' : 'Menyimpan data...';

        window.apotikLoading.show(message);
    }, true);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (
            ! link
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || link.target === '_blank'
            || link.hasAttribute('download')
        ) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin || url.hash === window.location.hash && url.pathname === window.location.pathname) {
            return;
        }

        window.apotikLoading.show('Memuat halaman...');
    }, true);

    window.addEventListener('load', () => {
        window.apotikLoading.hide();
    });

    const sidebarScrollKey = 'apotik.sidebar.scrollTop';
    const numberFormatter = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
    const integerFormatter = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
    const toNumber = (value, fallback = 0) => {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : fallback;
    };
    const roundCurrency = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
    const roundWholeCurrency = (value) => Math.round(toNumber(value, 0));
    const formatMedicinePriceInput = (value, inputType = '') => {
        const rawValue = String(value ?? '').trim();
        const isDeleteAction = String(inputType ?? '').startsWith('delete');
        let digits = '';

        if (! isDeleteAction && rawValue.includes(',')) {
            digits = rawValue
                .split(',', 2)[0]
                .replace(/\D+/g, '');
        } else if (
            ! isDeleteAction
            && /^\d+\.\d{1,2}$/.test(rawValue)
            && rawValue.split('.', 2)[0].length > 3
        ) {
            digits = rawValue
                .split('.', 2)[0]
                .replace(/\D+/g, '');
        } else {
            digits = rawValue.replace(/\D+/g, '');
        }

        if (digits === '') {
            return '';
        }

        return integerFormatter.format(Number(digits));
    };

    Alpine.data('layoutShell', () => ({
        sidebarOpen: false,
        isNavigatingSidebar: false,

        init() {
            window.addEventListener('beforeunload', () => this.rememberSidebarScroll());
            window.addEventListener('popstate', () => {
                this.navigateWithinShell(window.location.href, { replace: true, preserveSidebarState: true });
            });

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => this.restoreSidebarScroll());
                });
            });
        },

        rememberSidebarScroll() {
            if (! this.$refs.sidebarScroll) {
                return;
            }

            sessionStorage.setItem(sidebarScrollKey, String(this.$refs.sidebarScroll.scrollTop));
        },

        restoreSidebarScroll() {
            if (! this.$refs.sidebarScroll) {
                return;
            }

            const savedScrollTop = sessionStorage.getItem(sidebarScrollKey);

            if (savedScrollTop === null) {
                return;
            }

            const scrollTop = Number(savedScrollTop);

            if (Number.isFinite(scrollTop)) {
                this.$refs.sidebarScroll.scrollTop = scrollTop;
            }
        },

        handleSidebarNavigation(event) {
            const link = event.target.closest('a[href]');

            if (! link || event.defaultPrevented) {
                return;
            }

            if (
                event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
                || link.target === '_blank'
                || link.hasAttribute('download')
            ) {
                return;
            }

            const url = new URL(link.href, window.location.origin);

            if (url.origin !== window.location.origin) {
                return;
            }

            if (! ['/dashboard', '/master-data', '/pembelian', '/penjualan', '/stok-batch', '/keuangan', '/laporan', '/pengaturan', '/profile'].some((prefix) => url.pathname.startsWith(prefix))) {
                return;
            }

            event.preventDefault();
            this.navigateWithinShell(url.toString());
        },

        async navigateWithinShell(url, { replace = false, preserveSidebarState = false } = {}) {
            if (this.isNavigatingSidebar) {
                return;
            }

            const mainContent = this.$refs.mainContent;

            if (! mainContent) {
                window.location.assign(url);
                return;
            }

            this.isNavigatingSidebar = true;
            this.rememberSidebarScroll();

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Apotik-Partial': 'main-content',
                    },
                    credentials: 'same-origin',
                });

                if (! response.ok) {
                    window.location.assign(url);
                    return;
                }

                const html = await response.text();
                const parser = new DOMParser();
                const documentFragment = parser.parseFromString(html, 'text/html');
                const nextMainContent = documentFragment.querySelector('main');

                if (! nextMainContent) {
                    window.location.assign(url);
                    return;
                }

                document.title = documentFragment.title || document.title;
                mainContent.innerHTML = nextMainContent.innerHTML;
                Alpine.initTree(mainContent);

                if (replace) {
                    window.history.replaceState({}, '', url);
                } else if (window.location.href !== url) {
                    window.history.pushState({}, '', url);
                }

                this.syncSidebarState(url, preserveSidebarState);
                mainContent.scrollTop = 0;
            } catch (error) {
                window.location.assign(url);
            } finally {
                this.isNavigatingSidebar = false;
            }
        },

        syncSidebarState(url, preserveSidebarState = false) {
            const currentUrl = new URL(url, window.location.origin);
            const sidebarRoot = this.$refs.sidebarNav;

            if (! sidebarRoot) {
                return;
            }

            const normalizePath = (value) => {
                const normalized = value.replace(/\/+$/, '');

                return normalized === '' ? '/' : normalized;
            };
            const targetPath = normalizePath(currentUrl.pathname);
            const childLinks = sidebarRoot.querySelectorAll('.apotik-sidebar__child[href]');
            const itemLinks = sidebarRoot.querySelectorAll('.apotik-sidebar__item[href]');

            itemLinks.forEach((link) => {
                const isActive = normalizePath(new URL(link.href, window.location.origin).pathname) === targetPath;

                link.classList.toggle('is-active', isActive);
                link.querySelector('.apotik-sidebar__icon')?.classList.toggle('is-active', isActive);
                link.querySelector('.apotik-sidebar__text')?.classList.toggle('is-active', isActive);
            });

            childLinks.forEach((link) => {
                const linkPath = normalizePath(new URL(link.href, window.location.origin).pathname);
                const isActive = targetPath === linkPath || targetPath.startsWith(`${linkPath}/`);

                link.classList.toggle('is-active', isActive);
                link.querySelector('.apotik-sidebar__child-dot')?.classList.toggle('is-active', isActive);
                link.querySelector('.apotik-sidebar__child-text')?.classList.toggle('is-active', isActive);

                if (isActive && ! preserveSidebarState) {
                    const groupElement = link.closest('.apotik-sidebar__group');

                    if (groupElement && Alpine.$data(groupElement)) {
                        Alpine.$data(groupElement).open = true;
                    }
                }
            });
        },
    }));

    Alpine.data('sidebarGroup', (storageKey, activeByDefault) => ({
        open: activeByDefault,

        init() {
            const storedState = sessionStorage.getItem(`apotik.sidebar.group.${storageKey}`);

            if (storedState === null) {
                this.open = activeByDefault;
                return;
            }

            this.open = storedState === '1' || activeByDefault;
        },

        toggle() {
            this.open = ! this.open;
            sessionStorage.setItem(`apotik.sidebar.group.${storageKey}`, this.open ? '1' : '0');
        },
    }));

    Alpine.data('masterDeleteDialog', () => ({
        deleteModalOpen: false,
        deleteFormAction: '',
        deleteTarget: null,
        mainContentLockTarget: null,
        mainContentPreviousOverflow: '',
        cleanupTimer: null,

        init() {
            this.$watch('deleteModalOpen', (value) => {
                if (value) {
                    this.lockPageScroll();
                    return;
                }
            });
        },

        openDeleteDialog(payload = {}) {
            if (this.cleanupTimer) {
                window.clearTimeout(this.cleanupTimer);
                this.cleanupTimer = null;
            }

            this.deleteTarget = {
                title: payload.title ?? 'Hapus data ini?',
                description: payload.description ?? 'Data yang dihapus tidak bisa dikembalikan lagi.',
                warning: payload.warning ?? 'Pastikan item ini sudah tidak dipakai di data lain.',
                confirm_label: payload.confirm_label ?? 'Ya, hapus data',
                name: payload.name ?? '',
                code: payload.code ?? '',
            };
            this.deleteFormAction = payload.action ?? '';
            this.deleteModalOpen = true;

            this.$nextTick(() => {
                this.$refs.cancelDeleteButton?.focus();
            });
        },

        closeDeleteDialog() {
            this.deleteModalOpen = false;
            this.cleanupTimer = window.setTimeout(() => {
                this.unlockPageScroll();
                this.deleteFormAction = '';
                this.deleteTarget = null;
                this.cleanupTimer = null;
            }, 180);
        },

        lockPageScroll() {
            const mainContent = document.querySelector('[x-ref="mainContent"]');

            if (mainContent instanceof HTMLElement) {
                this.mainContentLockTarget = mainContent;
                this.mainContentPreviousOverflow = mainContent.style.overflowY ?? '';
                mainContent.style.overflowY = 'hidden';
            }
        },

        unlockPageScroll() {
            if (! this.mainContentLockTarget) {
                return;
            }

            this.mainContentLockTarget.style.overflowY = this.mainContentPreviousOverflow;
            this.mainContentLockTarget = null;
            this.mainContentPreviousOverflow = '';
        },
    }));

    Alpine.data('floatingActionMenu', () => ({
        open: false,
        menuStyles: '',

        toggleMenu() {
            if (this.open) {
                this.close();
                return;
            }

            this.open = true;

            this.$nextTick(() => {
                this.updatePosition();
            });
        },

        updatePosition() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;

            if (! trigger || ! panel) {
                return;
            }

            const triggerRect = trigger.getBoundingClientRect();
            const panelWidth = panel.offsetWidth || 160;
            const panelHeight = panel.offsetHeight || 120;
            const viewportPadding = 8;
            const offset = 4;
            const shouldOpenUp = (window.innerHeight - triggerRect.bottom) < (panelHeight + viewportPadding);

            let top = shouldOpenUp
                ? triggerRect.top - panelHeight - offset
                : triggerRect.bottom + offset;
            let left = triggerRect.right - panelWidth;

            top = Math.max(viewportPadding, Math.min(top, window.innerHeight - panelHeight - viewportPadding));
            left = Math.max(viewportPadding, Math.min(left, window.innerWidth - panelWidth - viewportPadding));

            this.menuStyles = `top: ${top}px; left: ${left}px;`;
        },

        close() {
            this.open = false;
            this.menuStyles = '';
        },
    }));

    Alpine.data('medicinePriceInput', () => ({
        formatInput(event) {
            event.target.value = formatMedicinePriceInput(event.target.value, event.inputType ?? '');
        },
    }));

    Alpine.data('purchaseInvoiceForm', (config = {}, supplierOptions = []) => {
        const medicineCatalog = Array.isArray(config.catalog) ? config.catalog : [];

        return ({
        invoice_number: config.invoice_number ?? '',
        invoice_date: config.invoice_date ?? '',
        supplier_id: config.supplier_id ?? '',
        payment_method: config.payment_method ?? 'credit',
        payment_kind: 'credit',
        tax_percentage: config.tax_percentage ?? '11',
        suppliers: Array.isArray(supplierOptions) ? supplierOptions : [],
        rows: [],
        searchTerm: '',
        baseVisibleMedicineLimit: 30,
        searchVisibleRowLimit: 50,
        nextFilledOrder: 1,
        nextDynamicRowId: 1,
        paymentModalOpen: false,

        init() {
            const initialItems = Array.isArray(config.items) ? config.items : [];

            this.rows = initialItems.map((item) => this.hydrateRow(item));
            this.nextDynamicRowId = this.rows.length + 1;
            this.payment_kind = String(this.payment_method ?? '') === 'credit' ? 'credit' : 'cash';
            this.syncCatalogRows();
            this.rows.forEach((row) => this.refreshRow(row, false));
            this.ensureCompanionRows();
            this.normalizePaymentState();
        },

        parseCheckboxValue(value, fallback = false) {
            if (value === null || typeof value === 'undefined' || String(value).trim() === '') {
                return fallback;
            }

            if (typeof value === 'boolean') {
                return value;
            }

            return ['1', 'true', 'on', 'yes'].includes(String(value).trim().toLowerCase());
        },

        hydrateRow(item) {
            return {
                key: item.key,
                medicine_id: item.medicine_id ?? '',
                medicine_code: item.medicine_code ?? '',
                medicine_name: item.medicine_name ?? '',
                medicine_label: item.medicine_label ?? '',
                composition: item.composition ?? '',
                purchase_unit: item.purchase_unit ?? '',
                unit_content: item.unit_content ?? 1,
                storage_location_id: item.storage_location_id ?? '',
                batch_number: item.batch_number ?? '',
                expiry_date: item.expiry_date ?? '',
                quantity: item.quantity ?? '',
                unit_price: item.unit_price ?? '',
                unit_price_display: this.formatMoneyInput(item.unit_price ?? ''),
                discount_percentage: item.discount_percentage ?? '',
                discount_amount: item.discount_amount ?? '',
                discount_amount_display: this.formatMoneyInput(item.discount_amount ?? ''),
                discount_mode: item.discount_mode === 'amount' ? 'amount' : 'percent',
                update_master_purchase_price: this.parseCheckboxValue(item.update_master_purchase_price, false),
                filled_order: null,
                line_total: 0,
                gross_total: 0,
                stock_quantity: 0,
                group_order: Number(item.group_order ?? 0),
                is_committed: item.is_committed === true,
            };
        },

        selectedSupplier() {
            const currentId = String(this.supplier_id ?? '');

            if (currentId === '') {
                return null;
            }

            return this.suppliers.find((supplier) => String(supplier.id ?? '') === currentId) ?? null;
        },

        setSearchTerm(value) {
            this.searchTerm = value;
            this.syncCatalogRows();
        },

        hasActiveSearch() {
            return String(this.searchTerm ?? '').trim() !== '';
        },

        trackRowUsage(row) {
            if (! this.rowIsUsed(row)) {
                row.filled_order = null;
                return;
            }

            if (row.filled_order === null) {
                row.filled_order = this.nextFilledOrder;
                this.nextFilledOrder += 1;
            }
        },

        sortRowsByFilled(force = false) {
            this.rows.forEach((row) => this.trackRowUsage(row));

            if (! force && this.hasActiveSearch()) {
                return;
            }

            const groupStates = new Map();

            this.rows.forEach((row) => {
                const medicineKey = String(row.medicine_id ?? '');
                const currentState = groupStates.get(medicineKey) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(row.group_order ?? 0),
                };

                if (this.rowIsUsed(row)) {
                    currentState.hasUsed = true;
                    currentState.firstFilledOrder = Math.min(currentState.firstFilledOrder, Number(row.filled_order ?? Number.MAX_SAFE_INTEGER));
                }

                currentState.groupOrder = Math.min(currentState.groupOrder, Number(row.group_order ?? 0));
                groupStates.set(medicineKey, currentState);
            });

            const rowBucket = (row, group) => {
                if (this.rowIsUsed(row)) {
                    return 0;
                }

                return 1;
            };

            this.rows = [...this.rows].sort((firstRow, secondRow) => {
                const firstGroup = groupStates.get(String(firstRow.medicine_id ?? '')) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(firstRow.group_order ?? 0),
                };
                const secondGroup = groupStates.get(String(secondRow.medicine_id ?? '')) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(secondRow.group_order ?? 0),
                };
                const firstBucket = rowBucket(firstRow, firstGroup);
                const secondBucket = rowBucket(secondRow, secondGroup);

                if (firstBucket !== secondBucket) {
                    return firstBucket - secondBucket;
                }

                if (firstBucket === 0) {
                    if (firstGroup.firstFilledOrder !== secondGroup.firstFilledOrder) {
                        return firstGroup.firstFilledOrder - secondGroup.firstFilledOrder;
                    }

                    return Number(firstRow.filled_order ?? 0) - Number(secondRow.filled_order ?? 0);
                }

                if (firstBucket === 1) {
                    return firstGroup.groupOrder - secondGroup.groupOrder;
                }

                return firstGroup.groupOrder - secondGroup.groupOrder;
            });
        },

        rowIsUsed(row) {
            return [
                row.batch_number,
                row.expiry_date,
                row.quantity,
                row.unit_price,
                row.discount_percentage,
                row.discount_amount,
            ].some((value) => value !== null && String(value).trim() !== '');
        },

        rowReadyForCompanion(row) {
            return String(row.medicine_id ?? '').trim() !== ''
                && String(row.batch_number ?? '').trim() !== ''
                && toNumber(row.quantity, 0) > 0;
        },

        medicineHasUsedRows(medicineId) {
            const normalizedId = String(medicineId ?? '');

            if (normalizedId === '') {
                return false;
            }

            return this.rows.some((row) => {
                return String(row.medicine_id ?? '') === normalizedId && this.rowIsUsed(row);
            });
        },

        isPrimaryPlaceholderRow(row) {
            if (this.rowIsUsed(row)) {
                return false;
            }

            const medicineRows = this.rowsForMedicine(row.medicine_id);
            const firstPlaceholder = medicineRows.find((medicineRow) => ! this.rowIsUsed(medicineRow));

            return firstPlaceholder?.key === row.key;
        },

        rowMatchesSearch(row) {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            if (query === '') {
                return true;
            }

            return [
                row.medicine_code,
                row.medicine_name,
                row.composition,
            ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
        },

        visibleRowCount() {
            return this.rows.filter((row) => this.rowMatchesSearch(row)).length;
        },

        displayRows() {
            return this.rows.filter((row) => this.rowMatchesSearch(row));
        },

        hasHiddenRows() {
            if (String(this.searchTerm ?? '').trim() !== '') {
                return this.filteredCatalogItems().length > this.searchVisibleRowLimit;
            }

            return medicineCatalog.length > this.baseVisibleMedicineLimit;
        },

        hiddenRowsCount() {
            if (String(this.searchTerm ?? '').trim() !== '') {
                return Math.max(this.filteredCatalogItems().length - this.searchVisibleRowLimit, 0);
            }

            return Math.max(medicineCatalog.length - this.baseVisibleMedicineLimit, 0);
        },

        uniqueMedicineIds() {
            return [...new Set(this.rows.map((row) => String(row.medicine_id ?? '')).filter((medicineId) => medicineId !== ''))];
        },

        rowsForMedicine(medicineId) {
            const normalizedId = String(medicineId ?? '');

            return this.rows.filter((row) => String(row.medicine_id ?? '') === normalizedId);
        },

        lastRowIndexForMedicine(medicineId) {
            const normalizedId = String(medicineId ?? '');
            let lastIndex = -1;

            this.rows.forEach((row, index) => {
                if (String(row.medicine_id ?? '') === normalizedId) {
                    lastIndex = index;
                }
            });

            return lastIndex;
        },

        buildCompanionRow(row) {
            return this.hydrateRow({
                key: `medicine-${row.medicine_id}-dup-${this.nextDynamicRowId++}`,
                group_order: row.group_order ?? 0,
                medicine_id: row.medicine_id,
                medicine_code: row.medicine_code,
                medicine_name: row.medicine_name,
                medicine_label: row.medicine_label,
                composition: row.composition,
                purchase_unit: row.purchase_unit,
                unit_content: row.unit_content,
                storage_location_id: row.storage_location_id,
                batch_number: '',
                expiry_date: '',
                quantity: '',
                unit_price: '',
                discount_percentage: '',
                discount_amount: '',
                discount_mode: 'percent',
                update_master_purchase_price: false,
            });
        },

        buildCatalogPlaceholderRow(item) {
            return this.hydrateRow({
                ...item,
                key: `medicine-${item.medicine_id}-base`,
            });
        },

        filteredCatalogItems() {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            if (query === '') {
                return medicineCatalog;
            }

            return medicineCatalog.filter((item) => {
                return [
                    item.medicine_code,
                    item.medicine_name,
                    item.composition,
                ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
            });
        },

        syncCatalogRows() {
            const hasQuery = String(this.searchTerm ?? '').trim() !== '';
            const targetItems = this.filteredCatalogItems().slice(0, hasQuery ? this.searchVisibleRowLimit : this.baseVisibleMedicineLimit);
            const targetIds = new Set(targetItems.map((item) => String(item.medicine_id ?? '')));
            const usedMedicineIds = new Set(
                this.rows
                    .filter((row) => this.rowIsUsed(row))
                    .map((row) => String(row.medicine_id ?? ''))
                    .filter((value) => value !== ''),
            );

            this.rows = this.rows.filter((row) => {
                const medicineId = String(row.medicine_id ?? '');

                if (this.rowIsUsed(row)) {
                    return true;
                }

                if (this.rowReadyForCompanion(row)) {
                    return true;
                }

                if (usedMedicineIds.has(medicineId)) {
                    return true;
                }

                return targetIds.has(medicineId);
            });

            targetItems.forEach((item) => {
                const medicineId = String(item.medicine_id ?? '');
                const hasAnyRow = this.rows.some((row) => String(row.medicine_id ?? '') === medicineId);

                if (! hasAnyRow) {
                    this.rows.push(this.buildCatalogPlaceholderRow(item));
                }
            });

            this.rows = [...this.rows].sort((firstRow, secondRow) => {
                const firstOrder = Number(firstRow.group_order ?? 0);
                const secondOrder = Number(secondRow.group_order ?? 0);

                if (firstOrder !== secondOrder) {
                    return firstOrder - secondOrder;
                }

                return String(firstRow.medicine_name ?? '').localeCompare(String(secondRow.medicine_name ?? ''), 'id');
            });
        },

        ensureCompanionRows() {
            this.uniqueMedicineIds().forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));
        },

        ensureCompanionRowsForMedicine(medicineId) {
            const medicineRows = this.rowsForMedicine(medicineId);

            if (medicineRows.length === 0) {
                return;
            }

            const completedRows = medicineRows.filter((row) => this.rowReadyForCompanion(row));
            const placeholderRows = medicineRows.filter((row) => ! this.rowReadyForCompanion(row));

            if (completedRows.length === 0) {
                const preservedPlaceholderKey = placeholderRows[0]?.key ?? null;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return ! this.rowReadyForCompanion(row) && row.key === preservedPlaceholderKey;
                });

                return;
            }

            if (placeholderRows.length === 0) {
                const newRow = this.buildCompanionRow(medicineRows[medicineRows.length - 1]);
                const insertIndex = this.lastRowIndexForMedicine(medicineId);

                this.rows.splice(insertIndex + 1, 0, newRow);

                return this.ensureCompanionRowsForMedicine(medicineId);
            }

            if (placeholderRows.length > 1) {
                const preservedPlaceholderKey = placeholderRows[0].key;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return this.rowReadyForCompanion(row) || row.key === preservedPlaceholderKey;
                });
            }
        },

        confirmCompanionRows() {
            this.rows.forEach((row) => {
                if (this.rowReadyForCompanion(row)) {
                    row.is_committed = true;
                    return;
                }

                row.is_committed = false;
            });

            this.uniqueMedicineIds().forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));
            this.syncCatalogRows();
            this.sortRowsByFilled(true);
        },

        openPaymentModal() {
            if (! this.canSubmit()) {
                return;
            }

            this.normalizePaymentState();
            this.paymentModalOpen = true;

            this.$nextTick(() => {
                this.$refs.confirmPurchasePaymentButton?.focus();
            });
        },

        closePaymentModal() {
            this.paymentModalOpen = false;
        },

        setPaymentKind(kind) {
            this.payment_kind = kind === 'credit' ? 'credit' : 'cash';

            if (this.payment_kind === 'credit') {
                this.payment_method = 'credit';
                return;
            }

            if (String(this.payment_method ?? '') === 'credit' || String(this.payment_method ?? '').trim() === '') {
                this.payment_method = 'cash';
            }
        },

        selectPaymentMethod(method) {
            this.payment_kind = 'cash';
            this.payment_method = method;
        },

        normalizePaymentState() {
            if (String(this.payment_method ?? '') === 'credit' || this.payment_kind === 'credit') {
                this.payment_kind = 'credit';
                this.payment_method = 'credit';
                return;
            }

            this.payment_kind = 'cash';

            if (! ['cash', 'transfer', 'qris', 'debit'].includes(String(this.payment_method ?? ''))) {
                this.payment_method = 'cash';
            }
        },

        isCreditPayment() {
            return this.payment_kind === 'credit';
        },

        paymentMethodLabel() {
            return {
                cash: 'Tunai',
                qris: 'QRIS',
                transfer: 'Transfer',
                debit: 'Debit',
                credit: 'Kredit',
            }[String(this.payment_method ?? '')] ?? 'Tunai';
        },

        handleRowMetaInput(row) {
            if (! this.rowReadyForCompanion(row)) {
                row.is_committed = false;
            }
        },

        handleBatchInput(row) {
            row.batch_number = String(row.batch_number ?? '').toUpperCase();
            this.handleRowMetaInput(row);
        },

        handleUnitContentInput(row) {
            const rawValue = String(row.unit_content ?? '').trim();

            if (rawValue === '') {
                this.refreshRow(row);
                return;
            }

            row.unit_content = String(roundCurrency(Math.max(toNumber(row.unit_content, 1), 1)));
            this.refreshRow(row);
        },

        rapikanRows() {
            this.confirmCompanionRows();
        },

        handleMoneyInput(row, field, event) {
            const value = this.parseMoneyInput(event.target.value);

            row[field] = value;
            row[`${field}_display`] = this.formatMoneyInput(value);
            event.target.value = row[`${field}_display`];

            if (field === 'discount_amount') {
                this.applyAmount(row);
                return;
            }

            this.refreshRow(row);
        },

        parseMoneyInput(value) {
            const normalized = String(value ?? '')
                .replace(/\./g, '')
                .replace(',', '.')
                .replace(/[^\d.]/g, '');
            const parsed = Number(normalized);

            if (normalized === '' || ! Number.isFinite(parsed)) {
                return '';
            }

            return String(Math.max(parsed, 0));
        },

        formatMoneyInput(value) {
            if (value === null || String(value).trim() === '') {
                return '';
            }

            return numberFormatter.format(roundCurrency(Math.max(toNumber(value, 0), 0)));
        },

        syncDiscountAmountDisplay(row) {
            if (row.discount_amount === null || String(row.discount_amount).trim() === '') {
                row.discount_amount_display = '';
                return;
            }

            row.discount_amount_display = integerFormatter.format(
                Math.max(roundWholeCurrency(row.discount_amount), 0),
            );
        },

        applyPercent(row) {
            const gross = this.rowGross(row);
            let percent = Math.max(toNumber(row.discount_percentage, 0), 0);

            if (percent > 100) {
                percent = 100;
                row.discount_percentage = '100';
            }

            row.discount_mode = 'percent';

            if (gross <= 0) {
                row.discount_amount = '';
                this.syncDiscountAmountDisplay(row);
                this.setDerivedValues(row, 0, 0);
                return;
            }

            const amount = roundWholeCurrency(gross * percent / 100);

            row.discount_amount = amount > 0 ? String(amount) : '';
            this.syncDiscountAmountDisplay(row);
            this.setDerivedValues(row, gross, amount);
        },

        applyAmount(row) {
            const gross = this.rowGross(row);
            let amount = Math.max(roundWholeCurrency(row.discount_amount), 0);

            row.discount_mode = 'amount';

            if (gross <= 0) {
                row.discount_percentage = '';
                this.syncDiscountAmountDisplay(row);
                this.setDerivedValues(row, 0, 0);
                return;
            }

            if (amount > gross) {
                amount = gross;
            }

            amount = roundWholeCurrency(amount);
            row.discount_amount = amount > 0 ? String(amount) : '';

            const percent = gross > 0 ? roundCurrency((amount / gross) * 100) : 0;

            row.discount_percentage = percent > 0 ? String(percent) : '';
            this.syncDiscountAmountDisplay(row);
            this.setDerivedValues(row, gross, amount);
        },

        refreshRow(row, shouldSort = true) {
            if (row.discount_mode === 'amount') {
                this.applyAmount(row);
                return;
            }

            this.applyPercent(row);
        },

        rowGross(row) {
            return roundCurrency(
                Math.max(toNumber(row.quantity, 0), 0) * Math.max(toNumber(row.unit_price, 0), 0),
            );
        },

        setDerivedValues(row, gross, discountAmount) {
            const safeGross = roundCurrency(gross);
            const safeDiscount = roundCurrency(Math.min(Math.max(discountAmount, 0), safeGross));
            const unitContent = String(row.unit_content ?? '').trim() === ''
                ? 0
                : Math.max(toNumber(row.unit_content, 1), 1);
            const quantity = Math.max(toNumber(row.quantity, 0), 0);

            row.gross_total = safeGross;
            row.line_total = roundCurrency(safeGross - safeDiscount);
            row.stock_quantity = roundCurrency(quantity * unitContent);
        },

        grossSubtotal() {
            return this.rows.reduce((total, row) => total + toNumber(row.gross_total, 0), 0);
        },

        totalDiscount() {
            return this.rows.reduce((total, row) => total + Math.max(toNumber(row.gross_total, 0) - toNumber(row.line_total, 0), 0), 0);
        },

        subtotalAfterDiscount() {
            return this.rows.reduce((total, row) => total + toNumber(row.line_total, 0), 0);
        },

        taxAmount() {
            const percentage = Math.min(Math.max(toNumber(this.tax_percentage, 0), 0), 100);

            return roundCurrency(this.subtotalAfterDiscount() * percentage / 100);
        },

        grandTotal() {
            return roundCurrency(this.subtotalAfterDiscount() + this.taxAmount());
        },

        totalStockQuantity() {
            return this.rows.reduce((total, row) => total + toNumber(row.stock_quantity, 0), 0);
        },

        activeRowCount() {
            return this.rows.filter((row) => this.rowIsUsed(row)).length;
        },

        canSubmit() {
            return this.activeRowCount() > 0
                && String(this.invoice_number ?? '').trim() !== ''
                && String(this.invoice_date ?? '').trim() !== ''
                && String(this.supplier_id ?? '').trim() !== '';
        },

        canConfirmPayment() {
            return this.canSubmit() && this.grandTotal() > 0;
        },

        submitInvoice() {
            if (! this.canConfirmPayment()) {
                return;
            }

            this.normalizePaymentState();
            this.closePaymentModal();
            if (typeof this.$refs.purchaseInvoiceForm?.requestSubmit === 'function') {
                this.$refs.purchaseInvoiceForm.requestSubmit();
                return;
            }

            this.$refs.purchaseInvoiceForm?.submit();
        },

        currency(value) {
            return currencyFormatter.format(roundCurrency(toNumber(value, 0)));
        },

        formatQuantity(value) {
            return numberFormatter.format(roundCurrency(toNumber(value, 0)));
        },
    });
    });

    Alpine.data('saleForm', (config = {}, customerOptions = []) => ({
        sale_number: config.sale_number ?? '',
        sale_date: config.sale_date ?? '',
        customer_id: config.customer_id ?? '',
        customerSearch: '',
        customerDropdownOpen: false,
        payment_method: config.payment_method ?? 'cash',
        payment_kind: config.payment_kind ?? 'cash',
        paid_amount: config.paid_amount ?? '',
        paid_amount_display: '',
        other_cost_amount: config.other_cost_amount ?? '',
        other_cost_amount_display: '',
        notes: config.notes ?? '',
        customers: Array.isArray(customerOptions) ? customerOptions : [],
        rows: [],
        searchTerm: '',
        nextFilledOrder: 1,
        nextDynamicRowId: 1,
        autoFillPaidAmount: false,
        paymentModalOpen: false,

        init() {
            const initialItems = Array.isArray(config.items) ? config.items : [];
            const hasHydratedPricing = initialItems.some((item) =>
                String(item?.markup_percentage ?? '').trim() !== ''
                || String(item?.unit_price ?? '').trim() !== ''
            );

            this.rows = initialItems.map((item) => this.hydrateRow(item));
            this.nextDynamicRowId = this.rows.length + 1;
            this.payment_kind = ['cash', 'social', 'credit'].includes(String(this.payment_kind ?? ''))
                ? String(this.payment_kind)
                : (String(this.payment_method ?? '') === 'credit' ? 'credit' : 'cash');
            this.autoFillPaidAmount = String(this.paid_amount ?? '').trim() === '';

            if (hasHydratedPricing) {
                this.rows.forEach((row) => {
                    this.syncBatchSelection(row);
                    this.syncRowPricing(row);
                    this.recalculateRow(row);
                });

                this.ensureCompanionRows();
            } else {
                this.applyCustomerPricing();
            }

            this.normalizePaymentState();
            this.syncOtherCostAmountDisplay();
            this.syncPaidAmount();
            this.syncCustomerSearch();
        },

        hydrateRow(item) {
            const batches = Array.isArray(item.batches) ? item.batches : [];
            const fallbackBatchId = item.stock_batch_id ?? (batches[0]?.id ?? '');

            return {
                key: item.key ?? '',
                medicine_id: item.medicine_id ?? '',
                medicine_code: item.medicine_code ?? '',
                medicine_name: item.medicine_name ?? '',
                principal_name: item.principal_name ?? '',
                composition: item.composition ?? '',
                small_unit: item.small_unit ?? 'unit',
                batches,
                stock_batch_id: String(fallbackBatchId ?? ''),
                base_unit_cost: item.base_unit_cost ?? '0',
                stock_quantity: item.stock_quantity ?? '0',
                quantity: item.quantity ?? '',
                markup_percentage: item.markup_percentage !== undefined && item.markup_percentage !== null
                    ? String(item.markup_percentage)
                    : '',
                unit_price: item.unit_price !== undefined && item.unit_price !== null
                    ? String(item.unit_price)
                    : '',
                line_total: 0,
                filled_order: null,
                group_order: Number(item.group_order ?? 0),
                is_committed: item.is_committed === true,
            };
        },

        selectedCustomer() {
            const currentId = String(this.customer_id ?? '');

            if (currentId === '') {
                return null;
            }

            return this.customers.find((customer) => String(customer.id ?? '') === currentId) ?? null;
        },

        customerDisplayLabel(customer) {
            if (! customer) {
                return '';
            }

            const groupName = String(customer.group_name ?? '').trim();

            return groupName !== '' && groupName !== 'Tanpa golongan'
                ? `${customer.name} - ${groupName}`
                : String(customer.name ?? '');
        },

        syncCustomerSearch() {
            this.customerSearch = this.customerDisplayLabel(this.selectedCustomer());
        },

        filteredCustomers() {
            const query = String(this.customerSearch ?? '').trim().toLocaleLowerCase('id-ID');

            const filtered = query === ''
                ? this.customers
                : this.customers.filter((customer) => [
                    customer.name,
                    customer.phone,
                    customer.group_name,
                ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query)));

            return filtered.slice(0, 12);
        },

        openCustomerDropdown() {
            if (this.customers.length === 0) {
                return;
            }

            this.customerDropdownOpen = true;
        },

        closeCustomerDropdown() {
            this.customerDropdownOpen = false;
        },

        handleCustomerSearchInput(value) {
            this.customerSearch = value;
            this.customerDropdownOpen = true;

            if (String(this.customer_id ?? '').trim() === '') {
                return;
            }

            const selectedLabel = this.customerDisplayLabel(this.selectedCustomer());

            if (String(value ?? '').trim() === selectedLabel.trim()) {
                return;
            }

            this.customer_id = '';
            this.applyCustomerPricing();
        },

        selectCustomer(customer) {
            this.customer_id = String(customer?.id ?? '');
            this.customerSearch = this.customerDisplayLabel(customer);
            this.customerDropdownOpen = false;
            this.applyCustomerPricing();
        },

        selectFirstFilteredCustomer() {
            const [firstCustomer] = this.filteredCustomers();

            if (! firstCustomer) {
                return;
            }

            this.selectCustomer(firstCustomer);
        },

        currentMarkup() {
            return Math.max(toNumber(this.selectedCustomer()?.markup_percentage ?? 0, 0), 0);
        },

        selectedGroupName() {
            return this.selectedCustomer()?.group_name ?? 'Tanpa golongan';
        },

        selectedCustomerPhone() {
            return this.selectedCustomer()?.phone ?? '';
        },

        syncRowPricing(row, fallbackMarkup = this.currentMarkup()) {
            const baseUnitCost = Math.max(toNumber(row.base_unit_cost, 0), 0);
            const markupPercentage = roundCurrency(Math.max(toNumber(row.markup_percentage, fallbackMarkup), 0));
            const unitPrice = roundCurrency(baseUnitCost + (baseUnitCost * markupPercentage / 100));

            row.markup_percentage = String(markupPercentage);
            row.unit_price = String(unitPrice);
        },

        applyCustomerPricing() {
            const markupPercentage = this.currentMarkup();

            this.rows.forEach((row) => {
                this.syncBatchSelection(row);
                row.markup_percentage = String(markupPercentage);
                this.syncRowPricing(row, markupPercentage);
                this.recalculateRow(row);
            });

            this.ensureCompanionRows();
            this.syncPaidAmount();
        },

        selectedBatch(row) {
            const currentId = String(row.stock_batch_id ?? '');

            return row.batches.find((batch) => String(batch.id ?? '') === currentId) ?? null;
        },

        canKeepSelectedBatch(medicineId, row) {
            const currentBatchId = String(row?.stock_batch_id ?? '');

            if (currentBatchId === '') {
                return false;
            }

            const availableInRow = Array.isArray(row?.batches)
                && row.batches.some((batch) => String(batch.id ?? '') === currentBatchId);

            if (! availableInRow) {
                return false;
            }

            return ! this.usedBatchIdsForMedicine(medicineId, row?.key ?? null).includes(currentBatchId);
        },

        syncBatchSelection(row) {
            if (! Array.isArray(row.batches) || row.batches.length === 0) {
                row.stock_batch_id = '';
                row.stock_quantity = '0';
                return;
            }

            const selectedBatch = this.selectedBatch(row) ?? row.batches[0];

            row.stock_batch_id = String(selectedBatch.id ?? '');
            row.stock_quantity = String(selectedBatch.stock_quantity ?? '0');
        },

        handleBatchChange(row) {
            this.syncBatchSelection(row);
            this.syncRowPricing(row);
            this.recalculateRow(row);

            if (! this.rowReadyForCompanion(row)) {
                row.is_committed = false;
                this.ensureCompanionRowsForMedicine(row.medicine_id);
            }

            this.syncPaidAmount();
        },

        handleMarkupInput(row) {
            this.syncRowPricing(row, 0);
            this.refreshRow(row);
        },

        setSearchTerm(value) {
            this.searchTerm = value;
        },

        hasActiveSearch() {
            return String(this.searchTerm ?? '').trim() !== '';
        },

        trackRowUsage(row) {
            if (! this.rowIsUsed(row)) {
                row.filled_order = null;
                return;
            }

            if (row.filled_order === null) {
                row.filled_order = this.nextFilledOrder;
                this.nextFilledOrder += 1;
            }
        },

        sortRowsByFilled(force = false) {
            this.rows.forEach((row) => this.trackRowUsage(row));

            if (! force && this.hasActiveSearch()) {
                return;
            }

            const groupStates = new Map();

            this.rows.forEach((row) => {
                const medicineKey = String(row.medicine_id ?? '');
                const currentState = groupStates.get(medicineKey) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(row.group_order ?? 0),
                };

                if (this.rowIsUsed(row)) {
                    currentState.hasUsed = true;
                    currentState.firstFilledOrder = Math.min(currentState.firstFilledOrder, Number(row.filled_order ?? Number.MAX_SAFE_INTEGER));
                }

                currentState.groupOrder = Math.min(currentState.groupOrder, Number(row.group_order ?? 0));
                groupStates.set(medicineKey, currentState);
            });

            const rowBucket = (row, group) => {
                if (this.rowIsUsed(row)) {
                    return 0;
                }

                return 1;
            };

            this.rows = [...this.rows].sort((firstRow, secondRow) => {
                const firstGroup = groupStates.get(String(firstRow.medicine_id ?? '')) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(firstRow.group_order ?? 0),
                };
                const secondGroup = groupStates.get(String(secondRow.medicine_id ?? '')) ?? {
                    hasUsed: false,
                    firstFilledOrder: Number.MAX_SAFE_INTEGER,
                    groupOrder: Number(secondRow.group_order ?? 0),
                };
                const firstBucket = rowBucket(firstRow, firstGroup);
                const secondBucket = rowBucket(secondRow, secondGroup);

                if (firstBucket !== secondBucket) {
                    return firstBucket - secondBucket;
                }

                if (firstBucket === 0) {
                    if (firstGroup.firstFilledOrder !== secondGroup.firstFilledOrder) {
                        return firstGroup.firstFilledOrder - secondGroup.firstFilledOrder;
                    }

                    return Number(firstRow.filled_order ?? 0) - Number(secondRow.filled_order ?? 0);
                }

                if (firstBucket === 1) {
                    return firstGroup.groupOrder - secondGroup.groupOrder;
                }

                return firstGroup.groupOrder - secondGroup.groupOrder;
            });
        },

        rowIsUsed(row) {
            return String(row.quantity ?? '').trim() !== '' && toNumber(row.quantity, 0) > 0;
        },

        rowReadyForCompanion(row) {
            return String(row.medicine_id ?? '').trim() !== ''
                && String(row.stock_batch_id ?? '').trim() !== ''
                && toNumber(row.quantity, 0) > 0;
        },

        medicineHasUsedRows(medicineId) {
            const normalizedId = String(medicineId ?? '');

            if (normalizedId === '') {
                return false;
            }

            return this.rows.some((row) => {
                return String(row.medicine_id ?? '') === normalizedId && this.rowIsUsed(row);
            });
        },

        isPrimaryPlaceholderRow(row) {
            if (this.rowIsUsed(row)) {
                return false;
            }

            const firstPlaceholder = this.rows.find((candidate) => {
                return String(candidate.medicine_id ?? '') === String(row.medicine_id ?? '')
                    && ! this.rowIsUsed(candidate);
            });

            return firstPlaceholder?.key === row.key;
        },

        rowMatchesSearch(row) {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            if (query === '') {
                return this.rowIsUsed(row) || this.isPrimaryPlaceholderRow(row);
            }

            return [
                row.medicine_code,
                row.medicine_name,
                row.principal_name,
                row.composition,
            ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
        },

        visibleRowCount() {
            return this.rows.filter((row) => this.rowMatchesSearch(row)).length;
        },

        batchLabel(row) {
            return this.selectedBatch(row)?.batch_number ?? '-';
        },

        batchExpiryLabel(row) {
            return this.selectedBatch(row)?.expiry_label ?? '-';
        },

        recalculateRow(row) {
            this.syncBatchSelection(row);
            const maximum = Math.max(toNumber(row.stock_quantity, 0), 0);
            const rawQuantity = String(row.quantity ?? '').trim();

            if (rawQuantity === '') {
                row.quantity = '';
                row.line_total = 0;
                return;
            }

            const safeQuantity = Math.min(Math.max(toNumber(row.quantity, 0), 0), maximum);

            row.quantity = safeQuantity > 0 ? String(roundCurrency(safeQuantity)) : '';
            row.line_total = roundCurrency(safeQuantity * Math.max(toNumber(row.unit_price, 0), 0));
        },

        refreshRow(row) {
            this.recalculateRow(row);

            if (! this.rowReadyForCompanion(row)) {
                row.is_committed = false;
                this.ensureCompanionRowsForMedicine(row.medicine_id);
            }

            this.syncPaidAmount();
        },

        rapikanRows() {
            this.confirmCompanionRows();
        },

        openPaymentModal() {
            if (! this.canSubmit()) {
                return;
            }

            this.normalizePaymentState();
            this.paymentModalOpen = true;

            this.$nextTick(() => {
                if (this.usesCashChange() || this.isSocialPayment()) {
                    this.$refs.paymentCashAmountInput?.focus();
                    return;
                }

                this.$refs.confirmPaymentButton?.focus();
            });
        },

        closePaymentModal() {
            this.paymentModalOpen = false;
        },

        setPaymentKind(kind) {
            this.payment_kind = ['cash', 'social', 'credit'].includes(kind) ? kind : 'cash';

            if (this.payment_kind === 'credit') {
                this.payment_method = 'credit';
                this.syncPaidAmount();
                return;
            }

            if (this.payment_kind === 'social') {
                if (String(this.payment_method ?? '') === 'credit' || String(this.payment_method ?? '').trim() === '') {
                    this.payment_method = 'cash';
                }

                if (this.autoFillPaidAmount || String(this.paid_amount ?? '').trim() === String(this.grandTotal())) {
                    this.autoFillPaidAmount = false;
                    this.paid_amount = '';
                }

                this.syncPaidAmount();
                return;
            }

            if (String(this.payment_method ?? '') === 'credit' || String(this.payment_method ?? '').trim() === '') {
                this.payment_method = 'cash';
            }

            this.syncPaidAmount();
        },

        selectPaymentMethod(method) {
            if (! this.isSocialPayment()) {
                this.payment_kind = 'cash';
            }

            this.payment_method = method;
            this.syncPaidAmount();
        },

        normalizePaymentState() {
            if (String(this.payment_method ?? '') === 'credit' || this.payment_kind === 'credit') {
                this.payment_kind = 'credit';
                this.payment_method = 'credit';
                this.paid_amount = '0';
                return;
            }

            if (this.payment_kind !== 'social') {
                this.payment_kind = 'cash';
            }

            if (! ['cash', 'transfer', 'qris', 'debit'].includes(String(this.payment_method ?? ''))) {
                this.payment_method = 'cash';
            }
        },

        isCreditPayment() {
            return this.payment_kind === 'credit';
        },

        isSocialPayment() {
            return this.payment_kind === 'social';
        },

        usesCashChange() {
            return ! this.isCreditPayment() && String(this.payment_method ?? '') === 'cash';
        },

        effectivePaidAmount() {
            if (this.isCreditPayment()) {
                return 0;
            }

            if (! this.isSocialPayment() && String(this.payment_method ?? '') !== 'cash') {
                return this.grandTotal();
            }

            const rawValue = String(this.paid_amount ?? '').trim();

            if (rawValue === '') {
                return this.isSocialPayment() ? 0 : this.grandTotal();
            }

            return roundCurrency(Math.max(toNumber(this.paid_amount, 0), 0));
        },

        paymentMethodLabel() {
            return {
                cash: 'Tunai',
                qris: 'QRIS',
                transfer: 'Transfer',
                debit: 'Debit',
                credit: 'Kredit',
            }[String(this.payment_method ?? '')] ?? 'Tunai';
        },

        paymentShortfall() {
            if (! this.isSocialPayment() && ! this.usesCashChange()) {
                return 0;
            }

            return roundCurrency(Math.max(this.grandTotal() - this.effectivePaidAmount(), 0));
        },

        uniqueMedicineIds() {
            return [...new Set(this.rows.map((row) => String(row.medicine_id ?? '')).filter((medicineId) => medicineId !== ''))];
        },

        rowsForMedicine(medicineId) {
            const normalizedId = String(medicineId ?? '');

            return this.rows.filter((row) => String(row.medicine_id ?? '') === normalizedId);
        },

        lastRowIndexForMedicine(medicineId) {
            const normalizedId = String(medicineId ?? '');
            let lastIndex = -1;

            this.rows.forEach((row, index) => {
                if (String(row.medicine_id ?? '') === normalizedId) {
                    lastIndex = index;
                }
            });

            return lastIndex;
        },

        usedBatchIdsForMedicine(medicineId, excludedRowKey = null) {
            return this.rowsForMedicine(medicineId)
                .filter((row) => row.key !== excludedRowKey && this.rowIsUsed(row))
                .map((row) => String(row.stock_batch_id ?? ''))
                .filter((batchId) => batchId !== '');
        },

        preferredBatchForMedicine(medicineId, excludedRowKey = null) {
            const medicineRows = this.rowsForMedicine(medicineId);
            const templateRow = medicineRows.find((row) => Array.isArray(row.batches) && row.batches.length > 0);

            if (! templateRow) {
                return null;
            }

            const usedBatchIds = this.usedBatchIdsForMedicine(medicineId, excludedRowKey);

            return templateRow.batches.find((batch) => ! usedBatchIds.includes(String(batch.id ?? '')))
                ?? templateRow.batches[0]
                ?? null;
        },

        buildCompanionRow(row) {
            const preferredBatch = this.preferredBatchForMedicine(row.medicine_id);
            const markupPercentage = this.currentMarkup();
            const hydratedRow = this.hydrateRow({
                key: `sale-item-${row.medicine_id}-dup-${this.nextDynamicRowId++}`,
                group_order: row.group_order ?? 0,
                medicine_id: row.medicine_id,
                medicine_code: row.medicine_code,
                medicine_name: row.medicine_name,
                principal_name: row.principal_name,
                composition: row.composition,
                small_unit: row.small_unit,
                batches: row.batches,
                stock_batch_id: String(preferredBatch?.id ?? row.stock_batch_id ?? ''),
                base_unit_cost: String(row.base_unit_cost ?? '0'),
                stock_quantity: String(preferredBatch?.stock_quantity ?? row.stock_quantity ?? '0'),
                quantity: '',
            });

            hydratedRow.markup_percentage = String(markupPercentage);
            this.syncRowPricing(hydratedRow, markupPercentage);

            return hydratedRow;
        },

        ensureCompanionRows() {
            this.uniqueMedicineIds().forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));
        },

        ensureCompanionRowsForMedicine(medicineId) {
            const medicineRows = this.rowsForMedicine(medicineId);

            if (medicineRows.length === 0) {
                return;
            }

            const completedRows = medicineRows.filter((row) => this.rowReadyForCompanion(row));
            const emptyRows = medicineRows.filter((row) => ! this.rowReadyForCompanion(row));

            if (completedRows.length === 0) {
                const preservedEmptyKey = emptyRows[0]?.key ?? null;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return ! this.rowReadyForCompanion(row) && row.key === preservedEmptyKey;
                });

                const placeholderRow = this.rowsForMedicine(medicineId).find((row) => ! this.rowReadyForCompanion(row));
                const preferredBatch = this.preferredBatchForMedicine(medicineId, placeholderRow?.key ?? null);

                if (placeholderRow && preferredBatch) {
                    const markupPercentage = this.currentMarkup();

                    placeholderRow.stock_batch_id = String(preferredBatch.id ?? '');
                    placeholderRow.stock_quantity = String(preferredBatch.stock_quantity ?? '0');
                    placeholderRow.markup_percentage = String(markupPercentage);
                    this.syncRowPricing(placeholderRow, markupPercentage);
                    placeholderRow.line_total = 0;
                }

                return;
            }

            if (emptyRows.length === 0) {
                const newRow = this.buildCompanionRow(medicineRows[medicineRows.length - 1]);
                const insertIndex = this.lastRowIndexForMedicine(medicineId);

                this.rows.splice(insertIndex + 1, 0, newRow);

                return this.ensureCompanionRowsForMedicine(medicineId);
            }

            if (emptyRows.length > 1) {
                const preservedEmptyKey = emptyRows[0].key;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return this.rowReadyForCompanion(row) || row.key === preservedEmptyKey;
                });
            }

            const placeholderRow = this.rowsForMedicine(medicineId).find((row) => ! this.rowIsUsed(row));
            const preferredBatch = this.preferredBatchForMedicine(medicineId, placeholderRow?.key ?? null);

            if (placeholderRow) {
                const markupPercentage = this.currentMarkup();
                const targetBatch = this.canKeepSelectedBatch(medicineId, placeholderRow)
                    ? this.selectedBatch(placeholderRow)
                    : preferredBatch;

                if (! targetBatch) {
                    return;
                }

                placeholderRow.stock_batch_id = String(targetBatch.id ?? '');
                placeholderRow.stock_quantity = String(targetBatch.stock_quantity ?? '0');
                placeholderRow.markup_percentage = String(markupPercentage);
                this.syncRowPricing(placeholderRow, markupPercentage);
                placeholderRow.line_total = 0;
            }
        },

        confirmCompanionRows() {
            this.rows.forEach((row) => {
                if (this.rowReadyForCompanion(row)) {
                    row.is_committed = true;
                    return;
                }

                row.is_committed = false;
            });

            this.uniqueMedicineIds().forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));
            this.sortRowsByFilled(true);
        },

        sanitizeMoneyValue(value) {
            const normalized = Math.max(toNumber(value, 0), 0);

            return String(roundCurrency(normalized));
        },

        parseMoneyInput(value) {
            const normalized = String(value ?? '')
                .replace(/\./g, '')
                .replace(',', '.')
                .replace(/[^\d.]/g, '');
            const parsed = Number(normalized);

            if (normalized === '' || ! Number.isFinite(parsed)) {
                return '';
            }

            return String(Math.max(parsed, 0));
        },

        formatMoneyInput(value) {
            if (value === null || String(value).trim() === '') {
                return '';
            }

            return numberFormatter.format(roundCurrency(Math.max(toNumber(value, 0), 0)));
        },

        syncPaidAmountDisplay() {
            this.paid_amount_display = (this.usesCashChange() || this.isSocialPayment())
                ? this.formatMoneyInput(this.paid_amount)
                : '';
        },

        syncOtherCostAmountDisplay() {
            this.other_cost_amount_display = this.formatMoneyInput(this.other_cost_amount);
        },

        handleOtherCostAmountInput(event) {
            const parsedValue = this.parseMoneyInput(event?.target?.value ?? this.other_cost_amount_display);
            const rawValue = String(parsedValue ?? '').trim();

            if (rawValue === '') {
                this.other_cost_amount = '';
                this.syncOtherCostAmountDisplay();
                this.syncPaidAmount();

                if (event?.target) {
                    event.target.value = this.other_cost_amount_display;
                }

                return;
            }

            this.other_cost_amount = this.sanitizeMoneyValue(parsedValue);
            this.syncOtherCostAmountDisplay();
            this.syncPaidAmount();

            if (event?.target) {
                event.target.value = this.other_cost_amount_display;
            }
        },

        handlePaidAmountInput(event) {
            if (! this.usesCashChange() && ! this.isSocialPayment()) {
                this.syncPaidAmount();
                return;
            }

            const parsedValue = this.parseMoneyInput(event?.target?.value ?? this.paid_amount_display);
            const rawValue = String(parsedValue ?? '').trim();

            if (rawValue === '') {
                this.autoFillPaidAmount = true;
                this.syncPaidAmount();

                if (event?.target) {
                    event.target.value = this.paid_amount_display;
                }

                return;
            }

            this.autoFillPaidAmount = false;
            this.paid_amount = this.sanitizeMoneyValue(parsedValue);
            this.syncPaidAmountDisplay();

            if (event?.target) {
                event.target.value = this.paid_amount_display;
            }
        },

        syncPaidAmount() {
            if (this.isCreditPayment()) {
                this.paid_amount = '0';
                this.syncPaidAmountDisplay();
                return;
            }

            if (this.isSocialPayment()) {
                if (String(this.paid_amount ?? '').trim() !== '') {
                    const clampedAmount = Math.min(Math.max(toNumber(this.paid_amount, 0), 0), this.grandTotal());
                    this.paid_amount = clampedAmount > 0 ? String(roundCurrency(clampedAmount)) : '';
                }

                this.syncPaidAmountDisplay();
                return;
            }

            if (String(this.payment_method ?? '') !== 'cash') {
                this.paid_amount = String(this.grandTotal());
                this.syncPaidAmountDisplay();
                return;
            }

            if (! this.autoFillPaidAmount) {
                this.syncPaidAmountDisplay();
                return;
            }

            this.paid_amount = String(this.grandTotal());
            this.syncPaidAmountDisplay();
        },

        activeRowCount() {
            return this.rows.filter((row) => this.rowIsUsed(row)).length;
        },

        effectiveOtherCostAmount() {
            const rawValue = String(this.other_cost_amount ?? '').trim();

            if (rawValue === '') {
                return 0;
            }

            return roundCurrency(Math.max(toNumber(this.other_cost_amount, 0), 0));
        },

        grandTotal() {
            return roundCurrency(
                this.rows.reduce((total, row) => total + toNumber(row.line_total, 0), 0)
                + this.effectiveOtherCostAmount()
            );
        },

        changeAmount() {
            if (! this.usesCashChange() || this.isSocialPayment()) {
                return 0;
            }

            return roundCurrency(Math.max(this.effectivePaidAmount() - this.grandTotal(), 0));
        },

        canSubmit() {
            return this.activeRowCount() > 0 && String(this.customer_id ?? '').trim() !== '';
        },

        canConfirmPayment() {
            if (! this.canSubmit()) {
                return false;
            }

            if (this.isCreditPayment()) {
                return this.grandTotal() > 0;
            }

            if (this.isSocialPayment()) {
                return this.effectivePaidAmount() > 0.001
                    && this.effectivePaidAmount() <= this.grandTotal() + 0.001
                    && this.paymentShortfall() > 0.001;
            }

            if (String(this.payment_method ?? '') === 'cash') {
                return this.effectivePaidAmount() + 0.001 >= this.grandTotal();
            }

            return this.grandTotal() > 0;
        },

        submitSale() {
            if (! this.canConfirmPayment()) {
                return;
            }

            this.normalizePaymentState();
            this.closePaymentModal();
            if (typeof this.$refs.saleForm?.requestSubmit === 'function') {
                this.$refs.saleForm.requestSubmit();
                return;
            }

            this.$refs.saleForm?.submit();
        },

        currency(value) {
            return currencyFormatter.format(roundCurrency(toNumber(value, 0)));
        },

        formatQuantity(value) {
            return numberFormatter.format(roundCurrency(toNumber(value, 0)));
        },
    }));

    Alpine.data('purchaseReturnInvoicePicker', (config = {}) => ({
        options: Array.isArray(config.options) ? config.options : [],
        selectedId: config.selectedId ? String(config.selectedId) : '',
        query: config.selectedLabel ?? '',
        isOpen: false,
        highlightedIndex: 0,

        filteredOptions() {
            const query = String(this.query ?? '').trim().toLocaleLowerCase('id-ID');
            const filtered = this.options.filter((option) => {
                if (query === '') {
                    return true;
                }

                return [
                    option.invoice_number,
                    option.supplier_name,
                    option.label,
                    option.invoice_date,
                ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
            });

            return filtered.slice(0, 8);
        },

        open() {
            this.isOpen = true;
            this.highlightedIndex = 0;
        },

        close() {
            this.isOpen = false;
            this.highlightedIndex = 0;
        },

        handleInput() {
            this.selectedId = '';
            this.isOpen = true;
            this.highlightedIndex = 0;
        },

        clearSelection() {
            this.query = '';
            this.selectedId = '';
            this.isOpen = true;
            this.highlightedIndex = 0;

            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },

        highlightNext() {
            if (! this.isOpen) {
                this.open();
                return;
            }

            const options = this.filteredOptions();

            if (options.length === 0) {
                return;
            }

            this.highlightedIndex = (this.highlightedIndex + 1) % options.length;
        },

        highlightPrevious() {
            if (! this.isOpen) {
                this.open();
                return;
            }

            const options = this.filteredOptions();

            if (options.length === 0) {
                return;
            }

            this.highlightedIndex = (this.highlightedIndex - 1 + options.length) % options.length;
        },

        confirmHighlighted() {
            const options = this.filteredOptions();

            if (options.length === 0) {
                return;
            }

            const option = options[Math.min(this.highlightedIndex, options.length - 1)];

            if (option) {
                this.selectOption(option);
            }
        },

        selectOption(option) {
            this.selectedId = String(option.id ?? '');
            this.query = option.label ?? '';
            this.close();

            this.$nextTick(() => {
                this.$root?.requestSubmit?.();
            });
        },
    }));

    Alpine.data('purchaseReturnForm', (config = {}) => ({
        return_number: config.return_number ?? '',
        return_date: config.return_date ?? '',
        tax_percentage: config.tax_percentage ?? '0',
        rows: [],
        searchTerm: '',

        init() {
            const initialRows = Array.isArray(config.rows) ? config.rows : [];

            this.rows = initialRows.map((item) => this.hydrateRow(item));
            this.rows.forEach((row) => this.refreshRow(row));
        },

        hydrateRow(item) {
            return {
                key: item.key ?? '',
                purchase_invoice_item_id: item.purchase_invoice_item_id ?? '',
                purchase_return_item_id: item.purchase_return_item_id ?? '',
                medicine_code: item.medicine_code ?? '',
                medicine_name: item.medicine_name ?? '',
                principal_name: item.principal_name ?? '',
                small_unit: item.small_unit ?? 'unit',
                batch_number: item.batch_number ?? '',
                expiry_date: item.expiry_date ?? '',
                expiry_label: item.expiry_label ?? '-',
                returned_quantity: item.returned_quantity ?? '0',
                returned_quantity_label: item.returned_quantity_label ?? '0',
                available_quantity: item.available_quantity ?? '0',
                available_quantity_label: item.available_quantity_label ?? '0',
                unit_price: item.unit_price ?? '0',
                unit_price_display: item.unit_price_display ?? item.unit_price ?? '0',
                tax_unit_amount: item.tax_unit_amount ?? '0',
                quantity: item.quantity ?? '',
                reason: item.reason ?? '',
                line_total: 0,
                tax_total: 0,
                landed_total: 0,
            };
        },

        rowMatchesSearch(row) {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            if (query === '') {
                return true;
            }

            return [
                row.medicine_code,
                row.medicine_name,
                row.principal_name,
                row.batch_number,
            ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
        },

        visibleRowCount() {
            return this.rows.filter((row) => this.rowMatchesSearch(row)).length;
        },

        rowIsUsed(row) {
            return String(row.quantity ?? '').trim() !== '' && toNumber(row.quantity, 0) > 0;
        },

        clampQuantity(row) {
            const rawValue = String(row.quantity ?? '').trim();

            if (rawValue === '') {
                row.quantity = '';
                this.refreshRow(row);
                return;
            }

            const maximum = Math.max(toNumber(row.available_quantity, 0), 0);
            const normalized = Math.min(Math.max(toNumber(row.quantity, 0), 0), maximum);

            row.quantity = normalized > 0 ? String(roundCurrency(normalized)) : '';
            this.refreshRow(row);
        },

        refreshRow(row) {
            const quantity = Math.max(toNumber(row.quantity, 0), 0);
            const maximum = Math.max(toNumber(row.available_quantity, 0), 0);
            const safeQuantity = Math.min(quantity, maximum);
            const unitPrice = Math.max(toNumber(row.unit_price, 0), 0);
            const taxUnitAmount = Math.max(toNumber(row.tax_unit_amount, 0), 0);

            row.line_total = roundCurrency(safeQuantity * unitPrice);
            row.tax_total = roundCurrency(safeQuantity * taxUnitAmount);
            row.landed_total = roundCurrency(row.line_total + row.tax_total);
        },

        activeRowCount() {
            return this.rows.filter((row) => this.rowIsUsed(row)).length;
        },

        subtotal() {
            return this.rows.reduce((total, row) => total + toNumber(row.line_total, 0), 0);
        },

        taxAmount() {
            return this.rows.reduce((total, row) => total + toNumber(row.tax_total, 0), 0);
        },

        grandTotal() {
            return this.rows.reduce((total, row) => total + toNumber(row.landed_total, 0), 0);
        },

        currency(value) {
            return currencyFormatter.format(roundCurrency(toNumber(value, 0)));
        },

        formatQuantity(value) {
            return numberFormatter.format(roundCurrency(toNumber(value, 0)));
        },
    }));

    Alpine.data('saleReturnForm', (config = {}) => ({
        return_number: config.return_number ?? '',
        return_date: config.return_date ?? '',
        rows: [],
        searchTerm: '',

        init() {
            const initialRows = Array.isArray(config.rows) ? config.rows : [];

            this.rows = initialRows.map((item) => this.hydrateRow(item));
            this.rows.forEach((row) => this.refreshRow(row));
        },

        hydrateRow(item) {
            return {
                key: item.key ?? '',
                sale_item_id: item.sale_item_id ?? '',
                medicine_code: item.medicine_code ?? '',
                medicine_name: item.medicine_name ?? '',
                principal_name: item.principal_name ?? '',
                small_unit: item.small_unit ?? 'unit',
                batch_number: item.batch_number ?? '',
                expiry_date: item.expiry_date ?? '',
                expiry_label: item.expiry_label ?? '-',
                sold_quantity: item.sold_quantity ?? '0',
                sold_quantity_label: item.sold_quantity_label ?? '0',
                available_quantity: item.available_quantity ?? '0',
                available_quantity_label: item.available_quantity_label ?? '0',
                current_stock: item.current_stock ?? '0',
                current_stock_label: item.current_stock_label ?? '0',
                unit_price: item.unit_price ?? '0',
                quantity: item.quantity ?? '',
                reason: item.reason ?? '',
                line_total: 0,
            };
        },

        rowMatchesSearch(row) {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            if (query === '') {
                return true;
            }

            return [
                row.medicine_code,
                row.medicine_name,
                row.principal_name,
                row.batch_number,
            ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
        },

        visibleRowCount() {
            return this.rows.filter((row) => this.rowMatchesSearch(row)).length;
        },

        rowIsUsed(row) {
            return String(row.quantity ?? '').trim() !== '' && toNumber(row.quantity, 0) > 0;
        },

        clampQuantity(row) {
            const rawValue = String(row.quantity ?? '').trim();

            if (rawValue === '') {
                row.quantity = '';
                this.refreshRow(row);
                return;
            }

            const maximum = Math.max(toNumber(row.available_quantity, 0), 0);
            const normalized = Math.min(Math.max(toNumber(row.quantity, 0), 0), maximum);

            row.quantity = normalized > 0 ? String(roundCurrency(normalized)) : '';
            this.refreshRow(row);
        },

        refreshRow(row) {
            const quantity = Math.max(toNumber(row.quantity, 0), 0);
            const maximum = Math.max(toNumber(row.available_quantity, 0), 0);
            const safeQuantity = Math.min(quantity, maximum);
            const unitPrice = Math.max(toNumber(row.unit_price, 0), 0);

            row.line_total = roundCurrency(safeQuantity * unitPrice);
        },

        activeRowCount() {
            return this.rows.filter((row) => this.rowIsUsed(row)).length;
        },

        grandTotal() {
            return this.rows.reduce((total, row) => total + toNumber(row.line_total, 0), 0);
        },

        currency(value) {
            return currencyFormatter.format(roundCurrency(toNumber(value, 0)));
        },

        formatQuantity(value) {
            return numberFormatter.format(roundCurrency(toNumber(value, 0)));
        },
    }));

    Alpine.data('openingStockSetupForm', (config = {}) => ({
        locationOptions: Array.isArray(config.locationOptions) ? config.locationOptions : [],
        selectedLocationId: String(config.initialLocationId ?? ''),
        rows: [],
        nextDynamicRowId: 1,
        searchTerm: '',

        init() {
            const initialRows = Array.isArray(config.initialRows) ? config.initialRows : [];

            this.rows = initialRows.length > 0
                ? initialRows.map((row) => this.hydrateRow(row))
                : [];

            this.nextDynamicRowId = this.rows.length + 1;
            this.syncSelectedLocation();
        },

        hydrateRow(row = {}) {
            return {
                key: row.key ?? `opening-row-${this.nextDynamicRowId++}`,
                medicine_id: String(row.medicine_id ?? ''),
                medicine_code: row.medicine_code ?? '',
                medicine_name: row.medicine_name ?? '',
                small_unit: row.small_unit ?? '',
                storage_location_id: String(row.storage_location_id ?? this.selectedLocationId ?? ''),
                batch_number: row.batch_number ?? '',
                expiry_date: row.expiry_date ?? '',
                quantity: row.quantity ?? '',
                purchase_price: row.purchase_price ?? '',
                selling_price: row.selling_price ?? '',
                notes: row.notes ?? '',
                is_committed: row.is_committed === true,
            };
        },

        blankRow(overrides = {}) {
            return this.hydrateRow({
                key: `opening-row-${this.nextDynamicRowId++}`,
                medicine_id: '',
                medicine_code: '',
                medicine_name: '',
                small_unit: '',
                storage_location_id: this.selectedLocationId,
                batch_number: '',
                expiry_date: '',
                quantity: '',
                purchase_price: '',
                selling_price: '',
                notes: '',
                ...overrides,
            });
        },

        handleNumericInput(index, field) {
            const row = this.rows[index];

            if (! row) {
                return;
            }

            const rawValue = String(row[field] ?? '').trim();

            if (rawValue === '') {
                row[field] = '';
            } else {
                row[field] = String(Math.max(toNumber(rawValue, 0), 0));
            }

            this.handleRowInput(index);
        },

        handleRowInput(index) {
            const row = this.rows[index];

            if (! row) {
                return;
            }

            if (! this.rowReadyForCompanion(row)) {
                row.is_committed = false;
            }
        },

        rowIsBlank(row) {
            return String(row.batch_number ?? '').trim() === ''
                && String(row.expiry_date ?? '').trim() === ''
                && String(row.quantity ?? '').trim() === ''
                && String(row.selling_price ?? '').trim() === '';
        },

        rowIsUsed(row) {
            return ! this.rowIsBlank(row);
        },

        rowReadyForCompanion(row) {
            return String(row.medicine_id ?? '').trim() !== ''
                && String(row.batch_number ?? '').trim() !== ''
                && toNumber(row.quantity, 0) > 0;
        },

        isCompanionPlaceholder(row) {
            return String(row.medicine_id ?? '').trim() !== '' && ! this.rowReadyForCompanion(row);
        },

        rowsForMedicine(medicineId) {
            return this.rows.filter((row) => String(row.medicine_id ?? '') === String(medicineId ?? ''));
        },

        filteredRows() {
            const query = String(this.searchTerm ?? '').trim().toLocaleLowerCase('id-ID');

            const matchedRows = this.rows
                .map((row, index) => ({ row, index }))
                .filter(({ row }) => {
                    if (query === '') {
                        return true;
                    }

                    return [
                        row.medicine_code,
                        row.medicine_name,
                    ].some((value) => String(value ?? '').toLocaleLowerCase('id-ID').includes(query));
                });

            const committedRows = matchedRows.filter(({ row }) => row.is_committed && this.rowIsUsed(row));
            const otherRows = matchedRows.filter(({ row }) => ! (row.is_committed && this.rowIsUsed(row)));

            return [...committedRows, ...otherRows];
        },

        lastRowIndexForMedicine(medicineId) {
            let foundIndex = -1;

            this.rows.forEach((row, index) => {
                if (String(row.medicine_id ?? '') === String(medicineId ?? '')) {
                    foundIndex = index;
                }
            });

            return foundIndex;
        },

        ensureCompanionRows() {
            const medicineIds = [...new Set(
                this.rows
                    .map((row) => String(row.medicine_id ?? '').trim())
                    .filter((medicineId) => medicineId !== '')
            )];

            medicineIds.forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));
        },

        confirmCompanionRows() {
            this.syncSelectedLocation();

            this.rows.forEach((row) => {
                if (this.rowReadyForCompanion(row)) {
                    row.is_committed = true;
                    return;
                }

                row.is_committed = false;
            });

            const medicineIds = [...new Set(
                this.rows
                    .map((row) => String(row.medicine_id ?? '').trim())
                    .filter((medicineId) => medicineId !== '')
            )];

            medicineIds.forEach((medicineId) => this.ensureCompanionRowsForMedicine(medicineId));

            this.normalizeRowOrder();
        },

        normalizeRowOrder() {
            const committedRows = this.rows.filter((row) => row.is_committed && this.rowIsUsed(row));
            const otherRows = this.rows.filter((row) => ! (row.is_committed && this.rowIsUsed(row)));

            this.rows = [...committedRows, ...otherRows];
        },

        ensureCompanionRowsForMedicine(medicineId) {
            if (String(medicineId ?? '').trim() === '') {
                return;
            }

            const medicineRows = this.rowsForMedicine(medicineId);

            if (medicineRows.length === 0) {
                return;
            }

            const completedRows = medicineRows.filter((row) => this.rowReadyForCompanion(row));
            const placeholderRows = medicineRows.filter((row) => ! this.rowReadyForCompanion(row));

            if (completedRows.length === 0) {
                const preservedPlaceholderKey = placeholderRows[0]?.key ?? null;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return ! this.rowReadyForCompanion(row) && row.key === preservedPlaceholderKey;
                });

                return;
            }

            if (placeholderRows.length === 0) {
                const sourceRow = medicineRows[medicineRows.length - 1];
                const insertIndex = this.lastRowIndexForMedicine(medicineId);

                this.rows.splice(insertIndex + 1, 0, this.blankRow({
                    medicine_id: String(sourceRow.medicine_id ?? ''),
                    medicine_code: sourceRow.medicine_code ?? '',
                    medicine_name: sourceRow.medicine_name ?? '',
                    small_unit: sourceRow.small_unit ?? '',
                    storage_location_id: this.selectedLocationId,
                    purchase_price: String(sourceRow.purchase_price ?? ''),
                    selling_price: String(sourceRow.selling_price ?? ''),
                }));

                return;
            }

            if (placeholderRows.length > 1) {
                const preservedKey = placeholderRows[0].key;

                this.rows = this.rows.filter((row) => {
                    if (String(row.medicine_id ?? '') !== String(medicineId ?? '')) {
                        return true;
                    }

                    return this.rowReadyForCompanion(row) || row.key === preservedKey;
                });
            }
        },

        syncSelectedLocation() {
            const locationId = String(this.selectedLocationId ?? '');

            this.rows.forEach((row) => {
                row.storage_location_id = locationId;
            });
        },

        rowValue(row) {
            return roundCurrency(toNumber(row.quantity, 0) * toNumber(row.purchase_price, 0));
        },

        currency(value) {
            return currencyFormatter.format(roundCurrency(toNumber(value, 0)));
        },
    }));
});

Alpine.start();
