
    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const form =
        document.getElementById(
            'generatorForm'
        );


    const conversation =
        document.getElementById(
            'conversation'
        );


    const welcome =
        document.getElementById(
            'welcome'
        );


    const prompt =
        document.getElementById(
            'prompt'
        );


    const negativePrompt =
        document.getElementById(
            'negative_prompt'
        );


    const negativeContainer =
        document.getElementById(
            'negativePromptContainer'
        );


    const workflow =
        document.getElementById(
            'workflow'
        );


    const imageInput =
        document.getElementById(
            'image'
        );


    const fileButton =
        document.getElementById(
            'fileButton'
        );


    const filePreview =
        document.getElementById(
            'filePreview'
        );


    const fileThumbnail =
        document.getElementById(
            'fileThumbnail'
        );


    const fileName =
        document.getElementById(
            'fileName'
        );


    const removeFile =
        document.getElementById(
            'removeFile'
        );


    const dropOverlay =
        document.getElementById(
            'dropOverlay'
        );


const statusBaseUrl =
    document.body.dataset.statusBaseUrl;


    let composerPreviewUrl =
        null;



    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    function sleep(ms) {

        return new Promise(
            resolve =>
                setTimeout(
                    resolve,
                    ms
                )
        );
    }


    function scrollToBottom() {

        window.scrollTo({
            top:
                document.body
                    .scrollHeight,

            behavior:
                'smooth'
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Auto resize textarea
    |--------------------------------------------------------------------------
    */

    function resizePrompt() {

        prompt.style.height =
            'auto';


        prompt.style.height =
            Math.min(
                prompt.scrollHeight,
                180
            ) + 'px';
    }


    prompt.addEventListener(
        'input',
        resizePrompt
    );



    /*
    |--------------------------------------------------------------------------
    | Workflow UI
    |--------------------------------------------------------------------------
    */

    function updateWorkflowFields() {

        if (
            workflow.value ===
            'auto'
        ) {

            /*
            | Ollama creates negative prompt.
            */

            negativeContainer
                .style
                .display =
                'none';


            fileButton
                .style
                .display =
                'flex';
        }


        else if (
            workflow.value ===
            'image_to_image'
        ) {

            negativeContainer
                .style
                .display =
                'block';


            fileButton
                .style
                .display =
                'flex';
        }


        else {

            /*
            | Text-to-image manual mode
            */

            negativeContainer
                .style
                .display =
                'block';


            fileButton
                .style
                .display =
                'none';


            clearAttachment();
        }
    }


    workflow.addEventListener(
        'change',
        updateWorkflowFields
    );


    updateWorkflowFields();



    /*
    |--------------------------------------------------------------------------
    | File picker
    |--------------------------------------------------------------------------
    */

    fileButton.addEventListener(
        'click',
        function () {

            imageInput.click();
        }
    );



    /*
    |--------------------------------------------------------------------------
    | Attach file
    |--------------------------------------------------------------------------
    */

    function attachFile(file) {

        if (!file) {
            return;
        }


        const acceptedTypes = [

            'image/jpeg',

            'image/png',

            'image/webp'

        ];


        if (
            !acceptedTypes.includes(
                file.type
            )
        ) {

            alert(
                'Please upload a JPG, PNG or WEBP image.'
            );

            return;
        }


        if (
            file.size >
            10 * 1024 * 1024
        ) {

            alert(
                'The image must be smaller than 10 MB.'
            );

            return;
        }


        /*
        | Put dropped file inside the
        | actual file input.
        */

        const transfer =
            new DataTransfer();


        transfer.items.add(
            file
        );


        imageInput.files =
            transfer.files;


        /*
        | Composer thumbnail
        */

        if (composerPreviewUrl) {

            URL.revokeObjectURL(
                composerPreviewUrl
            );
        }


        composerPreviewUrl =
            URL.createObjectURL(
                file
            );


        fileThumbnail.src =
            composerPreviewUrl;


        fileName.textContent =
            file.name;


        filePreview.style.display =
            'block';


        /*
        | If the user had selected
        | text-to-image and then attaches
        | an image, switch to Auto.
        */

        if (
            workflow.value ===
            'text_to_image'
        ) {

            workflow.value =
                'auto';

            updateWorkflowFields();
        }
    }


    imageInput.addEventListener(
        'change',
        function () {

            if (
                imageInput.files.length
                > 0
            ) {

                attachFile(
                    imageInput.files[0]
                );
            }
        }
    );



    /*
    |--------------------------------------------------------------------------
    | Remove file
    |--------------------------------------------------------------------------
    */

    function clearAttachment() {

        imageInput.value =
            '';


        filePreview.style.display =
            'none';


        fileThumbnail.src =
            '';


        fileName.textContent =
            '';


        if (composerPreviewUrl) {

            URL.revokeObjectURL(
                composerPreviewUrl
            );

            composerPreviewUrl =
                null;
        }
    }


    removeFile.addEventListener(
        'click',
        clearAttachment
    );



    /*
    |--------------------------------------------------------------------------
    | Drag and drop
    |--------------------------------------------------------------------------
    */

    let dragDepth = 0;


    document.addEventListener(
        'dragenter',
        function (event) {

            event.preventDefault();


            if (
                !event.dataTransfer
                    .types
                    .includes('Files')
            ) {
                return;
            }


            dragDepth++;


            dropOverlay.classList.add(
                'active'
            );
        }
    );


    document.addEventListener(
        'dragover',
        function (event) {

            event.preventDefault();
        }
    );


    document.addEventListener(
        'dragleave',
        function (event) {

            event.preventDefault();


            dragDepth--;


            if (dragDepth <= 0) {

                dragDepth = 0;


                dropOverlay
                    .classList
                    .remove(
                        'active'
                    );
            }
        }
    );


    document.addEventListener(
        'drop',
        function (event) {

            event.preventDefault();


            dragDepth = 0;


            dropOverlay
                .classList
                .remove(
                    'active'
                );


            if (
                event.dataTransfer
                    .files
                    .length
                > 0
            ) {

                attachFile(
                    event
                        .dataTransfer
                        .files[0]
                );
            }
        }
    );



    /*
    |--------------------------------------------------------------------------
    | Add user message to chat
    |--------------------------------------------------------------------------
    */

    function appendUserMessage(
        text,
        imageUrl
    ) {

        welcome.style.display =
            'none';


        const row =
            document.createElement(
                'div'
            );


        row.className =
            'user-message-row';


        const bubble =
            document.createElement(
                'div'
            );


        bubble.className =
            'user-message';


        /*
        | Attached reference image
        */

        if (imageUrl) {

            const img =
                document.createElement(
                    'img'
                );


            img.className =
                'user-reference-image';


            img.src =
                imageUrl;


            bubble.appendChild(
                img
            );
        }


        /*
        | User text
        */

        const messageText =
            document.createElement(
                'div'
            );


        messageText.className =
            'user-message-text';


        messageText.textContent =
            text;


        bubble.appendChild(
            messageText
        );


        row.appendChild(
            bubble
        );


        conversation.appendChild(
            row
        );


        scrollToBottom();
    }



    /*
    |--------------------------------------------------------------------------
    | Add assistant loading response
    |--------------------------------------------------------------------------
    */

    function appendAssistantLoading() {

        const container =
            document.createElement(
                'div'
            );


        container.className =
            'assistant-message';


        const working =
            document.createElement(
                'div'
            );


        working.className =
            'assistant-working';


        const dots =
            document.createElement(
                'div'
            );


        dots.className =
            'typing-dots';


        for (
            let i = 0;
            i < 3;
            i++
        ) {

            const dot =
                document.createElement(
                    'div'
                );


            dot.className =
                'typing-dot';


            dots.appendChild(
                dot
            );
        }


        const status =
            document.createElement(
                'div'
            );


        status.className =
            'status-label';


        status.textContent =
            'Understanding your request';


        working.appendChild(
            dots
        );


        working.appendChild(
            status
        );


        container.appendChild(
            working
        );


        conversation.appendChild(
            container
        );


        scrollToBottom();


        return {

            container:
                container,

            working:
                working,

            status:
                status,
        };
    }



    /*
    |--------------------------------------------------------------------------
    | Display final image
    |--------------------------------------------------------------------------
    */

    function completeAssistant(
        ui,
        imageData,
        metadata
    ) {

        ui.working.remove();


        const title =
            document.createElement(
                'div'
            );


        title.className =
            'generated-title';


        title.textContent =
            'Generated image';


        const image =
            document.createElement(
                'img'
            );


        image.className =
            'generated-image';


        image.src =
            imageData;


        image.alt =
            'Generated AI image';


        ui.container.appendChild(
            title
        );


        ui.container.appendChild(
            image
        );


        /*
        |--------------------------------------------------------------------------
        | Debug / AI interpretation
        |--------------------------------------------------------------------------
        */

        const details =
            document.createElement(
                'details'
            );


        details.className =
            'debug-box';


        const summary =
            document.createElement(
                'summary'
            );


        summary.textContent =
            'View AI interpretation';


        const debug =
            document.createElement(
                'div'
            );


        debug.className =
            'debug-content';


        if (
            metadata
                .detected_language
        ) {

            const language =
                document.createElement(
                    'p'
                );


            language.innerHTML =
                '<strong>Detected language:</strong> ';


            language.append(
                document.createTextNode(
                    metadata
                        .detected_language
                )
            );


            debug.appendChild(
                language
            );
        }


        const workflowInfo =
            document.createElement(
                'p'
            );


        workflowInfo.innerHTML =
            '<strong>Workflow:</strong> ';


        workflowInfo.append(
            document.createTextNode(
                metadata
                    .actual_workflow
            )
        );


        debug.appendChild(
            workflowInfo
        );


        const positive =
            document.createElement(
                'p'
            );


        positive.innerHTML =
            '<strong>Positive prompt:</strong> ';


        positive.append(
            document.createTextNode(
                metadata
                    .positive_prompt
            )
        );


        debug.appendChild(
            positive
        );


        const negative =
            document.createElement(
                'p'
            );


        negative.innerHTML =
            '<strong>Negative prompt:</strong> ';


        negative.append(
            document.createTextNode(
                metadata
                    .negative_prompt
                ||
                'None'
            )
        );


        debug.appendChild(
            negative
        );


        details.appendChild(
            summary
        );


        details.appendChild(
            debug
        );


        ui.container.appendChild(
            details
        );


        scrollToBottom();
    }



    /*
    |--------------------------------------------------------------------------
    | Assistant error
    |--------------------------------------------------------------------------
    */

    function failAssistant(
        ui,
        message
    ) {

        ui.working.remove();


        const error =
            document.createElement(
                'div'
            );


        error.className =
            'assistant-error';


        error.textContent =
            message;


        ui.container.appendChild(
            error
        );


        scrollToBottom();
    }



    /*
    |--------------------------------------------------------------------------
    | Poll ComfyUI generation status
    |--------------------------------------------------------------------------
    */

    async function pollGeneration(
        promptId,
        ui,
        metadata
    ) {

        let failedChecks = 0;


        while (true) {

            await sleep(1000);


            try {

                const response =
                    await fetch(
                        statusBaseUrl +
                        '/' +
                        encodeURIComponent(
                            promptId
                        ),
                        {
                            headers: {
                                'Accept':
                                    'application/json'
                            }
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        'Status request failed'
                    );
                }


                const data =
                    await response.json();


                failedChecks = 0;


                /*
                |--------------------------------------------------------------------------
                | Queued
                |--------------------------------------------------------------------------
                */

                if (
                    data.status ===
                    'queued'
                ) {

                    ui.status.textContent =
                        'Waiting in queue';

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Generating
                |--------------------------------------------------------------------------
                */

                if (
                    data.status ===
                    'generating'
                ) {

                    ui.status.textContent =
                        'Generating image';

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Processing output
                |--------------------------------------------------------------------------
                */

                if (
                    data.status ===
                    'processing'
                ) {

                    ui.status.textContent =
                        'Preparing result';

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Completed
                |--------------------------------------------------------------------------
                */

                if (
                    data.status ===
                    'completed'
                ) {

                    completeAssistant(
                        ui,
                        data.image_data,
                        metadata
                    );

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Failed
                |--------------------------------------------------------------------------
                */

                if (
                    data.status ===
                    'failed'
                ) {

                    failAssistant(
                        ui,
                        data.message
                        ??
                        'Generation failed.'
                    );

                    return;
                }

            }

            catch (error) {

                failedChecks++;


                /*
                | Don't instantly fail because of
                | one temporary connection problem.
                */

                if (
                    failedChecks < 5
                ) {

                    ui.status.textContent =
                        'Reconnecting';

                    continue;
                }


                failAssistant(
                    ui,
                    'Lost connection while checking generation status.'
                );


                return;
            }
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Submit message
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            const promptText =
                prompt
                    .value
                    .trim();


            if (!promptText) {

                prompt.focus();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Manual image-to-image needs an image
            |--------------------------------------------------------------------------
            */

            if (
                workflow.value ===
                'image_to_image'
                &&
                imageInput.files.length === 0
            ) {

                alert(
                    'Please attach a reference image for Image to Image.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Capture data BEFORE clearing composer
            |--------------------------------------------------------------------------
            */

            const attachedFile =
                imageInput.files[0]
                ?? null;


            const sentImageUrl =
                attachedFile
                ? URL.createObjectURL(
                    attachedFile
                )
                : null;


            const formData =
                new FormData(
                    form
                );


            /*
            |--------------------------------------------------------------------------
            | Add user's chat bubble immediately
            |--------------------------------------------------------------------------
            */

            appendUserMessage(
                promptText,
                sentImageUrl
            );


            /*
            |--------------------------------------------------------------------------
            | Add assistant typing dots immediately
            |--------------------------------------------------------------------------
            */

            const assistantUi =
                appendAssistantLoading();


            /*
            |--------------------------------------------------------------------------
            | Clear input immediately
            |--------------------------------------------------------------------------
            */

            prompt.value =
                '';


            negativePrompt.value =
                '';


            clearAttachment();


            resizePrompt();


            try {

                /*
                |--------------------------------------------------------------------------
                | Ask Laravel to analyse + submit workflow
                |--------------------------------------------------------------------------
                */

                const response =
                    await fetch(
                        form.action,
                        {

                            method:
                                'POST',

                            body:
                                formData,

                            headers: {

                                'Accept':
                                    'application/json',
                            },
                        }
                    );


                const data =
                    await response.json();


                /*
                |--------------------------------------------------------------------------
                | Laravel validation / server error
                |--------------------------------------------------------------------------
                */

                if (!response.ok) {

                    let message =
                        data.message
                        ??
                        'Could not start generation.';


                    /*
                    | Laravel validation errors
                    */

                    if (data.errors) {

                        const errors =
                            Object.values(
                                data.errors
                            )
                            .flat();


                        if (
                            errors.length
                            > 0
                        ) {

                            message =
                                errors[0];
                        }
                    }


                    throw new Error(
                        message
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | ComfyUI accepted the job
                |--------------------------------------------------------------------------
                */

                assistantUi
                    .status
                    .textContent =
                    'Waiting in queue';


                const metadata = {

                    actual_workflow:
                        data.actual_workflow,

                    detected_language:
                        data.detected_language,

                    positive_prompt:
                        data.positive_prompt,

                    negative_prompt:
                        data.negative_prompt,
                };


                /*
                |--------------------------------------------------------------------------
                | Start live status polling
                |--------------------------------------------------------------------------
                */

                pollGeneration(
                    data.prompt_id,
                    assistantUi,
                    metadata
                );

            }

            catch (error) {

                failAssistant(
                    assistantUi,
                    error.message
                    ??
                    'Something went wrong.'
                );
            }
        }
    );



    /*
    |--------------------------------------------------------------------------
    | Enter sends message
    | Shift + Enter creates new line
    |--------------------------------------------------------------------------
    */

    prompt.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key ===
                'Enter'
                &&
                !event.shiftKey
            ) {

                event.preventDefault();


                form.requestSubmit();
            }
        }
    );
