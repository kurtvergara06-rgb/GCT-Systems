<?php

namespace Tests\Feature;

use Tests\TestCase;

class PartialNavigationLifecycleTest extends TestCase
{
    public function test_partial_navigation_cleans_and_replaces_page_owned_ui(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));

        $this->assertStringContainsString('const cleanupPageOverlays = () => {', $source);
        $this->assertStringContainsString("'[data-page-owned]'", $source);
        $this->assertStringContainsString('syncPageOwnedElements(nextDocument);', $source);
        $this->assertStringContainsString('document.body.classList.remove(...transientClasses)', $source);
        $this->assertStringContainsString("element.style.removeProperty('overflow')", $source);
        $this->assertStringContainsString("document.querySelectorAll('dialog[open]')", $source);
        $hasBodyOverlayDiscovery = str_contains(
            $source,
            'document.body.querySelectorAll(PAGE_OVERLAY_SELECTOR)'
        ) || str_contains(
            $source,
            'Array.from(document.body.children)'
        );

        $this->assertTrue(
            $hasBodyOverlayDiscovery,
            'Partial navigation must discover body-level page overlays before replacing the page.'
        );
        $this->assertStringContainsString('Array.from(nextDocument.body.children)', $source);
        $this->assertStringContainsString('currentApp.className = nextApp.className', $source);
        $this->assertStringNotContainsString(
            'if (link.closest(\'#appSidebar[data-gct-shell="maintenance"]\')) return false;',
            $source
        );
    }

    public function test_cleanup_runs_before_the_old_main_fades_out(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
        $start = strpos($source, 'const beginMainExit = (main) => {');
        $end = strpos($source, 'const shellId', $start);
        $lifecycle = substr($source, $start, $end - $start);

        $beforeEvent = strpos($lifecycle, 'BEFORE_NAVIGATION_EVENT');
        $cleanup = strpos($lifecycle, 'cleanupPageOverlays();');
        $leaving = strpos($lifecycle, "main.classList.add('gct-main-leaving')");

        $this->assertNotFalse($beforeEvent);
        $this->assertNotFalse($cleanup);
        $this->assertNotFalse($leaving);
        $this->assertLessThan($cleanup, $beforeEvent);
        $this->assertLessThan($leaving, $cleanup);
    }

    public function test_transition_timing_and_reduced_motion_are_bounded(): void
    {
        $styles = file_get_contents(resource_path('css/Main-styles/page-transitions.css'));

        $this->assertStringContainsString('transition-duration: 120ms', $styles);
        $this->assertStringContainsString('opacity 180ms', $styles);
        $this->assertStringContainsString('translateY(2px)', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
    }
}
