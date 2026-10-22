<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Support\ClientIdentity;

class ChatController extends Controller
{
    public function index()
    {
        $clientId =
            ClientIdentity::id();


        $chat =
            Chat::where(
                'client_id',
                $clientId
            )
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->latest('updated_at')
            ->first();


        if (!$chat) {

            $chat =
                $this->createChat(
                    $clientId
                );
        }


        return redirect()
            ->route(
                'chats.show',
                $chat
            );
    }


    public function store()
    {
        $chat =
            $this->createChat(
                ClientIdentity::id()
            );


        return redirect()
            ->route(
                'chats.show',
                $chat
            );
    }


    public function show(Chat $chat)
    {
        $clientId =
            ClientIdentity::id();


        abort_unless(
            $chat->client_id === $clientId,
            404
        );


        abort_if(
            $chat->expires_at->isPast(),
            404
        );


        $chats =
            Chat::where(
                'client_id',
                $clientId
            )
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->latest('updated_at')
            ->get();


        $messages =
            $chat->messages()
                ->oldest()
                ->get();


        return view(
            'generate',
            [
                'chats' =>
                    $chats,

                'activeChat' =>
                    $chat,

                'messages' =>
                    $messages,
            ]
        );
    }


    public function destroy(Chat $chat)
    {
        abort_unless(
            $chat->client_id ===
                ClientIdentity::id(),
            404
        );


        $chat->delete();


        return redirect()
            ->route('chat.index');
    }


    private function createChat(
        string $clientId
    ): Chat {

        return Chat::create([

            'client_id' =>
                $clientId,

            'title' =>
                'New chat',

            'expires_at' =>
                now()->addDays(5),
        ]);
    }
}