<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class JournalRetrievalQueryData extends Data
{
    public function __construct(
        public ?string $referenceNumber = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public ?string $executionId = null,
        public ?string $eventType = null,
        public int $limit = 50,
        public int $offset = 0,
        public string $order = 'asc',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            referenceNumber: self::nullableString($data['reference_number'] ?? null),
            actorType: self::nullableString($data['actor_type'] ?? null),
            actorId: self::nullableString($data['actor_id'] ?? null),
            subjectType: self::nullableString($data['subject_type'] ?? null),
            subjectId: self::nullableString($data['subject_id'] ?? null),
            correlationId: self::nullableString($data['correlation_id'] ?? null),
            causationId: self::nullableString($data['causation_id'] ?? null),
            executionId: self::nullableString($data['execution_id'] ?? null),
            eventType: self::nullableString($data['event_type'] ?? null),
            limit: self::boundedInteger($data['limit'] ?? 50, 1, 200, 50),
            offset: self::boundedInteger($data['offset'] ?? 0, 0, PHP_INT_MAX, 0),
            order: self::orderValue($data['order'] ?? 'asc'),
        );
    }

    /**
     * @return array{
     *     reference_number: ?string,
     *     actor_type: ?string,
     *     actor_id: ?string,
     *     subject_type: ?string,
     *     subject_id: ?string,
     *     correlation_id: ?string,
     *     causation_id: ?string,
     *     execution_id: ?string,
     *     event_type: ?string,
     *     limit: int,
     *     offset: int,
     *     order: string
     * }
     */
    public function toArray(): array
    {
        return [
            'reference_number' => $this->referenceNumber,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'execution_id' => $this->executionId,
            'event_type' => $this->eventType,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'order' => $this->order,
        ];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    protected static function boundedInteger(mixed $value, int $minimum, int $maximum, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($minimum, min($maximum, (int) $value));
    }

    protected static function orderValue(mixed $value): string
    {
        return strtolower((string) $value) === 'desc' ? 'desc' : 'asc';
    }
}
