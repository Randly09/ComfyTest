import { elements } from './dom.js';

import {
    clearAttachment,
    resizePrompt,
} from './composer.js';

import {
    appendUserMessage,
    appendAssistantLoading,
    failAssistant,
} from './messages.js';

import {
    pollGeneration,
} from './polling.js';

export function initializeSubmit() {
    elements.form.addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const promptText =
                elements.prompt.value.trim();

            if (!promptText) {
                elements.prompt.focus();
                return;
            }

            if (
                elements.workflow.value === 'image_to_image'
                && elements.imageInput.files.length === 0
            ) {
                alert(
                    'Please attach a reference image for Image to Image.'
                );

                return;
            }

            const attachedFile =
                elements.imageInput.files[0]
                ?? null;

            const sentImageUrl =
                attachedFile
                    ? URL.createObjectURL(
                        attachedFile
                    )
                    : null;

            const formData =
                new FormData(
                    elements.form
                );

            appendUserMessage(
                promptText,
                sentImageUrl
            );

            const assistantUi =
                appendAssistantLoading();

            elements.prompt.value = '';
            elements.negativePrompt.value = '';

            clearAttachment();
            resizePrompt();

            const analysisWaitTimer =
                setTimeout(
                    () => {
                        if (
                            assistantUi.status
                            && assistantUi.status.isConnected
                        ) {
                            assistantUi
                                .status
                                .textContent =
                                'Waiting for AI analysis';
                        }
                    },
                    8000
                );

            try {
                const response =
                    await fetch(
                        elements.form.action,
                        {
                            method: 'POST',

                            body: formData,

                            headers: {
                                'Accept':
                                    'application/json',
                            },
                        }
                    );

                clearTimeout(
                    analysisWaitTimer
                );

                const data =
                    await response.json();

                if (!response.ok) {
                    let message =
                        data.message
                        || 'Could not start generation.';

                    if (data.errors) {
                        const errors =
                            Object.values(
                                data.errors
                            ).flat();

                        if (
                            errors.length > 0
                        ) {
                            message =
                                errors[0];
                        }
                    }

                    throw new Error(
                        message
                    );
                }

                assistantUi
                    .container
                    .dataset
                    .messageId =
                    data.message_id;

                assistantUi
                    .status
                    .textContent =
                    data.label
                    || 'In queue';

                pollGeneration(
                    data.message_id,
                    assistantUi,
                    {
                        actual_workflow:
                            data.actual_workflow,

                        detected_language:
                            data.detected_language,

                        positive_prompt:
                            data.positive_prompt,

                        negative_prompt:
                            data.negative_prompt,

                        attempt_count:
                            data.attempt_count,

                        max_attempts:
                            data.max_attempts,
                    }
                );

            } catch (error) {
                clearTimeout(
                    analysisWaitTimer
                );

                failAssistant(
                    assistantUi,
                    error.message
                    || 'Something went wrong.'
                );
            }
        }
    );
}