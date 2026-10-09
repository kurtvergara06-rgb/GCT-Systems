<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemUiSignalVisibilityTest extends TestCase
{
    public function test_shared_status_badges_expose_semantic_status_data(): void
    {
        $source = file_get_contents(
            resource_path('views/components/ui/status-badge.blade.php')
        );

        $this->assertStringContainsString(
            "'data-ui-component' => 'status-badge'",
            $source
        );
        $this->assertStringContainsString(
            "'data-status' =>",
            $source
        );
    }

    public function test_shared_action_buttons_expose_semantic_action_data(): void
    {
        $source = file_get_contents(
            resource_path('views/components/ui/action-button.blade.php')
        );

        $this->assertStringContainsString(
            'data-ui-component="action-button"',
            $source
        );
        $this->assertStringContainsString(
            'data-action="{{',
            $source
        );
    }

    public function test_global_styles_keep_sources_statuses_and_actions_visible(): void
    {
        $css = file_get_contents(
            resource_path('css/Main-styles/shared-ui-enhancements.css')
        );

        $this->assertStringContainsString(
            'SYSTEM-WIDE TABLE SIGNAL VISIBILITY',
            $css
        );
        $this->assertStringContainsString('.source-badge {', $css);
        $this->assertStringContainsString(
            '[data-ui-component="status-badge"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="view"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="create-po"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="approve"]',
            $css
        );
        $this->assertStringContainsString(
            '[data-ui-component="action-button"][data-action="delete"]',
            $css
        );
    }
}
