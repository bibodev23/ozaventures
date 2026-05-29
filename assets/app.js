import { config } from '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import GlobalSearchController from './controllers/global_search_controller.js';
import OutingLocationMapController from './controllers/outing_location_map_controller.js';

// Keep Turbo navigation, but do not add page-transition or loading animations.
config.drive.progressBarDelay = 999999;

const app = startStimulusApp();
app.register('global-search', GlobalSearchController);
app.register('outing-location-map', OutingLocationMapController);
window.OZStimulusApp = app;
import('./stimulus_bootstrap.js');
