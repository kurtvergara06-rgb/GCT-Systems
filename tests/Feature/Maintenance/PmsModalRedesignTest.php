<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class PmsModalRedesignTest extends TestCase
{
    public function test_add_pms_modal_keeps_existing_workflow_hooks_and_uses_grouped_sections(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/pms-scheduling.blade.php')
        );

        $this->assertStringContainsString(
            'class="pms-form-modal-overlay pms-add-modal-overlay"',
            $view
        );
        $this->assertStringContainsString('Bus Information', $view);
        $this->assertStringContainsString('PMS Mileage Information', $view);
        $this->assertStringContainsString('PMS Details', $view);
        $this->assertStringContainsString('Schedule &amp; Report', $view);

        foreach ([
            'pmsBusSelect',
            'currentGpsKm',
            'gpsReportDate',
            'lastPmsKm',
            'pmsIntervalKm',
            'nextPmsKm',
            'pmsStatusPreview',
            'maintenanceType',
            'customMaintenanceTypeGroup',
            'customMaintenanceType',
            'finalMaintenanceType',
        ] as $id) {
            $this->assertStringContainsString(
                'id="'.$id.'"',
                $view
            );
        }
    }

    public function test_edit_pms_modal_keeps_existing_update_hooks_and_uses_grouped_sections(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/pms-scheduling.blade.php')
        );

        $this->assertStringContainsString(
            'class="pms-form-modal-overlay pms-edit-modal-overlay"',
            $view
        );
        $this->assertStringContainsString(
            'Vehicle &amp; Task Information',
            $view
        );
        $this->assertStringContainsString(
            'Mileage Information',
            $view
        );

        foreach ([
            'editPmsBusNo',
            'editPmsMaintenanceType',
            'editCustomMaintenanceTypeGroup',
            'editCustomMaintenanceType',
            'editFinalMaintenanceType',
            'editLastPmsKm',
            'editPmsIntervalKm',
            'editNextPmsKm',
            'editRecommendedDate',
        ] as $id) {
            $this->assertStringContainsString(
                'id="'.$id.'"',
                $view
            );
        }
    }

    public function test_pms_task_list_modal_uses_redesigned_header_table_and_footer(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/pms-scheduling.blade.php')
        );

        $this->assertStringContainsString(
            'pms-task-list-modal',
            $view
        );
        $this->assertStringContainsString(
            'pms-task-list-heading',
            $view
        );
        $this->assertStringContainsString(
            'pms-modal-title-icon',
            $view
        );
        $this->assertStringContainsString(
            'pms-task-list-body',
            $view
        );

        // Existing task actions must remain available.
        $this->assertStringContainsString(
            'open-edit-pms',
            $view
        );
        $this->assertStringContainsString(
            'pms-schedules.destroy',
            $view
        );
        $this->assertStringContainsString(
            'pms-schedules.create-job-order',
            $view
        );
    }

    public function test_pms_modal_css_matches_the_new_visual_system_and_responsive_layout(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/pms-scheduling.css')
        );

        $this->assertStringContainsString(
            '#addPmsModal.pms-form-modal-overlay > .ui-form-modal',
            $css
        );
        $this->assertStringContainsString(
            '#editPmsModal.pms-form-modal-overlay > .ui-form-modal',
            $css
        );
        $this->assertStringContainsString(
            '.pms-tasks-popup .pms-task-list-modal',
            $css
        );
        $this->assertStringContainsString(
            'background: linear-gradient(135deg, #ffc400, #f5a800);',
            $css
        );
        $this->assertStringContainsString(
            'grid-template-columns: repeat(2, minmax(0, 1fr));',
            $css
        );
        $this->assertStringContainsString(
            '@media (max-width: 700px)',
            $css
        );
    }

    public function test_pms_javascript_still_targets_the_preserved_modal_controls(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/pms-scheduling.js')
        );

        foreach ([
            'addPmsModal',
            'editPmsModal',
            'pmsBusSelect',
            'maintenanceType',
            'editPmsMaintenanceType',
            'open-edit-pms',
            'open-pms-tasks-modal',
            'close-pms-tasks-modal',
        ] as $hook) {
            $this->assertStringContainsString(
                $hook,
                $js
            );
        }
    }
}
