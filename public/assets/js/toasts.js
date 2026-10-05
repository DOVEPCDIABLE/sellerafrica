(function () {
    const root = document.getElementById('toast-root');
    if (!root) return;

    let toasts = [];
    try {
        toasts = JSON.parse(root.dataset.toasts || '[]');
    } catch (error) {
        toasts = [];
    }

    function showToast(toast) {
        const item = document.createElement('div');
        item.className = `toast toast-${toast.type || 'info'}`;
        item.innerHTML = `<strong>${toast.type || 'info'}</strong><span>${toast.message || ''}</span>`;
        root.appendChild(item);

        window.setTimeout(() => {
            item.classList.add('leaving');
            window.setTimeout(() => item.remove(), 240);
        }, 4200);
    }

    toasts.forEach(showToast);
    window.SellerAfricaToast = showToast;
})();
