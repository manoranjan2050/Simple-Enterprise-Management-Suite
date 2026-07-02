if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sems/service-worker.js', { scope: '/sems/' })
            .catch((err) => console.error('SEMS service worker registration failed:', err));
    });
}
