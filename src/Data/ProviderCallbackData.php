<?php

namespace LBHurtado\XJournal\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class ProviderCallbackData extends Data
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
        public array $payload,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventType: self::stringValue($data['event_type'] ?? 'provider.callback.received', 'provider.callback.received'),
            occurredAt: self::dateValue($data['occurred_at'] ?? null),
            actor: ExecutionActorData::fromArray(self::arrayValue($data['actor'] ?? [
                'type' => 'provider',
                'name' => self::nullableString($data['provider'] ?? null),
            ])),
            subject: ExecutionSubjectData::fromArray(self::arrayValue($data['subject'] ?? [])),
            references: ExecutionReferenceData::fromArray(self::arrayValue($data['references'] ?? [])),
            payload: self::payload($data),
            metadata: array_replace([
                'source' => 'provider_callback',
                'integration' => 'provider.callback',
            ], self::arrayValue($data['metadata'] ?? [])),
        );
    }

    public function toJournalEvent(): JournalEventData
    {
        return new JournalEventData(
            eventType: $this->eventType,
            occurredAt: $this->occurredAt,
            actor: $this->actor,
            subject: $this->subject,
            references: $this->references,
            payload: $this->payload,
            metadata: $this->metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function payload(array $data): array
    {
        return array_replace([
            'provider' => self::nullableString($data['provider'] ?? null),
            'provider_reference' => self::nullableString($data['provider_reference'] ?? null),
            'raw_status' => self::nullableString($data['raw_status'] ?? null),
            'received_payload' => self::arrayValue($data['received_payload'] ?? []),
        ], self::arrayValue($data['payload'] ?? []));
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

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    protected static function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return $fallback;
    }
}
