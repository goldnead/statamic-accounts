<?php

namespace Goldnead\Accounts\PersonalData;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Every eraser, registered or tagged. Keyed by `key()`, later wins, so an
 * addon's own eraser replaces the one shipped here for it.
 */
class ErasureRegistry
{
    public const TAG = 'accounts.personal-data-erasers';

    /** @var array<string, ErasesPersonalData|class-string<ErasesPersonalData>> */
    protected array $erasers = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  ErasesPersonalData|class-string<ErasesPersonalData>  $eraser
     */
    public function register(ErasesPersonalData|string $eraser): static
    {
        $this->erasers[is_string($eraser) ? $eraser : $eraser::class] = $eraser;

        return $this;
    }

    /**
     * @return array<string, ErasesPersonalData>
     */
    public function all(): array
    {
        $resolved = [];

        $candidates = array_merge(
            array_values($this->erasers),
            iterator_to_array($this->container->tagged(self::TAG), false),
        );

        foreach ($candidates as $candidate) {
            $instance = is_string($candidate) ? $this->container->make($candidate) : $candidate;

            if (! $instance instanceof ErasesPersonalData) {
                throw new InvalidArgumentException(sprintf('[%s] does not implement %s.', is_object($instance) ? $instance::class : (string) $candidate, ErasesPersonalData::class));
            }

            $resolved[$instance->key()] = $instance;
        }

        return $resolved;
    }

    /**
     * @return array<string, ErasesPersonalData>
     */
    public function available(): array
    {
        return array_filter($this->all(), fn (ErasesPersonalData $eraser) => $eraser->available());
    }
}
