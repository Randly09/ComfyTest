<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AI Generator</title>


    <style>

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {
            margin: 0;

            min-height: 100vh;

            background: #212121;

            color: #ececec;

            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        .page {
            min-height: 100vh;

            display: flex;
            flex-direction: column;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .topbar {
            height: 58px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding:
                0
                24px;

            border-bottom:
                1px
                solid
                #323232;

            background:
                rgba(
                    33,
                    33,
                    33,
                    0.96
                );

            backdrop-filter:
                blur(10px);

            position:
                sticky;

            top: 0;

            z-index: 20;
        }


        .brand {
            font-size: 17px;

            font-weight: 600;
        }


        .system-name {
            font-size: 12px;

            color: #999999;
        }


        /*
        |--------------------------------------------------------------------------
        | Conversation
        |--------------------------------------------------------------------------
        */

        .conversation {
            width: 100%;

            max-width: 820px;

            margin:
                0
                auto;

            flex: 1;

            padding:
                40px
                22px
                220px;
        }


        /*
        |--------------------------------------------------------------------------
        | Welcome
        |--------------------------------------------------------------------------
        */

        .welcome {
            margin-top: 110px;

            text-align: center;
        }


        .welcome h1 {
            margin:
                0
                0
                10px;

            font-size: 31px;

            font-weight: 600;
        }


        .welcome p {
            color: #999999;

            margin: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | User message
        |--------------------------------------------------------------------------
        */

        .user-message-row {
            display: flex;

            justify-content: flex-end;

            margin-bottom: 32px;
        }


        .user-message {
            max-width: 75%;

            background: #303030;

            border-radius:
                20px
                20px
                5px
                20px;

            padding:
                12px
                16px;

            line-height: 1.5;

            overflow: hidden;
        }


        .user-message-text {
            white-space:
                pre-wrap;

            word-break:
                break-word;
        }


        .user-reference-image {
            display: block;

            width: 220px;

            max-width: 100%;

            max-height: 220px;

            object-fit: cover;

            border-radius: 12px;

            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Assistant
        |--------------------------------------------------------------------------
        */

        .assistant-message {
            margin-bottom: 42px;
        }


        .assistant-working {
            display: flex;

            align-items: center;

            gap: 12px;

            min-height: 40px;
        }


        .status-label {
            color: #aaaaaa;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Typing dots
        |--------------------------------------------------------------------------
        */

        .typing-dots {
            display: flex;

            align-items: center;

            gap: 5px;

            height: 24px;
        }


        .typing-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #dddddd;

            animation:
                typingDot
                1.25s
                infinite
                ease-in-out;
        }


        .typing-dot:nth-child(2) {
            animation-delay:
                0.15s;
        }


        .typing-dot:nth-child(3) {
            animation-delay:
                0.3s;
        }


        @keyframes typingDot {

            0%,
            60%,
            100% {

                opacity: 0.3;

                transform:
                    translateY(0);
            }


            30% {

                opacity: 1;

                transform:
                    translateY(-4px);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Generated result
        |--------------------------------------------------------------------------
        */

        .generated-title {
            color: #aaaaaa;

            font-size: 13px;

            margin-bottom: 10px;
        }


        .generated-image {
            display: block;

            max-width: 100%;

            max-height: 720px;

            border-radius: 16px;

            box-shadow:
                0
                8px
                30px
                rgba(
                    0,
                    0,
                    0,
                    0.28
                );
        }


        /*
        |--------------------------------------------------------------------------
        | AI interpretation debug
        |--------------------------------------------------------------------------
        */

        .debug-box {
            margin-top: 16px;

            border:
                1px
                solid
                #383838;

            border-radius: 13px;

            overflow: hidden;

            background: #292929;
        }


        .debug-box summary {
            cursor: pointer;

            padding:
                12px
                14px;

            color: #aaaaaa;

            font-size: 13px;
        }


        .debug-content {
            padding:
                0
                14px
                14px;

            line-height: 1.55;

            font-size: 13px;
        }


        .debug-content p {
            margin:
                8px
                0;
        }


        /*
        |--------------------------------------------------------------------------
        | Generation error
        |--------------------------------------------------------------------------
        */

        .assistant-error {
            padding:
                12px
                14px;

            border-radius: 12px;

            color: #ffb9b9;

            background: #412326;

            border:
                1px
                solid
                #643338;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | Composer background
        |--------------------------------------------------------------------------
        */

        .composer-wrapper {
            position: fixed;

            bottom: 0;
            left: 0;
            right: 0;

            z-index: 30;

            padding:
                35px
                18px
                26px;

            background:
                linear-gradient(
                    transparent,
                    #212121
                    30%
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Composer
        |--------------------------------------------------------------------------
        */

        .composer {
            width: 100%;

            max-width: 790px;

            margin:
                0
                auto;

            padding: 11px;

            background: #303030;

            border:
                1px
                solid
                #444444;

            border-radius: 27px;

            box-shadow:
                0
                8px
                30px
                rgba(
                    0,
                    0,
                    0,
                    0.28
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Prompt textarea
        |--------------------------------------------------------------------------
        */

        .prompt-textarea {
            display: block;

            width: 100%;

            min-height: 48px;

            max-height: 180px;

            resize: none;

            padding:
                8px
                8px
                3px;

            border: 0;

            outline: 0;

            background: transparent;

            color: #ffffff;

            font: inherit;

            font-size: 16px;

            line-height: 1.45;
        }


        .prompt-textarea::placeholder {
            color: #909090;
        }


        /*
        |--------------------------------------------------------------------------
        | Negative prompt
        |--------------------------------------------------------------------------
        */

        .negative-area {
            display: none;

            margin:
                4px
                5px
                8px;

            padding-top: 10px;

            border-top:
                1px
                solid
                #414141;
        }


        .negative-area textarea {
            display: block;

            width: 100%;

            resize: vertical;

            padding: 10px;

            background: #262626;

            color: white;

            border:
                1px
                solid
                #414141;

            border-radius: 11px;

            outline: none;

            font: inherit;

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | File preview
        |--------------------------------------------------------------------------
        */

        .file-preview {
            display: none;

            width:
                fit-content;

            max-width: 100%;

            margin:
                2px
                4px
                8px;

            padding: 7px;

            background: #252525;

            border-radius: 13px;
        }


        .file-preview-inner {
            display: flex;

            align-items: center;

            gap: 9px;
        }


        .file-thumbnail {
            width: 55px;
            height: 55px;

            border-radius: 9px;

            object-fit: cover;
        }


        .file-info {
            min-width: 0;
        }


        .file-name {
            max-width: 230px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

            font-size: 12px;
        }


        .file-label {
            color: #888888;

            font-size: 11px;

            margin-top: 3px;
        }


        .remove-file {
            border: 0;

            background: transparent;

            color: #999999;

            cursor: pointer;

            font-size: 20px;

            padding: 6px;
        }


        .remove-file:hover {
            color: white;
        }


        /*
        |--------------------------------------------------------------------------
        | Composer controls
        |--------------------------------------------------------------------------
        */

        .composer-bottom {
            display: flex;

            align-items: center;

            justify-content:
                space-between;

            gap: 10px;

            margin-top: 6px;
        }


        .composer-left {
            display: flex;

            align-items: center;

            gap: 7px;
        }


        .file-button {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            border:
                1px
                solid
                #4c4c4c;

            background: transparent;

            color: #ececec;

            font-size: 24px;

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;
        }


        .file-button:hover {
            background: #404040;
        }


        .workflow-select {
            outline: none;

            border: 0;

            background: #3c3c3c;

            color: #eeeeee;

            border-radius: 18px;

            padding:
                8px
                12px;

            cursor: pointer;

            font-size: 12px;
        }


        .send-button {
            width: 40px;
            height: 40px;

            border: 0;

            border-radius: 50%;

            background: #ffffff;

            color: #151515;

            font-size: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            cursor: pointer;
        }


        .send-button:hover {
            background: #dddddd;
        }


        /*
        |--------------------------------------------------------------------------
        | Drag/drop overlay
        |--------------------------------------------------------------------------
        */

        .drop-overlay {
            position: fixed;

            inset: 0;

            z-index: 100;

            display: none;

            align-items: center;

            justify-content: center;

            background:
                rgba(
                    20,
                    20,
                    20,
                    0.88
                );

            backdrop-filter:
                blur(5px);
        }


        .drop-overlay.active {
            display: flex;
        }


        .drop-box {
            padding:
                45px
                70px;

            border:
                2px
                dashed
                #888888;

            border-radius: 22px;

            background: #292929;

            text-align: center;

            font-size: 21px;
        }


        .drop-box small {
            display: block;

            margin-top: 8px;

            color: #999999;

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 650px
        ) {

            .conversation {
                padding-left: 14px;
                padding-right: 14px;
            }


            .composer-wrapper {
                padding-left: 8px;
                padding-right: 8px;
            }


            .user-message {
                max-width: 90%;
            }


            .system-name {
                display: none;
            }
        }

    </style>

</head>


<body>


{{-- DRAG OVERLAY --}}

<div
    class="drop-overlay"
    id="dropOverlay"
>

    <div class="drop-box">

        Add image

        <small>
            Drop your image here
        </small>

    </div>

</div>



<div class="page">


    {{-- HEADER --}}

    <header class="topbar">

        <div class="brand">
            AI Generator
        </div>


        <div class="system-name">
            ComfyUI + Ollama
        </div>

    </header>



    {{-- CHAT --}}

    <main
        class="conversation"
        id="conversation"
    >

        <div
            class="welcome"
            id="welcome"
        >

            <h1>
                What would you like to create?
            </h1>

            <p>
                Describe your idea in any language.
            </p>

        </div>

    </main>



    {{-- COMPOSER --}}

    <div class="composer-wrapper">

        <form
            action="{{ route('generate') }}"
            method="POST"
            enctype="multipart/form-data"
            class="composer"
            id="generatorForm"
        >

            @csrf


            {{-- FILE PREVIEW --}}

            <div
                class="file-preview"
                id="filePreview"
            >

                <div class="file-preview-inner">

                    <img
                        id="fileThumbnail"
                        class="file-thumbnail"
                        alt="Reference image"
                    >


                    <div class="file-info">

                        <div
                            id="fileName"
                            class="file-name"
                        ></div>


                        <div class="file-label">
                            Reference image
                        </div>

                    </div>


                    <button
                        type="button"
                        id="removeFile"
                        class="remove-file"
                    >
                        ×
                    </button>

                </div>

            </div>



            {{-- PROMPT --}}

            <textarea
                id="prompt"
                name="prompt"
                class="prompt-textarea"
                rows="1"
                placeholder="Message AI Generator..."
                required
            ></textarea>



            {{-- NEGATIVE PROMPT --}}

            <div
                id="negativePromptContainer"
                class="negative-area"
            >

                <textarea
                    id="negative_prompt"
                    name="negative_prompt"
                    rows="2"
                    placeholder="Things you do not want..."
                ></textarea>

            </div>



            {{-- CONTROLS --}}

            <div class="composer-bottom">

                <div class="composer-left">


                    <button
                        type="button"
                        class="file-button"
                        id="fileButton"
                        title="Add image"
                    >
                        +
                    </button>


                    <input
                        type="file"
                        name="image"
                        id="image"
                        accept="image/png,image/jpeg,image/webp"
                        hidden
                    >


                    <select
                        name="workflow"
                        id="workflow"
                        class="workflow-select"
                    >

                        <option value="auto">
                            Auto
                        </option>

                        <option value="text_to_image">
                            Text to Image
                        </option>

                        <option value="image_to_image">
                            Image to Image
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="send-button"
                    title="Send"
                >
                    ↑
                </button>

            </div>

        </form>

    </div>

</div>



<script>

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
        "{{ url('/generate/status') }}";


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

</script>


</body>

</html>