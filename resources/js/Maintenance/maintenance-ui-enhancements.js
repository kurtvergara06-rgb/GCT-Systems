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
     JOB ORDER RECOMMENDED SERVICE INTERVALS
     Rule-based recommendation aligned with the current
     Maintenance Type values used by the Job Order module.
  ========================================================= */
  const serviceIntervals = {
    PMS: {
      interval: '1–3 hours',
      description: 'Recommended interval for routine preventive maintenance.',
    },
    Repair: {
      interval: '2–6 hours',
      description: 'Recommended interval for general corrective repair work.',
    },
  };

  const getRecommendation = (maintenanceType) => {
    const key = String(maintenanceType || '').trim();
    return serviceIntervals[key] || null;
  };

  const createRecommendationCard = (select, id) => {
    if (!select) {
      return null;
    }

    const existing = document.getElementById(id);
    if (existing) {
      return existing;
    }

    const card = document.createElement('div');
    card.id = id;
    card.className = 'jo-time-recommendation';
    card.innerHTML = `
      <span class="jo-time-recommendation-icon" aria-hidden="true">
        <i class="fa-solid fa-clock"></i>
      </span>
      <div>
        <span class="jo-time-recommendation-label">Recommended Service Time</span>
        <strong class="jo-time-recommendation-value">Select a maintenance type</strong>
        <small class="jo-time-recommendation-note">The interval is based on the selected maintenance type.</small>
      </div>
    `;

    const fieldGroup = select.closest('.ui-form-group') || select.parentElement;
    fieldGroup?.insertAdjacentElement('afterend', card);

    return card;
  };

  const syncRecommendationCard = (select, card) => {
    if (!select || !card) {
      return;
    }

    const recommendation = getRecommendation(select.value);
    const value = card.querySelector('.jo-time-recommendation-value');
    const note = card.querySelector('.jo-time-recommendation-note');

    if (!recommendation) {
      card.classList.add('is-empty');
      if (value) value.textContent = 'Select a maintenance type';
      if (note) note.textContent = 'The interval is based on the selected maintenance type.';
      return;
    }

    card.classList.remove('is-empty');
    if (value) value.textContent = recommendation.interval;
    if (note) note.textContent = recommendation.description;
  };

  const newMaintenanceType = document.getElementById('jobMaintenanceType');
  const editMaintenanceType = document.getElementById('edit_maintenance_type');

  const newRecommendationCard = createRecommendationCard(
    newMaintenanceType,
    'newJoTimeRecommendation'
  );
  const editRecommendationCard = createRecommendationCard(
    editMaintenanceType,
    'editJoTimeRecommendation'
  );

  const syncNewRecommendation = () => {
    syncRecommendationCard(newMaintenanceType, newRecommendationCard);
  };
  const syncEditRecommendation = () => {
    syncRecommendationCard(editMaintenanceType, editRecommendationCard);
  };

  newMaintenanceType?.addEventListener('change', syncNewRecommendation);
  editMaintenanceType?.addEventListener('change', syncEditRecommendation);

  document.getElementById('openJobModal')?.addEventListener('click', () => {
    window.setTimeout(syncNewRecommendation, 0);
  });

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      window.setTimeout(syncEditRecommendation, 0);
    });
  });

  /* PMS Job Orders may be prefilled programmatically by the existing page JS. */
  window.setTimeout(() => {
    syncNewRecommendation();
    syncEditRecommendation();
  }, 0);

  /* Show the recommendation directly in the Job Order list as supporting context. */
  document.querySelectorAll('.jo-page .job-orders-table').forEach((table) => {
    const headers = Array.from(table.querySelectorAll('thead th'));
    const maintenanceTypeIndex = headers.findIndex(
      (header) => normalizeText(header.textContent) === 'maintenance type'
    );

    if (maintenanceTypeIndex < 0) {
      return;
    }

    table.querySelectorAll('tbody tr').forEach((row) => {
      const cell = row.children[maintenanceTypeIndex];

      if (!cell || cell.querySelector('.jo-time-inline')) {
        return;
      }

      const recommendation = getRecommendation(cell.textContent);

      if (!recommendation) {
        return;
      }

      const helper = document.createElement('span');
      helper.className = 'jo-time-inline';
      helper.innerHTML = `<i class="fa-regular fa-clock"></i> ${recommendation.interval}`;
      helper.title = `Recommended service time: ${recommendation.interval}`;
      cell.appendChild(helper);
    });
  });
});
