document.addEventListener('DOMContentLoaded', function () {

    const body = document.body;
    const html = document.documentElement;

    const sidebar =
        document.getElementById('appSidebar');

    const collapseBtn =
        document.getElementById('sidebarCollapseBtn');

    const shellId = sidebar?.dataset.gctShell || 'default';
    const dropdownStorageKey = `gct-sidebar-dropdowns:${shellId}`;


    /* =========================================================
       DEFAULT SIDEBAR STATE
       Always expanded when a page loads
    ========================================================= */

    html.classList.remove(
        'sidebar-start-collapsed'
    );

    body.classList.remove(
        'sidebar-collapsed'
    );

    if (sidebar) {
        sidebar.classList.remove(
            'collapsed'
        );
    }


    /* =========================================================
       SIDEBAR STATE
    ========================================================= */

    function setSidebarCollapsed(isCollapsed) {

        if (!sidebar) {
            return;
        }

        html.classList.remove(
            'sidebar-start-collapsed'
        );

        sidebar.classList.toggle(
            'collapsed',
            isCollapsed
        );

        body.classList.toggle(
            'sidebar-collapsed',
            isCollapsed
        );


        if (collapseBtn) {

            collapseBtn.setAttribute(
                'aria-expanded',
                isCollapsed
                    ? 'false'
                    : 'true'
            );

            collapseBtn.setAttribute(
                'title',
                isCollapsed
                    ? 'Expand sidebar'
                    : 'Collapse sidebar'
            );
        }
    }


    /* =========================================================
       SIDEBAR TOGGLE
    ========================================================= */

    if (collapseBtn && sidebar) {

        collapseBtn.addEventListener(
            'click',
            function () {

                const isCollapsed =
                    sidebar.classList.contains(
                        'collapsed'
                    );

                setSidebarCollapsed(
                    !isCollapsed
                );
            }
        );
    }


    /* =========================================================
       SIDEBAR DROPDOWNS
    ========================================================= */

    const getDropdownKey = (dropdown) => {
        const button = dropdown?.querySelector('.dropdown-toggle');
        const raw = button?.getAttribute('title') || button?.textContent || '';

        return raw
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const readDropdownState = () => {
        try {
            const raw = sessionStorage.getItem(dropdownStorageKey);
            const parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    };

    const writeDropdownState = (state) => {
        try {
            sessionStorage.setItem(dropdownStorageKey, JSON.stringify(state));
        } catch {
            // Keep sidebar usable even when sessionStorage is unavailable.
        }
    };

    const applyDropdownState = () => {
        const currentSidebar = document.getElementById('appSidebar');
        if (!currentSidebar) return;

        const saved = readDropdownState();

        currentSidebar.querySelectorAll('.menu-dropdown').forEach((dropdown) => {
            const key = getDropdownKey(dropdown);
            if (!key || !Object.prototype.hasOwnProperty.call(saved, key)) return;

            const open = saved[key] === true;
            dropdown.classList.toggle('open', open);
            dropdown.querySelector('.dropdown-toggle')?.setAttribute('aria-expanded', String(open));
        });
    };

    const persistDropdownState = (dropdown, open) => {
        const key = getDropdownKey(dropdown);
        if (!key) return;

        const saved = readDropdownState();
        saved[key] = Boolean(open);
        writeDropdownState(saved);
    };

    // Blade may initially open the parent for the current route. Preserve that
    // default only until the user explicitly opens/closes the group.
    applyDropdownState();

    document.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.dropdown-toggle'
                );

            if (!button) {
                return;
            }


            const dropdown =
                button.closest(
                    '.menu-dropdown'
                );

            if (!dropdown) {
                return;
            }


            event.preventDefault();


            /*
             * Expand sidebar first when user clicks
             * a dropdown while sidebar is collapsed.
             */
            if (
                sidebar &&
                sidebar.classList.contains(
                    'collapsed'
                )
            ) {

                setSidebarCollapsed(false);

                setTimeout(function () {

                    dropdown.classList.add(
                        'open'
                    );

                    button.setAttribute(
                        'aria-expanded',
                        'true'
                    );

                    persistDropdownState(dropdown, true);

                }, 150);

                return;
            }


            const isOpen =
                dropdown.classList.contains(
                    'open'
                );

            const nextOpen = !isOpen;


            dropdown.classList.toggle(
                'open',
                nextOpen
            );


            button.setAttribute(
                'aria-expanded',
                nextOpen
                    ? 'true'
                    : 'false'
            );

            persistDropdownState(dropdown, nextOpen);
        }
    );

    // Partial navigation re-syncs the server-rendered sidebar state. Restore the
    // user's manual dropdown choices immediately after the destination is ready.
    window.addEventListener('gct:navigation-ready', function () {
        applyDropdownState();
    });

    // Expose the restore helper so the partial-navigation layer can reapply it
    // immediately after syncing active links if needed.
    window.GCTSidebarState = Object.freeze({
        restoreDropdowns: applyDropdownState,
    });


    /* =========================================================
       PROFILE
    ========================================================= */

    const profileToggle =
        document.getElementById(
            'sidebarProfileToggle'
        );

    const profileMenu =
        document.getElementById(
            'sidebarProfileMenu'
        );


    /* =========================================================
       ACCOUNT PAGE LINKS
    ========================================================= */

    if (profileMenu) {
        profileMenu
            .querySelectorAll('.profile-menu-item')
            .forEach(function (item) {
                const label = item
                    .querySelector('span')
                    ?.textContent
                    ?.trim()
                    ?.toLowerCase();

                const accountUrl = label === 'profile'
                    ? '/account/profile'
                    : label === 'settings'
                        ? '/account/settings'
                        : null;

                if (!accountUrl) {
                    return;
                }

                if (item.tagName === 'A') {
                    item.setAttribute('href', accountUrl);
                    return;
                }

                item.disabled = false;

                item.addEventListener('click', function () {
                    window.location.assign(accountUrl);
                });
            });
    }


    function closeProfileMenu() {

        if (
            !profileToggle ||
            !profileMenu
        ) {
            return;
        }


        profileMenu.classList.remove(
            'show'
        );

        profileToggle.classList.remove(
            'active'
        );

        profileToggle.setAttribute(
            'aria-expanded',
            'false'
        );
    }


    function toggleProfileMenu() {

        if (
            !profileToggle ||
            !profileMenu
        ) {
            return;
        }


        const isOpen =
            profileMenu.classList.contains(
                'show'
            );


        if (isOpen) {

            closeProfileMenu();

        } else {

            profileMenu.classList.add(
                'show'
            );

            profileToggle.classList.add(
                'active'
            );

            profileToggle.setAttribute(
                'aria-expanded',
                'true'
            );
        }
    }


    if (
        profileToggle &&
        profileMenu
    ) {

        profileToggle.addEventListener(
            'click',
            function (event) {

                event.preventDefault();
                event.stopPropagation();

                toggleProfileMenu();
            }
        );


        profileMenu.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();
            }
        );


        document.addEventListener(
            'click',
            function () {

                closeProfileMenu();
            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {
                    closeProfileMenu();
                }
            }
        );
    }


    /* =========================================================
       MOBILE
    ========================================================= */

    window.addEventListener(
        'resize',
        function () {

            if (
                window.innerWidth <= 768 &&
                sidebar
            ) {

                setSidebarCollapsed(false);
            }
        }
    );

});
