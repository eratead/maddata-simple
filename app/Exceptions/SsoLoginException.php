<?php

namespace App\Exceptions;

use RuntimeException;

class SsoLoginException extends RuntimeException
{
    public const NO_USER_FOUND = 'NO_USER_FOUND';

    public const EMAIL_MATCH_NO_LINK = 'EMAIL_MATCH_NO_LINK';

    public const USER_INACTIVE = 'USER_INACTIVE';

    public static function noUserFound(): self
    {
        return new self('No MadData account found for this Google identity. Contact your administrator.', 0, null);
    }

    public static function emailMatchNoLink(): self
    {
        return new self(
            'An account exists for this email. Sign in with email and password, then connect Google in Settings.',
            0,
            null
        );
    }

    public static function userInactive(): self
    {
        return new self('This account has been disabled. Contact your administrator.', 0, null);
    }

    public function getErrorCode(): string
    {
        return match ($this->getMessage()) {
            'No MadData account found for this Google identity. Contact your administrator.' => self::NO_USER_FOUND,
            'An account exists for this email. Sign in with email and password, then connect Google in Settings.' => self::EMAIL_MATCH_NO_LINK,
            default => self::USER_INACTIVE,
        };
    }
}
