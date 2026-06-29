<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

class JournalEventData extends Data
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
        public array $payload = [],
        public ?ExecutionMoneyData $money = null,
        public array $metadata = [],
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
            payload: self::arrayValue($data['payload'] ?? []),
            money: array_key_exists('money', $data) && is_array($data['money']) ? ExecutionMoneyData::fromArray($data['money']) : null,
            metadata: self::arrayValue($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
}
