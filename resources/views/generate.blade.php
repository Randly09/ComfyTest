<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    @vite([
    'resources/css/generate.css',
    'resources/js/generate.js'
])
    <title>Summa AI Generator</title>
</head>

<body
    data-status-base-url="{{ url('/generate/status') }}"
>

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
            Summa AI Generator
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
</body>
</html>