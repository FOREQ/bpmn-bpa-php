'use strict';
// Avoid double clicks; failed HTML validation never disables the submit button.
document.querySelectorAll('.mc-main form').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) setTimeout(() => { button.disabled = true; }, 0);
  });
});
