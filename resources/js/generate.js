import { initializeComposer } from './generator/composer.js';
import { initializeSubmit } from './generator/submit.js';
import { resumePendingGenerations } from './generator/polling.js';

function initializeGenerator() {
    try {
        initializeComposer();
        initializeSubmit();
        resumePendingGenerations();

        console.log(
            'Summa AI generator initialized successfully.'
        );
    } catch (error) {
        console.error(
            'Summa AI generator failed to initialize:',
            error
        );
    }
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        initializeGenerator
    );
} else {
    initializeGenerator();
}