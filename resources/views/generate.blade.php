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

    <title>Summa AI</title>
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

    <div class="app-shell">

        {{-- SIDEBAR --}}

        <aside class="sidebar">

            <div class="sidebar-header">

                <div class="sidebar-title">
                    Summa AI
                </div>

                <form
                    action="{{ route('chats.store') }}"
                    method="POST"
                >
                    @csrf

                    <button
                        type="submit"
                        class="new-chat-button"
                    >
                        + New chat
                    </button>
                </form>
            </div>

            {{-- CHAT HISTORY --}}
            <div class="chat-list">

                @foreach($chats as $chat)

                    <a
                        href="{{ route('chats.show', $chat) }}"
                        class="chat-item {{ $activeChat->id === $chat->id ? 'active' : '' }}"
                    >
                        <span class="chat-title">
                            {{ $chat->title }}
                        </span>

                        <span class="chat-expiry">
                            Removed {{ $chat->expires_at->diffForHumans() }}
                        </span>
                    </a>

                @endforeach

            </div>


            <div class="sidebar-retention">
                Chats are automatically removed after 5 days.
            </div>

        </aside>


        {{-- MAIN APPLICATION --}}

        <div class="main-app">

            {{-- HEADER --}}

            <header class="topbar">

                <div class="brand">
                    Summa AI
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



                {{-- SAVED CHAT MESSAGES --}}

                @foreach($messages as $message)

                    @if($message->role === 'user')

                        <div class="user-message-row">

                            <div class="user-message">

                                @if($message->media_path)

                                    <img
                                        src="{{ route('messages.media', $message) }}"
                                        class="user-reference-image"
                                        alt="Reference image"
                                    >

                                @endif

                                <div class="user-message-text">
                                    {{ $message->content }}
                                </div>

                            </div>

                        </div>

                    @else

                        <div
                            class="assistant-message"

                            @if(
                                !in_array(
                                    $message->status,
                                    ['completed', 'failed']
                                )
                            )
                                data-message-id="{{ $message->id }}"
                            @endif
                        >

                            {{-- COMPLETED --}}

                            @if(
                                $message->status === 'completed'
                                && $message->media_path
                            )

                                <div class="generated-title">
                                    Generated image
                                </div>

                                <img
                                    src="{{ route('messages.media', $message) }}"
                                    class="generated-image"
                                    alt="Generated AI image"
                                >

                                @if($message->quality_status === 'best_available')

                                    <div class="quality-warning">
                                        Best available result after {{ $message->attempt_count }} attempts.
                                    </div>

                                @endif

                                <details class="debug-box">

                                    <summary>
                                        View AI interpretation
                                    </summary>

                                    <div class="debug-content">

                                        @if($message->detected_language)
                                            <p>
                                                <strong>Detected language:</strong>
                                                {{ $message->detected_language }}
                                            </p>
                                        @endif

                                        <p>
                                            <strong>Workflow:</strong>
                                            {{ $message->workflow }}
                                        </p>

                                        <p>
                                            <strong>Positive prompt:</strong>
                                            {{ $message->positive_prompt }}
                                        </p>

                                        <p>
                                            <strong>Negative prompt:</strong>
                                            {{ $message->negative_prompt ?: 'None' }}
                                        </p>

                                        <p>
                                            <strong>Generation attempts:</strong>
                                            {{ $message->attempt_count }} / {{ $message->max_attempts }}
                                        </p>

                                        @if(!empty($message->quality_requirements['primary_subject'] ?? null))
                                            <p>
                                                <strong>Primary subject:</strong>
                                                {{ $message->quality_requirements['primary_subject'] }}
                                            </p>
                                        @endif

                                        @if(
                                            array_key_exists(
                                                'expected_count',
                                                $message->quality_requirements ?? []
                                            )
                                            && ($message->quality_requirements['expected_count'] ?? null) !== null
                                        )
                                            <p>
                                                <strong>Expected subject count:</strong>
                                                {{ $message->quality_requirements['expected_count'] }}
                                            </p>
                                        @endif

                                        @if($message->observed_subject_count !== null)
                                            <p>
                                                <strong>Observed subject count:</strong>
                                                {{ $message->observed_subject_count }}
                                            </p>
                                        @endif

                                        @if(!empty($message->visual_facts['scene'] ?? null))
                                            <p>
                                                <strong>Observed scene:</strong>
                                                {{ $message->visual_facts['scene'] }}
                                            </p>
                                        @endif

                                        @if(!empty($message->quality_hard_failures))
                                            <p class="debug-hard-failure">
                                                <strong>Hard rule failures:</strong>
                                                {{ implode(', ', $message->quality_hard_failures) }}
                                            </p>
                                        @endif

                                        @if($message->quality_score !== null)
                                            <p>
                                                <strong>Quality score:</strong>
                                                {{ $message->quality_score }}/100
                                            </p>
                                        @endif

                                        @if($message->quality_feedback)
                                            <p>
                                                <strong>Quality feedback:</strong>
                                                {{ $message->quality_feedback }}
                                            </p>
                                        @endif

                                        @if(!empty($message->quality_problems))
                                            <p>
                                                <strong>Detected problems:</strong>
                                                {{ implode(', ', $message->quality_problems) }}
                                            </p>
                                        @endif

                                    </div>

                                </details>


                            {{-- FAILED --}}

                            @elseif($message->status === 'failed')

                                <div class="assistant-error">
                                    {{ $message->content ?? 'Generation failed.' }}
                                </div>


                            {{-- STILL WORKING --}}

                            @else

                                <div class="assistant-working">

                                    <div class="typing-dots">
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                        <div class="typing-dot"></div>
                                    </div>

                                    <div class="status-label">

                                        @if($message->status === 'analyzing')

                                            Understanding your request

                                        @elseif($message->status === 'queued')

                                            @if($message->queue_position === 1)
                                                Next in queue
                                            @elseif($message->queue_position)
                                                In queue · {{ $message->queue_position - 1 }} request(s) ahead of you
                                            @else
                                                In queue
                                            @endif

                                        @elseif($message->status === 'generating')

                                            @if($message->attempt_count > 1)
                                                Generating improved result · attempt {{ $message->attempt_count }}/{{ $message->max_attempts }}
                                            @else
                                                Generating image
                                            @endif

                                        @elseif($message->status === 'checking_quality')

                                            @if(in_array(
                                                $message->quality_status,
                                                ['inspect_pending', 'inspecting']
                                            ))
                                                Inspecting generated image
                                            @elseif(in_array(
                                                $message->quality_status,
                                                ['evaluate_pending', 'evaluating']
                                            ))
                                                Comparing result with your request
                                            @else
                                                Checking image quality
                                            @endif

                                        @elseif($message->status === 'retrying')

                                            Quality check failed · retrying

                                        @elseif($message->status === 'processing')

                                            Preparing result

                                        @else

                                            Processing

                                        @endif

                                    </div>

                                </div>

                            @endif

                        </div>

                    @endif

                @endforeach

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

                    <input
                        type="hidden"
                        name="chat_id"
                        value="{{ $activeChat->id }}"
                    >


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
                        placeholder="Send a request..."
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

    </div>
</body>
</html>
