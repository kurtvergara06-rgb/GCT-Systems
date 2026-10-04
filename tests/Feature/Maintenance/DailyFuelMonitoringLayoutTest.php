<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class DailyFuelMonitoringLayoutTest extends TestCase
{
    public function test_daily_fuel_monitoring_uses_compact_controls_and_stable_table_columns(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/fuel-reports.css')
        );

        $this->assertStringContainsString(
            '.fuel-page .daily-monitoring-toolbar',
            $css
        );
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr) auto;',
            $css
        );
        $this->assertStringContainsString(
            'grid-template-columns: minmax(230px, 320px) minmax(170px, 210px);',
            $css
        );
        $this->assertStringContainsString(
            '.fuel-page .daily-monitoring-form > .table-wrap',
            $css
        );
        $this->assertStringContainsString(
            'max-height: 455px;',
            $css
        );
        $this->assertStringContainsString(
            '.fuel-page .daily-monitoring-table th:nth-child(1)',
            $css
        );
        $this->assertStringContainsString(
            '.fuel-page .daily-monitoring-table .daily-inline-input',
            $css
        );
    }

    public function test_daily_fuel_monitoring_save_bar_uses_primary_yellow_action(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/fuel-reports.css')
        );

        $this->assertStringContainsString(
            '.fuel-page .daily-save-btn',
            $css
        );
        $this->assertStringContainsString(
            'background: #ffc400;',
            $css
        );
        $this->assertStringContainsString(
            'color: #061f3d;',
            $css
        );
    }

    public function test_daily_fuel_monitoring_layout_remains_responsive(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/fuel-reports.css')
        );

        $this->assertStringContainsString(
            '@media (max-width: 1050px)',
            $css
        );
        $this->assertStringContainsString(
            '@media (max-width: 700px)',
            $css
        );
    }
}
