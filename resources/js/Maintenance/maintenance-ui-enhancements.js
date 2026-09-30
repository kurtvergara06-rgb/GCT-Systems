window.GCTPartialNavigation.registerInitializer('maintenance-ui-enhancements', '.jo-page, .pms-page, .purchase-page, .referrals-page, .fuel-page, .mechanic-page', () => {
  const editDurationField = document.getElementById('editJoEstimatedDuration');

  if (!editDurationField) {
    return;
  }

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const valueInput = editDurationField.querySelector(
        'input[name="estimated_duration_value"]'
      );
      const unitSelect = editDurationField.querySelector(
        'select[name="estimated_duration_unit"]'
      );

      if (valueInput) {
        valueInput.value = button.dataset.estimatedDurationValue || '';
      }

      if (unitSelect) {
        unitSelect.value = button.dataset.estimatedDurationUnit || 'Hours';
      }
    });
  });
});
