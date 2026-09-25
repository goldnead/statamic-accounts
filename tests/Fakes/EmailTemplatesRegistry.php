<?php

namespace Goldnead\Accounts\Tests\Fakes;

/**
 * Stands in for email-templates' `TemplateRegistry` behind the
 * `email-templates.registry` binding: `register(array)` as the README of
 * statamic-email-templates (fc0df26) documents it, plus the two readers this
 * addon uses. Definitions stay arrays; closures are called by the tests.
 */
class EmailTemplatesRegistry
{
    /** @var array<string, array<string, mixed>> */
    public array $definitions = [];

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function register(array $definition): array
    {
        return $this->definitions[$definition['slug']] = $definition;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return $this->definitions[$slug] ?? null;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function byAddon(): array
    {
        $groups = [];

        foreach ($this->definitions as $definition) {
            $addon = $definition['addon'] ?? 'Other';
            $groups[$addon instanceof \Closure ? $addon() : $addon][] = $definition;
        }

        return $groups;
    }
}
