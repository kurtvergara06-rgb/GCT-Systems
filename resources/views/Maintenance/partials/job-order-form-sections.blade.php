@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $busFieldId = $isEdit ? 'edit_bus_no' : 'jobBusNo';
    $driverPreviewId = $isEdit ? 'editJobLinkedDriverPreview' : 'jobLinkedDriverPreview';
    $driverNameId = $isEdit ? 'editJobLinkedDriver' : 'jobLinkedDriver';
    $problemFieldId = $isEdit ? 'edit_problem_issue' : 'jobProblemIssue';
    $mechanicFieldId = $isEdit ? 'edit_assigned_mechanic' : 'jobAssignedMechanics';
    $durationFieldId = $isEdit ? 'editJoEstimatedDuration' : 'newJoEstimatedDuration';
    $durationUnitId = $isEdit ? 'editJobEstimatedDurationUnit' : 'jobEstimatedDurationUnit';
    $durationSuggestionId = $isEdit ? 'editJobDurationSuggestion' : 'jobDurationSuggestion';
    $partsSectionId = $isEdit ? 'editRequestedPartsSection' : 'newRequestedPartsSection';
    $addPartButtonId = $isEdit ? 'editAddPartBtn' : 'addPartBtn';
    $partsWrapperId = $isEdit ? 'editPartsNeededWrapper' : 'partsNeededWrapper';
    $maintenanceSlot = $isEdit ? 'editJo' : 'newJo';
    $formBuses = $isEdit ? $buses : $availableBuses;
    $formMechanics = $isEdit ? $allMechanics : $availableMechanics;
@endphp

<section class="jo-create-section jo-create-basic jo-create-job-details" data-jo-collapsible>
    <div class="jo-create-section-header">
        <span class="jo-create-section-icon"><i class="fa-solid fa-file-lines"></i></span>
        <div><h3><span class="jo-create-section-number">01 &mdash;</span> Job Details</h3></div>
    </div>

    <div class="ui-form-grid jo-create-basic-grid jo-create-job-details-grid">
        <x-ui.form-select
            label="Bus ID / Plate"
            name="bus_no"
            :id="$busFieldId"
            icon="fa-bus"
            placeholder="Select bus"
            :options="$formBuses->mapWithKeys(fn ($bus) => [$bus->bus_no => $bus->bus_no.' · '.($bus->plate_no ?: 'Plate not assigned')])->toArray()"
            required
        />

        <div class="jo-maintenance-job-slot" data-maintenance-job-slot="{{ $maintenanceSlot }}"></div>

        <div id="{{ $driverPreviewId }}" class="jo-linked-driver-preview ui-form-full" hidden>
            <i class="fa-solid fa-id-card" aria-hidden="true"></i>
            <span>Linked driver: <strong id="{{ $driverNameId }}" aria-live="polite"></strong></span>
        </div>

        <div class="ui-form-group ui-form-full jo-create-problem-field">
            <label for="{{ $problemFieldId }}">Complaint / Problem <span class="ui-required">*</span></label>
            <textarea
                name="problem_issue"
                id="{{ $problemFieldId }}"
                placeholder="Describe the reported issue or complaint..."
                required
            >@if(!$isEdit){{ old('problem_issue', $pmsCreate ? request('problem_issue', 'PMS maintenance is due based on processed GPS mileage.') : '') }}@endif</textarea>
        </div>
    </div>
</section>

<section class="jo-create-section jo-create-details jo-create-mechanics" data-jo-collapsible>
    <div class="jo-create-section-header">
        <span class="jo-create-section-icon"><i class="fa-solid fa-gears"></i></span>
        <div>
            <h3><span class="jo-create-section-number">02 &mdash;</span> Mechanic Assignment</h3>
            <p class="jo-create-step-description">Assign on-duty mechanics qualified for this repair.</p>
        </div>
    </div>

    <div class="jo-create-details-grid">
        <div class="ui-form-group jo-mechanic-multi-field">
            <label for="{{ $mechanicFieldId }}">Assigned Mechanics <span class="ui-required">*</span></label>
            <div class="ui-input-wrap">
                <select name="mechanic_ids[]" id="{{ $mechanicFieldId }}" multiple size="4" required>
                    @foreach($formMechanics as $mechanic)
                        <option
                            value="{{ $mechanic->id }}"
                            data-specializations="{{ $mechanic->specialization }}"
                            data-availability="Available"
                            @if(!$isEdit) @selected(in_array($mechanic->id, old('mechanic_ids', []))) @endif
                        >
                            {{ $mechanic->mechanic_name }}{{ $mechanic->specialization ? ' · '.$mechanic->specialization : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="jo-skill-coverage" data-jo-skill-coverage aria-live="polite">
                <div class="jo-skill-coverage-header">
                    <span class="jo-skill-coverage-icon"><i class="fa-solid fa-bullseye" aria-hidden="true"></i></span>
                    <span class="jo-skill-coverage-title"><strong>Specialization Coverage</strong><small>Required skills compared with assigned mechanics</small></span>
                    <span class="jo-skill-coverage-status" data-coverage-status>Not Evaluated</span>
                </div>
                <div class="jo-skill-coverage-body">
                    <div class="jo-skill-coverage-required">
                        <strong>Required Specializations for This Job</strong>
                        <div class="jo-skill-coverage-tags" data-coverage-tags><span class="jo-coverage-empty">No skill requirements recorded.</span></div>
                    </div>
                    <div class="jo-skill-coverage-summary">
                        <strong>Coverage Status</strong>
                        <span data-coverage-count>Awaiting specialization requirements.</span>
                        <p data-coverage-note>Select a maintenance job and mechanics to evaluate the recorded skill requirements.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="jo-create-section jo-create-duration" data-jo-collapsible>
    <div class="jo-create-section-header">
        <span class="jo-create-section-icon"><i class="fa-regular fa-clock"></i></span>
        <div><h3><span class="jo-create-section-number">03 &mdash;</span> Estimated Duration</h3><p class="jo-create-step-description">Set the expected repair time for Head review.</p></div>
    </div>

    <div class="jo-create-duration-grid">
        <div id="{{ $durationFieldId }}" class="ui-form-group jo-estimated-duration-field">
            <label>Duration <span class="ui-required">*</span></label>
            <div class="jo-duration-control">
                <div class="ui-input-wrap">
                    <input type="number" name="estimated_duration_value" min="0.25" step="0.25" value="" placeholder="Enter estimated duration" required>
                </div>
                <select name="estimated_duration_unit" id="{{ $durationUnitId }}" class="jo-duration-unit-select" required>
                    @foreach(['Hours', 'Minutes', 'Days'] as $durationUnit)
                        <option value="{{ $durationUnit }}" @if(!$isEdit) @selected(old('estimated_duration_unit', 'Hours') === $durationUnit) @endif>{{ $durationUnit }}</option>
                    @endforeach
                </select>
            </div>
            <small id="{{ $durationSuggestionId }}" class="jo-duration-suggestion" data-status="unavailable">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>Insufficient Historical Data</span>
            </small>
            <p class="jo-duration-compact-help"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Suggestions use at least 3 completed, approved Job Orders for the same specific maintenance work. Enter your own estimate for Head verification.</p>
        </div>
    </div>
</section>

<section id="{{ $partsSectionId }}" class="jo-create-section jo-create-parts jo-parts-section" data-jo-collapsible>
    <div class="jo-create-section-header jo-create-parts-header">
        <span class="jo-create-section-icon"><i class="fa-solid fa-box-open"></i></span>
        <div><h3><span class="jo-create-section-number">04 &mdash;</span> Requested Parts <small class="jo-section-optional">Optional</small></h3></div>
        <button type="button" id="{{ $addPartButtonId }}" class="jo-create-add-part-btn">
            <i class="fa-solid fa-plus"></i> Add Part
        </button>
    </div>

    <div class="jo-create-parts-table">
        <div class="jo-create-parts-table-head" aria-hidden="true">
            <span>#</span><span>Item / Part</span><span>Qty</span><span>Unit</span><span>Stock</span><span>Actions</span>
        </div>
        <div id="{{ $partsWrapperId }}" class="jo-parts-repeater jo-create-parts-repeater">
            <div class="jo-part-row part-needed-row">
                <input type="text" name="parts[0][name]" placeholder="Part name">
                <input type="number" name="parts[0][quantity]" min="1" placeholder="Qty">
                <select name="parts[0][unit]">
                    <option value="">Unit</option>
                    @foreach(['pcs', 'set', 'liter', 'gallon', 'bottle', 'box', 'meter', 'kg', 'pack', 'pair', 'roll', 'tube'] as $unit)
                        <option value="{{ $unit }}">{{ $unit }}</option>
                    @endforeach
                </select>
                <button type="button" class="remove-part-btn" title="Remove Part"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    </div>
    <input type="hidden" name="parts_issue_strategy" value="complete_before_issuance">
</section>
