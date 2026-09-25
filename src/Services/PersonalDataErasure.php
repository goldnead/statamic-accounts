<?php

namespace Goldnead\Accounts\Services;

use Goldnead\Accounts\PersonalData\Contributors\PaymentsContributor;
use Goldnead\Accounts\PersonalData\ErasureRegistry;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Illuminate\Support\Facades\DB;
use Statamic\Auth\User;

/**
 * Deleting what the suite holds about a person: the counterpart of
 * {@see PersonalDataExport}.
 *
 * {@see blockers()} answers whether the account may go (a running
 * subscription, a team the person holds alone with other members);
 * {@see erase()} runs every eraser in one transaction and returns what each
 * deleted, anonymised and kept.
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
     * Everything that stands in the way, as sentences for the customer.
     *
     * With the `cancel` policy, running subscriptions are cancelled first
     * through payments; only one that could not be cancelled remains a
     * blocker.
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        $erasers = $this->registry->available();
        $blockers = [];

        if ($this->subscriptionPolicy() === self::POLICY_CANCEL && ($erasers['payments'] ?? null) instanceof PaymentsContributor) {
            $blockers = $erasers['payments']->cancelRunning($user);
        }

        foreach ($erasers as $eraser) {
            $blockers = array_merge($blockers, $eraser->blockers($user));
        }

        return array_values(array_unique($blockers));
    }

    /**
     * Run every eraser. One transaction: a failing eraser rolls back the
     * others and the account stays as it was.
     *
     * @return array<string, ErasureResult>
     */
    public function erase(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $results = [];

            foreach ($this->registry->available() as $key => $eraser) {
                $results[$key] = $eraser->erase($user);
            }

            return $results;
        });
    }
}
