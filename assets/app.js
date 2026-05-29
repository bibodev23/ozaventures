import { config } from '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';

// Keep Turbo navigation, but do not add page-transition or loading animations.
config.drive.progressBarDelay = 999999;

const app = startStimulusApp();
window.OZStimulusApp = app;
import('./stimulus_bootstrap.js');
