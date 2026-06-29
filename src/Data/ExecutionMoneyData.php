<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionMoneyData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $amount = null,
        public ?string $currency = null,
        public ?int $minorAmount = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: self::nullableString($data['amount'] ?? null),
            currency: self::nullableString($data['currency'] ?? null),
            minorAmount: self::nullableInteger($data['minor_amount'] ?? null),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array{amount: ?string, currency: ?string, minor_amount: ?int, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'minor_amount' => $this->minorAmount,
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

    protected static function nullableInteger(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
