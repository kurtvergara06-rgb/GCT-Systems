document.addEventListener('DOMContentLoaded', function () {
  function openModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.add('show');
    modal.classList.add('active');
    modal.style.display = 'flex';
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

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

    if (!input) {
      return;
    }

    input.value =
      value === undefined ||
      value === null ||
      value === 'null'
        ? ''
        : value;
  }

  /*
  |--------------------------------------------------------------------------
  | ADD ITEM MODAL
  |--------------------------------------------------------------------------
  */

  const addModal = document.getElementById('addModal');
  const openAddModalButton = document.getElementById('openAddModal');

  if (openAddModalButton && addModal) {
    openAddModalButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      openModal(addModal);
    });
  }

  /*
  |--------------------------------------------------------------------------
  | IMPORT MODAL
  |--------------------------------------------------------------------------
  */

  const importModal = document.getElementById('importModal');
  const openImportModalButton = document.getElementById('openImportModal');

  if (openImportModalButton && importModal) {
    openImportModalButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

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
    const editButton = event.target.closest('.openEditModal');

    if (!editButton) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (!editModal || !editForm) {
      console.error('Edit modal or edit form was not found.');
      return;
    }

    const updateUrl = editButton.dataset.action;

    if (!updateUrl) {
      console.error('Inventory update URL was not found.');
      return;
    }

    /*
     * Set the PUT form destination.
     * This does not redirect the browser.
     */
    editForm.setAttribute('action', updateUrl);

    setInputValue(
      'edit_item_code',
      editButton.dataset.code
    );

    setInputValue(
      'edit_item_name',
      editButton.dataset.name
    );

    setInputValue(
      'edit_category',
      editButton.dataset.category
    );

    setInputValue(
      'edit_quantity',
      editButton.dataset.quantity
    );

    setInputValue(
      'edit_unit',
      editButton.dataset.unit
    );

    setInputValue(
      'edit_reorder',
      editButton.dataset.reorder
    );

    setInputValue(
      'edit_supplier',
      editButton.dataset.supplier
    );

    setInputValue(
      'edit_location',
      editButton.dataset.location
    );

    openModal(editModal);
  });

  /*
  |--------------------------------------------------------------------------
  | PREVENT EDIT FORM FROM USING GET
  |--------------------------------------------------------------------------
  */

  if (editForm) {
    editForm.addEventListener('submit', function (event) {
      const action = editForm.getAttribute('action');

      if (!action || action === '#') {
        event.preventDefault();

        console.error('The edit form action is missing.');
      }
    });
  }

  /*
  |--------------------------------------------------------------------------
  | CLOSE BUTTONS
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.closeModal').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      const modal = button.closest('.modal-overlay');

      closeModal(modal);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE WHEN CLICKING OUTSIDE MODAL
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CLOSE USING ESCAPE KEY
  |--------------------------------------------------------------------------
  */

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAllModals();
    }
  });
});