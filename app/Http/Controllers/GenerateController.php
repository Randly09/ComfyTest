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

    public function generate(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validate user input
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'workflow' => 'required|in:text_to_image,image_to_image',
            'prompt' => 'required|string|max:1000',

            'image' => [
                'required_if:workflow,Image_to_Image',
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
            | TEXT TO IMAGE
            |--------------------------------------------------------------------------
            */

            if ($request->workflow === 'text_to_image') {

                $workflow = $this->loadWorkflow(
                    'text_to_image_api.json'
                );

                // Positive prompt
                $workflow['157:145']['inputs']['text']
                    = $request->prompt;

                // Random seed
                $workflow['157:147']['inputs']['seed']
                    = random_int(1, 2147483647);
            }

            /*
            |--------------------------------------------------------------------------
            | IMAGE TO IMAGE
            |--------------------------------------------------------------------------
            */

            else {

                $workflow = $this->loadWorkflow(
                    'image_to_image.json'
                );

                // Positive prompt
                $workflow['25:4']['inputs']['text']
                    = $request->prompt;

                // Random seed
                $workflow['25:6']['inputs']['seed']
                    = random_int(1, 2147483647);

                /*
                |--------------------------------------------------------------------------
                | Upload image to ComfyUI
                |--------------------------------------------------------------------------
                */

                $uploadedFile = $request->file('image');

                $extension = $uploadedFile->getClientOriginalExtension();

                $filename =
                    'website_' .
                    Str::uuid() .
                    '.' .
                    $extension;

                $uploadResponse = Http::attach(
                    'image',
                    file_get_contents(
                        $uploadedFile->getRealPath()
                    ),
                    $filename
                )
                ->post(
                    $comfyUrl . '/upload/image',
                    [
                        'type' => 'input',
                        'overwrite' => 'true',
                    ]
                );

                if (!$uploadResponse->successful()) {

                    return back()
                        ->withErrors([
                            'generation' =>
                                'Could not upload the image to ComfyUI: ' .
                                $uploadResponse->body()
                        ])
                        ->withInput();
                }

                $uploadedImageName =
                    $uploadResponse->json('name');

                if (!$uploadedImageName) {

                    return back()
                        ->withErrors([
                            'generation' =>
                                'ComfyUI uploaded the image but did not return a filename.'
                        ])
                        ->withInput();
                }

                /*
                |--------------------------------------------------------------------------
                | Give uploaded image to LoadImage node
                |--------------------------------------------------------------------------
                */

                $workflow['25:22']['inputs']['image']
                    = $uploadedImageName;
            }

            /*
            |--------------------------------------------------------------------------
            | Send selected workflow to ComfyUI
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(30)->post(
                $comfyUrl . '/prompt',
                [
                    'prompt' => $workflow
                ]
            );

            if (!$response->successful()) {

                return back()
                    ->withErrors([
                        'generation' =>
                            'ComfyUI rejected the workflow: ' .
                            $response->body()
                    ])
                    ->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Get ComfyUI prompt ID
            |--------------------------------------------------------------------------
            */

            $promptId = $response->json('prompt_id');

            if (!$promptId) {

                return back()
                    ->withErrors([
                        'generation' =>
                            'ComfyUI did not return a prompt ID.'
                    ])
                    ->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Wait for generated image
            |--------------------------------------------------------------------------
            */

            $imageData = $this->waitForImage(
                $comfyUrl,
                $promptId
            );

            if (!$imageData) {

                return back()
                    ->withErrors([
                        'generation' =>
                            'ComfyUI finished but no image was returned.'
                    ])
                    ->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Return result to website
            |--------------------------------------------------------------------------
            */

            return view('generate', [
                'prompt' => $request->prompt,
                'selectedWorkflow' => $request->workflow,
                'imageData' => $imageData,
            ]);

        } catch (\Exception $e) {

            return back()
                ->withErrors([
                    'generation' =>
                        'Something went wrong: ' .
                        $e->getMessage()
                ])
                ->withInput();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Load workflow JSON
    |--------------------------------------------------------------------------
    */

    private function loadWorkflow(string $filename)
    {
        $path = storage_path(
            'app/workflows/' . $filename
        );

        if (!file_exists($path)) {

            throw new \Exception(
                'Workflow file not found: ' .
                $filename
            );
        }

        $workflow = json_decode(
            file_get_contents($path),
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
    | Wait for ComfyUI output
    |--------------------------------------------------------------------------
    */

    private function waitForImage(
        string $comfyUrl,
        string $promptId
    ) {

        for ($i = 0; $i < 180; $i++) {

            sleep(1);

            $historyResponse = Http::get(
                $comfyUrl .
                '/history/' .
                $promptId
            );

            if (!$historyResponse->successful()) {
                continue;
            }

            $history = $historyResponse->json();

            if (
                !isset(
                    $history[$promptId]['outputs']
                )
            ) {
                continue;
            }

            $outputs =
                $history[$promptId]['outputs'];

            foreach ($outputs as $output) {

                if (
                    !isset($output['images']) ||
                    empty($output['images'])
                ) {
                    continue;
                }

                $image =
                    $output['images'][0];

                $imageResponse =
                    Http::get(
                        $comfyUrl . '/view',
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
                    !$imageResponse->successful()
                ) {
                    continue;
                }

                return
                    'data:image/png;base64,' .
                    base64_encode(
                        $imageResponse->body()
                    );
            }
        }

        return null;
    }
}