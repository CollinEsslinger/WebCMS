(() => {
  'use strict';
  document.querySelectorAll('[data-filter-input]').forEach(input => {
    const target = document.getElementById(input.dataset.filterInput);
    input.addEventListener('input', () => target?.querySelectorAll('[data-search]').forEach(row => {
      row.hidden = !row.dataset.search.toLocaleLowerCase('de').includes(input.value.toLocaleLowerCase('de'));
    }));
  });
  const builder = document.querySelector('[data-form-builder]');
  if (builder) {
    const list = builder.querySelector('[data-fields]');
    const template = builder.querySelector('template');
    const renumber = () => list.querySelectorAll('[data-field-row]').forEach((row, i) => {
      row.querySelectorAll('[data-key]').forEach(input => { input.name = `fields[${i}][${input.dataset.key}]`; });
    });
    builder.querySelector('[data-add-field]').addEventListener('click', () => { list.append(template.content.cloneNode(true)); renumber(); list.lastElementChild.querySelector('input').focus(); });
    list.addEventListener('click', event => {
      const button = event.target.closest('[data-field-action]'); if (!button) return;
      const row = button.closest('[data-field-row]');
      if (button.dataset.fieldAction === 'delete') row.remove();
      if (button.dataset.fieldAction === 'up' && row.previousElementSibling) list.insertBefore(row,row.previousElementSibling);
      if (button.dataset.fieldAction === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling,row);
      renumber();
    }); renumber();
  }
  document.querySelectorAll('[data-confirm-native]').forEach(form => form.addEventListener('submit', event => {
    if (!confirm(form.dataset.confirmNative)) event.preventDefault();
  }));
})();
