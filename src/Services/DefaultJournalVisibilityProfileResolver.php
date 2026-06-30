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
        if (! is_array($eventProfiles) || ! array_key_exists($eventType, $eventProfiles) || ! is_array($eventProfiles[$eventType])) {
            return null;
        }

        $policy = $eventProfiles[$eventType];
        $roleMappings = $policy['roles'] ?? [];
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
