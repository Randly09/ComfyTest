<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'role',
        'content',
        'media_path',
        'media_type',
        'workflow',
        'comfy_prompt_id',
        'status',
        'queue_position',
        'detected_language',
        'positive_prompt',
        'negative_prompt',
        'attempt_count',
        'max_attempts',
        'quality_status',
        'quality_score',
        'quality_feedback',
        'quality_problems',
        'quality_retry_positive',
        'quality_retry_negative',
        'candidate_media_path',
        'best_candidate_path',
        'best_quality_score',
        'quality_requirements',
        'visual_facts',
        'observed_subject_count',
        'quality_hard_failures',
    ];

    protected function casts(): array
    {
        return [
            'queue_position' => 'integer',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'quality_score' => 'integer',
            'best_quality_score' => 'integer',
            'observed_subject_count' => 'integer',
            'quality_problems' => 'array',
            'quality_requirements' => 'array',
            'visual_facts' => 'array',
            'quality_hard_failures' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
