import { config } from '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import GlobalSearchController from './controllers/global_search_controller.js';

// Keep Turbo navigation, but do not add page-transition or loading animations.
config.drive.progressBarDelay = 999999;

const app = startStimulusApp();
app.register('global-search', GlobalSearchController);
window.OZStimulusApp = app;
import('./stimulus_bootstrap.js');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // The app must stay usable even if the browser refuses PWA registration.
        });
    });
}

const initAccountMenus = () => {
    document.querySelectorAll('details.user-menu').forEach((menu) => {
        if (menu.__ozAccountMenuReady) {
            return;
        }

        menu.__ozAccountMenuReady = true;
        const summary = menu.querySelector('summary');

        summary?.addEventListener('click', (event) => {
            event.preventDefault();

            document.querySelectorAll('details.user-menu[open]').forEach((openedMenu) => {
                if (openedMenu !== menu) {
                    openedMenu.removeAttribute('open');
                }
            });

            menu.toggleAttribute('open');
        });
    });
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || event.target.closest('details.user-menu')) {
        return;
    }

    document.querySelectorAll('details.user-menu[open]').forEach((menu) => {
        menu.removeAttribute('open');
    });
});

document.addEventListener('turbo:load', initAccountMenus);
document.addEventListener('DOMContentLoaded', initAccountMenus);
