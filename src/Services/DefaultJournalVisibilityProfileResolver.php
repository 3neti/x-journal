<?php

namespace LBHurtado\XJournal\Services;

use LBHurtado\XJournal\Contracts\JournalVisibilityProfileResolverContract;
use LBHurtado\XJournal\Data\JournalAccessActorData;
use LBHurtado\XJournal\Data\JournalAccessDecisionData;
use LBHurtado\XJournal\Data\JournalVisibilityProfileData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

class DefaultJournalVisibilityProfileResolver implements JournalVisibilityProfileResolverContract
{
    public function resolve(
        ExecutionJournalEntry $entry,
        JournalAccessActorData $actor,
        JournalAccessDecisionData $decision,
        JournalVisibilityProfileData $requestedProfile,
    ): JournalVisibilityProfileData {
        if ($requestedProfile->name !== JournalVisibilityProfileData::PROFILE_RAW) {
            return $requestedProfile;
        }

        if (! $decision->allowed) {
            return $requestedProfile;
        }

        $eventBased = $this->resolveByEventProfile($entry->event_type, $actor->roles, $decision);
        if ($eventBased !== null) {
            return $eventBased;
        }

        return $this->resolveByRoleProfile($actor->roles) ?? $requestedProfile;
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function resolveByRoleProfile(array $roles): ?JournalVisibilityProfileData
    {
        $roleProfiles = config('x-journal.visibility.role_profiles', []);
        if (! is_array($roleProfiles)) {
            return null;
        }

        foreach ($roles as $role) {
            if (! is_string($role) || trim($role) === '') {
                continue;
            }

            if (! array_key_exists($role, $roleProfiles)) {
                continue;
            }

            return $this->normalizeProfile($roleProfiles[$role]);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $roles
     */
    protected function resolveByEventProfile(string $eventType, array $roles, JournalAccessDecisionData $decision): ?JournalVisibilityProfileData
    {
        $eventProfiles = config('x-journal.visibility.event_profiles', []);
        if (! is_array($eventProfiles) || ! array_key_exists($eventType, $eventProfiles)) {
            return null;
        }

        $policy = $eventProfiles[$eventType];
        $directProfile = $this->extractDirectEventProfile($policy);
        if ($directProfile !== null) {
            return $directProfile;
        }

        if (! is_array($policy)) {
            return null;
        }

        $roleMappings = $policy['roles'] ?? $policy;
        if (! is_array($roleMappings)) {
            return null;
        }

        foreach ($roles as $role) {
            if (! is_string($role) || trim($role) === '') {
                continue;
            }

            if (array_key_exists($role, $roleMappings)) {
                return $this->normalizeProfile($roleMappings[$role]);
            }
        }

        if (array_key_exists('default', $policy) && $policy['default'] !== null) {
            return $this->normalizeProfile($policy['default']);
        }

        return null;
    }

    protected function extractDirectEventProfile(mixed $policy): ?JournalVisibilityProfileData
    {
        if (is_scalar($policy)) {
            return $this->normalizeProfile($policy);
        }

        if (! is_array($policy)) {
            return null;
        }

        return $this->isProfileDefinition($policy)
            ? $this->normalizeProfile($policy)
            : null;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    protected function isProfileDefinition(array $policy): bool
    {
        $profileKeys = [
            'name',
            'include_actor',
            'include_subject',
            'include_references',
            'include_payload',
            'include_metadata',
            'redact_actor_keys',
            'redact_subject_keys',
            'redact_payload_keys',
            'redact_metadata_keys',
        ];

        return count(array_intersect(array_keys($policy), $profileKeys)) > 0;
    }

    protected function normalizeProfile(mixed $definition): JournalVisibilityProfileData
    {
        if (is_array($definition)) {
            return JournalVisibilityProfileData::fromArray($definition);
        }

        return JournalVisibilityProfileData::fromArray([
            'name' => is_scalar($definition) ? (string) $definition : JournalVisibilityProfileData::PROFILE_RAW,
        ]);
    }
}
