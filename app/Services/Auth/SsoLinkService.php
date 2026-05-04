<?php

namespace App\Services\Auth;

use App\Exceptions\SsoLoginException;
use App\Models\User;
use App\Services\ActivityLogger;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SsoLinkService
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Resolve a Google identity to a MadData user for login.
     *
     * Resolution order:
     * 1. Find by google_sub → log in.
     * 2. Find by Google email but no google_sub link → block with EMAIL_MATCH_NO_LINK.
     * 3. No match at all → block with NO_USER_FOUND.
     * 4. Found but is_active=false → block with USER_INACTIVE.
     *
     * @throws SsoLoginException
     */
    public function resolveLogin(SocialiteUser $google): User
    {
        // 1. Look up by stable sub identifier
        $user = User::where('google_sub', $google->getId())->first();

        if ($user) {
            if (! $user->is_active) {
                throw SsoLoginException::userInactive();
            }

            return $user;
        }

        // 2. Look up by email — user exists but has not linked Google yet
        $emailUser = User::where('email', $google->getEmail())->first();

        if ($emailUser) {
            // Block regardless of active state — the message is "sign in with password first"
            throw SsoLoginException::emailMatchNoLink();
        }

        // 3. No account at all
        throw SsoLoginException::noUserFound();
    }

    /**
     * Link a Google identity to an existing user.
     * Caller must have already verified the user's password before calling this.
     */
    public function link(User $user, SocialiteUser $google): void
    {
        $user->update([
            'google_sub' => $google->getId(),
            'google_email' => $google->getEmail(),
            'google_linked_at' => now(),
        ]);

        $this->logger->log('sso.linked', $user, 'Google account linked.', [
            'google_email' => $google->getEmail(),
        ]);
    }

    /**
     * Remove the Google link from a user.
     * Caller must have already enforced the lockout invariant.
     */
    public function unlink(User $user): void
    {
        $googleEmail = $user->google_email;

        $user->update([
            'google_sub' => null,
            'google_email' => null,
            'google_linked_at' => null,
        ]);

        $this->logger->log('sso.unlinked', $user, 'Google account disconnected.', [
            'google_email' => $googleEmail,
        ]);
    }
}
