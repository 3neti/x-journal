<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

class JournalAccessActorData extends Data
{
    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $id = null,
        public ?string $type = null,
        public array $roles = [],
        public array $permissions = [],
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::nullableString($data['id'] ?? null),
            type: self::nullableString($data['type'] ?? null),
            roles: self::stringList($data['roles'] ?? []),
            permissions: self::stringList($data['permissions'] ?? []),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $item): ?string => is_scalar($item) && trim((string) $item) !== '' ? (string) $item : null,
                $value
            )
        ));
    }
}
