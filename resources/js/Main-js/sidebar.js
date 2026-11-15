document.addEventListener('DOMContentLoaded', function () {

    const body = document.body;
    const html = document.documentElement;

    const sidebar =
        document.getElementById('appSidebar');

    const collapseBtn =
        document.getElementById('sidebarCollapseBtn');


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

                }, 150);

                return;
            }


            const isOpen =
                dropdown.classList.contains(
                    'open'
                );


            dropdown.classList.toggle(
                'open',
                !isOpen
            );


            button.setAttribute(
                'aria-expanded',
                !isOpen
                    ? 'true'
                    : 'false'
            );
        }
    );


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