<?php

namespace App\Services\Tools\DTOs;

readonly class ToolResult
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
    ) {}

    public static function success(mixed $data): self
    {
        return new self(success: true, data: $data);
    }

    public static function error(string $message): self
    {
        return new self(success: false, error: $message);
    }

    public function toJson(): string
    {
        if (! $this->success) {
            return json_encode(['error' => $this->error]);
        }

        return is_string($this->data)
            ? json_encode(['result' => $this->data])
            : json_encode($this->data);
    }
}
