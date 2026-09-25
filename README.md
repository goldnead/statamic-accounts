<!-- statamic:hide -->

# Statamic Accounts

> The account features Statamic leaves to you: email verification, changing the address, deleting the account with a grace period, a personal data export, and a customer overview in the Control Panel.

<!-- /statamic:hide -->

Login, registration, the profile and password forms, password reset, two-factor
authentication, passkeys, OAuth, elevated sessions and impersonation are Statamic's
own. This addon does not replace any of them. It adds what core does not have:

- **Email verification** for frontend users: a signed link, a "send again" form, and
  the `accounts.verified` middleware that holds unconfirmed users back.
- **Changing the address** with confirmation: the new address is active only after
  its link is opened; the old one is told.
- **Deleting the account** with a grace period (14 days by default). The customer can
  withdraw until then; a daily run deletes the account **and what every addon holds
  about the person** (`ErasesPersonalData`), keeps what the law says must be kept
  (payments, invoices), and records which was which.
- **Personal data export** (GDPR Art. 15/20): a ZIP with one JSON file per addon.
  Every addon contributes its share through `ContributesPersonalData`.
- **Customer overview** in the Control Panel: payments, subscriptions, access grants,
  teams and history of one person on one screen, with "Sign in as" (core's
  impersonation, recorded).
- **Wiring**: a Control Panel screen that shows every event, its mail template and
  how many automations and webhooks listen to it.

Works with Statamic's file users and with Eloquent users (integer or uuid ids).

## Requirements

| | |
|---|---|
| Statamic | 6.0+ |
| PHP | 8.2+ |
| Laravel | 12.40+ or 13 |
| Database | yes, one table (`account_requests`) |

## Installation

```
composer require goldnead/statamic-accounts
php artisan migrate
```

The purge is scheduled daily at 03:40 through Statamic's addon scheduler; make sure
`php artisan schedule:run` runs on the server.

To edit the mails in the Control Panel, install `goldnead/statamic-email-templates`
and run `php please email-templates:import`: the five default mails become entries.

## Frontend

### Tags

Shaped like Statamic's `user:*` tags. Form tags render `<form>` with CSRF around their
contents and hand them `success`, `errors` (list) and `error` (by field). `redirect`
sets where to go after submitting (a path on the site).

```antlers
{{# Only renders for a signed-in user whose address is not confirmed. #}}
{{ accounts:verify_notice }}
    {{ if success }}<p>{{ success }}</p>{{ /if }}
    <p>Please confirm {{ email }}.</p>
    <button>Send the link again</button>
{{ /accounts:verify_notice }}

{{ accounts:change_email_form redirect="/account" }}
    {{ if pending_email }}<p>Waiting for {{ pending_email }} until {{ pending_expires }}.</p>{{ /if }}
    <input type="email" name="email">
    {{ error:email }}
    <button {{ if locked }}disabled{{ /if }}>Change address</button>
{{ /accounts:change_email_form }}

{{ accounts:delete_form }}
    {{ if pending }}
        <p>Your account will be deleted on {{ scheduled_for }}.</p>
        <button>Keep my account</button>
    {{ elseif blockers }}
        <ul>{{ blockers }}<li>{{ value }}</li>{{ /blockers }}</ul>
    {{ else }}
        {{ error:account }}
        <button>Delete my account in {{ grace_days }} days</button>
    {{ /if }}
{{ /accounts:delete_form }}

<a href="{{ accounts:export_url }}">Download my data</a>

{{# The outcome of a link from a mail: confirmed, expired, withdrawn. #}}
{{ accounts:status }}<p class="{{ kind }}">{{ message }}</p>{{ /accounts:status }}

{{ accounts:impersonating }}
    <p>{{ impersonator }} is signed in as you. <a href="{{ stop_url }}">Stop</a></p>
{{ /accounts:impersonating }}

{{ if {accounts:verified} }}…{{ /if }}
```

| Tag | Variables |
|---|---|
| `accounts:verify_notice` | `email`, `success`, `errors`, `error` |
| `accounts:change_email_form` | `email`, `pending_email`, `pending_expires`, `elevated`, `locked`, `cancel_url`, `success`, `errors`, `error`, `old` |
| `accounts:delete_form` | `pending`, `scheduled_for`, `grace_days`, `blockers`, `elevated`, `locked`, `success`, `errors`, `error` |
| `accounts:export_url` | the URL |
| `accounts:status` | `kind` (`success`/`error`), `message` |
| `accounts:impersonating` | `stop_url`, `impersonator` |
| `accounts:verified` | bool |

**Confirmation is Statamic's elevated session.** Changing the address, deleting and
downloading the data need one (`statamic.users.elevated_sessions_enabled`). Without it
the visitor is sent to core's confirmation page (password, passkey or mailed code,
whatever the account has) and comes back to where they were; the download starts on
its own. `elevated` tells a template whether the session already is. With elevated
sessions switched off in Statamic there is no second confirmation, as for core's own
sensitive actions.

**While an admin is signed in as the customer** (`locked`), all three answer 403: they
are the person's own decisions. The services refuse too (`AccountException` with
`field = impersonation`), so an API layer cannot skip it.

### Middleware

```php
Route::middleware(['web', 'auth', 'accounts.verified'])->group(…);
```

A signed-in user without a confirmed address goes to `accounts.verification.notice_url`;
a JSON request gets 403. Guests pass (that is what `auth` is for).

### Routes

All under `/!/statamic-accounts/`, named `statamic.accounts.*`. The GET links from the
mails are Laravel temporary signed URLs: a changed parameter or an expired link is
refused with 403, before any code of this addon runs.

## Events

Every change of state is a Laravel event with a serialisable payload: ids and
addresses, never a link or a token.

| Event | Trigger handle | Payload beyond `user_id`, `email`, `name` | Mail |
|---|---|---|---|
| `EmailVerificationSent` | `accounts.verification.sent` | | `accounts-verify-email` |
| `EmailVerified` | `accounts.email.verified` | | |
| `EmailChangeRequested` | `accounts.email_change.requested` | `new_email` | `accounts-confirm-email-change` |
| `EmailChanged` | `accounts.email.changed` | `old_email` (`email` is the new one) | `accounts-email-changed` (to the old address) |
| `AccountDeletionRequested` | `accounts.deletion.requested` | `scheduled_for` | `accounts-deletion-scheduled` |
| `AccountDeletionCancelled` | `accounts.deletion.cancelled` | | |
| `AccountDeleting` | (hook, no trigger) | | |
| `AccountDeleted` | `accounts.deleted` | | `accounts-account-deleted` |
| `PersonalDataExported` | `accounts.data.exported` | `sections`, `requested_by` | |

All in `Goldnead\Accounts\Events`. `AccountDeleting` is dispatched synchronously while
the user still exists, for addons that must clean up (cancel a subscription, anonymise
a contact). A listener that throws keeps that one account scheduled; the next run
tries again.

With `goldnead/statamic-automations` installed, every event is an automation trigger
(group "Accounts", context under `account.*`). With `goldnead/statamic-webhook-manager`,
every event is a webhook trigger. Both register through the siblings' own
`registerEventTrigger()`.

## One confirmation mail, not two

email-templates can also send Laravel's own `VerifyEmail` (template `core-verify-email`).
To keep a site from sending both, `accounts.verification.mail` picks one line:

- `auto` (default): an Eloquent user model that implements Laravel's `MustVerifyEmail`,
  on a site with Laravel's `verification.verify` route, gets **Laravel's** notification
  (`$model->sendEmailVerificationNotification()`); its link goes to the site's own
  route, and Laravel's `Verified` event is turned into this addon's `EmailVerified`
  with the same ledger entry. Everyone else, and every Statamic file user (they never
  implement `MustVerifyEmail`), gets **this addon's** mail `accounts-verify-email`.
- `accounts` / `laravel`: force one.

Statamic's own registration form does not fire Laravel's `Registered` event, so on a
Statamic-only site nothing else sends a confirmation. A site that fires `Registered`
itself (ChoirLive's API does) and also uses `auto` sends exactly one: Laravel's.

## Mails

Each mail is a template slug in `goldnead/statamic-email-templates`
(`accounts.mail.templates.*`), announced to its registry (`email-templates.registry`)
with the occasion ("Accounts: …"), the event, every placeholder with an example, and
the shipped text, so the template screen explains itself and `email-templates:import`
writes the defaults. A slug without an entry, or a site without that addon,
sends the default text shipped in `lang/{de,en}/mail.php`. Placeholders:
`{{ user.name }}`, `{{ user.email }}`, `{{ action_url }}`, `{{ new_email }}`,
`{{ old_email }}`, `{{ scheduled_for }}`, `{{ grace_days }}`, `{{ expires_in_hours }}`,
`{{ site_name }}`. Mails are sent, not queued.

## Deleting: what goes, what stays

When a deletion is due, `accounts:purge` asks every eraser whether anything stands in
the way, dispatches `AccountDeleting`, runs all erasers in one database transaction,
deletes the user and stores the result on the deletion request (row counts only, no
values). A failing eraser rolls the others back; the account stays scheduled.

| Addon | On deletion |
|---|---|
| accounts | Address changes deleted; the deletion request stays as the record, address blacked out |
| activity | `activity:anonymize --user=<id>` (the ledger's own API): entries under the user id keep type and time, lose user, actor, properties, context. Entries recorded under *another* user id that mention the person are beyond that API and stay; the record says so |
| entitlements | Grants held by the user and by the address deleted; team grants stay with the team |
| leadhub | Contact deleted with events, notes, follow-ups, tasks, revenue lines (database driver) |
| notifications | Notifications, preferences, digest runs deleted |
| teams | Memberships removed; a team held alone without other members is deleted with its invitations and roles; an ownership shared with another owner passes to them; the person's id comes off invitations they sent |
| payments | **Kept**, untouched: accounting records, ten years (§ 147 AO, § 14b UStG) |
| invoices | **Kept**, untouched: tax documents, ten years |

**What blocks a deletion** (at the request and again when it is due):

- A subscription that still charges (`pending`, `active`, `paused`, `suspended`).
  `deletion.active_subscriptions = block` (default) refuses with a link to the customer
  portal; `cancel` cancels it through payments' own `Subscriptions::cancel()` when the
  deletion is requested, and blocks only if that fails.
- Being the only owner of a team that has other members. Transfer the ownership first.

```php
use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;

class CourseProgressEraser implements ErasesPersonalData
{
    public function key(): string { return 'courses'; }
    public function label(): string { return 'Courses'; }
    public function available(): bool { return true; }
    public function blockers(User $user): array { return []; }   // sentences for the customer
    public function erase(User $user): ErasureResult
    {
        $n = Progress::where('user_id', $user->id())->delete();

        return new ErasureResult('courses', deleted: ['progress' => $n]);
    }
}

Accounts::eraseData(CourseProgressEraser::class);
// or: $this->app->tag([CourseProgressEraser::class], 'accounts.personal-data-erasers');
```

## Personal data export

```php
use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Facades\Accounts;
use Statamic\Auth\User;

class CourseProgress implements ContributesPersonalData
{
    public function key(): string { return 'courses'; }          // courses.json
    public function label(): string { return 'Courses'; }
    public function available(): bool { return true; }
    public function collect(User $user): array { return ['progress' => …]; }
}

Accounts::contributeData(CourseProgress::class);
// or: $this->app->tag([CourseProgress::class], 'accounts.personal-data');
```

Shipped contributors, each active only when its addon's tables exist: `account`,
`payments` (payments, items, subscriptions, withdrawals, cancellations by address),
`entitlements` (grants held by the address or the user), `leadhub` (contact by address
or user id, with events, notes, follow-ups, revenue; database driver only),
`notifications` (items, preferences, digests), `teams` (memberships), `invoices`
(invoices and lines by buyer address), `activity` (ledger entries under the user id). They read the
siblings' tables directly, across all brands, and leave out password hashes, tokens and
IP hashes. A contributor registered later under the same key replaces the shipped one.

`manifest.json` in the ZIP names the sections and any contributor that failed. Without
`ext-zip` the export is one JSON file.

## Control Panel

- **Accounts → Customers**: every account with its confirmation state and a scheduled
  deletion. Also reachable from core's Users listing (row action "Customer overview").
- **Customer overview**: account, payments, subscriptions, access grants, teams and the
  latest activity entries. Actions: send the confirmation link, mark as confirmed,
  schedule or withdraw deletion, export data, sign in as.
- **Accounts → Wiring**: events, their mail template (own entry or shipped text),
  the number of automations and webhooks listening, which sibling addons are
  installed, and the export contributors.

### Permissions

| Permission | Allows |
|---|---|
| `view accounts` | the list, the overview, the wiring screen |
| `manage accounts` | send the confirmation link, mark as confirmed, withdraw a deletion |
| `delete users` (core) | schedule a deletion; a super admin only by a super admin |
| `export account data` | the export from the Control Panel |
| `manage accounts settings` | the settings screen (with brand-context) |
| `impersonate users` (core) | "Sign in as" |

### Impersonation

"Sign in as" runs core's `Statamic\Actions\Impersonate`: the same permission, the same
elevated session, the same "Stop impersonating". This addon adds two things. Every start
and end, from the overview or from core's user listing, goes into
`goldnead/statamic-activity` (or the application log without it). And while it lasts,
`goldnead/statamic-identity-contracts` reports the customer as the actor with
`meta.impersonated_by` set to the admin, so every addon that records an actor shows
who really acted.

## Configuration

`config/accounts.php`, publish with `php artisan vendor:publish --tag=accounts-config`.

| Key | Default | |
|---|---|---|
| `verification.enabled` | `true` | Off: the middleware lets everyone through, the notice tag renders nothing |
| `verification.field` | `email_verified_at` | Where the moment of confirmation is stored on the user |
| `verification.send_on_register` | `true` | Send after core's registration form |
| `verification.expire_minutes` | `1440` | Link lifetime |
| `verification.notice_url` | `/` | Where the middleware sends unconfirmed users |
| `verification.redirect` | `/` | Where the link lands |
| `email_change.expire_minutes` | `1440` | |
| `email_change.notify_old_address` | `true` | |
| `email_change.redirect` | `/` | |
| `deletion.grace_days` | `14` | At least 1 |
| `deletion.active_subscriptions` | `block` | `block` or `cancel`, see above |
| `deletion.portal_url` | `''` | Where the subscription blocker links; empty: payments' portal |
| `deletion.logout` | `false` | Sign out after requesting |
| `deletion.redirect` | `/` | Where the withdraw link lands |
| `export.enabled` | `true` | Off: the customer's export answers 404 (the CP export stays) |
| `export.throttle` | `3,60` | |
| `mail.templates.*` | `accounts-…` | Slugs in email-templates |
| `impersonation.redirect` | `/` | Landing page for a customer without CP access |
| `integrations.automations` / `webhook_manager` / `activity` | `true` | Read while booting |
| `subject_types` | — | Extra `subject_type` values under which entitlements stores users |

With `goldnead/statamic-brand-context`, the grace period, link lifetimes, notice page,
switches and template slugs are editable per brand under Settings.

## PHP API

Everything the tags and the Control Panel do goes through services another addon can
call directly (an API layer, a site's own controller). All take a `Statamic\Auth\User`.

```php
use Goldnead\Accounts\Facades\Accounts;

Accounts::verification()->isVerified($user);            // bool
Accounts::verification()->send($user);                  // bool, false when already confirmed
Accounts::verification()->url($user);                   // signed link
Accounts::verification()->verify($userId, $hash);       // ?User; checks the address hash
Accounts::verification()->markVerified($user, $by);

Accounts::emailChange()->request($user, 'new@example.com');   // AccountRequest; throws AccountException
Accounts::emailChange()->pending($user);                      // ?AccountRequest
Accounts::emailChange()->confirm($requestId, $hash);          // User; throws AccountException
Accounts::emailChange()->cancel($user);                       // bool

Accounts::deletion()->blockers($user);        // list<string>; with the cancel policy, cancels first
Accounts::deletion()->request($user, $by);    // AccountRequest (idempotent while pending); throws AccountException when blocked or impersonating
Accounts::deletion()->pending($user);         // ?AccountRequest, ->due_at
Accounts::deletion()->cancel($user, $by);     // bool
Accounts::deletion()->graceDays();            // int
Accounts::deletion()->purgeDue();             // int, what `accounts:purge` runs

Accounts::export()->collect($user);           // ['manifest' => …, 'sections' => [key => array]]
Accounts::export()->build($user, 'customer'); // ['path', 'filename', 'mime'], caller deletes the file

Accounts::overview()->for($user);             // the customer overview as arrays
Accounts::impersonation()->allowed($admin, $user);
Accounts::impersonation()->start($admin, $user);   // redirect URL; throws AuthorizationException
Accounts::contributeData(MyContributor::class);
Accounts::eraseData(MyEraser::class);
Accounts::erasure()->erase($user);            // array<key, ErasureResult>; normally only via the purge
```

`emailChange()->request()`, `deletion()->request()` and `export()->build($user, 'customer')`
refuse while an impersonation is active. They do not check the elevated session; that
is the caller's HTTP concern (this addon's controllers do it, an API layer decides for
itself).

`Goldnead\Accounts\Exceptions\AccountException` carries a customer-facing message and
the form `field` it belongs to, ready to become a 422.

## Commands

`php please accounts:purge` deletes the accounts whose grace period is over.

## License

Proprietary, part of the goldnead suite licence. See `LICENSE.md`.
