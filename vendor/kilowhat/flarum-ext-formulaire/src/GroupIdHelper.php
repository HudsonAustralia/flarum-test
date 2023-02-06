<?php

namespace Kilowhat\Formulaire;

use Flarum\User\User;
use Illuminate\Support\Arr;

/**
 * We can't re-use the logic from core so this code is based on User::permissions()
 */
trait GroupIdHelper
{
    protected static $groupIds = [];

    /**
     * Part of the app uses ID as string, but in the database it's always an int
     * To make checks more reliable, we will ensure everything is cast to int before comparison
     * @param array $ids
     * @return array
     */
    public static function castArrayOfInt(array $ids): array
    {
        return array_map(function ($id) {
            return (int)$id;
        }, $ids);
    }

    public static function userGroupIds(User $user): array
    {
        if (!array_key_exists($user->id, self::$groupIds)) {
            // There is unfortunately no method to get the prepared list of group IDs in Flarum
            // To work around that, we extract the group IDs from the whereIn statement used in User::permissions()
            self::$groupIds[$user->id] = self::castArrayOfInt(
                Arr::get($user->permissions()->getQuery()->bindings, 'where', [])
            );
        }

        return self::$groupIds[$user->id];
    }

    public static function userIsInOneOfTheGroups(User $user, array $groupIdsToCheck): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return count(array_intersect(self::userGroupIds($user), self::castArrayOfInt($groupIdsToCheck))) > 0;
    }
}
