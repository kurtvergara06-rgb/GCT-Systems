document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('.job-orders-table tbody tr')
    .forEach((row) => {
      const rejectedPartStatus = row.querySelector(
        '.part-status-badge.rejected'
      );

      const finishButton = row.querySelector(
        '.finish-btn.open-finish-modal'
      );

      if (!rejectedPartStatus || !finishButton) {
        return;
      }

      finishButton.classList.remove(
        'open-finish-modal'
      );

      finishButton.classList.add(
        'locked-finish-btn'
      );

      finishButton.disabled = true;
      finishButton.type = 'button';
      finishButton.title =
        'Revise and resubmit the rejected Purchase Request before finishing this Job Order.';

      finishButton.innerHTML = `
        <i class="fa-solid fa-lock"></i>
        Locked
      `;
    });
});
