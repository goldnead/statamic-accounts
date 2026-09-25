<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\PersonalData\Contributors\PaymentsContributor;
use Goldnead\Accounts\PersonalData\ErasureRegistry;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Statamic\Auth\User;

/**
 * Deleting what the suite holds about a person: the counterpart of
 * {@see PersonalDataExport}.
 *
 * {@see blockers()} answers whether the account may go; {@see erase()} runs
 * every eraser and returns what each deleted, anonymised and kept. The
 * caller ({@see AccountDeletion}) wraps erase, the `AccountDeleting` event
 * and the user's deletion in one transaction.
 */
class PersonalDataErasure
{
    public const POLICY_BLOCK = 'block';

    public const POLICY_CANCEL = 'cancel';

    public function __construct(protected ErasureRegistry $registry) {}

    public function subscriptionPolicy(): string
    {
        return config('accounts.deletion.active_subscriptions') === self::POLICY_CANCEL
            ? self::POLICY_CANCEL
            : self::POLICY_BLOCK;
    }

    /**
     * Everything that stands in the way, as sentences. Never changes
     * anything.
     *
     * With the `cancel` policy a running subscription is not a blocker: it
     * will be cancelled right before the erasure ({@see cancelSubscriptions()}),
     * and not a moment earlier, so a request that is withdrawn or blocked by
     * something else leaves the subscription running.
     *
     * @return list<string>
     */
    public function blockers(User $user, string $audience = 'customer'): array
    {
        $blockers = [];

        foreach ($this->registry->available() as $key => $eraser) {
            if ($key === 'payments' && $this->subscriptionPolicy() === self::POLICY_CANCEL) {
                continue;
            }

            $blockers = array_merge($blockers, $eraser->blockers($user, $audience));
        }

        return array_values(array_unique($blockers));
    }

    /**
     * With the `cancel` policy, cancel every running subscription through
     * payments. Returns what could not be cancelled, as blockers. A no-op
     * with the `block` policy or without payments.
     *
     * @return list<string>
     */
    public function cancelSubscriptions(User $user): array
    {
        if ($this->subscriptionPolicy() !== self::POLICY_CANCEL) {
            return [];
        }

        $payments = $this->registry->available()['payments'] ?? null;

        return $payments instanceof PaymentsContributor ? $payments->cancelRunning($user) : [];
    }

    /**
     * Run every eraser. Call inside a transaction.
     *
     * @return array<string, ErasureResult>
     */
    public function erase(User $user): array
    {
        $results = [];

        foreach ($this->registry->available() as $key => $eraser) {
            $results[$key] = $eraser->erase($user);
        }

        return $results;
    }
}
