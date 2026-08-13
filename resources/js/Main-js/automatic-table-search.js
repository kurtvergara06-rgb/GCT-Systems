document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form.toolbar');

  forms.forEach((form) => {
    const input = form.querySelector('.search-box input[type="text"]');

    if (!input || input.dataset.autoSearchBound === 'true') {
      return;
    }

    input.dataset.autoSearchBound = 'true';

    let timer = null;
    let lastSubmittedValue = input.value.trim();

    const submitSearch = () => {
      const currentValue = input.value.trim();

      if (currentValue === lastSubmittedValue) {
        return;
      }

      lastSubmittedValue = currentValue;
      input.closest('.search-box')?.classList.add('is-searching');

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(submitSearch, 450);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        window.clearTimeout(timer);
        input.value = '';
        submitSearch();
      }
    });
  });
});
