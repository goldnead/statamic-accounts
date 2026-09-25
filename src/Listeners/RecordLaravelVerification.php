<?php

namespace Goldnead\Accounts\Listeners;

use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Support\Users;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An address confirmed through Laravel's own `verification.verify` route
 * (the `MustVerifyEmail` line) is the same fact as one confirmed through this
 * addon's link: same `EmailVerified` event, same ledger entry.
 */
class RecordLaravelVerification
{
    public function __construct(protected EmailVerification $verification) {}

    public function handle(Verified $event): void
    {
        $model = $event->user;
        $id = $model instanceof Authenticatable ? $model->getAuthIdentifier() : null;
        $user = Users::find(is_scalar($id) ? (string) $id : null);

        if ($user !== null) {
            $this->verification->recordVerified($user);
        }
    }
}
