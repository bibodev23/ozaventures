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

const isTouchPickerViewport = () => window.matchMedia('(pointer: coarse), (max-width: 900px)').matches;

const prepareMobilePicker = (select) => {
    const tomSelect = select.tomselect;

    if (!tomSelect || !tomSelect.control || !tomSelect.control_input) {
        return false;
    }

    const controlInput = tomSelect.control_input;
    const applyMobileMode = () => {
        if (!isTouchPickerViewport()) {
            tomSelect.wrapper?.classList.remove('ts-touch-picker');
            controlInput.removeAttribute('readonly');
            controlInput.removeAttribute('inputmode');
            return;
        }

        tomSelect.wrapper?.classList.add('ts-touch-picker');
        controlInput.setAttribute('readonly', 'readonly');
        controlInput.setAttribute('inputmode', 'none');
        controlInput.setAttribute('autocomplete', 'off');
        controlInput.setAttribute('aria-label', 'Selectionner dans la liste');
    };

    const openPicker = (event) => {
        if (!isTouchPickerViewport()) {
            return;
        }

        if (event?.target instanceof Element && event.target.closest('.remove')) {
            return;
        }

        applyMobileMode();

        window.setTimeout(() => {
            tomSelect.focus();

            if (tomSelect.settings.load && Object.keys(tomSelect.options).length === 0) {
                tomSelect.load('');
            }

            tomSelect.refreshOptions(false);
            tomSelect.open();
        }, 0);
    };

    applyMobileMode();

    if (!tomSelect.__ozTouchPickerReady) {
        tomSelect.__ozTouchPickerReady = true;
        tomSelect.control.addEventListener('pointerdown', openPicker, { passive: true });
        controlInput.addEventListener('focus', openPicker);
    }

    return true;
};

const initMobilePickers = () => {
    document.querySelectorAll('select.mobile-picker-no-keyboard').forEach((select) => {
        if (prepareMobilePicker(select)) {
            return;
        }

        [40, 160, 420].forEach((delay) => {
            window.setTimeout(() => prepareMobilePicker(select), delay);
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
document.addEventListener('turbo:load', initMobilePickers);
document.addEventListener('DOMContentLoaded', initAccountMenus);
document.addEventListener('DOMContentLoaded', initMobilePickers);
window.addEventListener('resize', initMobilePickers);
