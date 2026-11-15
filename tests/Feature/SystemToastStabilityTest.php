<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemToastStabilityTest extends TestCase
{
    public function test_toast_styles_have_one_owner_and_do_not_use_zoom_like_transforms(): void
    {
        $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $toastCss = file_get_contents(resource_path('css/Main-styles/system-toast.css'));

        $this->assertStringNotContainsString('.system-toast-root {', $theme);
        $this->assertStringNotContainsString('.system-toast-notification {', $theme);
        $this->assertStringContainsString(
            'Toasts are defined in Main-styles/system-toast.css.',
            $theme
        );

        $this->assertStringContainsString('transform: none;', $toastCss);
        $this->assertStringNotContainsString('translateY(-12px)', $toastCss);
        $this->assertStringNotContainsString('translateY(-10px)', $toastCss);
    }

    public function test_all_transient_system_toasts_auto_dismiss_by_type(): void
    {
        $component = file_get_contents(
            resource_path(
                'views/components/ui/system-toast.blade.php'
            )
        );

        $javascript = file_get_contents(
            resource_path(
                'js/Main-js/system-toast.js'
            )
        );

        $this->assertStringNotContainsString(
            "'timeout' => 0",
            $component
        );

        $this->assertStringContainsString(
            'const toastTimeoutByType = {',
            $javascript
        );

        $this->assertStringContainsString(
            'error: 7000',
            $javascript
        );

        $this->assertStringContainsString(
            'warning: 6000',
            $javascript
        );

        $this->assertStringContainsString(
            'resolveToastTimeout',
            $javascript
        );

        $this->assertStringNotContainsString(
            'duration <= 0',
            $javascript
        );
    }

    public function test_dynamic_toasts_use_one_resettable_removal_lifecycle(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/system-toast.js'));

        $this->assertStringContainsString('const toastTimers = new WeakMap();', $source);
        $this->assertStringContainsString('const scheduleToastRemoval =', $source);
        $this->assertStringContainsString(
            'attachToastBehavior(',
            $source
        );

        $this->assertStringNotContainsString(
            "window.setTimeout(() => {\n        toast.classList.add('is-removing');",
            $source
        );
    }
}
