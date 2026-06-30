<?php

namespace LBHurtado\XJournal\Data;

use Spatie\LaravelData\Data;

final class JournalVisibilityProfileData extends Data
{
    public const string PROFILE_RAW = 'raw';
    public const string PROFILE_SUMMARY = 'summary';
    public const string PROFILE_REDACTED = 'redacted';

    public function __construct(
        public string $name = self::PROFILE_RAW,
        public bool $includeActor = true,
        public bool $includeSubject = true,
        public bool $includeReferences = true,
        public bool $includePayload = true,
        public bool $includeMetadata = true,
        public array $redactActorKeys = [],
        public array $redactSubjectKeys = [],
        public array $redactPayloadKeys = [],
        public array $redactMetadataKeys = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $requestedName = self::stringValue($data['name'] ?? $data['profile'] ?? $data['type'] ?? self::PROFILE_RAW, self::PROFILE_RAW);
        $name = self::normalizeProfileName($requestedName);
        $options = self::arrayValue($data['options'] ?? []);

        $defaults = self::defaults($name);
        $hasOverride = function (string $name) use ($data, $options): bool {
            return array_key_exists($name, $data) || array_key_exists($name, $options);
        };

        return new self(
            name: $name,
            includeActor: $hasOverride('include_actor')
                ? self::boolValue(self::arrayValueKey($data, 'include_actor', $options), $defaults['includeActor'])
                : $defaults['includeActor'],
            includeSubject: $hasOverride('include_subject')
                ? self::boolValue(self::arrayValueKey($data, 'include_subject', $options), $defaults['includeSubject'])
                : $defaults['includeSubject'],
            includeReferences: $hasOverride('include_references')
                ? self::boolValue(self::arrayValueKey($data, 'include_references', $options), $defaults['includeReferences'])
                : $defaults['includeReferences'],
            includePayload: $hasOverride('include_payload')
                ? self::boolValue(self::arrayValueKey($data, 'include_payload', $options), $defaults['includePayload'])
                : $defaults['includePayload'],
            includeMetadata: $hasOverride('include_metadata')
                ? self::boolValue(self::arrayValueKey($data, 'include_metadata', $options), $defaults['includeMetadata'])
                : $defaults['includeMetadata'],
            redactActorKeys: self::stringList(
                $hasOverride('redact_actor_keys') ? self::arrayValueKey($data, 'redact_actor_keys', $options) : $defaults['redactActorKeys'],
            ),
            redactSubjectKeys: self::stringList(
                $hasOverride('redact_subject_keys') ? self::arrayValueKey($data, 'redact_subject_keys', $options) : $defaults['redactSubjectKeys'],
            ),
            redactPayloadKeys: self::stringList(
                $hasOverride('redact_payload_keys') ? self::arrayValueKey($data, 'redact_payload_keys', $options) : $defaults['redactPayloadKeys'],
            ),
            redactMetadataKeys: self::stringList(
                $hasOverride('redact_metadata_keys') ? self::arrayValueKey($data, 'redact_metadata_keys', $options) : $defaults['redactMetadataKeys'],
            ),
        );
    }

    public function projectActor(?array $value): array
    {
        return $this->projectMap($value, $this->includeActor, $this->redactActorKeys);
    }

    public function projectSubject(?array $value): array
    {
        return $this->projectMap($value, $this->includeSubject, $this->redactSubjectKeys);
    }

    public function projectReferences(?array $value): array
    {
        return $this->projectMap($value, $this->includeReferences, []);
    }

    public function projectPayload(?array $value): array
    {
        return $this->projectMap($value, $this->includePayload, $this->redactPayloadKeys);
    }

    public function projectMetadata(?array $value): array
    {
        return $this->projectMap($value, $this->includeMetadata, $this->redactMetadataKeys);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'include_actor' => $this->includeActor,
            'include_subject' => $this->includeSubject,
            'include_references' => $this->includeReferences,
            'include_payload' => $this->includePayload,
            'include_metadata' => $this->includeMetadata,
            'redact_actor_keys' => $this->redactActorKeys,
            'redact_subject_keys' => $this->redactSubjectKeys,
            'redact_payload_keys' => $this->redactPayloadKeys,
            'redact_metadata_keys' => $this->redactMetadataKeys,
        ];
    }

    protected function projectMap(?array $value, bool $include, array $redactKeys): array
    {
        if (! $include) {
            return [];
        }

        $payload = self::arrayValue($value);
        foreach ($redactKeys as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    protected static function defaults(string $name): array
    {
        return match ($name) {
            self::PROFILE_SUMMARY => [
                'includeActor' => true,
                'includeSubject' => true,
                'includeReferences' => true,
                'includePayload' => false,
                'includeMetadata' => false,
                'redactActorKeys' => ['name'],
                'redactSubjectKeys' => ['display'],
                'redactPayloadKeys' => [],
                'redactMetadataKeys' => [],
            ],
            self::PROFILE_REDACTED => [
                'includeActor' => true,
                'includeSubject' => true,
                'includeReferences' => true,
                'includePayload' => true,
                'includeMetadata' => true,
                'redactActorKeys' => [],
                'redactSubjectKeys' => [],
                'redactPayloadKeys' => [],
                'redactMetadataKeys' => [],
            ],
            default => [
                'includeActor' => true,
                'includeSubject' => true,
                'includeReferences' => true,
                'includePayload' => true,
                'includeMetadata' => true,
                'redactActorKeys' => [],
                'redactSubjectKeys' => [],
                'redactPayloadKeys' => [],
                'redactMetadataKeys' => [],
            ],
        };
    }

    protected static function normalizeProfileName(string $name): string
    {
        $normalized = strtolower(trim($name));
        if ($normalized === '') {
            return self::PROFILE_RAW;
        }

        return in_array($normalized, [self::PROFILE_RAW, self::PROFILE_SUMMARY, self::PROFILE_REDACTED], true)
            ? $normalized
            : self::PROFILE_RAW;
    }

    protected static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected static function arrayValueKey(array $data, string $key, array $options): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return $options[$key] ?? null;
    }

    protected static function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return $fallback;
    }

    protected static function boolValue(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        return $fallback;
    }

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
