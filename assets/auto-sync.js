(() => {
    const root = document.documentElement;
    const baseUrl = root.dataset.baseUrl || '/SWK_Phonto';
    const endpoint = `${baseUrl.replace(/\/$/, '')}/api/auto-sync-trigger.php`;
    const storageKey = 'swk_phonto_photo_signature';
    let checking = false;

    async function checkForUpdates() {
        if (checking || document.hidden) return;
        checking = true;

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (!response.ok) return;

            const data = await response.json();
            const signature = `${Number(data.photo_count) || 0}:${Number(data.latest_photo_id) || 0}`;
            const previous = sessionStorage.getItem(storageKey);

            sessionStorage.setItem(storageKey, signature);

            if (previous && previous !== signature) {
                window.location.reload();
            }
        } catch (error) {
            // The website remains usable when background synchronization is unavailable.
        } finally {
            checking = false;
        }
    }

    checkForUpdates();
    window.setInterval(checkForUpdates, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) checkForUpdates();
    });
})();
