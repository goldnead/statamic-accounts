<?php

namespace Goldnead\Accounts\PersonalData;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Every contributor to the personal data export, registered or tagged.
 */
class PersonalDataRegistry
{
    public const TAG = 'accounts.personal-data';

    /** @var array<string, ContributesPersonalData|class-string<ContributesPersonalData>> */
    protected array $contributors = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  ContributesPersonalData|class-string<ContributesPersonalData>  $contributor
     */
    public function register(ContributesPersonalData|string $contributor): static
    {
        $key = is_string($contributor) ? $contributor : $contributor::class;

        $this->contributors[$key] = $contributor;

        return $this;
    }

    /**
     * All contributors, available or not, keyed by their `key()`.
     *
     * @return array<string, ContributesPersonalData>
     */
    public function all(): array
    {
        $resolved = [];

        $candidates = array_merge(
            array_values($this->contributors),
            iterator_to_array($this->container->tagged(self::TAG), false),
        );

        foreach ($candidates as $candidate) {
            $instance = is_string($candidate) ? $this->container->make($candidate) : $candidate;

            if (! $instance instanceof ContributesPersonalData) {
                throw new InvalidArgumentException(sprintf('[%s] does not implement %s.', is_object($instance) ? $instance::class : (string) $candidate, ContributesPersonalData::class));
            }

            $resolved[$instance->key()] = $instance;
        }

        return $resolved;
    }

    /**
     * @return array<string, ContributesPersonalData>
     */
    public function available(): array
    {
        return array_filter($this->all(), fn (ContributesPersonalData $contributor) => $contributor->available());
    }
}
