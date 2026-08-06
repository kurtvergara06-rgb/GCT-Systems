document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('editJobModal');
  const editForm = document.getElementById('editJobForm');
  const busSelect = document.getElementById('edit_bus_no');
  const mechanicSelect = document.getElementById('edit_assigned_mechanic');

  if (!editModal || !editForm || !busSelect || !mechanicSelect) {
    return;
  }

  /*
   * Keep the bus value submitted, but prevent users from changing it
   * while editing an existing Job Order.
   */
  busSelect.classList.add('jo-edit-locked-select');
  busSelect.setAttribute('aria-readonly', 'true');
  busSelect.setAttribute('tabindex', '-1');

  let lockedBusValue = '';

  const lockCurrentBus = () => {
    lockedBusValue = busSelect.value;
    busSelect.dataset.lockedValue = lockedBusValue;
  };

  busSelect.addEventListener('mousedown', (event) => {
    event.preventDefault();
  });

  busSelect.addEventListener('keydown', (event) => {
    event.preventDefault();
  });

  busSelect.addEventListener('change', () => {
    if (busSelect.dataset.lockedValue) {
      busSelect.value = busSelect.dataset.lockedValue;
    }
  });

  editForm.addEventListener('submit', () => {
    if (lockedBusValue) {
      busSelect.value = lockedBusValue;
    }
  });

  /*
   * Build a searchable mechanic combobox while retaining the original
   * select as the submitted form control.
   */
  const group = mechanicSelect.closest('.ui-form-group');
  const inputWrap = mechanicSelect.closest('.ui-input-wrap');

  if (!group || !inputWrap) {
    return;
  }

  const combobox = document.createElement('div');
  combobox.className = 'jo-mechanic-combobox';

  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'jo-mechanic-combobox-trigger';
  trigger.setAttribute('aria-expanded', 'false');
  trigger.innerHTML = `
    <span class="jo-mechanic-combobox-icon">
      <i class="fa-solid fa-user-gear"></i>
    </span>
    <span class="jo-mechanic-combobox-label placeholder">
      Select available mechanic
    </span>
    <i class="fa-solid fa-chevron-down jo-mechanic-combobox-chevron"></i>
  `;

  const menu = document.createElement('div');
  menu.className = 'jo-mechanic-combobox-menu';
  menu.hidden = true;
  menu.innerHTML = `
    <div class="jo-mechanic-combobox-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input
        type="search"
        placeholder="Search mechanic name..."
        autocomplete="off"
      >
    </div>
    <div class="jo-mechanic-combobox-options"></div>
  `;

  combobox.append(trigger, menu);
  inputWrap.insertAdjacentElement('afterend', combobox);
  inputWrap.classList.add('jo-native-mechanic-select');

  const label = trigger.querySelector('.jo-mechanic-combobox-label');
  const searchInput = menu.querySelector('input');
  const optionsContainer = menu.querySelector('.jo-mechanic-combobox-options');

  const closeMenu = () => {
    menu.hidden = true;
    combobox.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  const updateLabel = () => {
    const selectedOption = mechanicSelect.options[mechanicSelect.selectedIndex];
    const selectedText = selectedOption?.value
      ? selectedOption.textContent.trim()
      : 'No mechanic assigned';

    label.textContent = selectedText;
    label.classList.toggle('placeholder', !selectedOption?.value);
  };

  const selectMechanic = (value) => {
    mechanicSelect.value = value;
    mechanicSelect.dispatchEvent(new Event('change', { bubbles: true }));
    updateLabel();
    renderOptions();
    closeMenu();
  };

  const renderOptions = () => {
    optionsContainer.innerHTML = '';

    const options = Array.from(mechanicSelect.options);

    options.forEach((option) => {
      const optionButton = document.createElement('button');
      optionButton.type = 'button';
      optionButton.className = 'jo-mechanic-combobox-option';
      optionButton.dataset.value = option.value;
      optionButton.dataset.search = option.textContent.trim().toLowerCase();

      if (option.value === mechanicSelect.value) {
        optionButton.classList.add('is-selected');
      }

      const displayText = option.value
        ? option.textContent.trim()
        : 'No mechanic assigned';

      optionButton.innerHTML = `
        <span>
          <strong>${displayText}</strong>
          <small>${option.value ? 'Available mechanic' : 'Keep Job Order on hold'}</small>
        </span>
        <i class="fa-solid fa-check"></i>
      `;

      optionButton.addEventListener('click', () => {
        selectMechanic(option.value);
      });

      optionsContainer.appendChild(optionButton);
    });

    if (!options.length) {
      optionsContainer.innerHTML = `
        <p class="jo-mechanic-combobox-empty">
          No available mechanics found.
        </p>
      `;
    }

    updateLabel();
  };

  const filterOptions = (query) => {
    const normalizedQuery = String(query || '').trim().toLowerCase();
    let visibleCount = 0;

    optionsContainer
      .querySelectorAll('.jo-mechanic-combobox-option')
      .forEach((optionButton) => {
        const searchText = optionButton.dataset.search || '';
        const matches = searchText.includes(normalizedQuery);

        optionButton.hidden = !matches;
        optionButton.style.display = matches ? '' : 'none';
        optionButton.setAttribute('aria-hidden', matches ? 'false' : 'true');

        if (matches) {
          visibleCount += 1;
        }
      });

    let emptyResult = optionsContainer.querySelector('.jo-mechanic-search-empty');

    if (!visibleCount && normalizedQuery) {
      if (!emptyResult) {
        emptyResult = document.createElement('p');
        emptyResult.className = 'jo-mechanic-combobox-empty jo-mechanic-search-empty';
        emptyResult.textContent = 'No mechanic matches your search.';
        optionsContainer.appendChild(emptyResult);
      }
    } else {
      emptyResult?.remove();
    }
  };

  const openMenu = () => {
    if (mechanicSelect.disabled) {
      return;
    }

    menu.hidden = false;
    combobox.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
    searchInput.value = '';
    filterOptions('');
    window.setTimeout(() => searchInput.focus(), 0);
  };

  const syncDisabledState = () => {
    trigger.disabled = mechanicSelect.disabled;
    combobox.classList.toggle('is-disabled', mechanicSelect.disabled);

    if (mechanicSelect.disabled) {
      closeMenu();
    }
  };

  trigger.addEventListener('click', () => {
    if (menu.hidden) {
      openMenu();
    } else {
      closeMenu();
    }
  });

  searchInput.addEventListener('input', () => {
    filterOptions(searchInput.value);
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      trigger.focus();
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();

      const firstVisibleOption = Array.from(
        optionsContainer.querySelectorAll('.jo-mechanic-combobox-option')
      ).find((optionButton) => optionButton.style.display !== 'none');

      firstVisibleOption?.click();
    }
  });

  document.addEventListener('click', (event) => {
    if (!combobox.contains(event.target)) {
      closeMenu();
    }
  });

  document.querySelectorAll('.open-edit-modal').forEach((button) => {
    button.addEventListener('click', () => {
      window.setTimeout(() => {
        lockCurrentBus();
        renderOptions();
        syncDisabledState();
      }, 0);
    });
  });

  const mechanicObserver = new MutationObserver(() => {
    renderOptions();
    syncDisabledState();
  });

  mechanicObserver.observe(mechanicSelect, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled'],
  });

  renderOptions();
  syncDisabledState();
});
