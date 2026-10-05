<?php

namespace Tests\Feature\Maintenance;

use Tests\TestCase;

class JobOrderCreateModalLayoutTest extends TestCase
{
    public function test_new_job_order_modal_uses_structured_gct_sections(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );

        $this->assertStringContainsString(
            'class="jo-create-modal-overlay"',
            $view
        );
        $this->assertStringContainsString(
            'Basic Information',
            $view
        );
        $this->assertStringContainsString(
            'Job Details',
            $view
        );
        $this->assertStringContainsString(
            'Requested Parts',
            $view
        );
        $this->assertStringContainsString(
            'jo-create-parts-table-head',
            $view
        );

        // Existing workflow hooks must remain intact.
        $this->assertStringContainsString(
            'id="jobAssignedMechanic"',
            $view
        );
        $this->assertStringContainsString(
            'id="newRequestedPartsSection"',
            $view
        );
        $this->assertStringContainsString(
            'id="newPartsLockedNotice"',
            $view
        );
        $this->assertStringContainsString(
            'id="partsNeededWrapper"',
            $view
        );
        $this->assertStringContainsString(
            'id="addPartBtn"',
            $view
        );
        $this->assertStringContainsString(
            'id="jobEstimatedDurationUnit"',
            $view
        );
        $this->assertStringContainsString(
            'class="jo-duration-unit-select"',
            $view
        );

        // Do not invent a persistence field that the Job Order backend does not support.
        $this->assertStringNotContainsString(
            'name="work_repair_to_perform"',
            $view
        );
    }

    public function test_edit_job_order_modal_matches_structured_gct_layout(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );

        $this->assertStringContainsString(
            'class="jo-edit-modal-overlay"',
            $view
        );
        $this->assertStringContainsString(
            'class="jo-edit-section jo-edit-basic"',
            $view
        );
        $this->assertStringContainsString(
            'class="jo-edit-section jo-edit-details"',
            $view
        );
        $this->assertStringContainsString(
            'id="editRequestedPartsSection"',
            $view
        );
        $this->assertStringContainsString(
            'jo-edit-parts-table-head',
            $view
        );
        $this->assertStringContainsString(
            'id="editJobEstimatedDurationUnit"',
            $view
        );
        $this->assertStringContainsString(
            'class="jo-duration-unit-select"',
            $view
        );

        // Existing edit workflow hooks must remain intact.
        $this->assertStringContainsString(
            'id="edit_assigned_mechanic"',
            $view
        );
        $this->assertStringContainsString(
            'id="editPartsNeededWrapper"',
            $view
        );
        $this->assertStringContainsString(
            'id="editAddPartBtn"',
            $view
        );
        $this->assertStringContainsString(
            'id="editJobMainActions"',
            $view
        );
        $this->assertStringContainsString(
            'id="viewOnlyJobActions"',
            $view
        );
    }

    public function test_edit_job_order_validation_accepts_only_the_existing_saved_bus(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Maintenance/JobOrderController.php')
        );

        $this->assertStringContainsString(
            'Rule::in([$jobOrder->bus_no])',
            $controller
        );
        $this->assertStringContainsString(
            'use Illuminate\\Validation\\Rule;',
            $controller
        );
    }

    public function test_edit_job_order_keeps_current_bus_available_even_if_missing_from_options(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'function setEditBusOption',
            $js
        );
        $this->assertStringContainsString(
            'data-current-job-bus',
            $js
        );
        $this->assertStringContainsString(
            'editBusNo.appendChild',
            $js
        );
        $this->assertStringContainsString(
            'editBusNo.value =',
            $js
        );
    }

    public function test_edit_job_order_modal_keeps_footer_visible_and_dropdowns_usable(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            '#editJobModal.jo-edit-modal-overlay > .ui-form-modal',
            $css
        );
        $this->assertStringContainsString(
            'width: min(920px, calc(100vw - 56px)) !important;',
            $css
        );
        $this->assertStringContainsString(
            '#editJobModal .jo-edit-details',
            $css
        );
        $this->assertStringContainsString(
            'overflow: visible;',
            $css
        );
        $this->assertStringContainsString(
            '#editJobModal .jo-duration-control .jo-duration-unit-select',
            $css
        );
        $this->assertStringContainsString(
            'pointer-events: auto;',
            $css
        );
        $this->assertStringContainsString(
            '#editJobModal .jo-edit-footer',
            $css
        );
        $this->assertStringContainsString(
            'flex: 0 0 auto;',
            $css
        );
        $this->assertStringContainsString(
            'position: sticky;',
            $css
        );
        $this->assertStringContainsString(
            'scroll-padding-bottom: 72px;',
            $css
        );
    }

    public function test_new_job_order_bus_field_is_wider_than_the_reference_field(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 0.82fr) minmax(0, 1.18fr);',
            $css
        );
    }

    public function test_maintenance_job_uses_custom_downward_dropdown(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/maintenance-ui-enhancements.js')
        );
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            'jo-maintenance-job-combobox',
            $js
        );
        $this->assertStringContainsString(
            'makeDownwardSpace',
            $js
        );
        $this->assertStringContainsString(
            '.jo-maintenance-job-menu',
            $css
        );
        $this->assertStringContainsString(
            'top: calc(100% + 7px);',
            $css
        );
    }

    public function test_new_job_order_refreshes_available_mechanics_without_page_reload(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'void refreshAvailableMechanicsDropdown();',
            $js
        );
        $this->assertStringContainsString(
            'window.GCTRefreshJobOrderMechanics',
            $js
        );
        $this->assertStringContainsString(
            'await window.GCTRefreshJobOrderMechanics();',
            $js
        );
    }

    public function test_duration_unit_stays_editable_but_locks_in_view_only_mode(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/maintenance-ui-enhancements.js')
        );

        $this->assertStringContainsString(
            'lockUnit = false',
            $js
        );
        $this->assertStringContainsString(
            'const unitReadonly = readonly && lockUnit;',
            $js
        );
        $this->assertStringContainsString(
            'setDurationReadonly(editJo.durationField, true, true);',
            $js
        );
    }

    public function test_new_job_order_modal_css_is_centered_and_matches_gct_scale(): void
    {
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            '#jobModal.jo-create-modal-overlay',
            $css
        );
        $this->assertStringContainsString(
            'justify-content: center !important;',
            $css
        );
        $this->assertStringContainsString(
            'width: min(920px, calc(100vw - 56px)) !important;',
            $css
        );
        $this->assertStringContainsString(
            'grid-template-columns: 42px minmax(0, 1fr) 96px 120px 58px;',
            $css
        );
        $this->assertStringContainsString(
            'background: linear-gradient(135deg, #ffc400, #f5a800);',
            $css
        );
        $this->assertStringContainsString(
            '#jobModal .ui-form-content',
            $css
        );
        $this->assertStringContainsString(
            'flex: 1 1 auto;',
            $css
        );
        $this->assertStringContainsString(
            'max-height: none;',
            $css
        );
        $this->assertStringContainsString(
            'flex: 0 0 auto;',
            $css
        );
        $this->assertStringContainsString(
            '#jobModal .jo-create-section.jo-create-details',
            $css
        );
        $this->assertStringContainsString(
            'overflow: visible;',
            $css
        );
        $this->assertStringContainsString(
            '#jobModal .jo-duration-control .jo-duration-unit-select',
            $css
        );
        $this->assertStringContainsString(
            'pointer-events: auto;',
            $css
        );
    }

    public function test_new_job_order_bus_dropdown_floats_with_four_rows_then_scrolls(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );
        $css = file_get_contents(
            resource_path('css/Maintenance/job-order.css')
        );

        $this->assertStringContainsString(
            "className: 'jo-bus-combobox'",
            $js
        );
        $this->assertStringContainsString(
            "menu.classList.add('jo-bus-menu-portal')",
            $js
        );
        $this->assertStringContainsString(
            'document.body.appendChild(menu);',
            $js
        );
        $this->assertStringContainsString(
            'positionBusMenu',
            $js
        );
        $this->assertStringContainsString(
            '.jo-bus-menu-portal',
            $css
        );
        $this->assertStringContainsString(
            'max-height: 208px;',
            $css
        );
        $this->assertStringContainsString(
            'overflow-y: auto;',
            $css
        );
        $this->assertStringContainsString(
            'min-height: 52px;',
            $css
        );
        $this->assertStringNotContainsString(
            'padding-bottom: 150px;',
            $css
        );
    }

    public function test_new_job_order_save_shows_clear_required_field_feedback(): void
    {
        $js = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertStringContainsString(
            'validateNewJobOrderForm',
            $js
        );
        $this->assertStringContainsString(
            'Please select a Bus.',
            $js
        );
        $this->assertStringContainsString(
            'Please enter the Problem / Issue.',
            $js
        );
        $this->assertStringContainsString(
            'Please enter the Work / Repair to Perform.',
            $js
        );
        $this->assertStringContainsString(
            'Please select a Maintenance Job.',
            $js
        );
        $this->assertStringContainsString(
            'Please enter a valid Estimated Time.',
            $js
        );
        $this->assertStringContainsString(
            "window.showSystemToast(",
            $js
        );
        $this->assertStringContainsString(
            "'Validation Error'",
            $js
        );
        $this->assertStringContainsString(
            'scrollIntoView?.({',
            $js
        );
    }


    public function test_job_order_bus_selectors_hide_internal_bus_id_but_keep_bus_no_as_value(): void
    {
        $view = file_get_contents(
            resource_path('views/Maintenance/job-order.blade.php')
        );
        $js = file_get_contents(
            resource_path('js/Maintenance/job-order.js')
        );

        $this->assertSame(
            2,
            substr_count($view, 'label="Bus Plate"')
        );
        $this->assertStringContainsString(
            <<<'BLADE'
$bus->bus_no => ($bus->plate_no ?: 'Plate not assigned')
BLADE,
            $view
        );
        $this->assertStringNotContainsString(
            <<<'BLADE'
$bus->bus_no . ($bus->plate_no ? ' - ' . $bus->plate_no : '')
BLADE,
            $view
        );
        $this->assertStringContainsString(
            "searchPlaceholder: 'Search plate number...'",
            $js
        );
        $this->assertStringContainsString(
            'button.dataset.search =',
            $js
        );
        $this->assertStringContainsString(
            'option.value',
            $js
        );
    }

}
