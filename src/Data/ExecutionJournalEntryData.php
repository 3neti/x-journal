<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class ExecutionJournalEntryData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventType,
        public CarbonInterface $occurredAt,
        public ExecutionActorData $actor,
        public ExecutionSubjectData $subject,
        public ExecutionReferenceData $references,
        public ?string $idempotencyKey = null,
        public array $payload = [],
        public ?ExecutionMoneyData $money = null,
        public ?ExecutionIntegrityData $integrity = null,
        public array $metadata = [],
        public ?string $referenceNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventType: self::stringValue($data['event_type'] ?? 'unknown'),
            occurredAt: self::dateValue($data['occurred_at'] ?? null),
            actor: ExecutionActorData::fromArray(self::arrayValue($data['actor'] ?? [])),
            subject: ExecutionSubjectData::fromArray(self::arrayValue($data['subject'] ?? [])),
            references: ExecutionReferenceData::fromArray(self::arrayValue($data['references'] ?? [])),
            idempotencyKey: self::nullableString($data['idempotency_key'] ?? null),
            payload: self::arrayValue($data['payload'] ?? []),
            money: array_key_exists('money', $data) && is_array($data['money']) ? ExecutionMoneyData::fromArray($data['money']) : null,
            integrity: ExecutionIntegrityData::fromArray(self::arrayValue($data['integrity'] ?? [])),
            metadata: self::arrayValue($data['metadata'] ?? []),
            referenceNumber: self::nullableString($data['reference_number'] ?? null),
        );
    }

    public function withReferenceNumber(string $referenceNumber): self
    {
        return new self(
            eventType: $this->eventType,
            occurredAt: $this->occurredAt,
            actor: $this->actor,
            subject: $this->subject,
            references: $this->references,
            idempotencyKey: $this->idempotencyKey,
            payload: $this->payload,
            money: $this->money,
            integrity: $this->integrity,
            metadata: $this->metadata,
            referenceNumber: $referenceNumber,
        );
    }

    public function withOccurredAt(CarbonInterface $occurredAt): self
    {
        return new self(
            eventType: $this->eventType,
            occurredAt: $occurredAt,
            actor: $this->actor,
            subject: $this->subject,
            references: $this->references,
            idempotencyKey: $this->idempotencyKey,
            payload: $this->payload,
            money: $this->money,
            integrity: $this->integrity,
            metadata: $this->metadata,
            referenceNumber: $this->referenceNumber,
        );
    }

    public function withIdempotencyKey(?string $idempotencyKey): self
    {
        return new self(
            eventType: $this->eventType,
            occurredAt: $this->occurredAt,
            actor: $this->actor,
            subject: $this->subject,
            references: $this->references,
            idempotencyKey: $idempotencyKey,
            payload: $this->payload,
            money: $this->money,
            integrity: $this->integrity,
            metadata: $this->metadata,
            referenceNumber: $this->referenceNumber,
        );
    }

    /**
     * @return array{
     *     reference_number: ?string,
     *     event_type: string,
     *     occurred_at: string,
     *     actor: array<string, mixed>,
     *     subject: array<string, mixed>,
     *     idempotency_key: ?string,
     *     money: ?array<string, mixed>,
     *     references: array<string, mixed>,
     *     payload: array<string, mixed>,
     *     integrity: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'reference_number' => $this->referenceNumber,
            'event_type' => $this->eventType,
            'occurred_at' => $this->occurredAt->toJSON(),
            'actor' => $this->actor->toArray(),
            'subject' => $this->subject->toArray(),
            'money' => $this->money?->toArray(),
            'references' => $this->references->toArray(),
            'idempotency_key' => $this->idempotencyKey,
            'payload' => $this->payload,
            'integrity' => ($this->integrity ?? new ExecutionIntegrityData)->toArray(),
            'metadata' => $this->metadata,
        ];
    }

    protected static function dateValue(mixed $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::now();
    }

    protected static function stringValue(mixed $value): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return 'unknown';
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
