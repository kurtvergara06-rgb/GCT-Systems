<?php

namespace Tests\Feature;

use Tests\TestCase;

class OperationDarkModeFoundationTest extends TestCase
{
    public function test_shared_theme_defines_dark_tokens_only_for_operation_pages(): void
    {
        $theme = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $this->assertStringContainsString('html[data-gct-theme="dark"] body[data-gct-operation-page="1"]', $theme);

        foreach (['--gct-theme-canvas', '--gct-theme-surface', '--gct-theme-fg', '--gct-theme-border', '--bg', '--card', '--text'] as $token) {
            $this->assertStringContainsString($token.':', $theme);
        }

        $this->assertStringContainsString('--bg-main: var(--gct-theme-canvas)', $theme);
    }

    public function test_operation_stylesheet_is_scoped_and_loaded_after_existing_page_assets(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $css = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString("str_starts_with(\$asset, 'resources/css/Operation/')", $layout);
        $this->assertStringContainsString('data-gct-operation-page="1"', $layout);
        $this->assertStringContainsString("['resources/css/Operation/operation-dark-mode.css']", $layout);
        $this->assertStringContainsString('resources/css/Operation/operation-dark-mode.css', $vite);
        $this->assertStringContainsString('html[data-gct-theme="dark"] body[data-gct-operation-page="1"]', $css);
        $this->assertStringNotContainsString('!important', $css);
        $this->assertStringNotContainsString('background: #000', $css);
    }

    public function test_existing_dark_styles_are_scoped_and_identifiers_use_theme_tokens(): void
    {
        $preferencesCss = file_get_contents(resource_path('css/Main-styles/account-preferences.css'));
        $sharedUiCss = file_get_contents(resource_path('css/Main-styles/shared-ui-enhancements.css'));
        $identifierCss = file_get_contents(resource_path('css/Main-styles/identifier-text.css'));
        $themeCss = file_get_contents(resource_path('css/Main-styles/theme.css'));
        $operationCss = file_get_contents(resource_path('css/Operation/operation-dark-mode.css'));
        $tripCss = file_get_contents(resource_path('css/Operation/Scheduling_And_Dispatch/trip-schedule.css'));
        $preferencesJs = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

        $this->assertStringContainsString('html[data-gct-theme="dark"]:has(.account-page)', $preferencesCss);
        $this->assertStringNotContainsString('html[data-gct-theme="dark"] :is(body,.app,.main,.account-page,.account-main)', $preferencesCss);
        $this->assertStringContainsString('var(--gct-id-background, #f8fbff)', $sharedUiCss);
        $this->assertStringContainsString('var(--gct-id-foreground, #111827)', $identifierCss);
        $this->assertStringContainsString('--gct-id-foreground: #e6f0ff', $themeCss);
        $this->assertStringContainsString('var(--gct-trip-group-heading-bg, #eaf1fb) !important', $tripCss);
        $this->assertStringContainsString('var(--gct-theme-surface)', $operationCss);
        $this->assertStringContainsString("document.body.dataset.gctOperationPage === '1'", $preferencesJs);
        $this->assertStringContainsString("root.style.colorScheme = darkPageEnabled ? effectiveTheme : 'light'", $preferencesJs);
    }

    public function test_saved_theme_hydrates_before_vite_and_scope_tracks_partial_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $navigation = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
        $preferences = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

        $this->assertStringContainsString("'gct:ui-preferences:'.auth()->id()", $layout);
        $this->assertStringContainsString('(prefers-color-scheme: dark)', $layout);
        $this->assertLessThan(
            strpos($layout, '@vite($viteAssets)'),
            strpos($layout, 'document.documentElement.dataset.gctTheme = theme')
        );
        $this->assertStringContainsString("nextDocument.body.dataset.gctOperationPage === '1'", $navigation);
        $this->assertStringContainsString('delete document.body.dataset.gctOperationPage', $navigation);
        $this->assertStringContainsString("theme: 'light'", $preferences);
        $this->assertStringContainsString("['light', 'dark', 'system']", $preferences);
    }
}
