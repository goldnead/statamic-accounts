<?php

/**
 * Gives Larastan's view finder the `accounts::` namespace the service
 * provider registers at runtime. Without it the analysis of this package on
 * its own treats `accounts::mail.layout` as an unknown view. Same fix as in
 * statamic-email-templates (commit 2706f47).
 */

use Illuminate\Contracts\View\Factory as ViewFactory;
use Larastan\Larastan\ApplicationResolver;

$app = ApplicationResolver::resolve();

/** @var ViewFactory $views */
$views = $app->make(ViewFactory::class);

$views->getFinder()->addNamespace('accounts', __DIR__.'/resources/views');
