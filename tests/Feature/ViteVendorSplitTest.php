<?php

namespace Tests\Feature;

use Tests\TestCase;

class ViteVendorSplitTest extends TestCase
{
    public function test_vite_uses_rolldown_vendor_groups_for_large_reusable_dependencies(): void
    {
        $config = file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString(
            'rolldownOptions',
            $config
        );
        $this->assertStringContainsString(
            'codeSplitting',
            $config
        );
        $this->assertStringContainsString(
            "name: 'vendor-charts'",
            $config
        );
        $this->assertStringContainsString(
            "name: 'vendor-realtime'",
            $config
        );
        $this->assertStringContainsString(
            'chart\\.js|@kurkle',
            $config
        );
        $this->assertStringContainsString(
            'laravel-echo|pusher-js|tweetnacl',
            $config
        );
        $this->assertStringNotContainsString(
            'manualChunks',
            $config
        );
    }
}
