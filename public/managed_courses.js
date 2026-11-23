'use strict';
// Avoid double clicks; failed HTML validation never disables the submit button.
document.querySelectorAll('.mc-main form').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) setTimeout(() => { button.disabled = true; }, 0);
  });
});

// Phones show each table row as a card; every cell keeps its column name.
document.querySelectorAll('.mc-table-wrap table').forEach(table => {
  const labels = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
  table.classList.add('mc-stack');
  table.querySelectorAll('tbody tr').forEach(row => {
    [...row.children].forEach((cell, index) => {
      if (!cell.hasAttribute('colspan') && labels[index]) cell.dataset.label = labels[index];
    });
  });
});
