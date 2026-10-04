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
        $this->assertStringContainsString('showPageImmediately', $source);
        $this->assertStringContainsString(".catch((error) => {", $source);
    }

    public function test_navigation_reveal_rejects_stale_async_completions(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/page-transitions.js'));

        $this->assertStringContainsString('let navigationSequence = 0;', $source);
        $this->assertStringContainsString('sequence === navigationSequence', $source);
        $this->assertStringContainsString('incomingMain === getMainElement()', $source);
        $this->assertStringContainsString('isCurrent: isCurrentNavigation', $source);
        $this->assertStringContainsString('systemAnimations.showPageImmediately?.()', $source);
        $this->assertStringContainsString('if (event.persisted)', $source);
    }

    public function test_shared_modal_lifecycle_has_open_close_and_duplicate_guards(): void
    {
        $source = $this->source();
        $backdrop = file_get_contents(resource_path('js/Main-js/global-modal-backdrop.js'));

        $this->assertStringContainsString('const animateModalOpen', $source);
        $this->assertStringContainsString('const animateModalClose', $source);
        $this->assertStringContainsString("existing?.phase === 'opening'", $source);
        $this->assertStringContainsString("previous.phase === 'closing'", $source);
        $this->assertStringContainsString("state?.phase === 'opening' || state?.phase === 'closing'", $source);
        $this->assertStringContainsString('scale: reduced ? 0.99 : 0.975', $source);
        $this->assertStringContainsString('preventBackdropDismissal', $backdrop);
        $this->assertStringContainsString('const observeOverlay = (overlay) => {', $source);
        $this->assertStringContainsString("surface?.classList.add('gct-system-modal-surface-animated')", $source);
        $this->assertStringContainsString("surface?.classList.remove('gct-system-modal-surface-animated')", $source);
        $this->assertStringContainsString("attributeFilter: ['class', 'style', 'hidden', 'aria-hidden']", $source);
        $this->assertStringContainsString("modalObserver.observe(document.body, {\n        subtree: true,\n        childList: true,\n    });", $source);
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
        $this->assertStringContainsString("'system:table-filtered'", $source);
        $this->assertStringContainsString('table?.tBodies?.[0]', $source);

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

    public function test_module_roots_are_explicit_instead_of_using_a_broad_main_fallback(): void
    {
        $source = $this->source();
        $operationDashboard = file_get_contents(resource_path('views/Operation/dashboard-operation.blade.php'));
        $purchaseHistory = file_get_contents(resource_path('views/Purchase/purchase-history.blade.php'));

        $this->assertStringContainsString('.operation-dashboard-main', $source);
        $this->assertStringContainsString('.purchase-history-page', $source);
        $this->assertDoesNotMatchRegularExpression('/roots:\s*[^\n]*\bmain\.main\b/', $source);
        $this->assertStringContainsString('class="main operation-dashboard-main"', $operationDashboard);
        $this->assertStringContainsString('class="main purchase-history-page"', $purchaseHistory);
    }
}
