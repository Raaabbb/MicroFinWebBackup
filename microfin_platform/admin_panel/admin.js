document.addEventListener('DOMContentLoaded', () => {
    const htmlElement = document.documentElement;

    const companyNameInput = document.getElementById('company-name');
    const companyNameDisplay = document.querySelector('.company-name-display');
    const primaryColorInput = document.getElementById('primary-color');
    const sidebarColorInput = null; // Sidebar uses bg_card color
    const primaryColorHex = primaryColorInput ? primaryColorInput.nextElementSibling : null;
    const sidebarColorHex = sidebarColorInput ? sidebarColorInput.nextElementSibling : null;

    const settingsForm = document.getElementById('settings-form');
    const saveBtn = document.getElementById('save-settings');

    const toggleBooking = document.getElementById('toggle-booking_system');
    const toggleRegistration = document.getElementById('toggle-user_registration');
    const toggleMaintenance = document.getElementById('toggle-maintenance_mode');
    const toggleEmails = document.getElementById('toggle-email_notifications');
    const toggleWebsite = document.getElementById('toggle-public_website_enabled');

    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    const viewSections = document.querySelectorAll('.view-section');
    const pageTitle = document.getElementById('page-title');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if(!targetId) return;
            const subTabId = item.getAttribute('data-subtab');
            const navTitle = item.getAttribute('data-title');
            
            e.preventDefault();
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            viewSections.forEach(section => section.classList.remove('active'));
            document.getElementById(targetId).classList.add('active');

            if (targetId === 'settings' && subTabId) {
                tabBtns.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                const targetTabButton = document.querySelector(`.tab-btn[data-tab="${subTabId}"]`);
                if (targetTabButton) {
                    targetTabButton.classList.add('active');
                }

                const targetTabContent = document.getElementById(subTabId);
                if (targetTabContent) {
                    targetTabContent.classList.add('active');
                }
            }

            if (pageTitle) {
                pageTitle.textContent = navTitle || item.querySelector('span:nth-child(2)').textContent;
            }

            // Update URL hash without causing a page jump
            history.replaceState(null, null, `#${targetId}`);
        });
    });

    // Auto-navigate if hash exists or ?section= param exists
    const urlParams = new URLSearchParams(window.location.search);
    const targetTab = window.location.hash ? window.location.hash.substring(1) : (urlParams.get('section') || urlParams.get('tab'));       
    if (targetTab) {
        const targetNav = document.querySelector(`.nav-item[data-target="${targetTab}"]`);
        if (targetNav) targetNav.click();
    }

    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Role View Swapping Logic
    const roleListItems = document.querySelectorAll('.role-list-item');
    const rolePanels = document.querySelectorAll('.role-permissions-panel');

    roleListItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            const roleId = item.getAttribute('data-role-id');
            if (!roleId) return;

            // Update active styling in sidebar
            roleListItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Hide all panels, show the selected one
            rolePanels.forEach(panel => {
                panel.style.display = 'none';
            });
            const targetPanel = document.getElementById(`role-panel-${roleId}`);
            if (targetPanel) {
                targetPanel.style.display = 'block';
            }

            // Update URL query string without reloading page
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('role_id', roleId);
            window.history.pushState({}, '', currentUrl);
        });
    });

    const themeToggleBtn = document.getElementById('theme-toggle');

    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        if (themeToggleBtn) {
            const icon = themeToggleBtn.querySelector('span');
            if (icon) {
                icon.textContent = theme === 'light' ? 'dark_mode' : 'light_mode';
            }
        }
    }

    async function persistTheme(theme) {
        try {
            await fetch('../backend/api_theme_preference.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: theme, role: 'tenant' })
            });
        } catch (error) {
            // Ignore persistence errors to keep the toggle responsive.
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

    function setPrimaryColor(val) {
        if (!primaryColorInput || !primaryColorHex) {
            return;
        }

        primaryColorInput.value = val;
        primaryColorHex.textContent = val;
        htmlElement.style.setProperty('--primary-color', val);
        const hex = val.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        htmlElement.style.setProperty('--primary-rgb', `${r}, ${g}, ${b}`);
    }

    if (primaryColorInput) {
        setPrimaryColor(primaryColorInput.value);
        primaryColorInput.addEventListener('input', (e) => setPrimaryColor(e.target.value));
    }



    // Live-update hex label for ALL color pickers in settings
    const colorVarMap = {
        'text_main': '--text-main',
        'text_muted': '--text-muted',
        'bg_body': '--bg-body',
        'bg_card': ['--bg-card', '--sidebar-bg']
    };
    document.querySelectorAll('.theme-colors input[type="color"]').forEach(input => {
        const hexSpan = input.nextElementSibling;
        if (!hexSpan || !hexSpan.classList.contains('color-hex')) return;
        input.addEventListener('input', (e) => {
            hexSpan.textContent = e.target.value;
            const cssVar = colorVarMap[input.name];
            if (cssVar) {
                if (Array.isArray(cssVar)) {
                    cssVar.forEach(v => htmlElement.style.setProperty(v, e.target.value));
                } else {
                    htmlElement.style.setProperty(cssVar, e.target.value);
                }
            }
        });
    });

    if (companyNameInput && companyNameDisplay) {
        companyNameInput.addEventListener('input', (e) => {
            companyNameDisplay.textContent = e.target.value || 'Company Admin';
        });
    }

    const fileInput = document.getElementById('logo-upload');
    const fileNameDisplay = document.querySelector('.file-name');
    if (fileInput && fileNameDisplay) {
        fileInput.addEventListener('change', (e) => {
            fileNameDisplay.textContent = e.target.files.length > 0 ? e.target.files[0].name : 'No file chosen';
        });
    }

    function syncToggleHiddenFields() {
        const map = [
            { checkbox: toggleBooking, hidden: document.getElementById('hidden-toggle-booking') },
            { checkbox: toggleRegistration, hidden: document.getElementById('hidden-toggle-registration') },
            { checkbox: toggleMaintenance, hidden: document.getElementById('hidden-toggle-maintenance') },
            { checkbox: toggleEmails, hidden: document.getElementById('hidden-toggle-emails') },
            { checkbox: toggleWebsite, hidden: document.getElementById('hidden-toggle-website') }
        ];

        map.forEach((item) => {
            if (!item.checkbox || !item.hidden) {
                return;
            }

            item.hidden.disabled = !item.checkbox.checked;
        });
    }

    [toggleBooking, toggleRegistration, toggleMaintenance, toggleEmails, toggleWebsite].forEach((toggle) => {
        if (toggle) {
            toggle.addEventListener('change', syncToggleHiddenFields);
        }
    });

    syncToggleHiddenFields();

    if (settingsForm && saveBtn) {
        settingsForm.addEventListener('submit', () => {
            syncToggleHiddenFields();
            saveBtn.innerText = 'Saving...';
            saveBtn.style.opacity = '0.8';
        });
    }

    // Role Presets Logic
    const rolePresetSelect = document.getElementById('role-preset');
    const roleNameInput = document.getElementById('create_role_name');
    const createRolePermissions = document.querySelectorAll('#create-role-permissions-container input[type="checkbox"]');

    if (rolePresetSelect && roleNameInput && createRolePermissions.length > 0) {
        const presets = {
            'manager': {
                name: 'Manager',
                perms: ['CREATE_LOAN', 'APPROVE_LOAN', 'VIEW_REPORTS', 'MANAGE_USERS', 'VIEW_KPI', 'EXPORT_DATA']
            },
            'loan_officer': {
                name: 'Loan Officer',
                perms: ['CREATE_LOAN', 'VIEW_LOANS', 'EDIT_LOAN', 'VIEW_CLIENT_DOCS']
            },
            'teller': {
                name: 'Teller',
                perms: ['PROCESS_PAYMENT', 'VIEW_TRANSACTIONS', 'VIEW_CLIENT_BASIC']
            }
        };

        rolePresetSelect.addEventListener('change', (e) => {
            const val = e.target.value;
            
            if (val === 'custom') {
                createRolePermissions.forEach(cb => cb.checked = false);
                return;
            }

            const preset = presets[val];
            if (preset) {
                // Auto-fill name if it's empty or currently matches another preset
                const currentName = roleNameInput.value.trim();
                const isCurrentNameAPreset = Object.values(presets).some(p => p.name === currentName) || currentName === '';
                if (isCurrentNameAPreset) {
                    roleNameInput.value = preset.name;
                }

                // Check boxes
                createRolePermissions.forEach(cb => {
                    cb.checked = preset.perms.includes(cb.value);
                });
            }
        });
    }

});
