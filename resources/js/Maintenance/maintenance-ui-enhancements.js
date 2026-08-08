document.addEventListener('DOMContentLoaded', () => {
  const normalizeText = (value) => String(value || '').trim().toLowerCase();

  document.querySelectorAll('.jo-page, .maintenance-dashboard-page').forEach((scope) => {
    scope.querySelectorAll('.badge, .stat-card p, .stat-card-link p').forEach((element) => {
      if (normalizeText(element.textContent) === 'on going') {
        element.textContent = 'In Progress';
      }
    });
  });

  document.querySelectorAll('.jo-page tbody tr, .purchase-request-page tbody tr').forEach((row) => {
    const text = normalizeText(row.textContent);

    if (text.includes('rejected')) {
      row.dataset.uiStatus = 'rejected';
    } else if (text.includes('completed') || text.includes('issued')) {
      row.dataset.uiStatus = 'completed';
    } else if (text.includes('on going') || text.includes('in progress')) {
      row.dataset.uiStatus = 'progress';
    } else if (text.includes('on hold') || text.includes('submitted')) {
      row.dataset.uiStatus = 'hold';
    }

    row.querySelectorAll('.badge').forEach((badge) => {
      if (normalizeText(badge.textContent) === 'on going') {
        badge.textContent = 'In Progress';
      }

      if (normalizeText(badge.textContent) === 'submitted') {
        badge.textContent = 'Pending Review';
      }

      if (normalizeText(badge.textContent) === 'for purchase') {
        badge.textContent = 'Ready for Purchase';
      }

      if (normalizeText(badge.textContent) === 'rejected') {
        badge.textContent = 'Needs Revision';
      }
    });

    const badges = Array.from(row.querySelectorAll('.badge'));
    const partStatus = badges.find((badge) => {
      const value = normalizeText(badge.textContent);
      return [
        'needs revision',
        'requested',
        'approved',
        'ready for purchase',
        'issued',
      ].includes(value);
    });

    row.querySelectorAll('*').forEach((element) => {
      if (normalizeText(element.textContent) !== 'locked') {
        return;
      }

      const partValue = normalizeText(partStatus?.textContent);
      element.textContent = partValue && partValue !== 'issued'
        ? 'Waiting for parts'
        : 'Pending completion';
      element.setAttribute('title', element.textContent);
    });
  });

  /* =========================================================
     JOB ORDER ESTIMATED WORK DURATION
     Maintenance staff enters the estimate after assessing the
     actual work instead of using an automatic repair assumption.
  ========================================================= */
  const createDurationField = (maintenanceTypeSelect, id, required = false) => {
    if (!maintenanceTypeSelect) {
      return null;
    }

    const existing = document.getElementById(id);
    if (existing) {
      return existing;
    }

    const group = document.createElement('div');
    group.id = id;
    group.className = 'ui-form-group jo-estimated-duration-field';
    group.innerHTML = `
      <label>
        Estimated Work Duration
        ${required ? '<span class="ui-required">*</span>' : ''}
      </label>

      <div class="jo-duration-control">
        <div class="ui-input-wrap has-icon">
          <span class="ui-input-icon">
            <i class="fa-solid fa-clock"></i>
          </span>
          <input
            type="number"
            name="estimated_duration_value"
            min="0.25"
            step="0.25"
            placeholder="e.g. 4"
            ${required ? 'required' : ''}
          >
        </div>

        <select name="estimated_duration_unit" ${required ? 'required' : ''}>
          <option value="Hours">Hours</option>
          <option value="Minutes">Minutes</option>
          <option value="Days">Days</option>
        </select>
      </div>
    `;

    const fieldGroup = maintenanceTypeSelect.closest('.ui-form-group') || maintenanceTypeSelect.parentElement;
    fieldGroup?.insertAdjacentElement('afterend', group);

    return group;
  };

  const newMaintenanceType = document.getElementById('jobMaintenanceType');
  const editMaintenanceType = document.getElementById('edit_maintenance_type');

  createDurationField(
    newMaintenanceType,
    'newJoEstimatedDuration',
    true
  );

  const editDurationField = createDurationField(
    editMaintenanceType,
    'editJoEstimatedDuration',
    false
  );

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      window.setTimeout(() => {
        if (!editDurationField) {
          return;
        }

        const valueInput = editDurationField.querySelector('input[name="estimated_duration_value"]');
        const unitSelect = editDurationField.querySelector('select[name="estimated_duration_unit"]');

        if (valueInput) {
          valueInput.value = button.dataset.estimatedDurationValue || '';
        }

        if (unitSelect) {
          unitSelect.value = button.dataset.estimatedDurationUnit || 'Hours';
        }
      }, 0);
    });
  });
});
