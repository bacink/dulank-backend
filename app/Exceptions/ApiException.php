<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception HTTP yang dirender sebagai envelope error API.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self($message, 404);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): self
    {
        return new self($message, 403);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(string $message, array $errors = []): self
    {
        return new self($message, 422, $errors);
    }
}
