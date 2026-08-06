document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('jobModal');
  const busSelect = document.getElementById('jobBusNo');
  const mechanicSelect = document.getElementById('jobAssignedMechanic');

  if (!modal) {
    return;
  }

  function setupSearchableSelect(select, config) {
    if (!select || select.dataset.searchableReady === 'true') {
      return;
    }

    const inputWrap = select.closest('.ui-input-wrap');

    if (!inputWrap) {
      return;
    }

    select.dataset.searchableReady = 'true';
    inputWrap.classList.add('jo-native-mechanic-select');

    const combobox = document.createElement('div');
    combobox.className = 'jo-mechanic-combobox jo-new-combobox';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'jo-mechanic-combobox-trigger';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = `
      <span class="jo-mechanic-combobox-icon">
        <i class="fa-solid ${config.icon}"></i>
      </span>
      <span class="jo-mechanic-combobox-label placeholder">
        ${config.placeholder}
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
          placeholder="${config.searchPlaceholder}"
          autocomplete="off"
        >
      </div>
      <div class="jo-mechanic-combobox-options"></div>
    `;

    combobox.append(trigger, menu);
    inputWrap.insertAdjacentElement('afterend', combobox);

    const label = trigger.querySelector('.jo-mechanic-combobox-label');
    const searchInput = menu.querySelector('input');
    const optionsContainer = menu.querySelector('.jo-mechanic-combobox-options');

    const getDisplayText = (option) => {
      const originalText = option?.textContent?.trim() || '';

      return typeof config.formatOptionText === 'function'
        ? config.formatOptionText(originalText, option?.value || '')
        : originalText;
    };

    const closeMenu = () => {
      menu.hidden = true;
      combobox.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    const updateLabel = () => {
      const option = select.options[select.selectedIndex];
      const hasValue = Boolean(option?.value);

      label.textContent = hasValue
        ? getDisplayText(option)
        : config.placeholder;
      label.classList.toggle('placeholder', !hasValue);
    };

    const filterOptions = () => {
      const query = searchInput.value.trim().toLowerCase();
      let visible = 0;

      optionsContainer
        .querySelectorAll('.jo-mechanic-combobox-option')
        .forEach((button) => {
          const matches = button.dataset.search.includes(query);
          button.style.display = matches ? '' : 'none';

          if (matches) {
            visible += 1;
          }
        });

      let empty = optionsContainer.querySelector('.jo-mechanic-search-empty');

      if (!visible && query) {
        if (!empty) {
          empty = document.createElement('p');
          empty.className = 'jo-mechanic-combobox-empty jo-mechanic-search-empty';
          empty.textContent = config.emptyMessage;
          optionsContainer.appendChild(empty);
        }
      } else {
        empty?.remove();
      }
    };

    const selectOption = (value) => {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      updateLabel();
      renderOptions();
      closeMenu();
    };

    const renderOptions = () => {
      optionsContainer.innerHTML = '';

      Array.from(select.options).forEach((option) => {
        if (!option.value) {
          return;
        }

        const originalText = option.textContent.trim();
        const displayText = getDisplayText(option);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'jo-mechanic-combobox-option';
        button.dataset.value = option.value;
        button.dataset.search = `${option.value} ${originalText}`.toLowerCase();

        if (option.value === select.value) {
          button.classList.add('is-selected');
        }

        button.innerHTML = `
          <span>
            <strong>${displayText}</strong>
            <small>${config.optionHint}</small>
          </span>
          <i class="fa-solid fa-check"></i>
        `;

        button.addEventListener('click', () => selectOption(option.value));
        optionsContainer.appendChild(button);
      });

      updateLabel();
    };

    const openMenu = () => {
      if (select.disabled) {
        return;
      }

      menu.hidden = false;
      combobox.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      searchInput.value = '';
      filterOptions();
      window.setTimeout(() => searchInput.focus(), 0);
    };

    trigger.addEventListener('click', () => {
      menu.hidden ? openMenu() : closeMenu();
    });

    searchInput.addEventListener('input', filterOptions);

    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeMenu();
      }

      if (event.key === 'Enter') {
        const firstVisible = Array.from(
          optionsContainer.querySelectorAll('.jo-mechanic-combobox-option')
        ).find((button) => button.style.display !== 'none');

        if (firstVisible) {
          event.preventDefault();
          selectOption(firstVisible.dataset.value);
        }
      }
    });

    document.addEventListener('click', (event) => {
      if (!combobox.contains(event.target)) {
        closeMenu();
      }
    });

    const observer = new MutationObserver(() => {
      renderOptions();
      trigger.disabled = select.disabled;
      combobox.classList.toggle('is-disabled', select.disabled);
    });

    observer.observe(select, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled'],
    });

    renderOptions();
  }

  setupSearchableSelect(busSelect, {
    icon: 'fa-bus',
    placeholder: 'Select Bus',
    searchPlaceholder: 'Search bus number or plate...',
    emptyMessage: 'No bus matches your search.',
    optionHint: 'Available bus',
    formatOptionText: (text) => {
      const parts = text
        .split(' - ')
        .map((part) => part.trim())
        .filter(Boolean);

      if (
        parts.length === 2 &&
        parts[0].toLowerCase() === parts[1].toLowerCase()
      ) {
        return parts[0];
      }

      return text;
    },
  });

  setupSearchableSelect(mechanicSelect, {
    icon: 'fa-user-gear',
    placeholder: 'Select Available Mechanic',
    searchPlaceholder: 'Search mechanic name...',
    emptyMessage: 'No mechanic matches your search.',
    optionHint: 'Available mechanic',
  });
});
