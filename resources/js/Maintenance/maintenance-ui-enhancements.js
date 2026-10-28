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
    { label: 'Brake Adjustment', value: 2, unit: 'Hours' },
    { label: 'Brake Air System Inspection', value: 3, unit: 'Hours' },
    { label: 'Brake Lining Replacement', value: 5, unit: 'Hours' },
    { label: 'Engine Oil Leak Inspection', value: 3, unit: 'Hours' },
    { label: 'Fuel Filter Replacement', value: 2, unit: 'Hours' },
    { label: 'Cooling System Inspection', value: 3, unit: 'Hours' },
    { label: 'Radiator and Coolant Servicing', value: 4, unit: 'Hours' },
    { label: 'Transmission and Clutch Inspection', value: 4, unit: 'Hours' },
    { label: 'Suspension Inspection and Repair', value: 5, unit: 'Hours' },
    { label: 'Steering System Inspection', value: 3, unit: 'Hours' },
    { label: 'Wheel Bearing Inspection', value: 3, unit: 'Hours' },
    { label: 'Tire Pressure and Condition Check', value: 1, unit: 'Hours' },
    { label: 'Headlight and Signal Light Repair', value: 2, unit: 'Hours' },
    { label: 'Wiring and Electrical Troubleshooting', value: 4, unit: 'Hours' },
    { label: 'Aircon Refrigerant Recharge', value: 3, unit: 'Hours' },
    { label: 'Body Panel and Bumper Repair', value: 6, unit: 'Hours' },
    { label: 'Door and Window Mechanism Repair', value: 3, unit: 'Hours' },
    { label: 'Chassis Bolt Retightening', value: 2, unit: 'Hours' },
    { label: 'Chassis Greasing', value: 2, unit: 'Hours' },
    { label: 'Exhaust System Inspection and Repair', value: 4, unit: 'Hours' },
  ];

  const presetLabels = new Set(maintenanceJobPresets.map((preset) => preset.label));

  const enhanceMaintenanceJobSelect = (select) => {
    if (!select || select.dataset.dropdownEnhanced === 'true') {
      return null;
    }

    const nativeWrap = select.closest('.ui-input-wrap');

    if (!nativeWrap) {
      return null;
    }

    select.dataset.dropdownEnhanced = 'true';
    nativeWrap.classList.add('jo-maintenance-job-native-wrap');

    const combobox = document.createElement('div');
    combobox.className = 'jo-maintenance-job-combobox';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jo-maintenance-job-trigger';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = `
      <span class="jo-maintenance-job-icon">
        <i class="fa-solid fa-screwdriver-wrench"></i>
      </span>
      <span class="jo-maintenance-job-label placeholder">
        Select Maintenance Job
      </span>
      <i class="fa-solid fa-chevron-down jo-maintenance-job-chevron"></i>
    `;

    const menu = document.createElement('div');
    menu.className = 'jo-maintenance-job-menu';
    menu.hidden = true;

    const optionsContainer = document.createElement('div');
    optionsContainer.className = 'jo-maintenance-job-options';
    menu.appendChild(optionsContainer);

    const selectedChips = document.createElement('div');
    selectedChips.className = 'jo-maintenance-job-selected-chips';
    selectedChips.setAttribute('aria-label', 'Selected maintenance jobs');
    selectedChips.hidden = true;
    combobox.append(trigger, selectedChips, menu);
    nativeWrap.insertAdjacentElement('afterend', combobox);

    // Create JO only: show chosen jobs in a separate, actionable summary panel.
    // Keep the original selectedChips element and native select as the source of truth.
    if (select.id === 'newJoMaintenanceJob') {
      const panel = document.createElement('section');
      panel.className = 'jo-selected-jobs-panel';
      panel.setAttribute('aria-label', 'Selected Maintenance Jobs');
      const heading = document.createElement('div');
      heading.className = 'jo-selected-jobs-heading';
      const title = document.createElement('strong');
      title.textContent = 'Selected Maintenance Jobs';
      const clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'jo-selected-jobs-clear';
      clear.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear All';
      clear.addEventListener('click', () => select.setSelectedJobs?.([]));
      heading.append(title, clear);
      const actions = document.createElement('div');
      actions.className = 'jo-selected-jobs-actions';
      const add = document.createElement('button');
      add.type = 'button';
      add.className = 'jo-selected-jobs-add';
      add.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i> Add Jobs';
      add.addEventListener('click', () => {
        if (menu.hidden) trigger.click();
        trigger.focus();
      });
      actions.append(add);
      panel.append(heading, selectedChips, actions);
      combobox.insertAdjacentElement('afterend', panel);
      panel.hidden = true;
      select.addEventListener('change', () => {
        panel.hidden = !(select.getSelectedJobs?.().length);
      });
    }

    const label = trigger.querySelector('.jo-maintenance-job-label');

    const sync = () => {
      const selectedOption = select.options[select.selectedIndex];
      const hasValue = Boolean(selectedOption?.value);

      const selectedJobs = select.getSelectedJobs
        ? select.getSelectedJobs() : [];
      label.textContent = selectedJobs.length
        ? selectedJobs.length + (selectedJobs.length === 1 ? ' Maintenance Job selected' : ' Maintenance Jobs selected')
        : (hasValue ? selectedOption.textContent.trim() : 'Select Maintenance Job');
      label.classList.toggle('placeholder', !hasValue && !selectedJobs.length);
      selectedChips.replaceChildren();
      selectedChips.hidden = selectedJobs.length === 0;
      const selectedPanel = combobox.nextElementSibling;
      if (selectedPanel?.classList.contains('jo-selected-jobs-panel')) {
        selectedPanel.hidden = selectedJobs.length === 0;
      }
      selectedJobs.forEach((job) => {
        const chip = document.createElement('span');
        chip.className = 'jo-maintenance-job-chip';
        const name = document.createElement('span');
        name.textContent = job;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'jo-maintenance-job-chip-remove';
        remove.setAttribute('aria-label', 'Remove ' + job);
        remove.innerHTML = '&times;';
        remove.addEventListener('click', (event) => {
          event.stopPropagation();
          select.toggleJobSelection?.(job);
          sync();
        });
        chip.append(name, remove);
        selectedChips.appendChild(chip);
      });

      optionsContainer
        .querySelectorAll('.jo-maintenance-job-option')
        .forEach((optionButton) => {
          optionButton.classList.toggle(
            'is-selected',
            selectedJobs.length ? selectedJobs.includes(optionButton.dataset.value) : optionButton.dataset.value === select.value
          );
        });
    };

    const close = () => {
      menu.hidden = true;
      combobox.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    const render = () => {
      optionsContainer.innerHTML = '';

      Array.from(select.options).forEach((option) => {
        if (!option.value) {
          return;
        }

        const optionButton = document.createElement('button');
        optionButton.type = 'button';
        optionButton.className = 'jo-maintenance-job-option';
        optionButton.dataset.value = option.value;
        optionButton.innerHTML = `
          <span>${option.textContent.trim()}</span>
          <i class="fa-solid fa-check"></i>
        `;

        optionButton.addEventListener('click', () => {
          if (typeof select.toggleJobSelection === 'function') {
            select.toggleJobSelection(option.value);
            sync();
            return;
          }
          select.value = option.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          sync();
          close();
        });

        optionsContainer.appendChild(optionButton);
      });

      sync();
    };

    const makeDownwardSpace = () => {
      const scroller = combobox.closest('.ui-form-modal-scrollable');

      if (!scroller) {
        return;
      }

      window.requestAnimationFrame(() => {
        const triggerRect = trigger.getBoundingClientRect();
        const scrollerRect = scroller.getBoundingClientRect();
        const desiredBottom = triggerRect.bottom
          + Math.min(menu.scrollHeight || 260, 260)
          + 16;

        if (desiredBottom > scrollerRect.bottom) {
          scroller.scrollBy({
            top: desiredBottom - scrollerRect.bottom,
            behavior: 'smooth',
          });
        }
      });
    };

    trigger.addEventListener('click', () => {
      if (menu.hidden) {
        menu.hidden = false;
        combobox.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        render();
        makeDownwardSpace();
      } else {
        close();
      }
    });

    document.addEventListener('click', (event) => {
      if (!combobox.contains(event.target)) {
        close();
      }
    });

    render();

    return {
      sync,
      close,
    };
  };

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

    const isNewJobOrder = idPrefix === 'newJo';

    problemGroup.classList.toggle('ui-form-full', isNewJobOrder);
    workGroup.classList.toggle('ui-form-full', isNewJobOrder);

    const label = workGroup.querySelector('label');
    if (label) {
      label.innerHTML = 'Work / Repair to Perform <span class="ui-required">*</span>';
    }

    workGroup.querySelectorAll('small, .ui-form-help, .form-text, .helper-text').forEach((helper) => helper.remove());

    if (workInput) {
      workInput.required = true;
      workInput.disabled = false;
      workInput.name = 'work_to_perform';
      workInput.placeholder = isNewJobOrder
        ? 'Describe the maintenance work to be performed...'
        : 'Describe the actual maintenance or repair work that will be performed...';
    }

    if (isNewJobOrder) {
      const jobDetailsGrid = form.querySelector('.jo-create-job-details-grid');
      jobDetailsGrid?.appendChild(workGroup);
    } else {
      // The edit form keeps its established two-column Problem / Work row.
      problemGroup.insertAdjacentElement('afterend', workGroup);
    }

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

    const isNewJobOrder = idPrefix === 'newJo';

    group.classList.remove('ui-form-full');
    durationField.classList.remove('ui-form-full');

    const durationLabel = durationField.querySelector('label');
    if (durationLabel) {
      durationLabel.innerHTML = 'Duration <span class="ui-required">*</span>';
    }

    const jobSlot = form.querySelector(`[data-maintenance-job-slot="${idPrefix}"]`);
    jobSlot?.replaceWith(group);

    const select = group.querySelector(`#${idPrefix}MaintenanceJob`);
    if (select) {
      select.name = 'maintenance_job';
      const selectedJobs = new Set();
      const hiddenHolder = document.createElement('div');
      hiddenHolder.className = 'jo-selected-maintenance-jobs';
      group.appendChild(hiddenHolder);
      select.getSelectedJobs = () => Array.from(selectedJobs);
      select.setSelectedJobs = (jobs) => {
        selectedJobs.clear();
        Array.from(jobs || []).forEach((job) => selectedJobs.add(job));
        hiddenHolder.replaceChildren();
        Array.from(selectedJobs).forEach((value) => {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'maintenance_jobs[]';
          hidden.value = value;
          hiddenHolder.appendChild(hidden);
        });
        select.value = Array.from(selectedJobs)[0] || '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
      };
      select.toggleJobSelection = (job) => {
        if (job === 'Other') {
          selectedJobs.clear();
          selectedJobs.add('Other');
        } else {
          selectedJobs.delete('Other');
          if (selectedJobs.has(job)) selectedJobs.delete(job);
          else selectedJobs.add(job);
        }
        hiddenHolder.replaceChildren();
        Array.from(selectedJobs).forEach((value) => {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'maintenance_jobs[]';
          hidden.value = value;
          hiddenHolder.appendChild(hidden);
        });
        select.value = Array.from(selectedJobs)[0] || '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
      };
    }
    const { valueInput, unitSelect } = findDurationControls(durationField);
    const maintenanceJobCombobox = enhanceMaintenanceJobSelect(select);

    // Custom work requests explicit skills from the shared Mechanic Master List catalog.
    let customGroup = null;
    let pendingCustomSpecializations = [];
    let updateCustomSelection = () => {};
    {
      const customWorkId = `${idPrefix}CustomWorkName`;
      const customSkillSearchId = `${idPrefix}CustomSkillSearch`;
      const customSpecializationsId = `${idPrefix}CustomSpecializations`;
      customGroup = document.createElement('div');
      customGroup.className = 'ui-form-group ui-form-full jo-custom-work-fields';
      customGroup.hidden = true;
      customGroup.innerHTML = `
        <label for="${customWorkId}">Specify Maintenance Job <span class="ui-required">*</span></label>
        <input id="${customWorkId}" name="custom_maintenance_job" maxlength="255" placeholder="Describe the custom maintenance job">
        <label for="${customSkillSearchId}">Required Specializations <span class="ui-required">*</span></label>
        <input id="${customSkillSearchId}" type="search" placeholder="Search specializations..." autocomplete="off">
        <div id="${customSpecializationsId}" class="jo-custom-skill-options" role="group" aria-label="Required specializations"></div>
        <small>Select one or more qualifications from Mechanic Master List.</small>
      `;
      group.insertAdjacentElement('afterend', customGroup);
      const search = customGroup.querySelector(`#${customSkillSearchId}`);
      const options = customGroup.querySelector(`#${customSpecializationsId}`);
      const dropdown = document.createElement('div');
      dropdown.className = 'jo-custom-skill-dropdown';
      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'jo-custom-skill-trigger';
      trigger.setAttribute('aria-expanded', 'false');
      trigger.innerHTML = '<span class="jo-custom-skill-summary">Select required specializations...</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
      const selectedChips = document.createElement('div');
      selectedChips.className = 'jo-custom-skill-chips';
      selectedChips.setAttribute('aria-label', 'Selected specializations');
      dropdown.append(trigger, selectedChips);
      search.parentNode.insertBefore(dropdown, search);
      const panel = document.createElement('div');
      panel.className = 'jo-custom-skill-panel';
      panel.hidden = true;
      panel.append(search, options);
      dropdown.insertAdjacentElement('afterend', panel);
      const summary = trigger.querySelector('.jo-custom-skill-summary');
      const updateSelection = () => {
        const checked = Array.from(options.querySelectorAll('input:checked'));
        summary.textContent = checked.length ? checked.length + ' specialization(s) selected' : 'Select required specializations...';
        selectedChips.replaceChildren();
        checked.forEach((input) => {
          const chip = document.createElement('span');
          chip.className = 'jo-custom-skill-chip';
          chip.append(document.createTextNode(input.value));
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.textContent = '×';
          remove.setAttribute('aria-label', 'Remove ' + input.value);
          remove.addEventListener('click', () => {
            input.checked = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          });
          chip.append(remove);
          selectedChips.append(chip);
        });
      };
      updateCustomSelection = updateSelection;
      const modalScroller = form.closest('.ui-form-modal-scrollable');
      const positionPanel = () => {
        if (panel.hidden || !document.body.contains(panel)) return;
        const rect = trigger.getBoundingClientRect();
        const gap = 6;
        const roomBelow = window.innerHeight - rect.bottom - 12;
        const roomAbove = rect.top - 12;
        const showAbove = roomBelow < 220 && roomAbove > roomBelow;
        const availableHeight = Math.max(110, (showAbove ? roomAbove : roomBelow) - gap);
        Object.assign(panel.style, {
          position: 'fixed',
          top: showAbove ? 'auto' : Math.round(rect.bottom + gap) + 'px',
          bottom: showAbove ? Math.round(window.innerHeight - rect.top + gap) + 'px' : 'auto',
          left: Math.round(rect.left) + 'px',
          width: Math.round(rect.width) + 'px',
          maxHeight: Math.min(286, availableHeight) + 'px',
          overflowY: 'auto',
          zIndex: '110500',
        });
      };
      const closePanel = () => {
        if (panel.hidden) return;
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        window.removeEventListener('resize', positionPanel);
        window.removeEventListener('scroll', positionPanel, true);
        modalScroller?.removeEventListener('scroll', positionPanel);
        panel.remove();
        dropdown.insertAdjacentElement('afterend', panel);
        panel.removeAttribute('style');
      };
      trigger.addEventListener('click', () => {
        if (!panel.hidden) {
          closePanel();
          return;
        }
        document.body.appendChild(panel);
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        positionPanel();
        window.addEventListener('resize', positionPanel);
        window.addEventListener('scroll', positionPanel, true);
        modalScroller?.addEventListener('scroll', positionPanel, { passive: true });
        search.focus();
      });
      search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closePanel();
          trigger.focus();
        }
      });
      document.addEventListener('click', (event) => {
        if (!dropdown.contains(event.target) && !panel.contains(event.target)) closePanel();
      });
      fetch('/job-orders/specializations', { headers: { Accept: 'application/json' } })
        .then((response) => { if (!response.ok) throw new Error('Catalog unavailable'); return response.json(); })
        .then((names) => {
          names.forEach((name) => {
            const label = document.createElement('label');
            label.className = 'jo-custom-skill-choice';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.name = 'specializations[]';
            check.value = name;
            check.addEventListener('change', () => {
              updateSelection();
              form.dispatchEvent(new Event('change', { bubbles: true }));
              window.GCTRefreshJobOrderMechanics?.();
            });
            label.append(check, document.createTextNode(' ' + name));
            options.appendChild(label);
          });
          const pending = new Set(pendingCustomSpecializations.map((name) => String(name).toLowerCase()));
          options.querySelectorAll('input[name="specializations[]"]').forEach((input) => {
            input.checked = pending.has(input.value.toLowerCase());
          });
          updateSelection();
        })
        .catch(() => { options.textContent = 'Specializations could not be loaded. Refresh to try again.'; });
      search.addEventListener('input', () => {
        const query = search.value.trim().toLowerCase();
        options.querySelectorAll('label').forEach((label) => {
          label.hidden = !label.textContent.toLowerCase().includes(query);
        });
      });

    }

    const applySelectedJob = () => {
      const preset = maintenanceJobPresets.find((item) => item.label === select?.value);
      const isOther = select?.value === 'Other';
      if (customGroup) {
        customGroup.hidden = !isOther;
        customGroup.querySelector(`[name="custom_maintenance_job"]`).required = isOther;
        customGroup.querySelectorAll('input[name="specializations[]"]').forEach((input) => {
          input.disabled = !isOther;
        });
      }
      window.GCTUpdateJoSectionIndicators?.();
      setDurationReadonly(durationField, false, false);

      maintenanceJobCombobox?.sync();
    };

    select?.addEventListener('change', applySelectedJob);
    setDurationReadonly(durationField, false, false);
    maintenanceJobCombobox?.sync();

    return {
      select,
      setSelectionFromWork(work, specializations = []) {
        const normalized = String(work || '').trim();

        if (!select) return;

        const savedJobs = normalized.split(',').map((job) => job.trim()).filter(Boolean);
        const usesPresets = savedJobs.length > 0 && savedJobs.every((job) => presetLabels.has(job));
        select.setSelectedJobs?.(usesPresets ? savedJobs : (normalized ? ['Other'] : []));
        const customWork = customGroup?.querySelector('[name="custom_maintenance_job"]');
        if (customWork) customWork.value = usesPresets ? '' : normalized;
        pendingCustomSpecializations = usesPresets ? [] : Array.from(specializations || []);
        const pending = new Set(pendingCustomSpecializations.map((name) => String(name).toLowerCase()));
        customGroup?.querySelectorAll('input[name="specializations[]"]').forEach((input) => {
          input.checked = pending.has(input.value.toLowerCase());
        });
        updateCustomSelection();
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

    const workInput = null;
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

      let specializations = [];
      try {
        specializations = JSON.parse(button.dataset.specializations || '[]');
      } catch {
        specializations = [];
      }
      editJo.jobField?.setSelectionFromWork(work, specializations);
      void window.GCTLoadEditDurationSuggestion?.();

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
