<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenerateController extends Controller
{
    public function index()
    {
        return view('generate');
    }


    /*
    |--------------------------------------------------------------------------
    | Start generation
    |--------------------------------------------------------------------------
    */

    public function generate(Request $request)
    {
        $request->validate([
            'workflow' => 'required|in:auto,text_to_image,image_to_image',
            'prompt' => 'required|string|max:1000',
            'negative_prompt' => 'nullable|string|max:1000',

            'image' => [
                'required_if:workflow,image_to_image',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ]);


        $comfyUrl = env(
            'COMFYUI_URL',
            'http://127.0.0.1:8188'
        );


        try {

            /*
            |--------------------------------------------------------------------------
            | Original user values
            |--------------------------------------------------------------------------
            */

            $selectedWorkflow =
                $request->workflow;

            $positivePrompt =
                $request->prompt;

            $negativePrompt =
                $request->negative_prompt ?? '';

            $detectedLanguage =
                null;


            /*
            |--------------------------------------------------------------------------
            | AUTO MODE
            |--------------------------------------------------------------------------
            */

            if ($selectedWorkflow === 'auto') {

                $analysis =
                    $this->analyseWithOllama(
                        $request->prompt,
                        $request->hasFile('image')
                    );


                $selectedWorkflow =
                    $analysis['workflow'];

                $positivePrompt =
                    $analysis['positive_prompt'];

                $negativePrompt =
                    $analysis['negative_prompt'];

                $detectedLanguage =
                    $analysis['detected_language'];


                /*
                |--------------------------------------------------------------------------
                | Safety check
                |--------------------------------------------------------------------------
                */

                if (
                    $selectedWorkflow === 'image_to_image'
                    &&
                    !$request->hasFile('image')
                ) {
                    $selectedWorkflow =
                        'text_to_image';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | TEXT TO IMAGE
            |--------------------------------------------------------------------------
            */

            if ($selectedWorkflow === 'text_to_image') {

                $workflow =
                    $this->loadWorkflow(
                        'text_to_image_api.json'
                    );


                /*
                | Positive
                */

                $workflow['157:145']['inputs']['text']
                    = $positivePrompt;


                /*
                | Negative
                */

                if ($negativePrompt !== '') {

                    $workflow['157:146']['inputs']['text']
                        = $negativePrompt;
                }


                /*
                | Random seed
                */

                $workflow['157:147']['inputs']['seed']
                    = random_int(
                        1,
                        2147483647
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | IMAGE TO IMAGE
            |--------------------------------------------------------------------------
            */ elseif (
                $selectedWorkflow ===
                'image_to_image'
            ) {

                if (!$request->hasFile('image')) {

                    return response()->json([
                        'message' =>
                        'Image to Image requires a reference image.'
                    ], 422);
                }


                $workflow =
                    $this->loadWorkflow(
                        'image_to_image.json'
                    );


                /*
                | Positive
                */

                $workflow['25:4']['inputs']['text']
                    = $positivePrompt;


                /*
                | Negative
                */

                if ($negativePrompt !== '') {

                    $workflow['25:5']['inputs']['text']
                        = $negativePrompt;
                }


                /*
                | Random seed
                */

                $workflow['25:6']['inputs']['seed']
                    = random_int(
                        1,
                        2147483647
                    );


                /*
                |--------------------------------------------------------------------------
                | Upload reference image to ComfyUI
                |--------------------------------------------------------------------------
                */

                $uploadedFile =
                    $request->file('image');


                $extension =
                    $uploadedFile
                    ->getClientOriginalExtension();


                $filename =
                    'website_' .
                    Str::uuid() .
                    '.' .
                    $extension;


                $uploadResponse =
                    Http::attach(
                        'image',
                        file_get_contents(
                            $uploadedFile
                                ->getRealPath()
                        ),
                        $filename
                    )
                    ->post(
                        $comfyUrl .
                            '/upload/image',
                        [
                            'type' =>
                            'input',

                            'overwrite' =>
                            'true',
                        ]
                    );


                if (
                    !$uploadResponse
                        ->successful()
                ) {

                    return response()->json([
                        'message' =>
                        'Could not upload the image to ComfyUI: ' .
                            $uploadResponse->body()
                    ], 500);
                }


                $uploadedImageName =
                    $uploadResponse
                    ->json('name');


                if (!$uploadedImageName) {

                    return response()->json([
                        'message' =>
                        'ComfyUI uploaded the image but did not return a filename.'
                    ], 500);
                }


                /*
                |--------------------------------------------------------------------------
                | Give uploaded image to LoadImage node
                |--------------------------------------------------------------------------
                */

                $workflow['25:22']['inputs']['image']
                    = $uploadedImageName;
            } else {

                return response()->json([
                    'message' =>
                    'Invalid workflow.'
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | Queue workflow in ComfyUI
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | We do NOT wait for the workflow anymore.
            |
            | ComfyUI immediately returns a prompt_id.
            |--------------------------------------------------------------------------
            */

            $response =
                Http::timeout(30)
                ->post(
                    $comfyUrl .
                        '/prompt',
                    [
                        'prompt' =>
                        $workflow
                    ]
                );


            if (!$response->successful()) {

                return response()->json([
                    'message' =>
                    'ComfyUI rejected the workflow: ' .
                        $response->body()
                ], 500);
            }


            $promptId =
                $response->json(
                    'prompt_id'
                );


            if (!$promptId) {

                return response()->json([
                    'message' =>
                    'ComfyUI did not return a prompt ID.'
                ], 500);
            }


            /*
            |--------------------------------------------------------------------------
            | Immediately return to browser
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'prompt_id' =>
                $promptId,

                'status' =>
                'queued',

                'actual_workflow' =>
                $selectedWorkflow,

                'detected_language' =>
                $detectedLanguage,

                'positive_prompt' =>
                $positivePrompt,

                'negative_prompt' =>
                $negativePrompt,

            ], 202);
        } catch (\Throwable $e) {

            return response()->json([
                'message' =>
                'Something went wrong: ' .
                    $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check generation status
    |--------------------------------------------------------------------------
    */

    public function status(string $promptId)
    {
        $comfyUrl = env(
            'COMFYUI_URL',
            'http://127.0.0.1:8188'
        );


        try {

            /*
            |--------------------------------------------------------------------------
            | First check history
            |--------------------------------------------------------------------------
            |
            | When the workflow finishes, it will appear here.
            |--------------------------------------------------------------------------
            */

            $historyResponse =
                Http::timeout(10)
                ->get(
                    $comfyUrl .
                        '/history/' .
                        $promptId
                );


            if (
                $historyResponse
                ->successful()
            ) {

                $history =
                    $historyResponse
                    ->json();


                if (
                    isset(
                        $history[$promptId]
                    )
                ) {

                    $entry =
                        $history[$promptId];


                    /*
                    |--------------------------------------------------------------------------
                    | Check for ComfyUI error
                    |--------------------------------------------------------------------------
                    */

                    $statusString =
                        $entry['status']['status_str']
                        ?? null;


                    if (
                        $statusString ===
                        'error'
                    ) {

                        return response()->json([
                            'status' =>
                            'failed',

                            'message' =>
                            'ComfyUI failed while generating the result.'
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Find generated image
                    |--------------------------------------------------------------------------
                    */

                    $image =
                        $this->findFirstImage(
                            $entry['outputs']
                                ?? []
                        );


                    if ($image) {

                        $imageResponse =
                            Http::timeout(30)
                            ->get(
                                $comfyUrl .
                                    '/view',
                                [
                                    'filename' =>
                                    $image['filename'],

                                    'subfolder' =>
                                    $image['subfolder']
                                        ?? '',

                                    'type' =>
                                    $image['type']
                                        ?? 'temp',
                                ]
                            );


                        if (
                            !$imageResponse
                                ->successful()
                        ) {

                            return response()->json([
                                'status' =>
                                'failed',

                                'message' =>
                                'The result was generated but could not be downloaded.'
                            ]);
                        }


                        $mime =
                            $imageResponse
                            ->header(
                                'Content-Type'
                            )
                            ?? 'image/png';


                        $imageData =
                            'data:' .
                            $mime .
                            ';base64,' .
                            base64_encode(
                                $imageResponse
                                    ->body()
                            );


                        return response()->json([
                            'status' =>
                            'completed',

                            'label' =>
                            'Complete',

                            'image_data' =>
                            $imageData,
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Workflow completed but no image exists
                    |--------------------------------------------------------------------------
                    */

                    $completed =
                        $entry['status']['completed']
                        ?? false;


                    if ($completed) {

                        return response()->json([
                            'status' =>
                            'failed',

                            'message' =>
                            'ComfyUI completed the workflow but no image output was found.'
                        ]);
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Check ComfyUI queue
            |--------------------------------------------------------------------------
            */

            $queueResponse =
                Http::timeout(10)
                ->get(
                    $comfyUrl .
                        '/queue'
                );


            if (
                $queueResponse
                ->successful()
            ) {

                $queue =
                    $queueResponse
                    ->json();


                /*
                | Currently running
                */

                if (
                    $this->queueContainsPrompt(
                        $queue['queue_running']
                            ?? [],
                        $promptId
                    )
                ) {

                    return response()->json([
                        'status' =>
                        'generating',

                        'label' =>
                        'Generating image'
                    ]);
                }


                /*
                | Waiting in queue
                */

                if (
                    $this->queueContainsPrompt(
                        $queue['queue_pending']
                            ?? [],
                        $promptId
                    )
                ) {

                    return response()->json([
                        'status' =>
                        'queued',

                        'label' =>
                        'Waiting in queue'
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Between queue / history states
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' =>
                'processing',

                'label' =>
                'Preparing result'
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'status' =>
                'connection_error',

                'message' =>
                'Could not check ComfyUI status.'
            ], 503);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Check if prompt exists in queue
    |--------------------------------------------------------------------------
    */

    private function queueContainsPrompt(
        array $queue,
        string $promptId
    ): bool {

        foreach ($queue as $item) {

            /*
            | ComfyUI queue items contain
            | the prompt ID at index 1.
            */

            if (
                isset($item[1])
                &&
                $item[1] === $promptId
            ) {

                return true;
            }
        }


        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Find first image in workflow output
    |--------------------------------------------------------------------------
    */

    private function findFirstImage(
        array $outputs
    ): ?array {

        foreach (
            $outputs
            as $output
        ) {

            if (
                isset($output['images'])
                &&
                !empty($output['images'])
            ) {

                return
                    $output['images'][0];
            }
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Load workflow JSON
    |--------------------------------------------------------------------------
    */

    private function loadWorkflow(
        string $filename
    ) {

        $path =
            storage_path(
                'app/workflows/' .
                    $filename
            );


        if (!file_exists($path)) {

            throw new \Exception(
                'Workflow file not found: ' .
                    $filename
            );
        }


        $workflow =
            json_decode(
                file_get_contents(
                    $path
                ),
                true
            );


        if (!$workflow) {

            throw new \Exception(
                'Could not read workflow: ' .
                    $filename
            );
        }


        return $workflow;
    }


    /*
    |--------------------------------------------------------------------------
    | Analyse request with Ollama
    |--------------------------------------------------------------------------
    */

    private function analyseWithOllama(
        string $userRequest,
        bool $hasImage
    ) {

        $ollamaUrl = env(
            'OLLAMA_URL',
            'http://127.0.0.1:11434'
        );


        $model = env(
            'OLLAMA_MODEL',
            'gemma3:4b'
        );


        /*
        |--------------------------------------------------------------------------
        | Required JSON structure
        |--------------------------------------------------------------------------
        */

        $schema = [

            'type' =>
            'object',

            'properties' => [

                'workflow' => [

                    'type' =>
                    'string',

                    'enum' => [
                        'text_to_image',
                        'image_to_image',
                    ],
                ],


                'detected_language' => [

                    'type' =>
                    'string',
                ],


                'positive_prompt' => [

                    'type' =>
                    'string',
                ],


                'negative_prompt' => [

                    'type' =>
                    'string',
                ],
            ],


            'required' => [

                'workflow',

                'detected_language',

                'positive_prompt',

                'negative_prompt',
            ],


            'additionalProperties' =>
            false,
        ];


        /*
        |--------------------------------------------------------------------------
        | Ollama instructions
        |--------------------------------------------------------------------------
        */

        $systemPrompt = <<<PROMPT

You are an AI request parser and workflow router for a local AI generation system.

The user may write their request in any language.

Your main responsibility is to understand the user's intent precisely without adding creative ideas that were not requested.

Your tasks are:

1. Detect the language used by the user.

2. Translate the user's request into natural English when necessary.

3. Determine whether the user is:
   - creating something new,
   - modifying an uploaded image,
   - adding something,
   - removing something,
   - replacing something,
   - changing only a specific property,
   - or preserving existing parts of the uploaded image.

4. Create positive_prompt containing what the FINAL result should contain.

5. Create negative_prompt containing what the FINAL result must NOT contain.

6. Choose the correct workflow.

Available workflows:

text_to_image:
Use when a completely new image should be generated from text only.

image_to_image:
Use when an uploaded reference image should be changed, transformed, edited, restyled, or used as the basis for the result.

IMPORTANT IMAGE EDITING RULES:

When the user uploads an image, interpret their request as an EDIT of that image unless they clearly ask for a completely new image.

Pay special attention to:

- objects that must be added
- objects that must be removed
- objects that must remain unchanged
- exact quantities
- colors that must change
- colors that must remain unchanged
- background preservation
- foreground preservation
- positioning
- replacing one object with another
- instructions such as:
  "only"
  "exactly"
  "one"
  "remove"
  "delete"
  "keep"
  "leave unchanged"
  "don't change"
  "same background"
  "except"
  "everything else stays the same"

Never ignore these instructions.

QUANTITY RULES:

If the user specifies an exact quantity, preserve that quantity explicitly.

Examples:

"only one cat"
means:
the final image must contain exactly one cat.

"remove the other cats"
means:
the final image must not contain any additional cats.

"add two trees"
means:
the final image should contain exactly two newly requested trees if that is what the user means.

Do not simplify quantity requirements into vague descriptions.

PRESERVATION RULES:

If the user says to keep something the same, preserve that requirement.

For example:

"keep the background the same"

must influence the final prompt.

Do not silently remove preservation instructions.

When editing an image, assume parts not mentioned by the user should remain as unchanged as reasonably possible.

REMOVAL RULES:

When the user asks to remove something, make sure the final intended result does not contain that thing.

For example:

"remove all other cats"

means:
the result should contain no additional cats beyond the cat the user wants to keep or add.

STRICT PROMPT RULES:

- Do NOT creatively improve the user's request.
- Do NOT add artistic styles unless explicitly requested.
- Do NOT add camera angles unless explicitly requested.
- Do NOT add lighting unless explicitly requested.
- Do NOT add rendering terminology unless explicitly requested.
- Do NOT add new objects.
- Do NOT invent locations.
- Do NOT invent colors.
- Do NOT invent people.
- Do NOT invent weather.
- Do NOT invent scenery.
- Do NOT invent composition details.

The positive_prompt should describe the desired FINAL state of the image.

The negative_prompt should describe things that must NOT exist in the final result.

For image editing, preserve explicit constraints such as:
- exactly one object
- unchanged background
- unchanged objects
- removal of unwanted objects

Do not reduce an editing request into only a few nouns if doing so loses meaning.

Do NOT automatically add generic negative terms such as:
blurry,
bad anatomy,
extra limbs,
watermark,
artifacts,
low quality,
8k,
cinematic,
masterpiece,
photorealistic,
high resolution,
octane render

unless the user specifically requests them.

If the user does not specify anything they do not want, negative_prompt may be an empty string.

Never choose image_to_image when no image has been uploaded.

If an image is uploaded and the user refers to:
"this image",
"this photo",
"this person",
"this car",
"this object",
"the background",
"change this",
"remove this",
"add this",
"keep this",
or equivalent wording in another language,
choose image_to_image.

Both positive_prompt and negative_prompt must always be written in English.

Before returning the result, check:

- Did I preserve exact quantities?
- Did I preserve removal instructions?
- Did I preserve things the user said must remain unchanged?
- Did I avoid adding details the user never requested?
- Does the prompt describe the user's intended final result?

Return only the requested JSON structure.

PROMPT;


        $userMessage =

            "User request:\n" .

            $userRequest .

            "\n\nReference image uploaded: " .

            (
                $hasImage
                ? 'YES'
                : 'NO'
            );


        /*
        |--------------------------------------------------------------------------
        | Send request to Ollama
        |--------------------------------------------------------------------------
        */

        $response =
            Http::timeout(120)
            ->post(
                $ollamaUrl .
                    '/api/chat',
                [

                    'model' =>
                    $model,


                    'messages' => [

                        [
                            'role' =>
                            'system',

                            'content' =>
                            $systemPrompt,
                        ],

                        [
                            'role' =>
                            'user',

                            'content' =>
                            $userMessage,
                        ],

                    ],


                    'stream' =>
                    false,


                    'format' =>
                    $schema,


                    'options' => [

                        'temperature' =>
                        0,
                    ],


                    /*
                        | Unload Ollama model
                        | so ComfyUI can use GPU memory.
                        */

                    'keep_alive' =>
                    0,
                ]
            );


        if (!$response->successful()) {

            throw new \Exception(
                'Could not connect to Ollama: ' .
                    $response->body()
            );
        }


        $content =
            $response
            ->json(
                'message.content'
            );


        if (!$content) {

            throw new \Exception(
                'Ollama returned an empty response.'
            );
        }


        $analysis =
            json_decode(
                $content,
                true
            );


        if (!$analysis) {

            throw new \Exception(
                'Ollama returned invalid JSON.'
            );
        }


        if (
            !isset(
                $analysis['workflow']
            )
            ||
            !isset(
                $analysis['detected_language']
            )
            ||
            !isset(
                $analysis['positive_prompt']
            )
            ||
            !array_key_exists(
                'negative_prompt',
                $analysis
            )
        ) {

            throw new \Exception(
                'Ollama response is missing required information.'
            );
        }


        return $analysis;
    }
}
