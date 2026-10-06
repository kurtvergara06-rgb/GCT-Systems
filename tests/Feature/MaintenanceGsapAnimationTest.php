<?php

namespace Tests\Feature;

use Tests\TestCase;

class MaintenanceGsapAnimationTest extends TestCase
{
    public function test_maintenance_uses_the_shared_system_animation_owner(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $maintenance = file_get_contents(resource_path('js/Maintenance/maintenance-animations.js'));

        $this->assertStringContainsString("import './Main-js/system-animations.js';", $app);
        $this->assertStringContainsString('window.GCTSystemAnimations', $maintenance);
        $this->assertStringNotContainsString('prepareMaintenanceReveal', $maintenance);
        $this->assertStringNotContainsString('revealMaintenancePage', $maintenance);
        $this->assertStringNotContainsString('animateDashboardPanels', $maintenance);
    }

    public function test_maintenance_pages_remain_registered_for_page_specific_interactions(): void
    {
        $source = file_get_contents(resource_path('js/Maintenance/maintenance-animations.js'));

        foreach ([
            '.maintenance-dashboard-main',
            '.referrals-page',
            '.jo-page',
            '.pms-page',
            '.mechanic-page',
            '.fuel-page',
            '.purchase-page',
        ] as $selector) {
            $this->assertStringContainsString($selector, $source);
        }

        $this->assertStringContainsString("'maintenance-simple-gsap'", $source);
    }

    public function test_sidebar_dropdown_animation_is_not_reintroduced(): void
    {
        $source = file_get_contents(resource_path('js/Maintenance/maintenance-animations.js'));

        $this->assertStringNotContainsString('animateOpenedSubmenu', $source);
        $this->assertStringContainsString('animateSidebarActiveItem', $source);
    }

    public function test_job_order_modals_delegate_to_the_shared_modal_lifecycle(): void
    {
        $source = file_get_contents(resource_path('js/Maintenance/job-order.js'));

        $this->assertStringContainsString('function openModal', $source);
        $this->assertStringContainsString('function closeModal', $source);
        $this->assertStringContainsString('?.animateModalOpen?.(modal)', $source);
        $this->assertStringContainsString('?.animateModalClose?.(', $source);
        $this->assertStringContainsString('function animatePartRowIn', $source);
        $this->assertStringNotContainsString('surfaceY = isReduced ? 20 : 38', $source);
    }

    public function test_loader_finishes_before_shared_page_reveal(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/page-transitions.js'));
        $hidePosition = strpos($source, 'await hideLoader({');
        $revealPosition = strpos($source, 'systemAnimations.revealPage()');

        $this->assertNotFalse($hidePosition);
        $this->assertNotFalse($revealPosition);
        $this->assertGreaterThan($hidePosition, $revealPosition);
        $this->assertStringContainsString('revealMain: false', $source);
    }

    public function test_navigation_loader_keeps_visible_progress_feedback(): void
    {
        $javascript = file_get_contents(resource_path('js/Main-js/page-transitions.js'));
        $styles = file_get_contents(resource_path('css/Main-styles/page-transitions.css'));

        $this->assertStringContainsString('const MIN_LOADER_MS = 280;', $javascript);
        $this->assertStringContainsString('gct-navigation-loader__spinner', $javascript);
        $this->assertStringContainsString(
            'animation: gctNavigationSpin 620ms linear infinite;',
            $styles
        );
    }

    public function test_global_confirmation_uses_shared_modal_animation_lifecycle(): void
    {
        $confirmation = file_get_contents(
            resource_path('js/Main-js/confirmation-modal.js')
        );
        $animations = file_get_contents(
            resource_path('js/Main-js/system-animations.js')
        );
        $styles = file_get_contents(
            resource_path('css/Main-styles/page-transitions.css')
        );

        $this->assertStringContainsString(
            '?.animateModalOpen?.(modal)',
            $confirmation
        );
        $this->assertStringContainsString(
            '?.animateModalClose?.(',
            $confirmation
        );
        $this->assertStringContainsString(
            "force3D: true",
            $animations
        );
        $this->assertStringContainsString(
            "ease: 'power2.out'",
            $animations
        );
        $this->assertStringContainsString(
            'will-change: opacity, transform;',
            $styles
        );
    }

}
