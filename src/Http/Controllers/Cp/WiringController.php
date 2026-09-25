<?php

namespace Goldnead\Accounts\Http\Controllers\Cp;

use Goldnead\Accounts\Support\Wiring;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Http\Controllers\CP\CpController;

/**
 * "What is connected": events, their mail templates, the automations and
 * webhooks listening, the sibling addons present, the export contributors.
 */
class WiringController extends CpController
{
    public function index(Wiring $wiring): Response
    {
        $this->authorize('view accounts');

        return Inertia::render('accounts::Wiring', array_merge($wiring->toArray(), [
            'indexUrl' => cp_route('accounts.index'),
            't' => trans('accounts::cp'),
        ]));
    }
}
