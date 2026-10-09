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
            "'Operation:Bus': ['/bus-master-list','/operation/driver-bus-assignment','/dashboard-operation','/operation/auto-scheduling','/job-orders','/pms-scheduling','/maintenance-dashboard','/admin/dashboard']",
            $js
        );

        $this->assertStringContainsString(
            "'Operation:Attendance': ['/mechanic-attendance','/driver-attendance','/operation/driver-bus-assignment'",
            $js
        );

        $this->assertStringNotContainsString(
            "'Operation:Bus': ['/inventory'",
            $js
        );
    }

    public function test_confirmed_native_submit_suppresses_its_own_realtime_feedback_until_redirect(): void
    {
        $confirmation = file_get_contents(resource_path('js/Main-js/confirmation-modal.js'));
        $echo = file_get_contents(resource_path('js/echo.js'));

        $markPosition = strpos(
            $confirmation,
            'markRealtimeMutationStart();'
        );
        $submitPosition = strpos(
            $confirmation,
            'formToSubmit.requestSubmit'
        );

        $this->assertNotFalse($markPosition);
        $this->assertNotFalse($submitPosition);
        $this->assertLessThan($submitPosition, $markPosition);

        $this->assertStringContainsString(
            'if (shouldSuppressRealtimeNotification()) {',
            $echo
        );
        $this->assertStringContainsString(
            "window.showSystemToast(message, 'info', 'System Updated'",
            $echo
        );
    }

    public function test_system_wide_mutations_share_the_same_realtime_suppression_guard(): void
    {
        $echo = file_get_contents(resource_path('js/echo.js'));
        $loading = file_get_contents(resource_path('js/Main-js/loading-state.js'));
        $confirmation = file_get_contents(resource_path('js/Main-js/confirmation-modal.js'));

        $this->assertStringContainsString(
            'window.GCTRealtimeMutation = Object.freeze({',
            $echo
        );
        $this->assertStringContainsString(
            'window.__gctRealtimeFetchWrapped',
            $echo
        );
        $this->assertStringContainsString(
            "!['GET', 'HEAD', 'OPTIONS'].includes(requestMethod)",
            $echo
        );
        $this->assertStringContainsString(
            'beginRealtimeMutation();',
            $echo
        );
        $this->assertStringContainsString(
            'endRealtimeMutation();',
            $echo
        );

        $this->assertStringContainsString(
            "if (!form.matches('[data-confirm-form]')) {",
            $loading
        );
        $this->assertStringContainsString(
            'window.GCTRealtimeMutation?.markNativeSubmit?.();',
            $loading
        );

        $this->assertStringContainsString(
            'window.GCTRealtimeMutation.markNativeSubmit();',
            $confirmation
        );
        $this->assertStringContainsString(
            'Date.now() + 30000',
            $confirmation
        );
    }
}
