<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientIdentity
{
    public static function id(): string
    {
        if (!session()->has('client_id')) {

            session([
                'client_id' => (string) Str::uuid()
            ]);
        }

        return session('client_id');
    }
}