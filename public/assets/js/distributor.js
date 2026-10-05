(() => {
  document.querySelectorAll('[data-reveal]').forEach(button => button.addEventListener('click', () => {
    const input=document.getElementById(button.dataset.reveal);
    input.type=input.type==='password'?'text':'password';
    button.textContent=input.type==='password'?'Show':'Hide';
    button.setAttribute('aria-label',(input.type==='password'?'Show ':'Hide ')+input.name.replaceAll('_',' '));
  }));
  const account=document.querySelector('#distribution-account-form');
  if(account) {
    const confirmation=account.querySelector('[name=password_confirmation]');
    const check=()=>confirmation.setCustomValidity(confirmation.value===account.querySelector('[name=password]').value?'':'Passwords do not match.');
    account.addEventListener('input',check);
    account.addEventListener('submit',()=>{const button=account.querySelector('button[type=submit]');button.disabled=true;button.textContent='Creating account...';});
  }
  const form = document.querySelector('#distribution-form');
  if (!form) return;
  const panels = [...form.querySelectorAll('[data-panel]')];
  const steps = [...document.querySelectorAll('[data-step]')];
  let current = Number(form.dataset.initialStep || 0);
  function show(step) {
    current = step;
    panels.forEach((panel, index) => { panel.hidden = index !== step; });
    steps.forEach((button, index) => { if (index === step) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); });
    form.querySelector('[data-back]').hidden = step === 0;
    form.querySelector('[data-next]').hidden = step === panels.length - 1;
    form.querySelector('[data-final]').hidden = step !== panels.length - 1;
    if (step === panels.length - 1) {
      const summary = document.querySelector('#answer-summary'); summary.replaceChildren();
      const dl = document.createElement('dl');
      panels.slice(0, -1).forEach(panel => panel.querySelectorAll('[name]').forEach(input => {
        const dt = document.createElement('dt'); dt.textContent = input.labels[0]?.textContent || input.name;
        const dd = document.createElement('dd'); dd.textContent = input.value || 'Not provided'; dl.append(dt, dd);
      })); summary.append(dl);
    }
  }
  function valid(panel) {
    let first;
    panel.querySelectorAll('input,select,textarea').forEach(input => {
      const error = document.querySelector('#error-' + input.name);
      if (!input.checkValidity()) { input.setAttribute('aria-invalid', 'true'); if (error) error.textContent = input.validationMessage; first ||= input; }
      else { input.removeAttribute('aria-invalid'); if (error) error.textContent = ''; }
    });
    if (first) { first.focus(); first.reportValidity(); }
    return !first;
  }
  form.querySelector('[data-next]').onclick = () => { if (valid(panels[current])) { show(current + 1); steps[current].focus(); } };
  form.querySelector('[data-back]').onclick = () => show(current - 1);
  form.addEventListener('input', event => {
    const input = event.target;
    if (input.matches('input,select,textarea') && input.checkValidity()) {
      input.removeAttribute('aria-invalid');
      const error = document.getElementById('error-' + input.name);
      if (error) error.textContent = '';
    }
  });
  steps.forEach((button, index) => { button.onclick = () => { if (index <= current || valid(panels[current])) show(index); }; });
  document.querySelectorAll('.errors a').forEach(link => link.onclick = () => { const field = document.getElementById(link.hash.slice(1)); if (field) show(panels.indexOf(field.closest('[data-panel]'))); });
  form.addEventListener('submit', event => {
    const intent = event.submitter?.value || 'draft';
    if (intent === 'submit') {
      for (let i = 0; i < panels.length; i++) {
        if ([...panels[i].querySelectorAll('input,select,textarea')].some(input => !input.checkValidity())) {
          event.preventDefault(); show(i); valid(panels[i]); return;
        }
      }
    }
    const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'intent'; hidden.value = intent; form.append(hidden);
    form.querySelectorAll('button').forEach(button => { button.disabled = true; });
    if (event.submitter) event.submitter.textContent = intent === 'submit' ? 'Submitting...' : 'Saving...';
  });
  show(current);
})();
