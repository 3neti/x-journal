<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class CockpitJournalQueryData extends Data
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public JournalAccessActorData $actor,
        public JournalRetrievalQueryData $query,
        public array $context = [],
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            actor: JournalAccessActorData::fromArray(self::arrayValue($data['actor'] ?? $data['access_actor'] ?? [])),
            query: JournalRetrievalQueryData::fromArray(self::arrayValue($data['query'] ?? $data['filters'] ?? [])),
            context: self::arrayValue($data['context'] ?? []),
            metadata: array_replace([
                'source' => 'cockpit',
                'integration' => 'cockpit.journal',
            ], self::arrayValue($data['metadata'] ?? [])),
        );
    }

    /**
     * @return array{
     *     actor: array<string, mixed>,
     *     query: array<string, mixed>,
     *     context: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'actor' => $this->actor->toArray(),
            'query' => $this->query->toArray(),
            'context' => $this->context,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
