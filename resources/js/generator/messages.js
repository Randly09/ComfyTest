import { elements } from './dom.js';
import { scrollToBottom } from './utils.js';

export function appendUserMessage(text, imageUrl) {
    elements.welcome.style.display = 'none';

    const row = document.createElement('div');
    row.className = 'user-message-row';

    const bubble = document.createElement('div');
    bubble.className = 'user-message';

    if (imageUrl) {
        const img = document.createElement('img');
        img.className = 'user-reference-image';
        img.src = imageUrl;
        img.alt = 'Reference image';
        bubble.appendChild(img);
    }

    const messageText = document.createElement('div');
    messageText.className = 'user-message-text';
    messageText.textContent = text;

    bubble.appendChild(messageText);
    row.appendChild(bubble);
    elements.conversation.appendChild(row);

    scrollToBottom();
}

export function appendAssistantLoading() {
    const container = document.createElement('div');
    container.className = 'assistant-message';

    const working = document.createElement('div');
    working.className = 'assistant-working';

    const dots = document.createElement('div');
    dots.className = 'typing-dots';

    for (let i = 0; i < 3; i++) {
        const dot = document.createElement('div');
        dot.className = 'typing-dot';
        dots.appendChild(dot);
    }

    const status = document.createElement('div');
    status.className = 'status-label';
    status.textContent = 'Understanding your request';

    working.appendChild(dots);
    working.appendChild(status);
    container.appendChild(working);
    elements.conversation.appendChild(container);

    scrollToBottom();

    return {
        container,
        working,
        status,
    };
}

function addDebugLine(container, label, value) {
    if (
        value === null
        || value === undefined
        || value === ''
        || (Array.isArray(value) && value.length === 0)
    ) {
        return;
    }

    const paragraph = document.createElement('p');
    const strong = document.createElement('strong');

    strong.textContent = label + ': ';
    paragraph.appendChild(strong);

    const displayValue = Array.isArray(value)
        ? value.join(', ')
        : String(value);

    paragraph.appendChild(
        document.createTextNode(displayValue)
    );

    container.appendChild(paragraph);
}

export function completeAssistant(
    ui,
    mediaUrl,
    metadata = {}
) {
    if (ui.working && ui.working.isConnected) {
        ui.working.remove();
    }

    const title = document.createElement('div');
    title.className = 'generated-title';
    title.textContent = 'Generated image';

    const image = document.createElement('img');
    image.className = 'generated-image';
    image.src = mediaUrl;
    image.alt = 'Generated AI image';

    ui.container.appendChild(title);
    ui.container.appendChild(image);

    if (metadata.quality_status === 'best_available') {
        const warning = document.createElement('div');
        warning.className = 'quality-warning';
        warning.textContent =
            'Best available result after '
            + (metadata.attempt_count || '?')
            + ' attempts.';

        ui.container.appendChild(warning);
    }

    const details = document.createElement('details');
    details.className = 'debug-box';

    const summary = document.createElement('summary');
    summary.textContent = 'View AI interpretation';

    const debug = document.createElement('div');
    debug.className = 'debug-content';

    addDebugLine(debug, 'Detected language', metadata.detected_language);
    addDebugLine(debug, 'Workflow', metadata.actual_workflow);
    addDebugLine(debug, 'Positive prompt', metadata.positive_prompt);
    addDebugLine(debug, 'Negative prompt', metadata.negative_prompt || 'None');

    const requirements = metadata.quality_requirements || {};
    addDebugLine(debug, 'Primary subject', requirements.primary_subject);

    if (
        metadata.expected_count !== null
        && metadata.expected_count !== undefined
    ) {
        addDebugLine(
            debug,
            'Expected subject count',
            metadata.expected_count
        );
    }

    if (
        metadata.observed_count !== null
        && metadata.observed_count !== undefined
    ) {
        addDebugLine(
            debug,
            'Observed subject count',
            metadata.observed_count
        );
    }

    const visualFacts = metadata.visual_facts || {};

    addDebugLine(
        debug,
        'Observed scene',
        visualFacts.scene
    );

    addDebugLine(
        debug,
        'Observed subject colors',
        visualFacts.primary_subject_colors
    );

    addDebugLine(
        debug,
        'Hard rule failures',
        metadata.hard_failures
    );

    if (metadata.attempt_count !== undefined) {
        addDebugLine(
            debug,
            'Generation attempts',
            metadata.attempt_count
            + ' / '
            + metadata.max_attempts
        );
    }

    if (
        metadata.quality_score !== null
        && metadata.quality_score !== undefined
    ) {
        addDebugLine(
            debug,
            'Quality score',
            metadata.quality_score + '/100'
        );
    }

    addDebugLine(
        debug,
        'Quality feedback',
        metadata.quality_feedback
    );

    addDebugLine(
        debug,
        'Detected problems',
        metadata.quality_problems
    );

    details.appendChild(summary);
    details.appendChild(debug);
    ui.container.appendChild(details);

    ui.container.removeAttribute('data-message-id');

    scrollToBottom();
}

export function failAssistant(ui, message) {
    if (ui.working && ui.working.isConnected) {
        ui.working.remove();
    }

    const error = document.createElement('div');
    error.className = 'assistant-error';
    error.textContent = message;

    ui.container.appendChild(error);
    ui.container.removeAttribute('data-message-id');

    scrollToBottom();
}