<?php

declare(strict_types=1);

namespace App\Domain\ErrorLog\DTOs;

final readonly class ErrorLogData
{
    /**
     * @param  list<array{
     *     id: int,
     *     level: string,
     *     exception_class: string,
     *     message: string,
     *     file: string|null,
     *     line: int|null,
     *     url: string|null,
     *     method: string|null,
     *     stack_trace: string|null,
     *     user: array{id: int, name: string, email: string}|null,
     *     occurred_at: string,
     * }>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public string $generatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'generated_at' => $this->generatedAt,
        ];
    }
}
