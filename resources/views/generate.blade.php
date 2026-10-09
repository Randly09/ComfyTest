<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>AI Generator</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h1 class="mb-3">
                        AI Generator
                    </h1>

                    <p class="text-muted">
                        Select how you want to generate your image.
                    </p>


                    @if ($errors->any())

                        <div class="alert alert-danger">

                            @foreach ($errors->all() as $error)

                                <div>
                                    {{ $error }}
                                </div>

                            @endforeach

                        </div>

                    @endif


                    <form
                        action="{{ route('generate') }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        @csrf


                        {{-- WORKFLOW --}}

                        <div class="mb-3">

                            <label
                                for="workflow"
                                class="form-label"
                            >
                                Generation Type
                            </label>

                            <select
                                name="workflow"
                                id="workflow"
                                class="form-select"
                            >

                                <option
                                    value="text_to_image"

                                    @selected(
                                        old(
                                            'workflow',
                                            $selectedWorkflow ?? ''
                                        )
                                        ===
                                        'text_to_image'
                                    )
                                >
                                    Text to Image
                                </option>


                                <option
                                    value="image_to_image"

                                    @selected(
                                        old(
                                            'workflow',
                                            $selectedWorkflow ?? ''
                                        )
                                        ===
                                        'image_to_image'
                                    )
                                >
                                    Image to Image
                                </option>

                            </select>

                        </div>


                        {{-- PROMPT --}}

                        <div class="mb-3">

                            <label
                                for="prompt"
                                class="form-label"
                            >
                                Prompt
                            </label>

                            <textarea
                                id="prompt"
                                name="prompt"
                                class="form-control"
                                rows="4"
                                placeholder="For example: A futuristic sports car..."
                                required
                            >{{ old('prompt', $prompt ?? '') }}</textarea>

                        </div>


                        {{-- IMAGE UPLOAD --}}

                        <div
                            class="mb-3"
                            id="imageUploadContainer"
                        >

                            <label
                                for="image"
                                class="form-label"
                            >
                                Example Image
                            </label>

                            <input
                                type="file"
                                name="image"
                                id="image"
                                class="form-control"
                                accept="image/png,image/jpeg,image/webp"
                            >

                            <div class="form-text">

                                Upload the image that
                                ComfyUI should use as
                                the starting image.

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Generate Image
                        </button>

                    </form>

                </div>

            </div>


            {{-- PROMPT USED --}}

            @if(isset($prompt))

                <div class="card shadow-sm mt-4">

                    <div class="card-body">

                        <h5>
                            Prompt Used
                        </h5>

                        <p class="mb-0">
                            {{ $prompt }}
                        </p>

                    </div>

                </div>

            @endif


            {{-- GENERATED IMAGE --}}

            @if(isset($imageData))

                <div class="card shadow-sm mt-4">

                    <div class="card-body">

                        <h5 class="mb-3">
                            Generated Image
                        </h5>

                        <img
                            src="{{ $imageData }}"
                            class="img-fluid rounded"
                            alt="Generated AI image"
                        >

                    </div>

                </div>

            @endif

        </div>

    </div>

</div>


<script>

    const workflow =
        document.getElementById('workflow');

    const imageContainer =
        document.getElementById(
            'imageUploadContainer'
        );

    const imageInput =
        document.getElementById('image');


    function updateWorkflowFields() {

        if (
            workflow.value
            ===
            'image_to_image'
        ) {

            imageContainer.style.display =
                'block';

            imageInput.required =
                true;

        } else {

            imageContainer.style.display =
                'none';

            imageInput.required =
                false;

        }

    }


    workflow.addEventListener(
        'change',
        updateWorkflowFields
    );


    updateWorkflowFields();

</script>

</body>

</html>