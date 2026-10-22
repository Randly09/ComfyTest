<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Chat extends Model
{
    protected $fillable = [
        'client_id',
        'title',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Chat $chat) {
            foreach ($chat->messages as $message) {
                $paths = array_unique(array_filter([
                    $message->media_path,
                    $message->candidate_media_path,
                    $message->best_candidate_path,
                ]));

                foreach ($paths as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }
}
