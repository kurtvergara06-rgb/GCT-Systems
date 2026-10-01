window.GCTPartialNavigation.registerInitializer('maintenance-ui-enhancements', '.jo-page, .pms-page, .purchase-page, .referrals-page, .fuel-page, .mechanic-page', () => {
  const editDurationField = document.getElementById('editJoEstimatedDuration');

  if (!editDurationField) {
    return;
  }

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

  const removeLegacyWorkField = (form) => {
    if (!form) return '';

    let legacyValue = '';

    form
      .querySelectorAll('textarea[name="work_to_perform"], input[name="work_to_perform"]')
      .forEach((field) => {
        if (field.matches('[data-work-to-perform]')) return;

        if (!legacyValue) {
          legacyValue = String(field.value || '').trim();
        }

        field.required = false;
        field.disabled = true;
        field.removeAttribute('name');

        const group = field.closest('.ui-form-group');
        if (group) {
          group.remove();
        } else {
          field.remove();
        }
      });

    return legacyValue;
  };

  const createPresetField = ({ idPrefix, form, durationField }) => {
    if (!form || !durationField || form.querySelector(`[data-maintenance-job-presets="${idPrefix}"]`)) {
      return null;
    }

    const legacyWork = removeLegacyWorkField(form);

    const group = document.createElement('div');
    group.className = 'ui-form-group ui-form-full';
    group.dataset.maintenanceJobPresets = idPrefix;

    const options = maintenanceJobPresets
      .map((preset) => `<option value="${preset.label}">${preset.label} — about ${preset.value} ${preset.unit}</option>`)
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
      <div data-custom-work-wrap hidden style="margin-top:10px;">
        <label for="${idPrefix}CustomWork" style="display:block;margin-bottom:6px;">
          Custom Work / Repair <span class="ui-required">*</span>
        </label>
        <textarea
          id="${idPrefix}CustomWork"
          data-custom-work
          rows="2"
          maxlength="2000"
          placeholder="Describe the maintenance work to perform..."
        ></textarea>
      </div>
      <input type="hidden" name="work_to_perform" data-work-to-perform>
      <small style="display:block;margin-top:6px;color:#64748b;font-size:11px;">
        Selecting a preset fills the usual estimated duration. You can still adjust the duration manually.
      </small>
    `;

    durationField.parentNode?.insertBefore(group, durationField);

    const select = group.querySelector(`#${idPrefix}MaintenanceJob`);
    const customWrap = group.querySelector('[data-custom-work-wrap]');
    const customInput = group.querySelector('[data-custom-work]');
    const hiddenInput = group.querySelector('[data-work-to-perform]');
    const { valueInput, unitSelect } = findDurationControls(durationField);

    const syncHiddenValue = () => {
      if (!hiddenInput || !select) return;

      if (select.value === 'Other') {
        hiddenInput.value = customInput?.value.trim() || '';
      } else {
        hiddenInput.value = select.value;
      }
    };

    const applyPresetDuration = () => {
      if (!select) return;

      const preset = maintenanceJobPresets.find((item) => item.label === select.value);
      if (!preset) return;

      if (valueInput) valueInput.value = preset.value;
      if (unitSelect) unitSelect.value = preset.unit;
    };

    const syncCustomVisibility = () => {
      const isOther = select?.value === 'Other';
      if (customWrap) customWrap.hidden = !isOther;
      if (customInput) {
        customInput.required = Boolean(isOther);
        if (!isOther) customInput.value = '';
      }
      syncHiddenValue();
    };

    select?.addEventListener('change', () => {
      syncCustomVisibility();
      applyPresetDuration();
    });

    customInput?.addEventListener('input', syncHiddenValue);

    const api = {
      group,
      select,
      customWrap,
      customInput,
      hiddenInput,
      valueInput,
      unitSelect,
      setWork(work, { preserveDuration = true, readonly = false } = {}) {
        const normalizedWork = String(work || '').trim();

        if (!normalizedWork) {
          if (select) select.value = '';
          if (customInput) customInput.value = '';
        } else if (presetLabels.has(normalizedWork)) {
          if (select) select.value = normalizedWork;
          if (customInput) customInput.value = '';
        } else {
          if (select) select.value = 'Other';
          if (customInput) customInput.value = normalizedWork;
        }

        syncCustomVisibility();

        if (!preserveDuration) {
          applyPresetDuration();
        }

        if (select) select.disabled = readonly;
        if (customInput) customInput.disabled = readonly;
      },
    };

    if (legacyWork) {
      api.setWork(legacyWork, { preserveDuration: true, readonly: false });
    }

    return api;
  };

  const newDurationField = document.getElementById('newJoEstimatedDuration');
  const newJobForm = document.getElementById('newJobOrderForm');
  const editJobForm = document.getElementById('editJobForm');

  const newPresetField = createPresetField({
    idPrefix: 'newJo',
    form: newJobForm,
    durationField: newDurationField,
  });

  const editPresetField = createPresetField({
    idPrefix: 'editJo',
    form: editJobForm,
    durationField: editDurationField,
  });

  if (newPresetField && !newPresetField.hiddenInput?.value) {
    newPresetField.setWork('', { preserveDuration: true, readonly: false });
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
      const { valueInput, unitSelect } = findDurationControls(editDurationField);

      if (valueInput) {
        valueInput.value = button.dataset.estimatedDurationValue || '';
      }

      if (unitSelect) {
        unitSelect.value = button.dataset.estimatedDurationUnit || 'Hours';
      }

      if (!editPresetField) return;

      const details = await loadWorkDetails();
      const work = details?.[button.dataset.id] || '';
      const readonly = button.dataset.viewOnly === '1' || button.dataset.status === 'Completed';

      editPresetField.setWork(work, {
        preserveDuration: true,
        readonly,
      });
    });
  });
});
