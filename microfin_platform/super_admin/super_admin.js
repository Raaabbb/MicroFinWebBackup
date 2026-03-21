document.addEventListener('DOMContentLoaded', () => {

    // ============================================================
    // SPA NAVIGATION (sidebar)
    // ============================================================
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    const viewSections = document.querySelectorAll('.view-section');
    const pageTitle = document.getElementById('page-title');

    navItems.forEach((item) => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if (!targetId) return;

            e.preventDefault();
            navItems.forEach((nav) => nav.classList.remove('active'));
            item.classList.add('active');

            viewSections.forEach((section) => section.classList.remove('active'));
            const targetSection = document.getElementById(targetId);
            if (targetSection) targetSection.classList.add('active');

            const label = item.querySelector('span:nth-child(2)');
            if (label) pageTitle.textContent = label.textContent;

            // Update URL hash without causing a page jump
            history.replaceState(null, null, `#${targetId}`);
        });
    });

    // Auto-navigate to section if hash exists or ?section= or ?tab= param exists
    const urlParams = new URLSearchParams(window.location.search);
    const targetTab = window.location.hash
        ? window.location.hash.substring(1)
        : (urlParams.get('section') || urlParams.get('tab'));
    if (targetTab) {
        const targetNav = document.querySelector(`.nav-item[data-target="${targetTab}"]`);
        if (targetNav) targetNav.click();
    }

    // ============================================================
    // THEME TOGGLE
    // ============================================================
    const themeToggleBtn = document.getElementById('theme-toggle');
    const htmlElement = document.documentElement;

    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        const icon = themeToggleBtn ? themeToggleBtn.querySelector('span') : null;
        if (icon) {
            icon.textContent = theme === 'light' ? 'dark_mode' : 'light_mode';
        }
    }

    async function persistTheme(theme) {
        try {
            await fetch('../backend/api_theme_preference.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: theme, role: 'super_admin' })
            });
        } catch (error) {
            // Ignore persistence failures and keep UI state.
        }
    }

    if (themeToggleBtn) {
        applyTheme(htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            applyTheme(newTheme);
            persistTheme(newTheme);
        });
    }

    // ============================================================
    // MODAL (Provision Tenant)
    // ============================================================
    const btnCreateTenant = document.getElementById('btn-create-tenant');
    const modalBackdrop = document.getElementById('modal-backdrop');
    const btnCloseModal = document.getElementById('close-modal');
    const btnCancelModal = document.getElementById('cancel-modal');
    const modalForm = modalBackdrop ? modalBackdrop.querySelector('form') : null;
    const btnSubmitTenant = document.getElementById('submit-tenant');

    const resetModalFormReadOnly = () => {
        if (modalForm) {
            Array.from(modalForm.elements).forEach(el => {
                if (el.tagName !== 'BUTTON' && el.type !== 'hidden') {
                    el.removeAttribute('readonly');
                    el.style.pointerEvents = '';
                    el.style.backgroundColor = '';
                    el.style.opacity = '';
                    el.style.cursor = '';
                }
            });
        }
    };

    const closeModal = () => {
        if (modalBackdrop) {
            modalBackdrop.classList.remove('show');
            if (modalForm) {
                modalForm.reset();
                resetModalFormReadOnly();
            }
        }
    };

    if (btnCreateTenant && modalBackdrop) {
        btnCreateTenant.addEventListener('click', () => {
            resetModalFormReadOnly();
            modalBackdrop.classList.add('show');
        });
    }
    if (btnCloseModal) btnCloseModal.addEventListener('click', closeModal);
    if (btnCancelModal) btnCancelModal.addEventListener('click', closeModal);
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });
    }

    if (modalForm && btnSubmitTenant) {
        modalForm.addEventListener('submit', () => {
            btnSubmitTenant.innerHTML = '<span class="material-symbols-rounded" style="animation: spin 1s linear infinite;">sync</span> Provisioning...';
            btnSubmitTenant.style.opacity = '0.8';
            btnSubmitTenant.disabled = true;
        });
        
        const nameInputGlobal = modalForm.querySelector('input[name="tenant_name"]');
        const slugInputGlobal = modalForm.querySelector('input[name="custom_slug"]');
        if (nameInputGlobal && slugInputGlobal) {
            nameInputGlobal.addEventListener('input', () => {
                if (!slugInputGlobal.dataset.manuallyEdited) {
                    slugInputGlobal.value = nameInputGlobal.value.toLowerCase().trim().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
                }
            });
            slugInputGlobal.addEventListener('input', () => {
                slugInputGlobal.dataset.manuallyEdited = ((slugInputGlobal.value.length > 0) ? 'true' : '');
            });
        }
    }

    // Create SA Modal
    const btnCreateSA = document.getElementById('btn-create-super-admin');
    const saModalBackdrop = document.getElementById('modal-sa-backdrop');
    if (btnCreateSA && saModalBackdrop) {
        btnCreateSA.addEventListener('click', () => saModalBackdrop.classList.add('show'));
    }
    const btnCloseSAModal = document.getElementById('close-sa-modal');
    const btnCancelSAModal = document.getElementById('cancel-sa-modal');
    
    if (btnCloseSAModal) btnCloseSAModal.addEventListener('click', () => {
        if (saModalBackdrop) {
            saModalBackdrop.classList.remove('show');
            saModalBackdrop.querySelector('form').reset();
        }
    });
    if (btnCancelSAModal) btnCancelSAModal.addEventListener('click', () => {
        if (saModalBackdrop) {
            saModalBackdrop.classList.remove('show');
            saModalBackdrop.querySelector('form').reset();
        }
    });
    if (saModalBackdrop) {
        saModalBackdrop.addEventListener('click', (e) => {
            if (e.target === saModalBackdrop) {
                saModalBackdrop.classList.remove('show');
                saModalBackdrop.querySelector('form').reset();
            }
        });
    }

    // Audit Details Modal
    const auditModalBackdrop = document.getElementById('modal-audit-backdrop');
    const btnCloseAuditModal = document.getElementById('close-audit-modal');
    const btnCloseAuditModalFooter = document.getElementById('close-audit-modal-footer');

    function closeAuditModal() {
        if (auditModalBackdrop) auditModalBackdrop.classList.remove('show');
    }

    function openAuditModalFromButton(buttonEl) {
        if (!auditModalBackdrop || !buttonEl) return;

        const setValue = (id, value) => {
            const field = document.getElementById(id);
            if (field) field.value = value || '—';
        };

        setValue('audit-detail-created-at', formatDateTime(buttonEl.dataset.createdAt));
        setValue('audit-detail-username', buttonEl.dataset.username || '—');
        setValue('audit-detail-user-email', buttonEl.dataset.userEmail || 'System');
        setValue('audit-detail-tenant-name', buttonEl.dataset.tenantName || 'Platform');
        setValue('audit-detail-action-type', buttonEl.dataset.actionType || '—');
        setValue('audit-detail-entity-type', buttonEl.dataset.entityType || '—');
        setValue('audit-detail-description', buttonEl.dataset.description || '—');

        auditModalBackdrop.classList.add('show');
    }

    if (btnCloseAuditModal) btnCloseAuditModal.addEventListener('click', closeAuditModal);
    if (btnCloseAuditModalFooter) btnCloseAuditModalFooter.addEventListener('click', closeAuditModal);
    if (auditModalBackdrop) {
        auditModalBackdrop.addEventListener('click', (e) => {
            if (e.target === auditModalBackdrop) closeAuditModal();
        });
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.audit-detail-btn');
        if (!trigger) return;
        e.preventDefault();
        openAuditModalFromButton(trigger);
    });

    // Bind provision buttons (from tenant table rows)
    bindProvisionButtons();


    // ============================================================
    // DASHBOARD: Charts + Polling
    // ============================================================
    let chartUserGrowth = null;
    let chartTenantActivity = null;
    let chartSalesTrends = null;

    const chartColors = {
        primary: '#0284c7',
        primaryLight: 'rgba(2, 132, 199, 0.2)',
        green: '#10b981',
        greenLight: 'rgba(16, 185, 129, 0.2)',
        purple: '#8b5cf6',
        purpleLight: 'rgba(139, 92, 246, 0.2)',
    };

    function buildTenantPalette(count) {
        const base = [
            '#0284c7', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6',
            '#f97316', '#6366f1', '#84cc16', '#ec4899', '#0ea5e9', '#22c55e'
        ];
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(base[i % base.length]);
        }
        return colors;
    }

    function toYmd(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function getUserGrowthDateRange() {
        const fromEl = document.getElementById('user-growth-date-from');
        const toEl = document.getElementById('user-growth-date-to');
        return {
            dateFrom: fromEl ? fromEl.value : '',
            dateTo: toEl ? toEl.value : ''
        };
    }

    function setUserGrowthDateDefaults() {
        const fromEl = document.getElementById('user-growth-date-from');
        const toEl = document.getElementById('user-growth-date-to');
        if (!fromEl || !toEl) return;

        const today = new Date();
        const sevenDaysBehind = new Date(today);
        sevenDaysBehind.setDate(today.getDate() - 7);

        if (!fromEl.value) fromEl.value = toYmd(sevenDaysBehind);
        if (!toEl.value) toEl.value = toYmd(today);
    }

    function buildDashboardQueryString() {
        const params = new URLSearchParams({ action: 'dashboard' });
        const { dateFrom, dateTo } = getUserGrowthDateRange();
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        return params.toString();
    }

    function buildUserGrowthDatasets(userGrowthChartData) {
        const series = (userGrowthChartData && Array.isArray(userGrowthChartData.series)) ? userGrowthChartData.series : [];
        const colors = buildTenantPalette(series.length);

        return series.map((s, idx) => {
            const color = colors[idx];
            return {
                label: s.tenant_name || `Tenant ${idx + 1}`,
                data: Array.isArray(s.points) ? s.points.map(v => Number(v || 0)) : [],
                borderColor: color,
                backgroundColor: color + '33',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: color,
                borderWidth: 2
            };
        });
    }

    function initDashboardCharts(data) {
        const userGrowthCtx = document.getElementById('chart-user-growth');
        const tenantActivityCtx = document.getElementById('chart-tenant-activity');
        const salesTrendsCtx = document.getElementById('chart-sales-trends');

        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#a1a1aa', font: { family: 'Outfit' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#a1a1aa', font: { family: 'Outfit' } }
                }
            }
        };

        const integerTickFormatter = (value) => {
            const numeric = Number(value);
            if (!Number.isFinite(numeric) || !Number.isInteger(numeric)) {
                return '';
            }
            return String(numeric);
        };

        if (userGrowthCtx) {
            const userGrowthLabels = (data.user_growth_chart && Array.isArray(data.user_growth_chart.labels))
                ? data.user_growth_chart.labels
                : [];
            const userGrowthDatasets = buildUserGrowthDatasets(data.user_growth_chart);
            chartUserGrowth = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: userGrowthLabels,
                    datasets: userGrowthDatasets
                },
                options: {
                    ...defaultOptions,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: '#a1a1aa', font: { family: 'Outfit' } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset && context.dataset.label ? context.dataset.label + ': ' : '';
                                    const numericValue = Number(context.parsed && context.parsed.y);
                                    const safeValue = Number.isFinite(numericValue) ? Math.round(numericValue) : 0;
                                    return datasetLabel + safeValue;
                                }
                            }
                        }
                    },
                    scales: {
                        ...defaultOptions.scales,
                        y: {
                            ...defaultOptions.scales.y,
                            ticks: {
                                ...defaultOptions.scales.y.ticks,
                                callback: integerTickFormatter
                            }
                        }
                    }
                }
            });
        }

        if (tenantActivityCtx) {
            chartTenantActivity = new Chart(tenantActivityCtx, {
                type: 'bar',
                data: {
                    labels: (data.tenant_activity_chart || []).map(d => d.month),
                    datasets: [
                        {
                            label: 'Active',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.active_count || 0)),
                            backgroundColor: chartColors.green,
                            borderRadius: 4,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Pending Application',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.pending_count || 0)),
                            backgroundColor: '#f59e0b',
                            borderRadius: 4,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Inactive',
                            data: (data.tenant_activity_chart || []).map(d => Number(d.inactive_count || 0)),
                            backgroundColor: '#ef4444',
                            borderRadius: 4,
                            maxBarThickness: 40
                        }
                    ]
                },
                options: {
                    ...defaultOptions,
                    plugins: {
                        legend: {
                            display: true,
                            labels: { color: '#a1a1aa', font: { family: 'Outfit' } }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: '#a1a1aa', font: { family: 'Outfit' } },
                            stacked: true
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: '#a1a1aa', font: { family: 'Outfit' } },
                            stacked: true
                        }
                    }
                }
            });
        }

        if (salesTrendsCtx) {
            chartSalesTrends = new Chart(salesTrendsCtx, {
                type: 'line',
                data: {
                    labels: (data.sales_trends_chart || []).map(d => d.month),
                    datasets: [{
                        label: 'Revenue',
                        data: (data.sales_trends_chart || []).map(d => parseFloat(d.total)),
                        borderColor: chartColors.green,
                        backgroundColor: chartColors.greenLight,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: chartColors.green
                    }]
                },
                options: defaultOptions
            });
        }
    }

    function updateDashboardStats(data) {
        const el = (id, val) => {
            const e = document.getElementById(id);
            if (e) e.textContent = val;
        };
        el('stat-active-tenants', data.active_tenants);
        el('stat-super-admin-accounts', data.active_super_admin_accounts);
        el('stat-inactive-users', data.inactive_users);
        el('stat-pending-apps', data.pending_applications ?? '0');
        el('stat-total-mrr', '₱' + data.total_mrr);

        // Update charts
        if (chartUserGrowth && data.user_growth_chart) {
            chartUserGrowth.data.labels = Array.isArray(data.user_growth_chart.labels) ? data.user_growth_chart.labels : [];
            chartUserGrowth.data.datasets = buildUserGrowthDatasets(data.user_growth_chart);
            chartUserGrowth.update('none');
        }
        if (chartTenantActivity && data.tenant_activity_chart) {
            chartTenantActivity.data.labels = data.tenant_activity_chart.map(d => d.month);
            chartTenantActivity.data.datasets[0].data = data.tenant_activity_chart.map(d => Number(d.active_count || 0));
            chartTenantActivity.data.datasets[1].data = data.tenant_activity_chart.map(d => Number(d.pending_count || 0));
            chartTenantActivity.data.datasets[2].data = data.tenant_activity_chart.map(d => Number(d.inactive_count || 0));
            chartTenantActivity.update('none');
        }
        if (chartSalesTrends && data.sales_trends_chart) {
            chartSalesTrends.data.labels = data.sales_trends_chart.map(d => d.month);
            chartSalesTrends.data.datasets[0].data = data.sales_trends_chart.map(d => parseFloat(d.total));
            chartSalesTrends.update('none');
        }
    }

    async function loadDashboardStats(initCharts = false) {
        try {
            const res = await fetch('api_dashboard_stats.php?' + buildDashboardQueryString());
            if (!res.ok) return;
            const data = await res.json();

            const fromEl = document.getElementById('user-growth-date-from');
            const toEl = document.getElementById('user-growth-date-to');
            if (fromEl && data.user_growth_date_from && !fromEl.value) fromEl.value = data.user_growth_date_from;
            if (toEl && data.user_growth_date_to && !toEl.value) toEl.value = data.user_growth_date_to;

            if (initCharts) {
                initDashboardCharts(data);
            }
            updateDashboardStats(data);
        } catch (e) {
            console.error('Dashboard load error:', e);
        }
    }

    setUserGrowthDateDefaults();
    loadDashboardStats(true);

    const btnApplyUserGrowthFilter = document.getElementById('btn-apply-user-growth-filter');
    if (btnApplyUserGrowthFilter) {
        btnApplyUserGrowthFilter.addEventListener('click', async () => {
            const fromEl = document.getElementById('user-growth-date-from');
            const toEl = document.getElementById('user-growth-date-to');
            if (!fromEl || !toEl) return;

            if (fromEl.value && toEl.value && fromEl.value > toEl.value) {
                const tmp = fromEl.value;
                fromEl.value = toEl.value;
                toEl.value = tmp;
            }
            await loadDashboardStats(false);
        });
    }

    // Poll every 5 seconds
    setInterval(async () => {
        await loadDashboardStats(false);
    }, 5000);

    // ============================================================
    // TENANT MANAGEMENT: Filter + Search
    // ============================================================
    const tenantStatusFilter = document.getElementById('tenant-status-filter');
    const applicationStatusFilter = document.getElementById('application-status-filter');
    const inquiryStatusFilter = document.getElementById('inquiry-status-filter');
    const tenantSearch = document.getElementById('tenant-search');
    const tenantsTable = document.getElementById('tenants-table');
    const tenantIntakeTabs = document.querySelectorAll('.tenant-intake-tab');
    let activeTenantView = document.querySelector('.tenant-intake-tab.active')?.getAttribute('data-view') || 'tenants';

    function normalizeInquiryStatus(rowStatus, rowRequestType) {
        const rawStatus = String(rowStatus || '').toLowerCase();
        const requestType = String(rowRequestType || '').toLowerCase();

        if (requestType === 'talk_to_expert') {
            if (rawStatus === 'pending') return 'new';
            if (rawStatus === 'contacted') return 'in_contact';
            if (rawStatus === 'new') return 'new';
            if (rawStatus === 'in contact') return 'in_contact';
            return 'closed';
        }

        if (rawStatus === 'active') return 'active';
        if (rawStatus === 'suspended') return 'suspended';
        if (rawStatus === 'rejected') return 'rejected';
        return 'pending';
    }

    function updateTenantManagementFilterVisibility() {
        activeTenantView = document.querySelector('.tenant-intake-tab.active')?.getAttribute('data-view') || activeTenantView;

        if (activeTenantView === 'inquiries') {
            if (tenantStatusFilter) tenantStatusFilter.style.display = 'none';
            if (applicationStatusFilter) applicationStatusFilter.style.display = 'none';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = '';
        } else if (activeTenantView === 'applications') {
            if (tenantStatusFilter) tenantStatusFilter.style.display = 'none';
            if (applicationStatusFilter) applicationStatusFilter.style.display = '';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = 'none';
        } else {
            if (tenantStatusFilter) tenantStatusFilter.style.display = '';
            if (applicationStatusFilter) applicationStatusFilter.style.display = 'none';
            if (inquiryStatusFilter) inquiryStatusFilter.style.display = 'none';
        }
    }

    tenantIntakeTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tenantIntakeTabs.forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            activeTenantView = tab.getAttribute('data-view') || 'all';
            updateTenantManagementFilterVisibility();
            filterTenantTable();
        });
    });

    function filterTenantTable() {
        if (!tenantsTable) return;
        const status = (activeTenantView === 'inquiries' && inquiryStatusFilter)
            ? inquiryStatusFilter.value
            : (activeTenantView === 'applications' && applicationStatusFilter)
                ? applicationStatusFilter.value
                : (tenantStatusFilter ? tenantStatusFilter.value : 'all');
        const search = tenantSearch ? tenantSearch.value.toLowerCase() : '';
        const rows = tenantsTable.querySelectorAll('tbody tr[data-status]');

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowRequestType = row.getAttribute('data-request-type') || 'tenant_application';
            const normalizedStatus = normalizeInquiryStatus(rowStatus, rowRequestType);
            const rowText = row.textContent.toLowerCase();
            const statusMatch = status === 'all' || normalizedStatus === status;
            const isApplication = rowRequestType === 'tenant_application' && (normalizedStatus === 'pending' || normalizedStatus === 'rejected');
            const isTenant = rowRequestType === 'tenant_application' && (normalizedStatus === 'active' || normalizedStatus === 'suspended');
            const isInquiry = rowRequestType === 'talk_to_expert';

            let viewMatch = false;
            if (activeTenantView === 'all') viewMatch = true;
            if (activeTenantView === 'tenants') viewMatch = isTenant;
            if (activeTenantView === 'applications') viewMatch = isApplication;
            if (activeTenantView === 'inquiries') viewMatch = isInquiry;

            const searchMatch = search === '' || rowText.includes(search);
            row.style.display = statusMatch && viewMatch && searchMatch ? '' : 'none';
        });
    }

    if (tenantStatusFilter) tenantStatusFilter.addEventListener('change', filterTenantTable);
    if (applicationStatusFilter) applicationStatusFilter.addEventListener('change', filterTenantTable);
    if (inquiryStatusFilter) inquiryStatusFilter.addEventListener('change', filterTenantTable);
    if (tenantSearch) tenantSearch.addEventListener('input', filterTenantTable);
    updateTenantManagementFilterVisibility();
    filterTenantTable();

    // ============================================================
    // REPORTS: Load via AJAX
    // ============================================================
    const btnApplyReportFilter = document.getElementById('btn-apply-report-filter');

    if (btnApplyReportFilter) {
        btnApplyReportFilter.addEventListener('click', () => {
            const dateFrom = document.getElementById('report-date-from').value;
            const dateTo = document.getElementById('report-date-to').value;
            const tenantId = document.getElementById('report-tenant-filter').value;

            const params = new URLSearchParams({ action: 'reports' });
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);
            if (tenantId) params.set('tenant_id', tenantId);

            fetch('api_dashboard_stats.php?' + params.toString())
                .then(r => r.json())
                .then(data => renderReports(data))
                .catch(e => console.error('Reports error:', e));
        });
    }

    function renderReports(data) {
        // Tenant Activity
        const taTbody = document.querySelector('#report-tenant-activity tbody');
        if (taTbody) {
            if (!data.tenant_activity || data.tenant_activity.length === 0) {
                taTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No data found for the selected filters.</td></tr>';
            } else {
                taTbody.innerHTML = data.tenant_activity.map(t => `
                    <tr>
                        <td>${esc(t.tenant_name)}</td>
                        <td><span class="badge">${esc(t.status)}</span></td>
                        <td><span class="badge ${t.status_legend === 'Active' ? 'badge-green' : (t.status_legend === 'Pending Application' ? 'badge-amber' : 'badge-red')}">${esc(t.status_legend || 'Inactive')}</span></td>
                        <td>${esc(t.plan_tier)}</td>
                        <td>${formatDate(t.created_at)}</td>
                    </tr>
                `).join('');
            }
        }
    }

    // ============================================================
    // SALES REPORT: Load via AJAX
    // ============================================================
    let chartRevenue = null;
    const revenuePeriodFilter = document.getElementById('revenue-period-filter');
    
    function loadSalesData() {
        const period = revenuePeriodFilter ? revenuePeriodFilter.value : 'monthly';
        const params = new URLSearchParams({ action: 'sales', period: period });

        fetch('api_dashboard_stats.php?' + params.toString())
            .then(r => r.json())
            .then(data => renderSalesReport(data))
            .catch(e => console.error('Sales error:', e));
    }

    if (document.getElementById('sales')) {
        // Load initially
        loadSalesData();
        
        // Reload when filter changes
        if (revenuePeriodFilter) {
            revenuePeriodFilter.addEventListener('change', loadSalesData);
        }
    }

    function renderSalesReport(data) {
        // Top tenants table
        const topTbody = document.querySelector('#top-tenants-table tbody');
        if (topTbody) {
            if (!data.top_tenants || data.top_tenants.length === 0) {
                topTbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center; padding:2rem;">No sales data found.</td></tr>';
            } else {
                topTbody.innerHTML = data.top_tenants.map((t, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${esc(t.tenant_name)}</td>
                        <td>${esc(t.plan_tier)}</td>
                            <td>₱${parseFloat(t.total_sales).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                        <td>${t.transaction_count}</td>
                    </tr>
                `).join('');
            }
        }

        // Revenue chart
        const revenueCtx = document.getElementById('chart-revenue');
        if (revenueCtx) {
            if (chartRevenue) chartRevenue.destroy();
            chartRevenue = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: (data.revenue_chart || []).map(d => d.period_label),
                    datasets: [{
                        label: 'Revenue',
                        data: (data.revenue_chart || []).map(d => parseFloat(d.total)),
                        borderColor: chartColors.green,
                        backgroundColor: chartColors.greenLight,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: chartColors.green
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a1a1aa', font: { family: 'Outfit' } } },
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a1a1aa', font: { family: 'Outfit' } } }
                    }
                }
            });
        }

        // Transaction history table
        const txTbody = document.querySelector('#sales-transactions-table tbody');
        if (txTbody) {
            if (!data.transactions || data.transactions.length === 0) {
                txTbody.innerHTML = '<tr><td colspan="6" class="text-muted" style="text-align:center; padding:2rem;">No transactions found.</td></tr>';
            } else {
                txTbody.innerHTML = data.transactions.map(tx => `
                    <tr>
                        <td><code>${esc(tx.payment_reference)}</code></td>
                        <td>${esc(tx.tenant_name || '—')}</td>
                        <td>₱${parseFloat(tx.payment_amount).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                        <td>${esc(tx.payment_method)}</td>
                        <td><span class="badge">${esc(tx.payment_status)}</span></td>
                        <td>${formatDate(tx.payment_date)}</td>
                    </tr>
                `).join('');
            }
        }
    }

    // ============================================================
    // AUDIT LOGS: Load via AJAX
    const btnApplyAuditFilter = document.getElementById('btn-apply-audit-filter');

    if (btnApplyAuditFilter) {
        btnApplyAuditFilter.addEventListener('click', () => {
            const actionType = document.getElementById('audit-action-filter').value;
            const tenantId = document.getElementById('audit-tenant-filter').value;
            const dateFrom = document.getElementById('audit-date-from').value;
            const dateTo = document.getElementById('audit-date-to').value;

            const params = new URLSearchParams({ action: 'audit_logs' });
            if (actionType) params.set('action_type', actionType);
            if (tenantId) params.set('tenant_id', tenantId);
            if (dateFrom) params.set('date_from', dateFrom);
            if (dateTo) params.set('date_to', dateTo);

            fetch('api_dashboard_stats.php?' + params.toString())
                .then(data => renderAuditLogs(data.logs || []))
                .catch(e => console.error('Audit logs error:', e));
        });
    }

    function renderAuditLogs(logs) {
        const tbody = document.querySelector('#audit-logs-table tbody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-muted" style="text-align:center; padding:2rem;">No audit logs match the selected filters.</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(log => `
            <tr>
                <td><small>${formatDateTime(log.created_at)}</small></td>
                <td><span style="font-family: monospace;">${esc(log.username || '—')}</span></td>
                <td>${esc(log.user_email || 'System')}</td>
                <td>${esc(log.tenant_name || 'Platform')}</td>
                <td><span class="badge badge-blue">${esc(log.action_type)}</span></td>
                <td>${esc(log.entity_type || '—')}</td>
                <td>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm audit-detail-btn"
                        data-created-at="${esc(log.created_at || '')}"
                        data-username="${esc(log.username || '—')}"
                        data-user-email="${esc(log.user_email || 'System')}"
                        data-tenant-name="${esc(log.tenant_name || 'Platform')}"
                        data-action-type="${esc(log.action_type || '—')}"
                        data-entity-type="${esc(log.entity_type || '—')}"
                        data-description="${esc(log.description || '—')}"
                    >
                        <span class="material-symbols-rounded" style="font-size:16px;">visibility</span> View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // ============================================================
    // SETTINGS: Sub-tab navigation
    // ============================================================
    const settingsTabs = document.querySelectorAll('.settings-tab');
    const settingsPanels = document.querySelectorAll('.settings-panel');

    settingsTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-settings-target');

            settingsTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            settingsPanels.forEach(p => p.classList.remove('active'));
            const panel = document.getElementById(target);
            if (panel) panel.classList.add('active');
        });
    });

    // ============================================================
    // HELPERS
    // ============================================================

    function bindProvisionButtons() {
        const btns = document.querySelectorAll('.btn-provision-from-demo');
        btns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const tenantName = btn.getAttribute('data-tenant-name');
                const companyEmail = btn.getAttribute('data-company-email');
                let planTier = btn.getAttribute('data-plan-tier');
                const requestType = btn.getAttribute('data-request-type') || 'tenant_application';
                if (!planTier || planTier === '') planTier = 'Starter';
                const firstName = btn.getAttribute('data-first-name') || '';
                const lastName = btn.getAttribute('data-last-name') || '';
                const mi = btn.getAttribute('data-mi') || '';
                const suffix = btn.getAttribute('data-suffix') || '';
                const companyAddress = btn.getAttribute('data-company-address') || '';

                if (modalForm) {
                    // Make fields read-only for demo provision
                    Array.from(modalForm.elements).forEach(el => {
                        if (el.tagName !== 'BUTTON' && el.type !== 'hidden') {
                            el.setAttribute('readonly', 'true');
                            if (el.tagName === 'SELECT' || el.type === 'checkbox') {
                                el.style.pointerEvents = 'none';
                                el.style.opacity = '0.7';
                            }
                            el.style.backgroundColor = 'var(--bg-tertiary)';
                            el.style.cursor = 'default';
                        }
                    });

                    const nameInput = modalForm.querySelector('input[name="tenant_name"]');
                    const emailInput = modalForm.querySelector('input[name="admin_email"]');
                    const slugInput = modalForm.querySelector('input[name="custom_slug"]');
                    const requestTypeInput = modalForm.querySelector('input[name="request_type"]');
                    const planSelect = modalForm.querySelector('select[name="plan_tier"]');
                    const firstNameInput = modalForm.querySelector('input[name="first_name"]');
                    const lastNameInput = modalForm.querySelector('input[name="last_name"]');
                    const miInput = modalForm.querySelector('input[name="mi"]');
                    const suffixSelect = modalForm.querySelector('select[name="suffix"]');
                    const companyAddressInput = modalForm.querySelector('input[name="company_address"]');

                    if (nameInput) nameInput.value = tenantName;
                    if (emailInput) emailInput.value = companyEmail;
                    if (requestTypeInput) requestTypeInput.value = requestType;
                    if (slugInput) {
                        slugInput.value = tenantName.toLowerCase().trim().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');
                        delete slugInput.dataset.manuallyEdited;
                    }
                    if (firstNameInput) firstNameInput.value = firstName;
                    if (lastNameInput) lastNameInput.value = lastName;
                    if (miInput) miInput.value = mi;
                    if (suffixSelect) {
                        for (let i = 0; i < suffixSelect.options.length; i++) {
                            if (suffixSelect.options[i].value === suffix) {
                                suffixSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    if (companyAddressInput) companyAddressInput.value = companyAddress;
                    if (planSelect) {
                        for (let i = 0; i < planSelect.options.length; i++) {
                            if (planSelect.options[i].value === planTier) {
                                planSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }

                if (modalBackdrop) modalBackdrop.classList.add('show');
            });
        });
    }



    function esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

});
