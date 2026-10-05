(() => {
  const form = document.querySelector('.login-form');
  const toggle = document.querySelector('[data-sa-password-toggle]');
  const password = document.getElementById('password');
  toggle?.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.setAttribute('aria-pressed', String(show));
  });
  const submit = form?.querySelector('[type="submit"]');
  const label = submit?.textContent;
  form?.addEventListener('submit', () => {
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    submit.textContent = form.elements.intent.value === 'mfa' ? 'Verifying...' : 'Signing in...';
  });
  window.addEventListener('pageshow', () => {
    if (!submit) return;
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
    submit.textContent = label;
  });
})();
