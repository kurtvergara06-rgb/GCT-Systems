<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomErrorPagesTest extends TestCase
{
    public function test_custom_error_views_exist_and_render_expected_content(): void
    {
        $pages = [
            '403' => ['Access denied', 'permission'],
            '404' => ['Page not found', 'could not be found'],
            '419' => ['Session expired', 'security token'],
            '500' => ['Something went wrong', 'unexpected problem'],
            '503' => ['Service temporarily unavailable', 'temporarily unable'],
        ];

        foreach ($pages as $code => [$title, $messageFragment]) {
            $viewName = "errors.{$code}";

            $this->assertTrue(
                view()->exists($viewName),
                "Expected {$viewName} to exist."
            );

            $html = view($viewName)->render();

            $this->assertStringContainsString($code, $html);
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString($messageFragment, $html);
            $this->assertStringContainsString('GCT Transport Services, Inc.', $html);
            $this->assertStringContainsString('Return to system', $html);
        }
    }

    public function test_error_layout_is_standalone_and_contains_recovery_actions(): void
    {
        $layout = file_get_contents(
            resource_path('views/errors/layout.blade.php')
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $layout);
        $this->assertStringContainsString("asset('img/gct_logo.png')", $layout);
        $this->assertStringContainsString("href=\"{{ url('/') }}\"", $layout);
        $this->assertStringContainsString('onclick="history.back()"', $layout);
        $this->assertStringContainsString('prefers-reduced-motion', $layout);
    }
}
