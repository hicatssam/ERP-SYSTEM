import './bootstrap';

/**
 * Order details actions.
 *
 * Keep these handlers in the compiled app bundle instead of relying only on
 * inline onclick attributes. This makes Confirm / Complete / Cancel work even
 * when a browser or CSP blocks inline JavaScript.
 */
document.addEventListener('DOMContentLoaded', () => {
    const modalIds = {
        confirm: 'confirmOrderModal',
        complete: 'completeOrderModal',
        cancel: 'cancelOrderModal',
    };

    const openModal = (action) => {
        const modalId = modalIds[action];
        const modal = modalId ? document.getElementById(modalId) : null;

        if (!modal) {
            return false;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const firstButton = modal.querySelector('button, textarea, input, select');
        window.setTimeout(() => firstButton?.focus(), 30);

        return true;
    };

    const closeModal = (modal) => {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.action-btns button[type="button"]').forEach((button) => {
        const text = (button.textContent || '').trim();
        let action = null;

        if (text.includes('تأكيد الطلب')) action = 'confirm';
        else if (text.includes('إكمال الطلب')) action = 'complete';
        else if (text.includes('إلغاء الطلب')) action = 'cancel';

        if (!action) return;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            openModal(action);
        });
    });

    document.querySelectorAll('.order-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });

        modal.querySelectorAll('.order-modal-close, .order-modal-footer .btn-ghost').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal(modal);
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.order-modal.is-open').forEach(closeModal);
    });

    const confirmForm = document.getElementById('confirmOrderForm');
    if (confirmForm) {
        confirmForm.addEventListener('submit', () => {
            const button = document.getElementById('confirmOrderSubmitBtn');
            if (!button) return;

            button.disabled = true;
            button.textContent = 'جاري تأكيد الطلب...';
        });
    }
});
