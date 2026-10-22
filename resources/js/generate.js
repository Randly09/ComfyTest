import { initializeComposer } from './generator/composer.js';
import { initializeSubmit } from './generator/submit.js';
import { resumePendingGenerations } from './generator/polling.js';

initializeComposer();
initializeSubmit();
resumePendingGenerations();