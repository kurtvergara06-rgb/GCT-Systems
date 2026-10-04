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
            'x: 5',
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
            'scale: 0.975',
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
            'beforeFade',
            $transitions
        );
        $this->assertStringContainsString(
            'await maintenanceReveal.prepare()',
            $transitions
        );
        $this->assertStringContainsString(
            'maintenanceReveal.reveal()',
            $transitions
        );

        // Initial page entry must not animate cards/tables into position.
        $this->assertStringNotContainsString(
            'animateMaintenancePage',
            $shared
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
