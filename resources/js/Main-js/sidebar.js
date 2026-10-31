import gsap from 'gsap';

document.addEventListener('DOMContentLoaded', function () {

    const body = document.body;
    const html = document.documentElement;

    const sidebar =
        document.getElementById('appSidebar');

    const collapseBtn =
        document.getElementById('sidebarCollapseBtn');

    const shellId = sidebar?.dataset.gctShell || 'default';
    const dropdownStorageKey = `gct-sidebar-dropdowns:${shellId}`;
    let sidebarMotionTimer = null;
    let sidebarObserver = null;

    const prefersReducedMotion = () => {
        if (
            window.__GCT_FORCE_MOTION__ === true
            || window.__GCT_ENABLE_MOTION__ === true
            || document.documentElement.dataset.gctMotion === 'enabled'
        ) {
            return false;
        }

        return (
            window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false
        );
    };

    const animateSidebarActiveItem = () => {
        const activeItems = document.querySelectorAll(
            '#appSidebar .menu-item.active, '
            + '#appSidebar .submenu-item.active'
        );

        if (!activeItems.length) return;

        const reduced = prefersReducedMotion();
        const xOffset = reduced ? -7 : -14;

        gsap.fromTo(
            activeItems,
            {
                opacity: 0.55,
                x: xOffset,
            },
            {
                opacity: 1,
                x: 0,
                duration: reduced ? 0.28 : 0.38,
                ease: 'power3.out',
                clearProps: 'opacity,transform',
            }
        );
    };

    const animateSidebarShellState = () => {
        if (!sidebar) return;

        const reduced = prefersReducedMotion();
        const icons = sidebar.querySelectorAll(
            '.brand-icon, '
            + '.menu-item > i, '
            + '.submenu-item > i'
        );

        if (!icons.length) return;

        gsap.fromTo(
            icons,
            {
                opacity: reduced ? 0.78 : 0.6,
                scale: reduced ? 0.97 : 0.92,
            },
            {
                opacity: 1,
                scale: 1,
                duration: reduced ? 0.18 : 0.26,
                stagger: reduced ? 0.01 : 0.02,
                ease: 'power2.out',
                clearProps: 'opacity,transform',
            }
        );
    };

    const ensureSidebarObserver = () => {
        sidebarObserver?.disconnect();
        sidebarObserver = null;

        if (!sidebar) return;

        sidebarObserver = new MutationObserver((mutations) => {
            if (mutations.some((mutation) => mutation.target === sidebar)) {
                animateSidebarShellState();
            }
        });

        sidebarObserver.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class'],
        });
    };

    const ensureSidebarMotionStyles = () => {
        if (document.getElementById('gctSidebarMotionStyles')) return;

        const style = document.createElement('style');
        style.id = 'gctSidebarMotionStyles';
        style.textContent = `
            #appSidebar,
            .main {
                transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1) !important;
                transition-duration: 320ms !important;
            }

            #appSidebar .brand-text,
            #appSidebar .menu-item > span,
            #appSidebar .dropdown-arrow,
            #appSidebar .user-box-text,
            #appSidebar .profile-chevron {
                transition: opacity 150ms ease, transform 150ms ease !important;
                will-change: opacity, transform;
            }

            #appSidebar .submenu {
                transition: opacity 130ms ease !important;
            }

            #appSidebar.is-collapsing .brand-text,
            #appSidebar.is-collapsing .menu-item > span,
            #appSidebar.is-collapsing .dropdown-arrow,
            #appSidebar.is-collapsing .user-box-text,
            #appSidebar.is-collapsing .profile-chevron,
            #appSidebar.is-expanding .brand-text,
            #appSidebar.is-expanding .menu-item > span,
            #appSidebar.is-expanding .dropdown-arrow,
            #appSidebar.is-expanding .user-box-text,
            #appSidebar.is-expanding .profile-chevron {
                opacity: 0 !important;
                transform: translateX(-4px) !important;
                pointer-events: none !important;
            }

            #appSidebar.is-collapsing .submenu,
            #appSidebar.is-expanding .submenu {
                opacity: 0 !important;
                pointer-events: none !important;
            }

            #appSidebar.is-collapsing .menu,
            #appSidebar.is-expanding .menu {
                overflow: hidden;
            }

            @media (prefers-reduced-motion: reduce) {
                #appSidebar,
                .main,
                #appSidebar .brand-text,
                #appSidebar .menu-item > span,
                #appSidebar .dropdown-arrow,
                #appSidebar .user-box-text,
                #appSidebar .profile-chevron,
                #appSidebar .submenu {
                    transition: none !important;
                }
            }
        `;
        document.head.appendChild(style);
    };

    ensureSidebarMotionStyles();
    ensureSidebarObserver();
    animateSidebarActiveItem();


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

    function updateCollapseButton(isCollapsed) {
        if (!collapseBtn) return;

        collapseBtn.setAttribute(
            'aria-expanded',
            isCollapsed ? 'false' : 'true'
        );

        collapseBtn.setAttribute(
            'title',
            isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'
        );
    }

    function finishSidebarMotion() {
        if (!sidebar) return;

        sidebar.classList.remove('is-collapsing', 'is-expanding');
        if (sidebarMotionTimer) {
            window.clearTimeout(sidebarMotionTimer);
            sidebarMotionTimer = null;
        }
    }

    function setSidebarCollapsed(isCollapsed) {

        if (!sidebar) {
            return;
        }

        finishSidebarMotion();

        html.classList.remove(
            'sidebar-start-collapsed'
        );

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            sidebar.classList.toggle('collapsed', isCollapsed);
            body.classList.toggle('sidebar-collapsed', isCollapsed);
            updateCollapseButton(isCollapsed);
            return;
        }

        if (isCollapsed) {
            // Hide labels/submenus first so they never get squeezed while the
            // sidebar width is shrinking.
            sidebar.classList.add('is-collapsing');

            window.setTimeout(() => {
                sidebar.classList.add('collapsed');
                body.classList.add('sidebar-collapsed');
                updateCollapseButton(true);
            }, 120);

            sidebarMotionTimer = window.setTimeout(() => {
                finishSidebarMotion();
            }, 470);

            return;
        }

        // Expand the shell first while labels remain hidden, then reveal the
        // labels after the width transition has nearly completed.
        sidebar.classList.add('is-expanding');
        sidebar.classList.remove('collapsed');
        body.classList.remove('sidebar-collapsed');
        updateCollapseButton(false);

        sidebarMotionTimer = window.setTimeout(() => {
            finishSidebarMotion();
        }, 360);
    }


    /* =========================================================
       SIDEBAR TOGGLE
    ========================================================= */

    if (collapseBtn && sidebar) {

        collapseBtn.addEventListener(
            'click',
            function () {

                if (sidebar.classList.contains('is-collapsing') || sidebar.classList.contains('is-expanding')) {
                    return;
                }

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

    document.addEventListener('pointerover', function (event) {
        const item = event.target.closest?.(
            '#appSidebar .menu-item, '
            + '#appSidebar .submenu-item'
        );

        if (
            !item
            || (
                event.relatedTarget instanceof Node
                && item.contains(event.relatedTarget)
            )
        ) {
            return;
        }

        const hoverTargets = item.querySelectorAll(
            ':scope > i:not(.dropdown-arrow), :scope > span'
        );

        gsap.to(hoverTargets.length ? hoverTargets : item, {
            x: 10,
            duration: 0.24,
            ease: 'power2.out',
            overwrite: true,
        });
    });

    document.addEventListener('pointerout', function (event) {
        const item = event.target.closest?.(
            '#appSidebar .menu-item, '
            + '#appSidebar .submenu-item'
        );

        if (
            !item
            || (
                event.relatedTarget instanceof Node
                && item.contains(event.relatedTarget)
            )
        ) {
            return;
        }

        const hoverTargets = item.querySelectorAll(
            ':scope > i:not(.dropdown-arrow), :scope > span'
        );

        gsap.to(hoverTargets.length ? hoverTargets : item, {
            x: 0,
            duration: 0.24,
            ease: 'power2.out',
            overwrite: true,
            clearProps: 'transform',
        });
    });


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

                }, 400);

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
        ensureSidebarObserver();
        animateSidebarActiveItem();
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
