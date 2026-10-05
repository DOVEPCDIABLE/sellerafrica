document.addEventListener('click', (event) => {
  const copyTrigger = event.target.closest('[data-copy-target]');
  if (copyTrigger) {
    const key = copyTrigger.getAttribute('data-copy-target');
    const source = document.querySelector(`[data-copy-source="${key}"]`);
    const value = source?.value || source?.textContent || '';
    if (!value.trim()) return;

    const done = () => {
      const original = copyTrigger.textContent;
      copyTrigger.textContent = 'Copied';
      window.setTimeout(() => {
        copyTrigger.textContent = original || 'Copy link';
      }, 1600);
    };

    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(value).then(done).catch(() => {
        source?.select?.();
        document.execCommand?.('copy');
        done();
      });
    } else {
      source?.select?.();
      document.execCommand?.('copy');
      done();
    }
    return;
  }

  const trigger = event.target.closest('[data-portal-menu]');
  if (trigger) {
    document.body.classList.toggle('portal-sidebar-open');
    return;
  }

  if (document.body.classList.contains('portal-sidebar-open') && !event.target.closest('.portal-sidebar')) {
    document.body.classList.remove('portal-sidebar-open');
  }
});

document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;

  if (form.dataset.isSubmitting === '1') {
    event.preventDefault();
    return;
  }

  if (!form.checkValidity()) {
    return;
  }

  form.dataset.isSubmitting = '1';
  form.querySelectorAll('button[type="submit"]').forEach((button) => {
    button.dataset.originalText = button.textContent || '';
    button.textContent = button.dataset.loadingText || 'Saving...';
    button.classList.add('is-loading');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
  });
});
