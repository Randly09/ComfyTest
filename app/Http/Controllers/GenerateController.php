<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Support\ClientIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateController extends Controller
{
    private const DEFAULT_MAX_ATTEMPTS = 3;
    private const DEFAULT_MIN_QUALITY_SCORE = 85;
    private const HARD_FAILURE_SCORE_CAP = 60;

    /**
     * Start a new generation request.
     *
     * Ollama analysis still happens before this request returns, but the timeout is
     * intentionally much longer than before so a second user can wait for Ollama
     * instead of failing after 120 seconds.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|exists:chats,id',
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

        $comfyUrl = env('COMFYUI_URL', 'http://127.0.0.1:8188');
        $assistantMessage = null;

        try {
            $chat = Chat::where('id', $request->chat_id)
                ->where('client_id', ClientIdentity::id())
                ->where('expires_at', '>', now())
                ->firstOrFail();

            $storedUploadPath = null;
            $uploadedFile = $request->file('image');

            if ($uploadedFile) {
                $storedUploadPath = $uploadedFile->store(
                    'chat-uploads/' . $chat->id,
                    'public'
                );
            }

            $userMessage = $chat->messages()->create([
                'role' => 'user',
                'content' => $request->prompt,
                'media_path' => $storedUploadPath,
                'media_type' => $storedUploadPath ? 'image' : null,
                'status' => 'completed',
            ]);

            if ($chat->title === 'New chat') {
                $chat->update([
                    'title' => Str::limit($request->prompt, 40, '…'),
                ]);
            }

            $chat->touch();

            $assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'status' => 'analyzing',
                'media_type' => 'image',
                'quality_status' => 'pending',
                'attempt_count' => 0,
                'max_attempts' => $this->maxAttempts(),
            ]);

            $selectedWorkflow = $request->workflow;
            $positivePrompt = $request->prompt;
            $negativePrompt = $request->negative_prompt ?? '';

            /*
            |--------------------------------------------------------------------------
            | Always analyse the request
            |--------------------------------------------------------------------------
            |
            | Even when the user manually chooses a workflow, we still need
            | structured requirements for grounded quality control.
            */
            $analysis = $this->analyseWithOllama(
                $request->prompt,
                $request->hasFile('image')
            );

            $requirements = $this->normalizeRequirements(
                $analysis['requirements'] ?? []
            );

            $detectedLanguage = $analysis['detected_language'];

            if ($selectedWorkflow === 'auto') {
                $selectedWorkflow = $analysis['workflow'];
                $positivePrompt = $analysis['positive_prompt'];
                $negativePrompt = $analysis['negative_prompt'];

                if (
                    $selectedWorkflow === 'image_to_image'
                    && !$request->hasFile('image')
                ) {
                    $selectedWorkflow = 'text_to_image';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Strengthen exact requirements before ComfyUI sees the prompt
            |--------------------------------------------------------------------------
            */
            [$positivePrompt, $negativePrompt] =
                $this->strengthenPromptsFromRequirements(
                    $positivePrompt,
                    $negativePrompt,
                    $requirements
                );

            if (
                $selectedWorkflow === 'image_to_image'
                && !$storedUploadPath
            ) {
                throw new \Exception(
                    'Image to Image requires a reference image.'
                );
            }

            $assistantMessage->update([
                'workflow' => $selectedWorkflow,
                'detected_language' => $detectedLanguage,
                'positive_prompt' => $positivePrompt,
                'negative_prompt' => $negativePrompt,
                'quality_requirements' => $requirements,
            ]);

            $this->queueGenerationAttempt(
                $assistantMessage->fresh(),
                $userMessage,
                $comfyUrl
            );

            $assistantMessage->refresh();

            return response()->json([
                'message_id' => $assistantMessage->id,
                'status' => $assistantMessage->status,
                'label' => 'In queue',
                'actual_workflow' => $assistantMessage->workflow,
                'detected_language' => $assistantMessage->detected_language,
                'positive_prompt' => $assistantMessage->positive_prompt,
                'negative_prompt' => $assistantMessage->negative_prompt,
                'attempt_count' => $assistantMessage->attempt_count,
                'max_attempts' => $assistantMessage->max_attempts,
            ], 202);
        } catch (\Throwable $e) {
            if ($assistantMessage) {
                $assistantMessage->update([
                    'status' => 'failed',
                    'content' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * The browser now polls by Message ID instead of ComfyUI prompt ID.
     * This allows Laravel to replace the ComfyUI prompt ID whenever an image
     * fails quality control and a retry is submitted.
     */
    public function status(Message $message)
    {
        $this->authorizeMessage($message);

        $comfyUrl = env('COMFYUI_URL', 'http://127.0.0.1:8188');

        try {
            $message->refresh();

            if ($message->status === 'completed' && $message->media_path) {
                return response()->json(
                    $this->completedPayload($message)
                );
            }

            if ($message->status === 'failed') {
                return response()->json([
                    'status' => 'failed',
                    'message' => $message->content ?? 'Generation failed.',
                ]);
            }

            if ($message->status === 'analyzing') {
                return response()->json([
                    'status' => 'analyzing',
                    'label' => 'Understanding your request',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Grounded quality-control state machine
            |--------------------------------------------------------------------------
            |
            | 1. inspect_pending  -> literal visual facts / object count
            | 2. evaluate_pending -> semantic + visual quality evaluation
            |
            | Each expensive AI call happens on a later poll so the browser can
            | display the correct live status while that request is running.
            */
            if (
                $message->status === 'checking_quality'
                && $message->quality_status === 'inspect_pending'
                && $message->candidate_media_path
            ) {
                return $this->inspectCandidateFacts($message);
            }

            if (
                $message->status === 'checking_quality'
                && $message->quality_status === 'evaluate_pending'
                && $message->candidate_media_path
            ) {
                return $this->handleQualityCheck($message, $comfyUrl);
            }

            if (!$message->comfy_prompt_id) {
                return response()->json([
                    'status' => 'processing',
                    'label' => 'Preparing generation',
                ]);
            }

            $historyResponse = Http::connectTimeout(10)
                ->timeout(30)
                ->get($comfyUrl . '/history/' . $message->comfy_prompt_id);

            if ($historyResponse->successful()) {
                $history = $historyResponse->json();

                if (isset($history[$message->comfy_prompt_id])) {
                    $entry = $history[$message->comfy_prompt_id];
                    $statusString = $entry['status']['status_str'] ?? null;

                    if ($statusString === 'error') {
                        $message->update([
                            'status' => 'failed',
                            'content' => 'ComfyUI failed while generating the result.',
                        ]);

                        return response()->json([
                            'status' => 'failed',
                            'message' => 'ComfyUI failed while generating the result.',
                        ]);
                    }

                    $image = $this->findFirstImage($entry['outputs'] ?? []);

                    if ($image) {
                        $candidatePath = $this->downloadCandidateImage(
                            $message,
                            $image,
                            $comfyUrl
                        );

                        $message->update([
                            'status' => 'checking_quality',
                            'quality_status' => 'inspect_pending',
                            'candidate_media_path' => $candidatePath,
                            'visual_facts' => null,
                            'observed_subject_count' => null,
                            'quality_hard_failures' => null,
                            'queue_position' => null,
                        ]);

                        return response()->json([
                            'status' => 'checking_quality',
                            'label' => 'Inspecting generated image',
                            'attempt_count' => $message->attempt_count,
                            'max_attempts' => $message->max_attempts,
                        ]);
                    }

                    $completed = $entry['status']['completed'] ?? false;

                    if ($completed) {
                        $message->update([
                            'status' => 'failed',
                            'content' => 'ComfyUI completed the workflow but no image output was found.',
                        ]);

                        return response()->json([
                            'status' => 'failed',
                            'message' => 'ComfyUI completed the workflow but no image output was found.',
                        ]);
                    }
                }
            }

            $queueResponse = Http::connectTimeout(10)
                ->timeout(30)
                ->get($comfyUrl . '/queue');

            if ($queueResponse->successful()) {
                $queue = $queueResponse->json();
                $promptId = $message->comfy_prompt_id;

                $runningPosition = $this->queuePosition(
                    $queue['queue_running'] ?? [],
                    $promptId
                );

                if ($runningPosition !== null) {
                    $message->update([
                        'status' => 'generating',
                        'queue_position' => 0,
                    ]);

                    return response()->json([
                        'status' => 'generating',
                        'queue_position' => 0,
                        'label' => $message->attempt_count > 1
                            ? 'Generating improved result · attempt ' . $message->attempt_count . '/' . $message->max_attempts
                            : 'Generating image',
                        'attempt_count' => $message->attempt_count,
                        'max_attempts' => $message->max_attempts,
                    ]);
                }

                $position = $this->queuePosition(
                    $queue['queue_pending'] ?? [],
                    $promptId
                );

                if ($position !== null) {
                    $ahead = $position - 1;
                    $message->update([
                        'status' => 'queued',
                        'queue_position' => $position,
                    ]);

                    if ($ahead === 0) {
                        $label = $message->attempt_count > 1
                            ? 'Retry queued · next in queue'
                            : 'Next in queue';
                    } elseif ($ahead === 1) {
                        $label = 'In queue · 1 request ahead of you';
                    } else {
                        $label = 'In queue · ' . $ahead . ' requests ahead of you';
                    }

                    return response()->json([
                        'status' => 'queued',
                        'queue_position' => $position,
                        'ahead' => $ahead,
                        'label' => $label,
                        'attempt_count' => $message->attempt_count,
                        'max_attempts' => $message->max_attempts,
                    ]);
                }
            }

            $message->update([
                'status' => 'processing',
            ]);

            return response()->json([
                'status' => 'processing',
                'label' => 'Preparing result',
                'attempt_count' => $message->attempt_count,
                'max_attempts' => $message->max_attempts,
            ]);
        } catch (\Throwable $e) {
            /*
            |--------------------------------------------------------------------------
            | Recover an interrupted quality-control AI call
            |--------------------------------------------------------------------------
            */
            if (
                $message->quality_status === 'inspecting'
                && $message->candidate_media_path
            ) {
                $message->update([
                    'quality_status' => 'inspect_pending',
                    'status' => 'checking_quality',
                ]);
            } elseif (
                $message->quality_status === 'evaluating'
                && $message->candidate_media_path
            ) {
                $message->update([
                    'quality_status' => 'evaluate_pending',
                    'status' => 'checking_quality',
                ]);
            }

            return response()->json([
                'status' => 'connection_error',
                'message' => 'Could not check generation status: ' . $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Serve saved chat media through Laravel so chat files are protected by the
     * current browser/user identity and do not depend on a public storage URL.
     */
    public function media(Message $message)
    {
        $this->authorizeMessage($message);

        abort_unless(
            $message->media_path
            && Storage::disk('public')->exists($message->media_path),
            404
        );

        return Storage::disk('public')->response($message->media_path);
    }

    private function inspectCandidateFacts(Message $message)
    {
        $message->update([
            'quality_status' => 'inspecting',
            'status' => 'checking_quality',
        ]);

        $message->refresh();

        $requirements = $this->normalizeRequirements(
            $message->quality_requirements ?? []
        );

        $facts = $this->inspectImageFacts(
            $message,
            $requirements
        );

        $observedCount = $this->resolveObservedSubjectCount($facts);
        $facts['primary_subject_count'] = $observedCount;

        $hardFailures = $this->evaluateHardRequirements(
            $requirements,
            $facts
        );

        $message->update([
            'visual_facts' => $facts,
            'observed_subject_count' => $observedCount,
            'quality_hard_failures' => $hardFailures,
            'quality_status' => 'evaluate_pending',
            'status' => 'checking_quality',
        ]);

        return response()->json([
            'status' => 'checking_quality',
            'label' => 'Comparing result with your request',
            'expected_count' => $requirements['expected_count'] ?? null,
            'observed_count' => $observedCount,
            'hard_failures' => $hardFailures,
            'attempt_count' => $message->attempt_count,
            'max_attempts' => $message->max_attempts,
        ]);
    }

    private function handleQualityCheck(Message $message, string $comfyUrl)
    {
        $message->update([
            'quality_status' => 'evaluating',
            'status' => 'checking_quality',
        ]);

        $message->refresh();

        $userMessage = $this->userMessageForAssistant($message);
        $requirements = $this->normalizeRequirements(
            $message->quality_requirements ?? []
        );
        $visualFacts = is_array($message->visual_facts)
            ? $message->visual_facts
            : [];

        $hardFailures = is_array($message->quality_hard_failures)
            ? $message->quality_hard_failures
            : $this->evaluateHardRequirements(
                $requirements,
                $visualFacts
            );

        $evaluation = $this->evaluateImageQuality(
            $message,
            $userMessage,
            $requirements,
            $visualFacts
        );

        $score = max(0, min(100, (int) $evaluation['score']));

        /*
        |--------------------------------------------------------------------------
        | Laravel is the final authority for hard constraints
        |--------------------------------------------------------------------------
        |
        | A vision model is never allowed to give a passing score when a hard
        | deterministic requirement (for example exact subject count) failed.
        */
        if (!empty($hardFailures)) {
            $score = min(
                $score,
                self::HARD_FAILURE_SCORE_CAP
            );
        }

        $approved =
            empty($hardFailures)
            && $score >= $this->minimumQualityScore();

        $candidatePath = $message->candidate_media_path;

        $problems = array_values(array_unique(array_filter(array_merge(
            $hardFailures,
            is_array($evaluation['problems'] ?? null)
                ? $evaluation['problems']
                : []
        ))));

        $hardRetry = $this->buildHardRuleRetryPrompts(
            $requirements,
            $visualFacts,
            $hardFailures
        );

        $retryPositive = $this->mergePromptParts(
            $hardRetry['positive'],
            (string) ($evaluation['retry_positive_prompt'] ?? '')
        );

        $retryNegative = $this->mergePromptParts(
            $hardRetry['negative'],
            (string) ($evaluation['retry_negative_prompt'] ?? '')
        );

        $feedback = trim((string) ($evaluation['feedback'] ?? ''));

        if (!empty($hardFailures)) {
            $feedback = trim(
                'Hard requirement failed: '
                . implode(' ', $hardFailures)
                . ($feedback !== '' ? ' ' . $feedback : '')
            );
        }

        $message->update([
            'quality_score' => $score,
            'quality_feedback' => $feedback,
            'quality_problems' => $problems,
            'quality_hard_failures' => $hardFailures,
            'quality_retry_positive' => $retryPositive,
            'quality_retry_negative' => $retryNegative,
        ]);

        $message->refresh();

        $this->rememberBestCandidate(
            $message,
            $candidatePath,
            $score
        );

        $message->refresh();

        if ($approved) {
            $this->completeWithCandidate(
                $message,
                $candidatePath,
                'approved'
            );

            return response()->json(
                $this->completedPayload(
                    $message->fresh()
                )
            );
        }

        if ($message->attempt_count < $message->max_attempts) {
            if (
                $candidatePath
                && $candidatePath !== $message->best_candidate_path
            ) {
                Storage::disk('public')->delete(
                    $candidatePath
                );
            }

            $nextAttempt = $message->attempt_count + 1;

            $message->update([
                'status' => 'retrying',
                'quality_status' => 'rejected',
                'candidate_media_path' => null,
                'queue_position' => null,
            ]);

            $this->queueGenerationAttempt(
                $message->fresh(),
                $userMessage,
                $comfyUrl
            );

            return response()->json([
                'status' => 'retrying',
                'label' => 'Quality check failed · retrying ('
                    . $nextAttempt
                    . '/'
                    . $message->max_attempts
                    . ')',
                'quality_score' => $score,
                'quality_feedback' => $feedback,
                'quality_problems' => $problems,
                'hard_failures' => $hardFailures,
                'observed_count' => $message->observed_subject_count,
                'expected_count' => $requirements['expected_count'] ?? null,
                'attempt_count' => $nextAttempt,
                'max_attempts' => $message->max_attempts,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | No attempt reached the threshold: return the highest-scoring candidate
        |--------------------------------------------------------------------------
        */
        $bestPath =
            $message->best_candidate_path
            ?: $candidatePath;

        if (!$bestPath) {
            throw new \Exception(
                'No generated candidate was available after quality control.'
            );
        }

        $message->update([
            'status' => 'completed',
            'quality_status' => 'best_available',
            'media_path' => $bestPath,
            'candidate_media_path' => null,
            'queue_position' => null,
            'quality_score' => $message->best_quality_score ?? $score,
        ]);

        return response()->json(
            $this->completedPayload(
                $message->fresh(),
                'Best available result after quality checks'
            )
        );
    }

    private function queueGenerationAttempt(
        Message $message,
        Message $userMessage,
        string $comfyUrl
    ): void {
        $nextAttempt = $message->attempt_count + 1;

        if ($nextAttempt > $message->max_attempts) {
            throw new \Exception('Maximum generation attempts reached.');
        }

        $positivePrompt = trim((string) $message->positive_prompt);
        $negativePrompt = trim((string) $message->negative_prompt);

        if ($nextAttempt > 1) {
            if ($message->quality_retry_positive) {
                $positivePrompt = trim(
                    $positivePrompt
                    . "\n\nImportant correction for this retry: "
                    . $message->quality_retry_positive
                );
            }

            if ($message->quality_retry_negative) {
                $negativePrompt = trim(
                    $negativePrompt
                    . ($negativePrompt !== '' ? ', ' : '')
                    . $message->quality_retry_negative
                );
            }
        }

        if ($message->workflow === 'text_to_image') {
            $workflow = $this->loadWorkflow('text_to_image_api.json');

            $workflow['157:145']['inputs']['text'] = $positivePrompt;
            $workflow['157:146']['inputs']['text'] = $negativePrompt;
            $workflow['157:147']['inputs']['seed'] = random_int(1, 2147483647);
        } elseif ($message->workflow === 'image_to_image') {
            $workflow = $this->loadWorkflow('image_to_image.json');

            if (
                !$userMessage->media_path
                || !Storage::disk('public')->exists($userMessage->media_path)
            ) {
                throw new \Exception('The reference image for this chat could not be found.');
            }

            $workflow['25:4']['inputs']['text'] = $positivePrompt;
            $workflow['25:5']['inputs']['text'] = $negativePrompt;
            $workflow['25:6']['inputs']['seed'] = random_int(1, 2147483647);

            $extension = pathinfo($userMessage->media_path, PATHINFO_EXTENSION) ?: 'png';
            $filename = 'website_' . Str::uuid() . '.' . $extension;

            $uploadResponse = Http::connectTimeout(10)
                ->timeout(90)
                ->attach(
                    'image',
                    Storage::disk('public')->get($userMessage->media_path),
                    $filename
                )
                ->post($comfyUrl . '/upload/image', [
                    'type' => 'input',
                    'overwrite' => 'true',
                ]);

            if (!$uploadResponse->successful()) {
                throw new \Exception(
                    'Could not upload the image to ComfyUI: ' . $uploadResponse->body()
                );
            }

            $uploadedImageName = $uploadResponse->json('name');

            if (!$uploadedImageName) {
                throw new \Exception(
                    'ComfyUI uploaded the image but did not return a filename.'
                );
            }

            $workflow['25:22']['inputs']['image'] = $uploadedImageName;
        } else {
            throw new \Exception('Invalid workflow.');
        }

        $response = Http::connectTimeout(10)
            ->timeout(60)
            ->post($comfyUrl . '/prompt', [
                'prompt' => $workflow,
            ]);

        if (!$response->successful()) {
            throw new \Exception(
                'ComfyUI rejected the workflow: ' . $response->body()
            );
        }

        $promptId = $response->json('prompt_id');

        if (!$promptId) {
            throw new \Exception('ComfyUI did not return a prompt ID.');
        }

        $message->update([
            'attempt_count' => $nextAttempt,
            'comfy_prompt_id' => $promptId,
            'status' => 'queued',
            'quality_status' => 'pending',
            'candidate_media_path' => null,
            'visual_facts' => null,
            'observed_subject_count' => null,
            'quality_hard_failures' => null,
            'queue_position' => null,
        ]);
    }

    private function downloadCandidateImage(
        Message $message,
        array $image,
        string $comfyUrl
    ): string {
        $imageResponse = Http::connectTimeout(10)
            ->timeout(60)
            ->get($comfyUrl . '/view', [
                'filename' => $image['filename'],
                'subfolder' => $image['subfolder'] ?? '',
                'type' => $image['type'] ?? 'temp',
            ]);

        if (!$imageResponse->successful()) {
            throw new \Exception(
                'The result was generated but could not be downloaded.'
            );
        }

        $extension = strtolower(
            pathinfo($image['filename'], PATHINFO_EXTENSION)
        );

        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $path = 'quality-candidates/'
            . $message->chat_id
            . '/message-' . $message->id
            . '/attempt-' . $message->attempt_count
            . '-' . Str::uuid()
            . '.' . $extension;

        Storage::disk('public')->put(
            $path,
            $imageResponse->body()
        );

        return $path;
    }

    private function inspectImageFacts(
        Message $message,
        array $requirements
    ): array {
        $path = $message->candidate_media_path;

        if (
            !$path
            || !Storage::disk('public')->exists($path)
        ) {
            throw new \Exception(
                'Candidate image for visual inspection could not be found.'
            );
        }

        $ollamaUrl = env(
            'OLLAMA_URL',
            'http://127.0.0.1:11434'
        );

        $model = env(
            'OLLAMA_VISION_MODEL',
            env('OLLAMA_MODEL', 'gemma3:4b')
        );

        $targetSubject = trim(
            (string) ($requirements['primary_subject'] ?? '')
        );

        if ($targetSubject === '') {
            $targetSubject = 'main visible subject';
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'primary_subject_count' => [
                    'type' => 'integer',
                ],
                'primary_subject_instances' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'primary_subject_description' => [
                    'type' => 'string',
                ],
                'primary_subject_colors' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'primary_subject_attributes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'primary_subject_actions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'scene' => [
                    'type' => 'string',
                ],
                'visible_objects' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'notes' => [
                    'type' => 'string',
                ],
            ],
            'required' => [
                'primary_subject_count',
                'primary_subject_instances',
                'primary_subject_description',
                'primary_subject_colors',
                'primary_subject_attributes',
                'primary_subject_actions',
                'scene',
                'visible_objects',
                'notes',
            ],
            'additionalProperties' => false,
        ];

        $systemPrompt = <<<'SYS_PROMPT'
You are a literal visual-inspection system.

Your task is NOT to judge whether the image follows a prompt.
Your task is ONLY to report visible facts from the attached image.

COUNTING IS CRITICAL:
- Count EVERY distinct visible instance of the target subject.
- Scan the entire image from top-left to bottom-right.
- Repeated or duplicated subjects must each count separately.
- Do not group multiple visible objects into one object.
- Add ONE short entry to primary_subject_instances for EACH distinct visible instance.
- primary_subject_count must equal the number of distinct instances you can see.
- If six target objects are visible, the answer must be 6.
- If none are visible, return 0.
- Do not guess what quantity the user probably wanted.
- You are intentionally NOT being told the expected quantity.

FACT REPORTING:
- Report colors actually visible on the target subject.
- Report visible attributes and actions conservatively.
- Describe the scene literally.
- List other clearly visible object categories.
- Never invent an object that is not visible.
- Do not score image quality.
- Do not approve or reject anything.
SYS_PROMPT;

        $userContent = "Target subject to inspect:\n"
            . $targetSubject
            . "\n\nCount every distinct visible instance of this target subject and report literal visual facts only."
            . "\n\nThe expected quantity is intentionally hidden from you.";

        $response = Http::connectTimeout(10)
            ->timeout(600)
            ->post($ollamaUrl . '/api/chat', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                        'images' => [
                            base64_encode(
                                Storage::disk('public')->get($path)
                            ),
                        ],
                    ],
                ],
                'stream' => false,
                'format' => $schema,
                'options' => [
                    'temperature' => 0,
                ],
                'keep_alive' => 0,
            ]);

        if (!$response->successful()) {
            throw new \Exception(
                'Could not inspect the generated image: '
                . $response->body()
            );
        }

        $content = $response->json(
            'message.content'
        );

        if (!$content) {
            throw new \Exception(
                'The visual-inspection model returned an empty response.'
            );
        }

        $facts = json_decode(
            $content,
            true
        );

        if (!is_array($facts)) {
            throw new \Exception(
                'The visual-inspection model returned invalid JSON.'
            );
        }

        foreach ([
            'primary_subject_count',
            'primary_subject_instances',
            'primary_subject_description',
            'primary_subject_colors',
            'primary_subject_attributes',
            'primary_subject_actions',
            'scene',
            'visible_objects',
            'notes',
        ] as $requiredKey) {
            if (!array_key_exists($requiredKey, $facts)) {
                throw new \Exception(
                    'The visual-inspection response is missing: '
                    . $requiredKey
                );
            }
        }

        return $facts;
    }

    private function resolveObservedSubjectCount(
        array $facts
    ): int {
        $reported = max(
            0,
            (int) ($facts['primary_subject_count'] ?? 0)
        );

        $instances = is_array(
            $facts['primary_subject_instances'] ?? null
        )
            ? array_values(array_filter(
                $facts['primary_subject_instances'],
                fn ($value) =>
                    is_string($value)
                    && trim($value) !== ''
            ))
            : [];

        /*
        |--------------------------------------------------------------------------
        | Redundant counting safeguard
        |--------------------------------------------------------------------------
        |
        | The model reports both a number and one list item per visible instance.
        | Laravel uses the larger value so "count=1 but six listed instances"
        | cannot accidentally pass an exact-count requirement.
        */
        return max(
            $reported,
            count($instances)
        );
    }

    private function evaluateHardRequirements(
        array $requirements,
        array $facts
    ): array {
        $failures = [];

        $expectedCount =
            $requirements['expected_count']
            ?? null;

        $observedCount =
            isset($facts['primary_subject_count'])
                ? (int) $facts['primary_subject_count']
                : null;

        $subject = trim(
            (string) (
                $requirements['primary_subject']
                ?? 'primary subject'
            )
        );

        if ($subject === '') {
            $subject = 'primary subject';
        }

        /*
        |--------------------------------------------------------------------------
        | Exact subject count
        |--------------------------------------------------------------------------
        */
        if (
            $expectedCount !== null
            && $observedCount !== null
            && (int) $expectedCount !== $observedCount
        ) {
            $failures[] =
                'Expected exactly '
                . (int) $expectedCount
                . ' '
                . $subject
                . ', but the visual inspection found '
                . $observedCount
                . '.';
        }

        /*
        |--------------------------------------------------------------------------
        | Required colors
        |--------------------------------------------------------------------------
        */
        $requiredColors = is_array(
            $requirements['required_colors']
            ?? null
        )
            ? $requirements['required_colors']
            : [];

        $observedColors = is_array(
            $facts['primary_subject_colors']
            ?? null
        )
            ? $facts['primary_subject_colors']
            : [];

        $observedColorText =
            $this->normalizeComparableText(
                implode(
                    ' ',
                    $observedColors
                )
            );

        foreach ($requiredColors as $requiredColor) {
            $normalizedColor =
                $this->normalizeComparableText(
                    (string) $requiredColor
                );

            if (
                $normalizedColor !== ''
                && !str_contains(
                    $observedColorText,
                    $normalizedColor
                )
            ) {
                $failures[] =
                    'Required color "'
                    . $requiredColor
                    . '" was not confirmed on the primary subject.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Explicit forbidden visible elements
        |--------------------------------------------------------------------------
        */
        $forbidden = is_array(
            $requirements['forbidden_elements']
            ?? null
        )
            ? $requirements['forbidden_elements']
            : [];

        $visibleText =
            $this->normalizeComparableText(
                implode(
                    ' ',
                    array_merge(
                        is_array(
                            $facts['visible_objects']
                            ?? null
                        )
                            ? $facts['visible_objects']
                            : [],
                        [
                            (string) (
                                $facts['primary_subject_description']
                                ?? ''
                            ),
                            (string) (
                                $facts['notes']
                                ?? ''
                            ),
                        ]
                    )
                )
            );

        foreach ($forbidden as $forbiddenElement) {
            $normalizedForbidden =
                $this->normalizeComparableText(
                    (string) $forbiddenElement
                );

            if (
                $normalizedForbidden !== ''
                && str_contains(
                    $visibleText,
                    $normalizedForbidden
                )
            ) {
                $failures[] =
                    'Forbidden element "'
                    . $forbiddenElement
                    . '" appears to be visible.';
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    $failures
                )
            )
        );
    }

    private function buildHardRuleRetryPrompts(
        array $requirements,
        array $facts,
        array $hardFailures
    ): array {
        if (empty($hardFailures)) {
            return [
                'positive' => '',
                'negative' => '',
            ];
        }

        $positiveParts = [];
        $negativeParts = [];

        $subject = trim(
            (string) (
                $requirements['primary_subject']
                ?? ''
            )
        );

        $expectedCount =
            $requirements['expected_count']
            ?? null;

        $observedCount =
            isset($facts['primary_subject_count'])
                ? (int) $facts['primary_subject_count']
                : null;

        if (
            $subject !== ''
            && $expectedCount !== null
            && $observedCount !== null
            && (int) $expectedCount !== $observedCount
        ) {
            $positiveParts[] =
                'The final image must contain exactly '
                . (int) $expectedCount
                . ' '
                . $subject
                . '.';

            if ((int) $expectedCount === 1) {
                $negativeParts[] =
                    'multiple '
                    . $subject
                    . ', additional '
                    . $subject
                    . ', duplicate '
                    . $subject
                    . ', extra '
                    . $subject;
            } else {
                $negativeParts[] =
                    'more or fewer than exactly '
                    . (int) $expectedCount
                    . ' '
                    . $subject;
            }
        }

        $requiredColors = is_array(
            $requirements['required_colors']
            ?? null
        )
            ? $requirements['required_colors']
            : [];

        if (
            $subject !== ''
            && !empty($requiredColors)
        ) {
            $positiveParts[] =
                'The '
                . $subject
                . ' must visibly be '
                . implode(
                    ' and ',
                    $requiredColors
                )
                . '.';
        }

        $forbidden = is_array(
            $requirements['forbidden_elements']
            ?? null
        )
            ? $requirements['forbidden_elements']
            : [];

        if (!empty($forbidden)) {
            $negativeParts[] =
                implode(
                    ', ',
                    $forbidden
                );
        }

        return [
            'positive' => implode(
                ' ',
                array_unique(
                    $positiveParts
                )
            ),
            'negative' => implode(
                ', ',
                array_unique(
                    $negativeParts
                )
            ),
        ];
    }

    private function evaluateImageQuality(
        Message $message,
        Message $userMessage,
        array $requirements,
        array $visualFacts
    ): array {
        $path = $message->candidate_media_path;

        if (
            !$path
            || !Storage::disk('public')->exists($path)
        ) {
            throw new \Exception(
                'Candidate image for quality control could not be found.'
            );
        }

        $ollamaUrl = env(
            'OLLAMA_URL',
            'http://127.0.0.1:11434'
        );

        $model = env(
            'OLLAMA_VISION_MODEL',
            env('OLLAMA_MODEL', 'gemma3:4b')
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'score' => [
                    'type' => 'integer',
                ],
                'feedback' => [
                    'type' => 'string',
                ],
                'problems' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'retry_positive_prompt' => [
                    'type' => 'string',
                ],
                'retry_negative_prompt' => [
                    'type' => 'string',
                ],
            ],
            'required' => [
                'score',
                'feedback',
                'problems',
                'retry_positive_prompt',
                'retry_negative_prompt',
            ],
            'additionalProperties' => false,
        ];

        $systemPrompt = <<<'SYS_PROMPT'
You are the second-stage quality evaluator for an AI image generation system.

An independent visual inspector has already examined the image and returned literal visual facts.
Those visual facts are evidence. Do NOT casually contradict or overwrite them.

Your job is to:
1. Compare the original user request with the structured requirements.
2. Use the supplied visual facts as the factual grounding.
3. Inspect the attached image for broader semantic correctness and visible generation defects.
4. Give a conservative quality score.
5. Explain mismatches clearly.
6. Provide focused retry corrections when needed.

IMPORTANT:
- You are NOT responsible for the final pass/fail decision.
- Laravel will enforce exact hard constraints separately.
- Do not claim a different object count than the supplied visual facts.
- If the visual facts say multiple primary subjects are visible, your feedback must acknowledge that.
- Do not invent requirements the user never requested.
- Minor artistic differences are acceptable unless explicitly constrained.
- Severe malformed anatomy, duplicate subjects, broken geometry, unreadable subject identity, or obvious generation artifacts should reduce the score.

SCORING:
- 95-100: Excellent match with no meaningful issue.
- 85-94: Good match with only minor non-critical issues.
- 70-84: Noticeable mismatch or visual-quality problem.
- 40-69: Major mismatch with one or more important requirements.
- 0-39: Fundamentally wrong or unusable.

RETRY PROMPTS:
- retry_positive_prompt: only corrective desired details.
- retry_negative_prompt: only things that should be avoided.
- Do not add unrelated creative ideas.
- Return empty retry strings if no correction is needed.
SYS_PROMPT;

        $userContent =
            "Original user request:\n"
            . ($userMessage->content ?? '')
            . "\n\nStructured requirements:\n"
            . json_encode(
                $requirements,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            . "\n\nIndependent visual facts:\n"
            . json_encode(
                $visualFacts,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            . "\n\nCurrent positive prompt:\n"
            . ($message->positive_prompt ?? '')
            . "\n\nCurrent negative prompt:\n"
            . ($message->negative_prompt ?: 'None')
            . "\n\nRequired minimum score: "
            . $this->minimumQualityScore()
            . "/100"
            . "\n\nEvaluate the attached candidate conservatively.";

        $response = Http::connectTimeout(10)
            ->timeout(600)
            ->post($ollamaUrl . '/api/chat', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                        'images' => [
                            base64_encode(
                                Storage::disk('public')->get($path)
                            ),
                        ],
                    ],
                ],
                'stream' => false,
                'format' => $schema,
                'options' => [
                    'temperature' => 0,
                ],
                'keep_alive' => 0,
            ]);

        if (!$response->successful()) {
            throw new \Exception(
                'Could not run the semantic quality check: '
                . $response->body()
            );
        }

        $content = $response->json(
            'message.content'
        );

        if (!$content) {
            throw new \Exception(
                'The semantic quality model returned an empty response.'
            );
        }

        $evaluation = json_decode(
            $content,
            true
        );

        if (!is_array($evaluation)) {
            throw new \Exception(
                'The semantic quality model returned invalid JSON.'
            );
        }

        foreach ([
            'score',
            'feedback',
            'problems',
            'retry_positive_prompt',
            'retry_negative_prompt',
        ] as $requiredKey) {
            if (!array_key_exists($requiredKey, $evaluation)) {
                throw new \Exception(
                    'The semantic quality response is missing: '
                    . $requiredKey
                );
            }
        }

        return $evaluation;
    }

    private function mergePromptParts(
        string ...$parts
    ): string {
        $clean = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return implode(
            ' ',
            array_values(
                array_unique(
                    $clean
                )
            )
        );
    }

    private function normalizeComparableText(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
        );

        $value = preg_replace(
            '/[^a-z0-9\s\-]+/u',
            ' ',
            $value
        );

        $value = preg_replace(
            '/\s+/',
            ' ',
            $value ?? ''
        );

        return trim(
            (string) $value
        );
    }

    private function rememberBestCandidate(
        Message $message,
        ?string $candidatePath,
        int $score
    ): void {
        if (!$candidatePath) {
            return;
        }

        $currentBestScore = $message->best_quality_score;
        $currentBestPath = $message->best_candidate_path;

        if ($currentBestScore === null || $score > $currentBestScore) {
            if (
                $currentBestPath
                && $currentBestPath !== $candidatePath
                && $currentBestPath !== $message->media_path
            ) {
                Storage::disk('public')->delete($currentBestPath);
            }

            $message->update([
                'best_candidate_path' => $candidatePath,
                'best_quality_score' => $score,
            ]);
        }
    }

    private function completeWithCandidate(
        Message $message,
        string $candidatePath,
        string $qualityStatus
    ): void {
        $oldBest = $message->best_candidate_path;

        if (
            $oldBest
            && $oldBest !== $candidatePath
            && $oldBest !== $message->media_path
        ) {
            Storage::disk('public')->delete($oldBest);
        }

        $message->update([
            'status' => 'completed',
            'quality_status' => $qualityStatus,
            'media_path' => $candidatePath,
            'candidate_media_path' => null,
            'best_candidate_path' => $candidatePath,
            'best_quality_score' => $message->quality_score,
            'queue_position' => null,
        ]);
    }

    private function userMessageForAssistant(Message $assistantMessage): Message
    {
        $userMessage = Message::where('chat_id', $assistantMessage->chat_id)
            ->where('role', 'user')
            ->where('id', '<', $assistantMessage->id)
            ->latest('id')
            ->first();

        if (!$userMessage) {
            throw new \Exception('Could not find the user request for this generation.');
        }

        return $userMessage;
    }

    private function completedPayload(
        Message $message,
        string $label = 'Complete'
    ): array {
        $requirements = $this->normalizeRequirements(
            $message->quality_requirements ?? []
        );

        return [
            'status' => 'completed',
            'label' => $label,
            'media_url' => route('messages.media', $message),
            'quality_status' => $message->quality_status,
            'quality_score' => $message->quality_score,
            'best_quality_score' => $message->best_quality_score,
            'quality_feedback' => $message->quality_feedback,
            'quality_problems' => $message->quality_problems ?? [],
            'quality_requirements' => $requirements,
            'visual_facts' => $message->visual_facts ?? [],
            'expected_count' => $requirements['expected_count'] ?? null,
            'observed_count' => $message->observed_subject_count,
            'hard_failures' => $message->quality_hard_failures ?? [],
            'attempt_count' => $message->attempt_count,
            'max_attempts' => $message->max_attempts,
            ...$this->messageMetadata($message),
        ];
    }

    private function messageMetadata(Message $message): array
    {
        return [
            'actual_workflow' => $message->workflow,
            'detected_language' => $message->detected_language,
            'positive_prompt' => $message->positive_prompt,
            'negative_prompt' => $message->negative_prompt,
        ];
    }

    private function authorizeMessage(Message $message): void
    {
        $message->loadMissing('chat');

        abort_unless(
            $message->chat
            && $message->chat->client_id === ClientIdentity::id(),
            404
        );
    }

    private function queuePosition(array $queue, string $promptId): ?int
    {
        foreach ($queue as $index => $item) {
            if (isset($item[1]) && $item[1] === $promptId) {
                return $index + 1;
            }
        }

        return null;
    }

    private function findFirstImage(array $outputs): ?array
    {
        foreach ($outputs as $output) {
            if (isset($output['images']) && !empty($output['images'])) {
                return $output['images'][0];
            }
        }

        return null;
    }

    private function loadWorkflow(string $filename): array
    {
        $path = storage_path('app/workflows/' . $filename);

        if (!file_exists($path)) {
            throw new \Exception('Workflow file not found: ' . $filename);
        }

        $workflow = json_decode(file_get_contents($path), true);

        if (!is_array($workflow)) {
            throw new \Exception('Could not read workflow: ' . $filename);
        }

        return $workflow;
    }

    private function normalizeRequirements(
        array $requirements
    ): array {
        $expectedCount =
            isset($requirements['expected_count'])
                ? (int) $requirements['expected_count']
                : -1;

        if ($expectedCount < 0) {
            $expectedCount = null;
        }

        $normalizeList = function ($value): array {
            if (!is_array($value)) {
                return [];
            }

            return array_values(
                array_unique(
                    array_filter(
                        array_map(
                            fn ($item) =>
                                is_string($item)
                                    ? trim($item)
                                    : '',
                            $value
                        ),
                        fn ($item) =>
                            $item !== ''
                    )
                )
            );
        };

        return [
            'primary_subject' => trim(
                (string) (
                    $requirements['primary_subject']
                    ?? ''
                )
            ),
            'expected_count' => $expectedCount,
            'required_colors' => $normalizeList(
                $requirements['required_colors']
                ?? []
            ),
            'required_attributes' => $normalizeList(
                $requirements['required_attributes']
                ?? []
            ),
            'required_actions' => $normalizeList(
                $requirements['required_actions']
                ?? []
            ),
            'setting' => trim(
                (string) (
                    $requirements['setting']
                    ?? ''
                )
            ),
            'framing_requirements' => $normalizeList(
                $requirements['framing_requirements']
                ?? []
            ),
            'forbidden_elements' => $normalizeList(
                $requirements['forbidden_elements']
                ?? []
            ),
            'preservation_requirements' => $normalizeList(
                $requirements['preservation_requirements']
                ?? []
            ),
        ];
    }

    private function strengthenPromptsFromRequirements(
        string $positivePrompt,
        string $negativePrompt,
        array $requirements
    ): array {
        $positivePrompt = trim(
            $positivePrompt
        );

        $negativePrompt = trim(
            $negativePrompt
        );

        $subject = trim(
            (string) (
                $requirements['primary_subject']
                ?? ''
            )
        );

        $expectedCount =
            $requirements['expected_count']
            ?? null;

        if (
            $subject !== ''
            && $expectedCount !== null
        ) {
            $countInstruction =
                'exactly '
                . (int) $expectedCount
                . ' '
                . $subject;

            $positivePrompt =
                $this->mergePromptParts(
                    $countInstruction . '.',
                    $positivePrompt
                );

            if ((int) $expectedCount === 1) {
                $negativePrompt =
                    $this->mergePromptParts(
                        $negativePrompt,
                        'multiple '
                            . $subject
                            . ', additional '
                            . $subject
                            . ', duplicate '
                            . $subject
                            . ', extra '
                            . $subject
                    );
            }
        }

        return [
            $positivePrompt,
            $negativePrompt,
        ];
    }

    private function analyseWithOllama(
        string $userRequest,
        bool $hasImage
    ): array {
        $ollamaUrl = env(
            'OLLAMA_URL',
            'http://127.0.0.1:11434'
        );

        $model = env(
            'OLLAMA_MODEL',
            'gemma3:4b'
        );

        $requirementsSchema = [
            'type' => 'object',
            'properties' => [
                'primary_subject' => [
                    'type' => 'string',
                ],
                /*
                | -1 means "no exact count specified".
                */
                'expected_count' => [
                    'type' => 'integer',
                ],
                'required_colors' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'required_attributes' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'required_actions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'setting' => [
                    'type' => 'string',
                ],
                'framing_requirements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'forbidden_elements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'preservation_requirements' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'primary_subject',
                'expected_count',
                'required_colors',
                'required_attributes',
                'required_actions',
                'setting',
                'framing_requirements',
                'forbidden_elements',
                'preservation_requirements',
            ],
            'additionalProperties' => false,
        ];

        $schema = [
            'type' => 'object',
            'properties' => [
                'workflow' => [
                    'type' => 'string',
                    'enum' => [
                        'text_to_image',
                        'image_to_image',
                    ],
                ],
                'detected_language' => [
                    'type' => 'string',
                ],
                'positive_prompt' => [
                    'type' => 'string',
                ],
                'negative_prompt' => [
                    'type' => 'string',
                ],
                'requirements' =>
                    $requirementsSchema,
            ],
            'required' => [
                'workflow',
                'detected_language',
                'positive_prompt',
                'negative_prompt',
                'requirements',
            ],
            'additionalProperties' => false,
        ];

        $systemPrompt = <<<'SYS_PROMPT'
You are an AI request parser and workflow router for a local AI generation system.

The user may write their request in any language.

Your responsibilities are:
1. Detect the user's language.
2. Translate the generation intent into clear English when necessary.
3. Choose the correct workflow.
4. Create a positive prompt describing the intended FINAL result.
5. Create a negative prompt describing things that must NOT appear.
6. Extract structured requirements for later automatic quality control.

AVAILABLE WORKFLOWS

text_to_image:
Use when a completely new image should be generated from text only.

image_to_image:
Use when an uploaded image should be edited, transformed, restyled, changed, or used as the visual basis for the result.

STRUCTURED REQUIREMENTS

primary_subject:
The main countable or visually important requested subject.
Examples:
- "A red Porsche car driving on a highway" -> "Porsche car"
- "A polar bear close-up" -> "polar bear"
- "three palm trees on a beach" -> "palm tree"

expected_count:
- Use an integer when the user clearly requests an exact quantity.
- IMPORTANT: a clearly singular countable main subject introduced as "a", "an", "one", "a single", or equivalent wording means expected_count=1.
- Explicit numbers such as two, three, four, etc. must be preserved exactly.
- Use -1 only when no exact quantity can reasonably be inferred.
Examples:
"A red Porsche car driving on a highway" -> 1
"A polar bear portrait" -> 1
"three red cars" -> 3
"cars driving on a highway" -> -1
"a crowd at a concert" -> 1 crowd, not an exact number of people

required_colors:
Only colors explicitly required for the primary subject.

required_attributes:
Important visible characteristics explicitly requested.

required_actions:
Actions the primary subject must visibly be doing.

setting:
The explicitly requested scene/location, or an empty string if none.

framing_requirements:
Examples include close-up, portrait, full body, aerial view, centered subject.
Only include framing explicitly requested.

forbidden_elements:
Only elements the USER explicitly says must not be present.
Do not place automatically-derived duplicate-subject negatives here.

preservation_requirements:
For image editing, include things the user says must stay unchanged.
For text-to-image, normally return an empty array.

PROMPT RULES

- Preserve exact quantities.
- If expected_count is 1, positive_prompt must make the single-subject intent explicit.
- When useful, negative_prompt should discourage multiple, duplicate, additional, or extra copies of a subject that must appear exactly once.
- If expected_count is greater than 1, positive_prompt must say exactly that number.
- Preserve explicit colors, actions, settings, framing, removals, and preservation instructions.
- Do NOT add styles, camera choices, lighting, people, scenery, colors, objects, or artistic details the user did not request.
- negative_prompt may be empty if nothing needs to be excluded.
- Never choose image_to_image when no image was uploaded.
- When an image is uploaded and the user refers to changing, removing, adding, replacing, keeping, preserving, the background, this image, this photo, or similar wording, choose image_to_image.
- Both positive_prompt and negative_prompt must be written in English.
- detected_language should use a real language name such as English, Dutch, Spanish, Papiamento, etc. Do not return "Unknown" when the language is clearly identifiable.

Before returning:
- Check the quantity one more time.
- Check that every explicit user constraint exists in the structured requirements.
- Check that positive_prompt and negative_prompt do not contradict the requirements.
- Return only the requested JSON structure.
SYS_PROMPT;

        $userMessage =
            "User request:\n"
            . $userRequest
            . "\n\nReference image uploaded: "
            . ($hasImage ? 'YES' : 'NO');

        $response = Http::connectTimeout(10)
            ->timeout(600)
            ->post(
                $ollamaUrl . '/api/chat',
                [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userMessage,
                        ],
                    ],
                    'stream' => false,
                    'format' => $schema,
                    'options' => [
                        'temperature' => 0,
                    ],
                    'keep_alive' => 0,
                ]
            );

        if (!$response->successful()) {
            throw new \Exception(
                'Could not connect to Ollama: '
                . $response->body()
            );
        }

        $content = $response->json(
            'message.content'
        );

        if (!$content) {
            throw new \Exception(
                'Ollama returned an empty response.'
            );
        }

        $analysis = json_decode(
            $content,
            true
        );

        if (!is_array($analysis)) {
            throw new \Exception(
                'Ollama returned invalid JSON.'
            );
        }

        foreach ([
            'workflow',
            'detected_language',
            'positive_prompt',
            'negative_prompt',
            'requirements',
        ] as $requiredKey) {
            if (!array_key_exists($requiredKey, $analysis)) {
                throw new \Exception(
                    'Ollama response is missing required information: '
                    . $requiredKey
                );
            }
        }

        if (!is_array($analysis['requirements'])) {
            throw new \Exception(
                'Ollama returned invalid structured requirements.'
            );
        }

        return $analysis;
    }

    private function minimumQualityScore(): int
    {
        return max(
            0,
            min(
                100,
                (int) env('QUALITY_MIN_SCORE', self::DEFAULT_MIN_QUALITY_SCORE)
            )
        );
    }

    private function maxAttempts(): int
    {
        return max(
            1,
            min(
                5,
                (int) env('QUALITY_MAX_ATTEMPTS', self::DEFAULT_MAX_ATTEMPTS)
            )
        );
    }
}
