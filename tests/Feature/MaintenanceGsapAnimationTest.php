<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceGsapAnimationTest extends TestCase
{
    public function test_shared_maintenance_gsap_module_is_loaded(): void
    {
        $package = file_get_contents(base_path('package.json'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            '"gsap": "^3.15.0"',
            $package
        );

        $this->assertStringContainsString(
            "import './Maintenance/maintenance-animations.js';",
            $app
        );
    }

    public function test_shared_animation_module_covers_maintenance_pages_and_sidebar(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );

        foreach ([
            '.maintenance-dashboard-main',
            '.referrals-page',
            '.jo-page',
            '.pms-page',
            '.mechanic-page',
            '.fuel-page',
            '.purchase-page',
        ] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $source
            );
        }

        $this->assertStringContainsString(
            "gctShell === 'maintenance'",
            $source
        );
        $this->assertStringContainsString(
            'animateOpenedSubmenu',
            $source
        );
        $this->assertStringContainsString(
            'animateSidebarActiveItem',
            $source
        );
        $this->assertStringContainsString(
            'x: 10',
            $source
        );
    }

    public function test_maintenance_motion_stays_simple_and_non_repeating(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );

        $this->assertStringContainsString(
            '(prefers-reduced-motion: reduce)',
            $source
        );
        $this->assertStringContainsString(
            'startScale',
            $source
        );
        $this->assertStringContainsString(
            '? 0.78',
            $source
        );
        $this->assertStringContainsString(
            ': 1.15',
            $source
        );
        $this->assertStringContainsString(
            'duration:',
            $source
        );
        $this->assertStringNotContainsString(
            'repeat:',
            $source
        );
        $this->assertStringNotContainsString(
            'yoyo:',
            $source
        );
        $this->assertStringNotContainsString(
            'back.out',
            $source
        );
    }

    public function test_navigation_hides_layout_construction_and_reveals_only_stable_maintenance_ui(): void
    {
        $shared = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );
        $transitions = file_get_contents(
            resource_path('js/Main-js/page-transitions.js')
        );

        $this->assertStringContainsString(
            'prepareMaintenanceReveal',
            $shared
        );
        $this->assertStringContainsString(
            'document.fonts.ready',
            $shared
        );
        $this->assertStringContainsString(
            'measureStableMaintenanceLayout',
            $shared
        );
        $this->assertStringContainsString(
            'getBoundingClientRect()',
            $shared
        );
        $this->assertStringContainsString(
            'window.GCTMaintenanceReveal',
            $shared
        );
        $this->assertStringContainsString(
            'revealMaintenancePage',
            $shared
        );

        $this->assertStringContainsString(
            'await maintenanceReveal.prepare()',
            $transitions
        );
        $this->assertStringContainsString(
            'await hideLoader({',
            $transitions
        );
        $this->assertStringContainsString(
            'maintenanceReveal.reveal()',
            $transitions
        );

        $hidePosition = strpos(
            $transitions,
            'await hideLoader({'
        );
        $revealPosition = strpos(
            $transitions,
            'maintenanceReveal.reveal()'
        );

        $this->assertNotFalse($hidePosition);
        $this->assertNotFalse($revealPosition);
        $this->assertGreaterThan(
            $hidePosition,
            $revealPosition
        );

        // Initial page entry must not animate cards/tables into position.
        $this->assertStringNotContainsString(
            'animateMaintenancePage',
            $shared
        );
    }

    public function test_visible_motion_strength_is_intentional_without_per_card_page_assembly(): void
    {
        $shared = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );
        $jobOrder = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'initialOpen',
            $shared
        );
        $this->assertStringContainsString(
            'startScale',
            $shared
        );
        $this->assertStringContainsString(
            'surfaceY = reduced ? 20 : 38',
            $shared
        );
        $this->assertStringContainsString(
            'surfaceScale = reduced ? 0.975 : 0.94',
            $shared
        );
        $this->assertStringContainsString(
            'x: 10',
            $shared
        );

        $this->assertStringContainsString(
            'surfaceY = isReduced ? 20 : 38',
            $jobOrder
        );
        $this->assertStringContainsString(
            'surfaceScale = isReduced ? 0.975 : 0.94',
            $jobOrder
        );

        $this->assertStringNotContainsString(
            'animateJobOrderPageEntrance',
            $jobOrder
        );
        $this->assertStringNotContainsString(
            'animateMaintenancePage',
            $shared
        );
    }

    public function test_direct_page_open_uses_a_slow_visible_gsap_reveal(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );

        $this->assertStringContainsString(
            'initialOpen = false',
            $source
        );
        $this->assertStringContainsString(
            'initialOpen',
            $source
        );
        $this->assertStringContainsString(
            '? (reduced ? 34 : 72)',
            $source
        );
        $this->assertStringContainsString(
            '? (reduced ? 0.975 : 0.94)',
            $source
        );
        $this->assertStringContainsString(
            '? (reduced ? 0.78 : 1.15)',
            $source
        );
        $this->assertStringContainsString(
            'initialOpen: true',
            $source
        );
        $this->assertStringContainsString(
            '120',
            $source
        );
    }

    public function test_navigation_loader_has_a_visible_spinner_even_with_reduced_motion(): void
    {
        $javascript = file_get_contents(
            resource_path('js/Main-js/page-transitions.js')
        );
        $styles = file_get_contents(
            resource_path('css/Main-styles/page-transitions.css')
        );

        $this->assertStringContainsString(
            'const MIN_LOADER_MS = 280;',
            $javascript
        );
        $this->assertStringContainsString(
            'gct-navigation-loader__spinner',
            $javascript
        );
        $this->assertStringContainsString(
            'width: 30px;',
            $styles
        );
        $this->assertStringContainsString(
            'border-right-color: #061f3d;',
            $styles
        );
        $this->assertStringContainsString(
            'animation: gctNavigationSpin 620ms linear infinite;',
            $styles
        );
        $this->assertStringContainsString(
            'animation: gctNavigationSpin 820ms linear infinite !important;',
            $styles
        );
    }

    public function test_ajax_refreshes_and_modals_keep_lightweight_motion(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );

        $this->assertStringContainsString(
            "'ajax:content-updated'",
            $source
        );
        $this->assertStringContainsString(
            'animateSummaryRefresh',
            $source
        );
        $this->assertStringContainsString(
            'animateRows',
            $source
        );
        $this->assertStringContainsString(
            'animateModalOpen',
            $source
        );
        $this->assertStringContainsString(
            '.part-needed-row',
            $source
        );
    }

    public function test_job_order_initial_layout_is_static_while_interactions_still_animate(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringNotContainsString(
            'forceGsapDemoMotion',
            $source
        );
        $this->assertStringNotContainsString(
            'back.out',
            $source
        );
        $this->assertStringNotContainsString(
            'rotateX',
            $source
        );
        $this->assertStringNotContainsString(
            '[JO GSAP demo]',
            $source
        );
        $this->assertStringNotContainsString(
            'animateJobOrderPageEntrance',
            $source
        );
        $this->assertStringNotContainsString(
            'waitForJobOrderReveal',
            $source
        );
        $this->assertStringContainsString(
            'function openModal',
            $source
        );
        $this->assertStringContainsString(
            'function animatePartRowIn',
            $source
        );
        $this->assertStringContainsString(
            '(prefers-reduced-motion: reduce)',
            $source
        );
    }
}
