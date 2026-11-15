window.GCTPartialNavigation.registerInitializer('maintenance-ui-enhancements', '.jo-page, .pms-page, .purchase-page, .referrals-page, .fuel-page, .mechanic-page', () => {
  const maintenanceJobPresets = [
    { label: 'Change Oil', value: 5, unit: 'Hours' },
    { label: 'Oil Filter Replacement', value: 2, unit: 'Hours' },
    { label: 'Brake Inspection', value: 2, unit: 'Hours' },
    { label: 'Brake Pad Replacement', value: 4, unit: 'Hours' },
    { label: 'Air Filter Replacement', value: 1, unit: 'Hours' },
    { label: 'Aircon Servicing', value: 5, unit: 'Hours' },
    { label: 'Battery Replacement', value: 1, unit: 'Hours' },
    { label: 'Tire Replacement', value: 2, unit: 'Hours' },
    { label: 'Engine Tune-Up', value: 6, unit: 'Hours' },
    { label: 'Full PMS', value: 8, unit: 'Hours' },
  ];

  const presetLabels = new Set(maintenanceJobPresets.map((preset) => preset.label));

  const findDurationControls = (durationField) => ({
    valueInput: durationField?.querySelector('input[name="estimated_duration_value"]') || null,
    unitSelect: durationField?.querySelector('select[name="estimated_duration_unit"]') || null,
  });

  const setDurationReadonly = (
    durationField,
    readonly,
    lockUnit = false
  ) => {
    const { valueInput, unitSelect } = findDurationControls(durationField);

    if (valueInput) {
      valueInput.readOnly = readonly;
      valueInput.setAttribute('aria-readonly', readonly ? 'true' : 'false');
      valueInput.style.cursor = readonly ? 'default' : '';
    }

    if (unitSelect) {
      const unitReadonly = readonly && lockUnit;

      unitSelect.setAttribute('aria-readonly', unitReadonly ? 'true' : 'false');
      unitSelect.style.pointerEvents = unitReadonly ? 'none' : '';
      unitSelect.style.cursor = unitReadonly ? 'default' : '';
      unitSelect.tabIndex = unitReadonly ? -1 : 0;
    }
  };

  const hideMaintenanceTypeField = (form, { idPrefix, fallback = 'Repair' } = {}) => {
    if (!form) return null;

    const existingHidden = form.querySelector(`input[data-maintenance-type-hidden="${idPrefix}"]`);
    if (existingHidden) return existingHidden;

    const select = form.querySelector('select[name="maintenance_type"], #jobMaintenanceType, #edit_maintenance_type');
    const initialValue = String(select?.value || fallback || 'Repair').trim() || 'Repair';

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'maintenance_type';
    hidden.value = initialValue;
    hidden.dataset.maintenanceTypeHidden = idPrefix;
    form.appendChild(hidden);

    if (select) {
      select.required = false;
      select.disabled = true;
      select.removeAttribute('name');
      select.closest('.ui-form-group')?.remove();
    }

    return hidden;
  };

  const ensureWorkField = (form, problemField, idPrefix) => {
    if (!form || !problemField) return null;

    const problemGroup = problemField.closest('.ui-form-group');
    if (!problemGroup) return null;

    const candidates = Array.from(form.querySelectorAll('textarea[name="work_to_perform"], textarea[id*="WorkToPerform"], textarea[id*="work_to_perform"]'));
    let workInput = candidates[0] || null;
    let workGroup = workInput?.closest('.ui-form-group') || null;

    candidates.slice(1).forEach((duplicate) => {
      const duplicateGroup = duplicate.closest('.ui-form-group');
      if (duplicateGroup && duplicateGroup !== workGroup) {
        duplicateGroup.remove();
      } else {
        duplicate.remove();
      }
    });

    if (!workInput || !workGroup || !document.contains(workGroup)) {
      workGroup = document.createElement('div');
      workGroup.className = 'ui-form-group';
      workGroup.innerHTML = `
        <label for="${idPrefix}WorkToPerform">
          Work / Repair to Perform <span class="ui-required">*</span>
        </label>
        <textarea
          name="work_to_perform"
          id="${idPrefix}WorkToPerform"
          maxlength="2000"
          placeholder="Describe the actual maintenance or repair work that will be performed..."
          required
        ></textarea>
      `;
      workInput = workGroup.querySelector('textarea[name="work_to_perform"]');
    }

    problemGroup.classList.remove('ui-form-full');
    workGroup.classList.remove('ui-form-full');

    const label = workGroup.querySelector('label');
    if (label) {
      label.innerHTML = 'Work / Repair to Perform <span class="ui-required">*</span>';
    }

    workGroup.querySelectorAll('small, .ui-form-help, .form-text, .helper-text').forEach((helper) => helper.remove());

    if (workInput) {
      workInput.required = true;
      workInput.disabled = false;
      workInput.name = 'work_to_perform';
      workInput.placeholder = 'Describe the actual maintenance or repair work that will be performed...';
    }

    // The form grid uses source order for the two-column layout. Force the
    // work field directly beside Problem / Issue and remove any stale duplicate.
    problemGroup.insertAdjacentElement('afterend', workGroup);

    return workInput;
  };

  const ensureMaintenanceJobField = ({ form, durationField, idPrefix }) => {
    if (!form || !durationField) return null;

    const existingGroups = Array.from(form.querySelectorAll(`[data-maintenance-job-presets="${idPrefix}"]`));
    let group = existingGroups.shift() || null;
    existingGroups.forEach((duplicate) => duplicate.remove());

    if (!group) {
      group = document.createElement('div');
      group.className = 'ui-form-group';
      group.dataset.maintenanceJobPresets = idPrefix;

      const options = maintenanceJobPresets
        .map((preset) => `<option value="${preset.label}">${preset.label}</option>`)
        .join('');

      group.innerHTML = `
        <label for="${idPrefix}MaintenanceJob">
          Maintenance Job <span class="ui-required">*</span>
        </label>
        <div class="ui-input-wrap has-icon">
          <span class="ui-input-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
          <select id="${idPrefix}MaintenanceJob" required>
            <option value="">Select Maintenance Job</option>
            ${options}
            <option value="Other">Other / Custom Work</option>
          </select>
        </div>
      `;
    }

    group.classList.remove('ui-form-full');
    durationField.classList.remove('ui-form-full');

    const durationLabel = durationField.querySelector('label');
    if (durationLabel) {
      durationLabel.innerHTML = 'Estimated Time <span class="ui-required">*</span>';
    }

    // Keep Maintenance Job immediately before Estimated Time so both occupy
    // the same two-column row.
    durationField.insertAdjacentElement('beforebegin', group);

    const select = group.querySelector(`#${idPrefix}MaintenanceJob`);
    const { valueInput, unitSelect } = findDurationControls(durationField);

    const applySelectedJob = () => {
      const preset = maintenanceJobPresets.find((item) => item.label === select?.value);
      const isOther = select?.value === 'Other';

      if (preset) {
        if (valueInput) valueInput.value = preset.value;
        if (unitSelect) unitSelect.value = preset.unit;
        setDurationReadonly(durationField, true);
      } else {
        setDurationReadonly(durationField, !isOther);
      }
    };

    select?.addEventListener('change', applySelectedJob);
    setDurationReadonly(durationField, true);

    return {
      select,
      setSelectionFromWork(work) {
        const normalized = String(work || '').trim();

        if (!select) return;

        if (presetLabels.has(normalized)) {
          select.value = normalized;
        } else if (normalized) {
          select.value = 'Other';
        } else {
          select.value = '';
        }

        applySelectedJob();
      },
    };
  };

  const setupJoForm = ({ formId, durationId, problemSelector, idPrefix }) => {
    const form = document.getElementById(formId);
    const durationField = document.getElementById(durationId);
    const problemField = form?.querySelector(problemSelector);

    if (!form || !durationField || !problemField) {
      return null;
    }

    const maintenanceType = hideMaintenanceTypeField(form, {
      idPrefix,
      fallback: 'Repair',
    });

    const workInput = ensureWorkField(form, problemField, idPrefix);
    const jobField = ensureMaintenanceJobField({ form, durationField, idPrefix });

    return {
      form,
      durationField,
      maintenanceType,
      workInput,
      jobField,
    };
  };

  const newJo = setupJoForm({
    formId: 'newJobOrderForm',
    durationId: 'newJoEstimatedDuration',
    problemSelector: 'textarea[name="problem_issue"]',
    idPrefix: 'newJo',
  });

  const editJo = setupJoForm({
    formId: 'editJobForm',
    durationId: 'editJoEstimatedDuration',
    problemSelector: 'textarea[name="problem_issue"]',
    idPrefix: 'editJo',
  });

  if (newJo?.form && newJo.maintenanceType) {
    newJo.form.addEventListener('submit', () => {
      const pmsScheduleId = newJo.form.querySelector('input[name="pms_schedule_id"]')?.value;
      newJo.maintenanceType.value = pmsScheduleId ? 'PMS' : 'Repair';
    });
  }

  let workDetailsPromise = null;

  const loadWorkDetails = async () => {
    if (!workDetailsPromise) {
      workDetailsPromise = fetch('/job-orders/work-details', {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((response) => {
          if (!response.ok) throw new Error('Unable to load Job Order work details.');
          return response.json();
        })
        .catch(() => ({}));
    }

    return workDetailsPromise;
  };

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', async () => {
      const { valueInput, unitSelect } = findDurationControls(editJo?.durationField);

      if (valueInput) {
        valueInput.value = button.dataset.estimatedDurationValue || '';
      }

      if (unitSelect) {
        unitSelect.value = button.dataset.estimatedDurationUnit || 'Hours';
      }

      if (editJo?.maintenanceType) {
        editJo.maintenanceType.value = button.dataset.maintenanceType || 'Repair';
      }

      if (!editJo) return;

      const details = await loadWorkDetails();
      const work = details?.[button.dataset.id] || '';

      if (editJo.workInput) {
        editJo.workInput.value = work;
      }

      editJo.jobField?.setSelectionFromWork(work);

      const readonly = button.dataset.viewOnly === '1' || button.dataset.status === 'Completed';

      if (editJo.workInput) {
        editJo.workInput.disabled = readonly;
      }

      if (editJo.jobField?.select) {
        editJo.jobField.select.disabled = readonly;
      }

      if (readonly) {
        setDurationReadonly(editJo.durationField, true, true);
      }
    });
  });
});
