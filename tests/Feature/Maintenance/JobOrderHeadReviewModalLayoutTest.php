<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class JobOrderHeadReviewModalLayoutTest extends TestCase
{
    public function test_head_review_uses_stacked_verification_and_authorization_sections(): void
    {
        $view = file_get_contents(resource_path('views/Maintenance/job-order.blade.php'));
        $css = file_get_contents(resource_path('css/Maintenance/job-order.css'));
        $javascript = file_get_contents(resource_path('js/Maintenance/job-order.js'));

        foreach ([
            'Job Order Details',
            'Historical Median Duration',
            'Submitted Duration',
            "Head's duration decision",
            'Duration Verification',
            'Head Authorization',
            'Signature upload',
            'Signatory name',
            'Return for Revision',
            'Approve Job Order',
        ] as $copy) {
            $this->assertStringContainsString($copy, $view);
        }

        $this->assertStringContainsString('jo-duration-verification-grid', $view);
        $this->assertStringContainsString('jo-review-step-heading', $view);
        $this->assertStringContainsString('$headReviewMechanics->take(1)', $view);
        $this->assertStringContainsString('$headReviewMechanics->skip(1)', $view);
        $this->assertStringContainsString('jo-review-mechanic-more', $view);
        $this->assertStringContainsString('fa-chevron-down', $view);
        $this->assertStringContainsString('.jo-head-review-body .jo-review-details {overflow:visible;}', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $css);
        $this->assertStringContainsString('Verify details, confirm duration, and authorize the Job Order.', $view);
        $this->assertStringContainsString('jo-review-detail-support-grid', $view);
        $this->assertStringContainsString('jo-review-parts-progress', $view);
        $this->assertStringContainsString('Possible Delay — Parts Shortage', $view);
    }

    public function test_review_uses_real_duration_service_data_and_authenticated_signatory(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Maintenance/JobOrderIndexController.php'));
        $view = file_get_contents(resource_path('views/Maintenance/job-order.blade.php'));

        $this->assertStringContainsString('$durationSuggestions = $jobOrders->getCollection()', $controller);
        $this->assertStringContainsString('$this->durations->suggestion(', $controller);
        $this->assertStringContainsString('$jobOrder->work_to_perform', $controller);
        $this->assertStringContainsString('$durationSuggestion[\'sample_size\']', $view);
        $this->assertStringContainsString('value="{{ $currentUser->name }}"', $view);
        $this->assertStringNotContainsString('Kenneth Dela Cruz', $view);
    }
}
