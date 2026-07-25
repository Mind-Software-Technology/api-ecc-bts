<?php

namespace App\Support;

use Google\Auth\AccessToken;

class GoogleIdTokenVerifier
{
    /**
     * @return array<string, mixed>|false
     */
    public function verify(string $idToken): array|false
    {
        return (new AccessToken)->verify($idToken, ['audience' => config('services.google.client_id')]);
    }
}
