<?php

namespace Goldnead\Accounts;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureRegistry;
use Goldnead\Accounts\PersonalData\PersonalDataRegistry;
use Goldnead\Accounts\Services\AccountDeletion;
use Goldnead\Accounts\Services\CustomerOverview;
use Goldnead\Accounts\Services\EmailChange;
use Goldnead\Accounts\Services\EmailVerification;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Services\PersonalDataErasure;
use Goldnead\Accounts\Services\PersonalDataExport;

/**
 * The public entry point, behind the `Accounts` facade.
 *
 * Every operation lives in a service that another addon (an API layer, a
 * site's own controller) can call directly; the Antlers tags and the
 * Control Panel are callers of the same services.
 */
class AccountsManager
{
    public function verification(): EmailVerification
    {
        return app(EmailVerification::class);
    }

    public function emailChange(): EmailChange
    {
        return app(EmailChange::class);
    }

    public function deletion(): AccountDeletion
    {
        return app(AccountDeletion::class);
    }

    public function export(): PersonalDataExport
    {
        return app(PersonalDataExport::class);
    }

    public function overview(): CustomerOverview
    {
        return app(CustomerOverview::class);
    }

    public function impersonation(): Impersonation
    {
        return app(Impersonation::class);
    }

    public function personalData(): PersonalDataRegistry
    {
        return app(PersonalDataRegistry::class);
    }

    /**
     * @param  ContributesPersonalData|class-string<ContributesPersonalData>  $contributor
     */
    public function contributeData(ContributesPersonalData|string $contributor): static
    {
        $this->personalData()->register($contributor);

        return $this;
    }

    public function erasure(): PersonalDataErasure
    {
        return app(PersonalDataErasure::class);
    }

    public function erasers(): ErasureRegistry
    {
        return app(ErasureRegistry::class);
    }

    /**
     * @param  ErasesPersonalData|class-string<ErasesPersonalData>  $eraser
     */
    public function eraseData(ErasesPersonalData|string $eraser): static
    {
        $this->erasers()->register($eraser);

        return $this;
    }
}
