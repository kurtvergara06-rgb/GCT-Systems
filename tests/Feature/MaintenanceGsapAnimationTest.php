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
            "x: 5",
            $source
        );
    }

    public function test_maintenance_motion_is_subtle_and_respects_reduced_motion(): void
    {
        $source = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );

        $this->assertStringContainsString(
            '(prefers-reduced-motion: reduce)',
            $source
        );
        $this->assertStringContainsString(
            'y: 10',
            $source
        );
        $this->assertStringContainsString(
            'scale: 0.975',
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

    public function test_page_entrance_waits_until_navigation_loader_is_revealed(): void
    {
        $shared = file_get_contents(
            resource_path('js/Maintenance/maintenance-animations.js')
        );
        $jobOrder = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'waitForMaintenanceReveal',
            $shared
        );
        $this->assertStringContainsString(
            'gct-navigation-loading',
            $shared
        );
        $this->assertStringContainsString(
            'gct-main-loader-hold',
            $shared
        );
        $this->assertStringContainsString(
            'waitForJobOrderReveal',
            $jobOrder
        );
        $this->assertStringContainsString(
            'gct-navigation-loading',
            $jobOrder
        );
    }

    public function test_ajax_refreshes_and_modals_use_lightweight_motion(): void
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

    public function test_job_order_demo_motion_was_reduced_to_simple_motion(): void
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
        $this->assertStringContainsString(
            '(prefers-reduced-motion: reduce)',
            $source
        );
    }
}
