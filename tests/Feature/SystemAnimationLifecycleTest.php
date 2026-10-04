<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemAnimationLifecycleTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(resource_path('js/Main-js/system-animations.js'));
    }

    public function test_all_business_modules_are_registered_in_shared_config(): void
    {
        $source = $this->source();

        foreach (['admin', 'operation', 'maintenance', 'warehouse', 'purchase'] as $module) {
            $this->assertStringContainsString("    {$module}: Object.freeze({", $source);
        }

        $this->assertStringContainsString('const MODULE_PAGE_CONFIG', $source);
        $this->assertStringContainsString('const MAJOR_PANEL_SELECTOR', $source);
        $this->assertStringContainsString('Array.from(root.children)', $source);
    }

    public function test_page_reveal_is_single_pass_and_idempotent(): void
    {
        $source = $this->source();

        foreach (['preparing', 'ready', 'revealing', 'shown'] as $state) {
            $this->assertStringContainsString("'{$state}'", $source);
        }

        $this->assertStringContainsString('pageState.animation?.kill()', $source);
        $this->assertStringContainsString('gsap.killTweensOf(pageState.panels)', $source);
        $this->assertStringContainsString('pageState.animation = gsap.to(pageState.panels', $source);
        $this->assertStringNotContainsString('gsap.fromTo(root', $source);
        $this->assertStringContainsString("ease: 'power3.out'", $source);
        $this->assertStringContainsString('initialOpen ? 0.82 : 0.74', $source);
        $this->assertStringContainsString('initialOpen ? 0.065 : 0.055', $source);
    }

    public function test_shared_modal_lifecycle_has_open_close_and_duplicate_guards(): void
    {
        $source = $this->source();
        $backdrop = file_get_contents(resource_path('js/Main-js/global-modal-backdrop.js'));

        $this->assertStringContainsString('const animateModalOpen', $source);
        $this->assertStringContainsString('const animateModalClose', $source);
        $this->assertStringContainsString("existing?.phase === 'opening'", $source);
        $this->assertStringContainsString("previous.phase === 'closing'", $source);
        $this->assertStringContainsString('scale: reduced ? 0.99 : 0.975', $source);
        $this->assertStringContainsString('preventBackdropDismissal', $backdrop);
    }

    public function test_toasts_use_shared_subtle_enter_and_exit_hooks(): void
    {
        $source = $this->source();
        $toasts = file_get_contents(resource_path('js/Main-js/system-toast.js'));

        $this->assertStringContainsString('const animateToastIn', $source);
        $this->assertStringContainsString('const animateToastOut', $source);
        $this->assertStringContainsString('?.animateToastIn?.(toast)', $toasts);
        $this->assertStringContainsString('?.animateToastOut?.(toast, finishRemoval)', $toasts);
    }

    public function test_ajax_refresh_animates_regions_without_replaying_page_reveal(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("'system:region-replaced'", $source);
        $this->assertStringContainsString("'ajax:content-updated'", $source);
        $this->assertStringContainsString('queueRegionAnimation', $source);
        $this->assertStringContainsString('pendingRegionElements', $source);

        $ajaxListener = substr(
            $source,
            strpos($source, "document.addEventListener('ajax:content-updated'"),
            400
        );

        $this->assertStringNotContainsString('revealPage(', $ajaxListener);
        $this->assertStringNotContainsString('showLoader(', $ajaxListener);
    }

    public function test_partial_navigation_registers_one_shared_initializer(): void
    {
        $source = $this->source();

        $this->assertSame(1, substr_count($source, "'system-animations'"));
        $this->assertStringContainsString("'main.main'", $source);
        $this->assertStringContainsString(
            "window.addEventListener('gct:navigation-before', resetPageReveal)",
            $source
        );
    }
}
