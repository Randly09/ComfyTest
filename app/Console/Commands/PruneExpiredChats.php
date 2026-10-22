<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;

class PruneExpiredChats extends Command
{
    protected $signature =
        'chats:prune';


    protected $description =
        'Delete AI chats older than their retention period';


    public function handle(): int
    {
        $chats =
            Chat::where(
                'expires_at',
                '<=',
                now()
            )
            ->get();


        $count =
            $chats->count();


        foreach ($chats as $chat) {

            $chat->delete();
        }


        $this->info(
            "Deleted {$count} expired chats."
        );


        return self::SUCCESS;
    }
}