import { saveWithExpiry, getWithExpiry } from './utils';
console.info("campaigns.js runs here");
document.addEventListener('DOMContentLoaded', () => {
    const STORAGE_KEY = 'clientSelection';
    const EXPIRATION_MINUTES = 120; // 2 hours
    const select = document.getElementById('client_id');
    if (!select) return;

    // Restore saved value
    const saved = getWithExpiry(STORAGE_KEY);
    if (saved && [...select.options].some(opt => opt.value === saved)) {
        if (select.value !== saved) {
            select.value = saved;
            const url = new URL(window.location.href);
            const pathMatch = url.pathname.match(/\/campaigns\/client\/(\d+)/);
            const currentClientId = pathMatch ? pathMatch[1] : '';
            if (saved !== currentClientId) {
                window.location.href = '/campaigns/client/' + saved;
            }
        }
    }

    // Save on change
    select.addEventListener('change', function () {
        saveWithExpiry(STORAGE_KEY, this.value, EXPIRATION_MINUTES);
    });
});
