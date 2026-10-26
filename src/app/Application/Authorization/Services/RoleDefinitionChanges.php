<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

/**
 * What changed in a role's definition, in the shape the role history keeps: the permission
 * keys added and removed, and the fields that changed (name, association member requirement,
 * holder limit). The display name and the description are not part of it.
 */
final readonly class RoleDefinitionChanges
{
    /** The fields of a role the history follows. */
    public const array FIELDS = ['name', 'requires_association_member', 'max_holders'];

    /**
     * @param  array<int, string>  $keysBefore
     * @param  array<int, string>  $keysAfter
     * @param  array<string, mixed>  $fieldsBefore  only the FIELDS; a missing one counts as null
     * @param  array<string, mixed>  $fieldsAfter
     * @return array{permissions_added?: list<string>, permissions_removed?: list<string>, fields?: array<string, array{from: mixed, to: mixed}>}
     */
    public function between(array $keysBefore, array $keysAfter, array $fieldsBefore = [], array $fieldsAfter = []): array
    {
        $changes = [];

        $added = $this->sorted(array_diff($keysAfter, $keysBefore));
        $removed = $this->sorted(array_diff($keysBefore, $keysAfter));

        if ($added !== []) {
            $changes['permissions_added'] = $added;
        }

        if ($removed !== []) {
            $changes['permissions_removed'] = $removed;
        }

        $fields = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $fieldsBefore) && ! array_key_exists($field, $fieldsAfter)) {
                continue;
            }

            $from = $fieldsBefore[$field] ?? null;
            $to = $fieldsAfter[$field] ?? null;

            if ($from !== $to) {
                $fields[$field] = ['from' => $from, 'to' => $to];
            }
        }

        if ($fields !== []) {
            $changes['fields'] = $fields;
        }

        return $changes;
    }

    /**
     * @param  array<int, string>  $keys
     * @return list<string>
     */
    private function sorted(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
