<?php

namespace Goldnead\Accounts\Facades;

use Goldnead\Accounts\AccountsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goldnead\Accounts\Services\EmailVerification verification()
 * @method static \Goldnead\Accounts\Services\EmailChange emailChange()
 * @method static \Goldnead\Accounts\Services\AccountDeletion deletion()
 * @method static \Goldnead\Accounts\Services\PersonalDataExport export()
 * @method static \Goldnead\Accounts\Services\CustomerOverview overview()
 * @method static \Goldnead\Accounts\Services\Impersonation impersonation()
 * @method static \Goldnead\Accounts\PersonalData\PersonalDataRegistry personalData()
 * @method static AccountsManager contributeData(\Goldnead\Accounts\Contracts\ContributesPersonalData|string $contributor)
 *
 * @see AccountsManager
 */
class Accounts extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AccountsManager::class;
    }
}
