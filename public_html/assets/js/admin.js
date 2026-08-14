// Kleine, CSP-konforme Helfer fürs Backoffice (keine Inline-Handler).
// - [data-autosubmit]: Formular bei Änderung absenden
// - form[data-confirm]: Rückfrage vor dem Absenden

document.addEventListener('change', (ev) => {
  const el = ev.target;
  if (el instanceof HTMLElement && el.hasAttribute('data-autosubmit') && el.form) {
    el.form.submit();
  }
});

document.addEventListener('submit', (ev) => {
  const form = ev.target;
  if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm')) {
    const msg = form.getAttribute('data-confirm') || 'Aktion bestätigen?';
    if (!window.confirm(msg)) {
      ev.preventDefault();
    }
  }
});
