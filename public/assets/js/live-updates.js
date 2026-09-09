console.log('Live updates loaded');

document.addEventListener('DOMContentLoaded', () => {
    const INTERVAL = 5000;

    async function updateElement(element) {
        const url = element.dataset.liveUrl;

        if (
            !url ||
            element.dataset.liveLoading === '1' ||
            element.matches(':focus-within')
        ) {
            return;
        }

        element.dataset.liveLoading = '1';

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 419) {
                window.location.reload();
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const contentType =
                response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                const data = await response.json();

                if (data.html !== undefined) {
                    replaceContent(element, data.html);
                }

                if (data.value !== undefined) {
                    element.textContent = data.value;
                }

                return;
            }

            const html = await response.text();
            replaceContent(element, html);
        } catch (error) {
            console.error('Live update failed:', url, error);
        } finally {
            delete element.dataset.liveLoading;
        }
    }

    function replaceContent(element, html) {
        if (element.innerHTML.trim() === html.trim()) {
            return;
        }

        element.innerHTML = html;

        element.dispatchEvent(
            new CustomEvent('live:updated', {
                bubbles: true,
                detail: {
                    url: element.dataset.liveUrl,
                },
            })
        );
    }

    const stockErrorModal =
    document.getElementById('createStockErrorModal');

if (stockErrorModal) {
    document.body.style.overflow = 'hidden';
}
});