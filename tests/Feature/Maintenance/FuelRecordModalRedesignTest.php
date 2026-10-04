<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class FuelRecordModalRedesignTest extends TestCase
{
    public function test_fuel_record_modal_keeps_existing_workflow_ids(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/fuel-reports.blade.php')
        );

        foreach ([
            'fuelModal',
            'fuelForm',
            'fuelFormMethod',
            'fuelModalTitle',
            'fuelReportDate',
            'fuelBusNo',
            'fuelDriverName',
            'fuelLiters',
            'gpsStatusCard',
            'gpsStatusTitle',
            'gpsStatusMessage',
            'gpsStatusDetails',
            'gpsDistanceValue',
            'gpsIdlingValue',
            'useManualDistance',
            'manualDistanceFields',
            'fuelDistanceKm',
            'manualDistanceReason',
            'efficiencyValue',
            'efficiencyStatus',
            'fuelRemarks',
            'cancelFuelModal',
            'saveFuelRecord',
            'saveFuelText',
        ] as $id) {
            $this->assertStringContainsString(
                'id="'.$id.'"',
                $view
            );
        }
    }

    public function test_fuel_record_modal_uses_redesigned_sections_and_live_preview_hooks(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/fuel-reports.blade.php')
        );
        $js = file_get_contents(
            resource_path('js/Maintenance/fuel-reports.js')
        );

        $this->assertStringContainsString(
            'fuel-record-redesign-modal',
            $view
        );
        $this->assertStringContainsString(
            'GPS Mileage Lookup',
            $view
        );
        $this->assertStringContainsString(
            'Fuel Efficiency Preview',
            $view
        );
        $this->assertStringContainsString(
            'id="fuelGpsLookupButton"',
            $view
        );
        $this->assertStringContainsString(
            'id="fuelEfficiencyDistance"',
            $view
        );
        $this->assertStringContainsString(
            'id="fuelEfficiencyLiters"',
            $view
        );
        $this->assertStringContainsString(
            'fuelGpsLookupButton',
            $js
        );
        $this->assertStringContainsString(
            'fuelEfficiencyDistance',
            $js
        );
        $this->assertStringContainsString(
            'fuelEfficiencyLiters',
            $js
        );
    }

    public function test_fuel_record_modal_css_is_centered_scrollable_and_responsive(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/fuel-reports.css')
        );

        $this->assertStringContainsString(
            '#fuelModal .fuel-record-redesign-modal',
            $css
        );
        $this->assertStringContainsString(
            'width: min(980px, calc(100vw - 48px));',
            $css
        );
        $this->assertStringContainsString(
            'max-height: calc(100vh - 48px);',
            $css
        );
        $this->assertStringContainsString(
            '#fuelModal .fuel-record-modal-body',
            $css
        );
        $this->assertStringContainsString(
            'overflow-y: auto;',
            $css
        );
        $this->assertStringContainsString(
            '@media (max-width: 640px)',
            $css
        );
    }
}
