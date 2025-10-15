<?php

declare(strict_types=1);

namespace App\Http\Response;

/*
 * Wrapper for all types of responses
 */
final readonly class ApiResponse
{
    public function __construct(
        private bool $success,
        private ?string $message = null,
        private mixed $data = null,
        private ?array $errors = null,
        private int $status = 200,
    ) {
    }

    public static function success(mixed $data = null, string $message = '', int $status = 200): self
    {
        return new self(true, $message, $data, null, $status);
    }

    public static function error(string|array $message, array $errors = [], int $status = 400): self
    {
        if (is_array($message)) {
            $message = implode(PHP_EOL, $message);
        }

        return new self(false, $message, null, $errors, $status);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data'    => $this->data,
            'errors'  => $this->errors,
        ];
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
