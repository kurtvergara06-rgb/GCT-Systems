document.addEventListener('DOMContentLoaded', () => {
  const addModal = document.getElementById('addModal');
  const editModal = document.getElementById('editModal');
  const viewModal = document.getElementById('viewModal');
  const importModal = document.getElementById('importModal');

  const openAddModal = document.getElementById('openAddModal');
  const openImportModal = document.getElementById('openImportModal');

  const closeButtons = document.querySelectorAll('.closeModal');

  function openModal(modal) {
    if (modal) {
      modal.classList.add('active');
    }
  }

  function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach((modal) => {
      modal.classList.remove('active');
    });
  }

  if (openAddModal) {
    openAddModal.addEventListener('click', () => openModal(addModal));
  }

  if (openImportModal) {
    openImportModal.addEventListener('click', () => openModal(importModal));
  }

  closeButtons.forEach((button) => {
    button.addEventListener('click', closeAllModals);
  });

  document.querySelectorAll('.modal-overlay').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeAllModals();
      }
    });
  });

  document.querySelectorAll('.openEditModal').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById('editForm').action = button.dataset.action;

      document.getElementById('edit_item_code').value = button.dataset.code;
      document.getElementById('edit_item_name').value = button.dataset.name;
      document.getElementById('edit_category').value = button.dataset.category;
      document.getElementById('edit_quantity').value = button.dataset.quantity;
      document.getElementById('edit_unit').value = button.dataset.unit;
      document.getElementById('edit_reorder').value = button.dataset.reorder;
      document.getElementById('edit_supplier').value = button.dataset.supplier || '';
      document.getElementById('edit_location').value = button.dataset.location || '';

      openModal(editModal);
    });
  });

  document.querySelectorAll('.openViewModal').forEach((button) => {
    button.addEventListener('click', () => {
      document.getElementById('view_code').textContent = button.dataset.code;
      document.getElementById('view_name').textContent = button.dataset.name;
      document.getElementById('view_category').textContent = button.dataset.category;
      document.getElementById('view_quantity').textContent = button.dataset.quantity;
      document.getElementById('view_unit').textContent = button.dataset.unit;
      document.getElementById('view_reorder').textContent = button.dataset.reorder;
      document.getElementById('view_status').textContent = button.dataset.status;
      document.getElementById('view_supplier').textContent = button.dataset.supplier;
      document.getElementById('view_location').textContent = button.dataset.location;
      document.getElementById('view_updated').textContent = button.dataset.updated;

      openModal(viewModal);
    });
  });
});