<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class ExecutionReferenceData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public ?string $executionId = null,
        public ?string $providerReference = null,
        public ?string $externalReference = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            correlationId: self::nullableString($data['correlation_id'] ?? null),
            causationId: self::nullableString($data['causation_id'] ?? null),
            executionId: self::nullableString($data['execution_id'] ?? null),
            providerReference: self::nullableString($data['provider_reference'] ?? null),
            externalReference: self::nullableString($data['external_reference'] ?? null),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array{correlation_id: ?string, causation_id: ?string, execution_id: ?string, provider_reference: ?string, external_reference: ?string, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'execution_id' => $this->executionId,
            'provider_reference' => $this->providerReference,
            'external_reference' => $this->externalReference,
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
