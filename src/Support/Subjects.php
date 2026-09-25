<?php

namespace Goldnead\Accounts\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * The `subject_type` values under which siblings store a user.
 *
 * Same derivation as statamic-courses: the Eloquent user model's morph class
 * when users live in the database, `user` otherwise, plus whatever a site
 * configured. Payments stores grants under `email` instead; that pair is
 * matched separately.
 */
class Subjects
{
    /**
     * @return list<string>
     */
    public static function userTypes(): array
    {
        $types = ['user'];

        foreach (['courses.entitlements.subject_type', 'accounts.subject_types'] as $key) {
            foreach ((array) config($key, []) as $configured) {
                if (is_string($configured) && $configured !== '') {
                    $types[] = $configured;
                }
            }
        }

        $class = config('auth.providers.users.model');

        if (is_string($class) && class_exists($class) && is_subclass_of($class, Model::class)) {
            $types[] = (new $class)->getMorphClass();
            $types[] = $class;
        }

        return array_values(array_unique($types));
    }
}
