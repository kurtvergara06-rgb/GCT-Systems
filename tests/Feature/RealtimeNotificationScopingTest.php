<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealtimeNotificationScopingTest extends TestCase
{
    public function test_realtime_toast_is_queued_only_after_page_relevance_check(): void
    {
        $js = file_get_contents(resource_path('js/echo.js'));

        $relevancePosition = strpos(
            $js,
            'if (!isRelevantPage) return;'
        );

        $notificationPosition = strpos(
            $js,
            "queueRealtimeNotification(payload?.message || 'System data was updated.');"
        );

        $this->assertNotFalse($relevancePosition);
        $this->assertNotFalse($notificationPosition);
        $this->assertLessThan(
            $notificationPosition,
            $relevancePosition
        );

        $this->assertStringContainsString(
            "'Operation:Bus': ['/bus-master-list','/dashboard-operation','/operation/auto-scheduling','/job-orders','/pms-scheduling','/maintenance-dashboard','/admin/dashboard']",
            $js
        );

        $this->assertStringNotContainsString(
            "'Operation:Bus': ['/inventory'",
            $js
        );
    }
}
