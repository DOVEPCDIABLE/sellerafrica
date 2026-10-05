(() => {
  const dialog = document.getElementById('account-mobile-menu');
  const trigger = document.querySelector('.account-menu-toggle');
  if (!dialog || !trigger) return;
  const close = () => dialog.close();
  trigger.addEventListener('click', () => {
    dialog.showModal();
    trigger.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('account-menu-open');
  });
  dialog.querySelector('.account-menu-close').addEventListener('click', close);
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
  });
  dialog.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
  dialog.addEventListener('close', () => {
    trigger.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('account-menu-open');
    if (trigger.getClientRects().length) trigger.focus();
  });
  matchMedia('(max-width: 1100px)').addEventListener('change', event => {
    if (!event.matches && dialog.open) close();
  });
})();
