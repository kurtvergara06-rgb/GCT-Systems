document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('scheduleModal');
  const form = document.getElementById('scheduleForm');
  const method = document.getElementById('scheduleFormMethod');

  const title = document.getElementById('scheduleModalTitle');
  const subtitle = document.getElementById('scheduleModalSubtitle');
  const saveButton = document.getElementById('saveScheduleBtn');

  const frequency = document.getElementById('scheduleFrequency');
  const customGroup = document.getElementById('customIntervalGroup');
  const customDays = document.getElementById('customIntervalDays');

  const startDate = document.getElementById('scheduleStartDate');
  const nextDate = document.getElementById('scheduleNextDate');

  const openButton = document.getElementById('openScheduleModal');
  const closeButton = document.getElementById('closeScheduleModal');
  const cancelButton = document.getElementById('cancelScheduleModal');

  let viewingSchedule = false;

  function openModal() {
    modal?.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal?.classList.remove('show');
    document.body.style.overflow = '';
    viewingSchedule = false;
  }

  function setValue(id, value) {
    const element = document.getElementById(id);

    if (element) {
      element.value = value ?? '';
    }
  }

  function getLocalToday() {
    const today = new Date();

    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
  }

  function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
  }

  function createDateFromInput(value) {
    if (!value) {
      return null;
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
      return null;
    }

    const year = Number(parts[0]);
    const month = Number(parts[1]) - 1;
    const day = Number(parts[2]);

    return new Date(year, month, day);
  }

  /**
   * Adds months without overflowing into another month.
   *
   * Example:
   * January 31 + 1 month = February 28
   * instead of March 3.
   */
  function addMonthsClamped(date, numberOfMonths) {
    const originalDay = date.getDate();

    date.setDate(1);
    date.setMonth(date.getMonth() + numberOfMonths);

    const finalDayOfMonth = new Date(
      date.getFullYear(),
      date.getMonth() + 1,
      0
    ).getDate();

    date.setDate(Math.min(originalDay, finalDayOfMonth));

    return date;
  }

  function updateCustomInterval() {
    const isCustom = frequency?.value === 'Custom';

    customGroup?.classList.toggle('hidden', !isCustom);

    if (customDays) {
      customDays.required = isCustom;

      if (!isCustom && !viewingSchedule) {
        customDays.value = '';
      }
    }
  }

  function calculateNextPurchaseDate() {
    if (!startDate || !nextDate || !frequency) {
      return;
    }

    const calculatedDate = createDateFromInput(startDate.value);

    if (!calculatedDate) {
      nextDate.value = '';
      return;
    }

    switch (frequency.value) {
      case 'Weekly':
        calculatedDate.setDate(calculatedDate.getDate() + 7);
        break;

      case 'Biweekly':
        calculatedDate.setDate(calculatedDate.getDate() + 14);
        break;

      case 'Monthly':
        addMonthsClamped(calculatedDate, 1);
        break;

      case 'Quarterly':
        addMonthsClamped(calculatedDate, 3);
        break;

      case 'Semiannual':
        addMonthsClamped(calculatedDate, 6);
        break;

      case 'Yearly': {
        const originalMonth = calculatedDate.getMonth();

        calculatedDate.setFullYear(calculatedDate.getFullYear() + 1);

        /*
         * Handles February 29.
         */
        if (calculatedDate.getMonth() !== originalMonth) {
          calculatedDate.setDate(0);
        }

        break;
      }

      case 'Custom': {
        const interval = Number(customDays?.value || 0);

        if (!Number.isInteger(interval) || interval < 1) {
          nextDate.value = '';
          return;
        }

        calculatedDate.setDate(calculatedDate.getDate() + interval);
        break;
      }

      default:
        nextDate.value = startDate.value;
        return;
    }

    nextDate.value = formatDateForInput(calculatedDate);
  }

  function enableFormFields() {
    form
      ?.querySelectorAll('input, select, textarea')
      .forEach(function (element) {
        element.disabled = false;
      });

    /*
     * Next Purchase Date remains automatic.
     */
    if (nextDate) {
      nextDate.readOnly = true;
    }
  }

  function disableFormFields() {
    form
      ?.querySelectorAll('input, select, textarea')
      .forEach(function (element) {
        element.disabled = true;
      });
  }

  function resetForm() {
    if (!form) {
      return;
    }

    viewingSchedule = false;

    form.reset();
    form.action = form.dataset.storeUrl;

    enableFormFields();

    if (method) {
      method.value = 'POST';
    }

    setValue('scheduleQuantity', '1');
    setValue('scheduleUnit', 'PC');
    setValue('scheduleEstimatedCost', '0');
    setValue('scheduleStatus', 'Active');

    const today = getLocalToday();

    setValue('scheduleStartDate', today);

    if (frequency && !frequency.value) {
      frequency.value = 'Weekly';
    }

    if (title) {
      title.textContent = 'New Purchase Schedule';
    }

    if (subtitle) {
      subtitle.textContent = 'Create a recurring procurement plan.';
    }

    if (saveButton) {
      saveButton.textContent = 'Save Schedule';
      saveButton.style.display = '';
    }

    updateCustomInterval();
    calculateNextPurchaseDate();
  }

  function fillScheduleForm(data) {
    setValue('scheduleName', data.schedule_name);
    setValue('scheduleSupplier', data.supplier_name);
    setValue('scheduleContact', data.supplier_contact);
    setValue('scheduleItem', data.item);

    /*
     * Display quantity as a whole number.
     */
    setValue(
      'scheduleQuantity',
      Math.max(1, parseInt(data.quantity || 1, 10))
    );

    setValue('scheduleUnit', data.unit || 'PC');
    setValue('scheduleFrequency', data.frequency);
    setValue('customIntervalDays', data.custom_interval_days);

    setValue(
      'scheduleStartDate',
      String(data.start_date || '').slice(0, 10)
    );

    setValue(
      'scheduleNextDate',
      String(data.next_purchase_date || '').slice(0, 10)
    );

    setValue('scheduleEstimatedCost', data.estimated_cost);
    setValue('scheduleStatus', data.status || 'Active');
    setValue('scheduleNotes', data.notes);

    updateCustomInterval();
  }

  function parseScheduleData(button) {
    try {
      return JSON.parse(button.dataset.schedule || '{}');
    } catch (error) {
      console.error('Invalid schedule data:', error);
      return null;
    }
  }

  openButton?.addEventListener('click', function () {
    resetForm();
    openModal();
  });

  closeButton?.addEventListener('click', function () {
    closeModal();
  });

  cancelButton?.addEventListener('click', function () {
    closeModal();
  });

  startDate?.addEventListener('change', function () {
    calculateNextPurchaseDate();
  });

  frequency?.addEventListener('change', function () {
    updateCustomInterval();
    calculateNextPurchaseDate();
  });

  customDays?.addEventListener('input', function () {
    calculateNextPurchaseDate();
  });

  document.addEventListener('click', function (event) {
    const button = event.target.closest(
      '.open-edit-schedule, .open-view-schedule'
    );

    if (!button || !form) {
      return;
    }

    const data = parseScheduleData(button);

    if (!data) {
      return;
    }

    viewingSchedule = button.classList.contains(
      'open-view-schedule'
    );

    enableFormFields();
    fillScheduleForm(data);

    if (viewingSchedule) {
      form.action = '#';

      if (method) {
        method.value = 'PUT';
      }

      if (title) {
        title.textContent = 'Purchase Schedule Details';
      }

      if (subtitle) {
        subtitle.textContent =
          'Read-only scheduled purchase information.';
      }

      if (saveButton) {
        saveButton.style.display = 'none';
      }

      disableFormFields();
    } else {
      form.action = button.dataset.updateUrl;

      if (method) {
        method.value = 'PUT';
      }

      if (title) {
        title.textContent = 'Edit Purchase Schedule';
      }

      if (subtitle) {
        subtitle.textContent =
          'Update the recurring procurement details.';
      }

      if (saveButton) {
        saveButton.textContent = 'Update Schedule';
        saveButton.style.display = '';
      }
    }

    openModal();
  });

  form?.addEventListener('submit', function (event) {
    if (viewingSchedule) {
      event.preventDefault();
      return;
    }

    const quantity = document.getElementById('scheduleQuantity');

    if (quantity) {
      const quantityValue = Number(quantity.value);

      if (!Number.isInteger(quantityValue) || quantityValue < 1) {
        event.preventDefault();
        quantity.focus();

        window.alert(
          'Quantity must be a whole number greater than zero.'
        );

        return;
      }
    }

    calculateNextPurchaseDate();

    if (!nextDate?.value) {
      event.preventDefault();

      window.alert(
        'Please provide a valid frequency and custom interval.'
      );
    }
  });

  modal?.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal?.classList.contains('show')) {
      closeModal();
    }
  });

  updateCustomInterval();
});