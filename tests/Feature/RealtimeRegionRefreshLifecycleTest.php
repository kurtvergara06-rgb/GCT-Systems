<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealtimeRegionRefreshLifecycleTest extends TestCase
{
    public function test_realtime_region_refresh_cannot_replace_a_newly_navigated_page(): void
    {
        $source = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString('let realtimeRegionRefreshController = null;', $source);
        $this->assertStringContainsString('realtimeRegionRefreshController?.abort();', $source);
        $this->assertStringContainsString('signal: controller.signal', $source);
        $this->assertStringContainsString('realtimeRegionRefreshController !== controller', $source);
        $this->assertStringContainsString('window.location.href !== requestUrl', $source);
        $this->assertStringContainsString("error?.name === 'AbortError'", $source);
        $this->assertStringContainsString("window.addEventListener('gct:navigation-before'", $source);
        $this->assertStringContainsString("'Maintenance:RolePermission': ['/purchase-requests']", $source);
        $this->assertStringContainsString("payload?.entity !== 'RolePermission'", $source);
    }
}
