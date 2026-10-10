<?php

namespace App\Services\DecisionEngine;

use RuntimeException;

/**
 * Anything that stopped a call to the Decision Engine from succeeding.
 *
 * Two kinds, because they call for different things on screen:
 * - unavailable: the engine could not be reached or failed on its side. The
 *   request may never have arrived. Nothing the officer typed is wrong, and
 *   trying again later is the right move.
 * - rejected: the engine answered and refused the request, with a reason.
 *   Retrying unchanged will be refused again.
 *
 * The message is safe to show an officer. It never contains the token.
 */
class DecisionEngineException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $unavailable,
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorType = null,
        public readonly ?string $errorField = null,
    ) {
        parent::__construct($message);
    }

    public static function unavailable(string $detail, ?int $httpStatus = null): self
    {
        return new self($detail, unavailable: true, httpStatus: $httpStatus);
    }

    public static function rejected(int $httpStatus, ?string $type, string $message, ?string $field = null): self
    {
        return new self($message, unavailable: false, httpStatus: $httpStatus, errorType: $type, errorField: $field);
    }

    public function isType(string $type): bool
    {
        return $this->errorType === $type;
    }
}
