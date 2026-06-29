<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionIntegrityData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $hash = null,
        public ?string $previousHash = null,
        public ?string $signature = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hash: self::nullableString($data['hash'] ?? null),
            previousHash: self::nullableString($data['previous_hash'] ?? null),
            signature: self::nullableString($data['signature'] ?? null),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array{hash: ?string, previous_hash: ?string, signature: ?string, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'previous_hash' => $this->previousHash,
            'signature' => $this->signature,
            'metadata' => $this->metadata,
        ];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
