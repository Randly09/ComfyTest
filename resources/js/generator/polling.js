import { statusBaseUrl } from './dom.js';
import { sleep } from './utils.js';
import {
    completeAssistant,
    failAssistant,
} from './messages.js';

export async function pollGeneration(
    messageId,
    ui,
    initialMetadata = {}
) {
    let failedChecks = 0;

    while (true) {
        await sleep(1000);

        try {
            const response = await fetch(
                statusBaseUrl
                + '/'
                + encodeURIComponent(messageId),
                {
                    headers: {
                        'Accept': 'application/json',
                    },
                }
            );

            let data = {};

            try {
                data = await response.json();
            } catch (error) {
                // Generic HTTP error below is enough.
            }

            if (!response.ok) {
                throw new Error(
                    data.message
                    || 'Status request failed.'
                );
            }

            failedChecks = 0;

            if (data.status === 'completed') {
                completeAssistant(
                    ui,
                    data.media_url,
                    {
                        ...initialMetadata,
                        ...data,
                    }
                );

                return;
            }

            if (data.status === 'failed') {
                failAssistant(
                    ui,
                    data.message
                    || 'Generation failed.'
                );

                return;
            }

            ui.status.textContent =
                data.label
                || 'Processing';

        } catch (error) {
            failedChecks++;

            if (failedChecks < 5) {
                ui.status.textContent =
                    'Reconnecting...';

                console.error(
                    'Generation status error:',
                    error
                );

                continue;
            }

            failAssistant(
                ui,
                error.message
                || 'Lost connection while checking generation status.'
            );

            return;
        }
    }
}

export function resumePendingGenerations() {
    const pendingMessages =
        document.querySelectorAll(
            '.assistant-message[data-message-id]'
        );

    pendingMessages.forEach(container => {
        const messageId =
            container.dataset.messageId;

        const working =
            container.querySelector(
                '.assistant-working'
            );

        const status =
            container.querySelector(
                '.status-label'
            );

        if (
            !messageId
            || !working
            || !status
        ) {
            return;
        }

        pollGeneration(
            messageId,
            {
                container,
                working,
                status,
            }
        );
    });
}