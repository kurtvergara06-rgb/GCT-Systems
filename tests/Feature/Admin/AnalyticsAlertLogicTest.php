<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AnalyticsStageController;
use ReflectionMethod;
use Tests\TestCase;

class AnalyticsAlertLogicTest extends TestCase
{
    private function invokeAlert(
        string $stage,
        string $domain,
        object $diagnostic,
        ?object $predictive = null
    ): ?array {
        $method = new ReflectionMethod(
            AnalyticsStageController::class,
            'buildAnalyticsAlert'
        );
        $method->setAccessible(true);

        return $method->invoke(
            app(AnalyticsStageController::class),
            $stage,
            $domain,
            $diagnostic,
            $predictive
        );
    }

    private function diagnosticFixture(array $overrides = []): object
    {
        $diagnostic = (object) [
            'all' => (object) [
                'signals' => 0,
                'high_impact' => 0,
            ],
            'fleet' => (object) [
                'diagnostics' => (object) [
                    'review_count' => 0,
                    'delay_count' => 0,
                    'slow_movement_count' => 0,
                    'high_idle_count' => 0,
                ],
            ],
            'fuel' => (object) [
                'review_units' => collect(),
                'high_idling_units' => collect(),
            ],
            'bus_health' => (object) [
                'attention_buses' => collect(),
                'overdue_orders' => collect(),
            ],
            'inventory' => (object) [
                'attention_rows' => collect(),
                'critical' => 0,
                'low' => 0,
            ],
        ];

        foreach ($overrides as $path => $value) {
            data_set($diagnostic, $path, $value);
        }

        return $diagnostic;
    }

    public function test_zero_diagnostic_data_does_not_generate_an_alert(): void
    {
        $alert = $this->invokeAlert(
            'diagnostic',
            'fleet-trip',
            $this->diagnosticFixture()
        );

        $this->assertNull($alert);
    }

    public function test_real_delay_threshold_signal_generates_an_alert(): void
    {
        $diagnostic = $this->diagnosticFixture([
            'all.signals' => 3,
            'all.high_impact' => 2,
            'fleet.diagnostics.review_count' => 3,
            'fleet.diagnostics.delay_count' => 2,
            'fleet.diagnostics.slow_movement_count' => 1,
        ]);

        $alert = $this->invokeAlert(
            'diagnostic',
            'fleet-trip',
            $diagnostic
        );

        $this->assertNotNull($alert);
        $this->assertSame('Delay Spike Detected', $alert['title']);
        $this->assertSame('3 trips flagged for review', $alert['metric']);
        $this->assertStringContainsString(
            'recorded route baseline',
            $alert['message']
        );
    }

    public function test_inventory_alert_requires_actual_low_or_zero_stock(): void
    {
        $this->assertNull(
            $this->invokeAlert(
                'diagnostic',
                'inventory',
                $this->diagnosticFixture()
            )
        );

        $diagnostic = $this->diagnosticFixture([
            'all.signals' => 1,
            'all.high_impact' => 1,
            'inventory.attention_rows' => collect([(object) ['item_code' => 'PART-01']]),
            'inventory.critical' => 1,
            'inventory.low' => 0,
        ]);

        $alert = $this->invokeAlert(
            'diagnostic',
            'inventory',
            $diagnostic
        );

        $this->assertSame('Stockout Detected', $alert['title']);
        $this->assertSame('critical', $alert['priority']);
    }

    public function test_predictive_all_alert_requires_medium_or_high_risk(): void
    {
        $diagnostic = $this->diagnosticFixture([
            'all.signals' => 1,
        ]);

        $lowOnly = (object) [
            'all' => (object) [
                'risk' => (object) [
                    'low' => 1,
                    'medium' => 0,
                    'high' => 0,
                ],
            ],
        ];

        $this->assertNull(
            $this->invokeAlert(
                'predictive',
                'all',
                $diagnostic,
                $lowOnly
            )
        );

        $withRisk = (object) [
            'all' => (object) [
                'risk' => (object) [
                    'low' => 0,
                    'medium' => 2,
                    'high' => 1,
                ],
            ],
        ];

        $alert = $this->invokeAlert(
            'predictive',
            'all',
            $diagnostic,
            $withRisk
        );

        $this->assertSame('Forecast Risk Detected', $alert['title']);
        $this->assertSame('critical', $alert['priority']);
    }

    public function test_insight_component_no_longer_contains_hardcoded_demo_alerts(): void
    {
        $source = file_get_contents(
            resource_path('views/components/analytics/insight-toast.blade.php')
        );

        $this->assertStringNotContainsString('+14.2 min peak delay', $source);
        $this->assertStringNotContainsString('Bus 07 has an 82% predicted delay risk', $source);
        $this->assertStringNotContainsString('3 buses flagged for idle burn', $source);
        $this->assertStringContainsString('@if($hasInsight)', $source);
    }
}
