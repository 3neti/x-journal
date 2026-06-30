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
        public JournalVisibilityProfileData $visibilityProfile,
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
            visibilityProfile: JournalVisibilityProfileData::fromArray(self::visibilityProfileValue($data['visibility_profile'] ?? $data['presentation'] ?? $data['profile'] ?? [])),
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
     *     visibility_profile: array<string, mixed>,
     *     context: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'actor' => $this->actor->toArray(),
            'query' => $this->query->toArray(),
            'visibility_profile' => $this->visibilityProfile->toArray(),
            'context' => $this->context,
            'metadata' => $this->metadata,
        ];
    }

    protected static function visibilityProfileValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_scalar($value) && trim((string) $value) !== '') {
            return ['name' => (string) $value];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
