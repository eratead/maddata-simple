export function saveWithExpiry(key, value, ttlMinutes = 120) {
    const item = {
        value: value,
        expiry: Date.now() + ttlMinutes * 60 * 1000,
    };
    localStorage.setItem(key, JSON.stringify(item));
}

export function getWithExpiry(key) {
    const raw = localStorage.getItem(key);
    if (!raw) return null;

    try {
        const item = JSON.parse(raw);
        if (Date.now() > item.expiry) {
            localStorage.removeItem(key);
            return null;
        }
        return item.value;
    } catch (e) {
        return null;
    }
}

export function po(message) {
    console.info(message);
}
