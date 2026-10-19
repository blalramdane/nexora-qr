import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { LocaleProvider } from './i18n/LocaleProvider';

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    const registerServiceWorker = () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // The app remains fully usable in the browser when SW registration is unavailable.
        });
    };

    if (document.readyState === 'complete') registerServiceWorker();
    else window.addEventListener('load', registerServiceWorker, { once: true });
}

createInertiaApp({
    title: (title) => title ? `${title} — Nexora QR` : 'Nexora QR',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<LocaleProvider><App {...props} /></LocaleProvider>);
    },
    progress: {
        color: '#111827',
    },
});
