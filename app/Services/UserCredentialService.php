<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewUserCredentialsNotification;
use Illuminate\Support\Str;

class UserCredentialService
{
    /**
     * Generate a secure temporary password.
     */
    public function generateTemporaryPassword(): string
    {
        return Str::password(12);
    }

    /**
     * Send temporary login credentials to the user.
     */
    public function sendCredentials(
        User $user,
        string $temporaryPassword
    ): void {
        $user->notify(
            new NewUserCredentialsNotification(
                $temporaryPassword
            )
        );
    }
}
