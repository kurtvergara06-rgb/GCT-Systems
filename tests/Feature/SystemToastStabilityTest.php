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

    public function test_dynamic_toasts_use_one_resettable_removal_lifecycle(): void
    {
        $source = file_get_contents(resource_path('js/Main-js/system-toast.js'));

        $this->assertStringContainsString('const toastTimers = new WeakMap();', $source);
        $this->assertStringContainsString('const scheduleToastRemoval =', $source);
        $this->assertStringContainsString(
            'attachToastBehavior(toast, options.timeout ?? removeDelay);',
            $source
        );

        $this->assertStringNotContainsString(
            "window.setTimeout(() => {\n        toast.classList.add('is-removing');",
            $source
        );
    }
}
