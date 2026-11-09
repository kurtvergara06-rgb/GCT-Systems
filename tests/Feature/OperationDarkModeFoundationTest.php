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

    public function test_saved_theme_hydrates_before_vite_and_scope_tracks_partial_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/components/layout/app.blade.php'));
        $navigation = file_get_contents(resource_path('js/Main-js/partial-navigation.js'));
        $preferences = file_get_contents(resource_path('js/Main-js/account-preferences.js'));

        $this->assertStringContainsString("'gct:ui-preferences:'.auth()->id()", $layout);
        $this->assertStringContainsString("'prefers-color-scheme: dark'", $layout);
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
