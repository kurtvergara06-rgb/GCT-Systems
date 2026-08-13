class SearchableSelect {
  constructor(select, options = {}) {
    if (!select || select.dataset.searchableSelectInitialized === 'true') {
      return;
    }

    this.select = select;
    this.options = {
      placeholder: options.placeholder || 'Search and select...',
      emptyText: options.emptyText || 'No matching records found.',
      label: options.label || null,
    };

    this.select.dataset.searchableSelectInitialized = 'true';
    this.build();
    this.bind();
    this.syncFromSelect();
  }

  build() {
    const wrapper = document.createElement('div');
    wrapper.className = 'ui-searchable-select';

    const control = document.createElement('div');
    control.className = 'ui-searchable-select__control';

    const searchIcon = document.createElement('i');
    searchIcon.className = 'fa-solid fa-magnifying-glass ui-searchable-select__search-icon';
    searchIcon.setAttribute('aria-hidden', 'true');

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'ui-searchable-select__input';
    input.placeholder = this.options.placeholder;
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'ui-searchable-select__toggle';
    toggle.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
    toggle.setAttribute('aria-label', 'Show options');

    const panel = document.createElement('div');
    panel.className = 'ui-searchable-select__panel';
    panel.hidden = true;
    panel.setAttribute('role', 'listbox');

    control.append(searchIcon, input, toggle);
    wrapper.append(control, panel);

    this.select.classList.add('ui-searchable-select__native');
    this.select.insertAdjacentElement('afterend', wrapper);

    this.wrapper = wrapper;
    this.input = input;
    this.toggle = toggle;
    this.panel = panel;

    const label = this.select.closest('.form-group')?.querySelector(`label[for="${this.select.id}"]`);
    if (label && this.options.label) {
      label.textContent = this.options.label;
    }
  }

  bind() {
    this.input.addEventListener('focus', () => this.open());
    this.input.addEventListener('input', () => {
      this.open();
      this.render(this.input.value);
    });

    this.input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.close();
        this.input.blur();
      }

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        this.open();
        this.panel.querySelector('button:not([hidden])')?.focus();
      }
    });

    this.toggle.addEventListener('click', () => {
      if (this.panel.hidden) {
        this.open();
        this.input.focus();
      } else {
        this.close();
      }
    });

    this.select.addEventListener('change', () => this.syncFromSelect());

    this.select.form?.addEventListener('reset', () => {
      window.setTimeout(() => this.syncFromSelect(), 0);
    });

    document.addEventListener('click', (event) => {
      if (!this.wrapper.contains(event.target)) {
        this.close();
      }
    });
  }

  getOptions() {
    return Array.from(this.select.options).filter((option) => option.value !== '');
  }

  render(query = '') {
    const normalized = query.trim().toLowerCase();
    const options = this.getOptions().filter((option) => {
      const haystack = `${option.value} ${option.textContent}`.toLowerCase();
      return !normalized || haystack.includes(normalized);
    });

    this.panel.innerHTML = '';

    if (!options.length) {
      const empty = document.createElement('div');
      empty.className = 'ui-searchable-select__empty';
      empty.textContent = this.options.emptyText;
      this.panel.append(empty);
      return;
    }

    options.forEach((option) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'ui-searchable-select__option';
      item.setAttribute('role', 'option');
      item.dataset.value = option.value;

      const title = document.createElement('strong');
      title.textContent = option.value;

      const optionText = option.textContent.trim();
      const secondaryText = optionText.replace(option.value, '').replace(/^\s*[—-]\s*/, '').trim();

      const meta = document.createElement('span');
      meta.textContent = secondaryText || 'Official Bus ID';

      item.append(title, meta);

      if (option.value === this.select.value) {
        item.classList.add('is-selected');
        item.setAttribute('aria-selected', 'true');
      }

      item.addEventListener('click', () => this.choose(option));
      item.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          item.nextElementSibling?.focus();
        }
        if (event.key === 'ArrowUp') {
          event.preventDefault();
          (item.previousElementSibling || this.input).focus();
        }
        if (event.key === 'Escape') {
          this.close();
          this.input.focus();
        }
      });

      this.panel.append(item);
    });
  }

  choose(option) {
    this.select.value = option.value;
    this.select.dispatchEvent(new Event('change', { bubbles: true }));
    this.syncFromSelect();
    this.close();
    this.input.focus();
  }

  syncFromSelect() {
    const selected = this.select.selectedOptions?.[0];
    this.input.value = selected?.value ? selected.textContent.trim() : '';
    this.render('');
  }

  open() {
    this.render(this.input.value);
    this.panel.hidden = false;
    this.wrapper.classList.add('is-open');
    this.input.setAttribute('aria-expanded', 'true');
  }

  close() {
    this.panel.hidden = true;
    this.wrapper.classList.remove('is-open');
    this.input.setAttribute('aria-expanded', 'false');
  }
}

const initializeSearchableSelects = () => {
  const fuelBusSelect = document.getElementById('fuelBusNo');

  if (fuelBusSelect) {
    const instance = new SearchableSelect(fuelBusSelect, {
      label: 'Bus ID',
      placeholder: 'Type a Bus ID or plate number...',
      emptyText: 'No matching buses found.',
    });

    fuelBusSelect._searchableSelect = instance;

    document.querySelectorAll('[data-edit-fuel], #openFuelModal').forEach((button) => {
      button.addEventListener('click', () => {
        window.setTimeout(() => instance?.syncFromSelect(), 0);
      });
    });
  }
};

document.addEventListener('DOMContentLoaded', initializeSearchableSelects);

export { SearchableSelect, initializeSearchableSelects };
