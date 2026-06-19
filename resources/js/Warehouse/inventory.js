document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (!modal) return;

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove('show');
    modal.classList.remove('active');
    modal.style.display = 'none';
  }

  function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(function (modal) {
      closeModal(modal);
    });
  }

  function setInputValue(id, value) {
    const input = document.getElementById(id);

    if (!input) return;

    input.value =
      value === undefined || value === null || value === 'null'
        ? ''
        : value;
  }

  /*
  |--------------------------------------------------------------------------
  | ADD ITEM MODAL
  |--------------------------------------------------------------------------
  */

  const addModal = document.getElementById('addModal');
  const openAddModal = document.getElementById('openAddModal');

  if (openAddModal && addModal) {
    openAddModal.addEventListener('click', function (event) {
      event.preventDefault();
      openModal(addModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | IMPORT MODAL
  |--------------------------------------------------------------------------
  */

  const importModal = document.getElementById('importModal');
  const openImportModal = document.getElementById('openImportModal');

  if (openImportModal && importModal) {
    openImportModal.addEventListener('click', function (event) {
      event.preventDefault();
      openModal(importModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | EDIT ITEM MODAL
  |--------------------------------------------------------------------------
  */

  const editModal = document.getElementById('editModal');
  const editForm = document.getElementById('editForm');

  document.addEventListener('click', function (event) {
    const button = event.target.closest('.openEditModal');

    if (!button) return;

    event.preventDefault();

    if (editForm) {
      editForm.action = button.dataset.action || '#';
    }

    setInputValue('edit_item_code', button.dataset.code);
    setInputValue('edit_item_name', button.dataset.name);
    setInputValue('edit_category', button.dataset.category);
    setInputValue('edit_quantity', button.dataset.quantity);
    setInputValue('edit_unit', button.dataset.unit);
    setInputValue('edit_reorder', button.dataset.reorder);
    setInputValue('edit_supplier', button.dataset.supplier);
    setInputValue('edit_location', button.dataset.location);

    openModal(editModal);
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE BUTTONS
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.closeModal').forEach(function (button) {
    button.addEventListener('click', function () {
      const modal = button.closest('.modal-overlay');
      closeModal(modal);
    });
  });

  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});