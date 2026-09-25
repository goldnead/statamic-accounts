# Changelog

## 0.2.0 — 2026-09-25

Findings from the ChoirLive end-to-end check.

### Added

- **"Your password was changed"** (`password_changed`, slug `accounts-password-changed`, in the
  email-templates registry, editable in the CP). Sent to the account's address after any change
  of the password: Statamic's profile form (which fires no event of its own), the Control Panel,
  a reset link, the host's own code. Watched at `UserSaving`/`UserSaved` (Eloquent: the model's
  dirty state; file users: the hash stored on disk). New accounts are not told. New config key
  `password_change.notify` (default `true`), also on the settings screen.
- Event `PasswordChanged` (`accounts.password.changed`): a trigger in automations and
  webhook-manager and a row on the Wiring screen. Payload `user_id`, `email`, `name`, never the
  password. Ledger entry `accounts.password_changed`.
- **Change of address, told before it happens:** `email_change_requested`
  (slug `accounts-email-change-requested`) goes to the current address as soon as a new one is
  entered. Until now the old address heard of it only after the new one was confirmed, which is
  too late when a taken-over session moves the account. Same switch as before,
  `email_change.notify_old_address`.

### Checked

- The notice after confirmation (`email_changed`) was sent all along, for file and Eloquent users
  (new test `the_old_address_hears_of_the_change_with_eloquent_users`).

### Upgrading

- No migration. Two new template slugs; sites that import templates run
  `php please email-templates:import` again to get them as editable entries.

## 0.1.1 — 2026-09-25

- `export.throttle` is applied: the customer's download is limited per person
  (`max,minutes`, default 3 per 60 minutes), read per request through the named rate
  limiter `accounts-export`. Before, the setting was not read and the route was
  unlimited.
- `export.enabled = false` switches off only the customer's download, as the README
  says. The Control Panel export for admins with `export account data` stays, so a
  request under Art. 15 GDPR can always be answered.

## 0.1.0 — 2026-09-25

First version.

- Email verification for frontend users: signed link with an address hash, "send again"
  form (`accounts:verify_notice`), `accounts.verified` middleware, mail after core's
  registration.
- Changing the address with confirmation (`accounts:change_email_form`): the new address
  becomes active only once its link is opened; the old address is told.
- Account deletion with a grace period (`accounts:delete_form`, default 14 days), a
  withdraw link in the mail, daily `accounts:purge`, `AccountDeleting` hook before and
  `AccountDeleted` after.
- Personal data export as a ZIP with one JSON file per contributor; contract
  `ContributesPersonalData`; shipped contributors for the account, payments,
  entitlements, leadhub, notifications and teams.
- Customer overview in the Control Panel with payments, subscriptions, access grants,
  teams and history; "Sign in as" through core's impersonation, recorded in activity and
  attributed through identity-contracts.
- Wiring screen: events, mail templates, listening automations and webhooks, installed
  siblings, export contributors.
- Every event is an automation trigger and a webhook trigger when those addons are
  installed. Every mail is an email-templates slug with a shipped default.
- Settings through brand-context's settings layer.
- Works with file and Eloquent users.
- Deleting erases, not only the user: contract `ErasesPersonalData` with erasers for
  entitlements, teams, leadhub, notifications, activity (through `activity:anonymize`)
  and this addon's own requests; payments and invoices are kept (§ 147 AO, § 14b UStG)
  and reported. The deletion record keeps what was deleted, anonymised and kept.
- A running subscription blocks the deletion with a link to the customer portal, or,
  with `deletion.active_subscriptions = cancel`, is cancelled through payments. Being
  the only owner of a team with other members blocks it too.
- Changing the address, deleting and exporting ask for Statamic's elevated session
  instead of a password field, and are closed during an impersonation.
- Scheduling a deletion in the Control Panel needs core's `delete users`; a super admin
  only by a super admin.
- Entries written during an impersonation name the admin as actor.
- Statuses, intervals, sources, roles and activity types in the customer overview are
  translated.
- `deletion.grace_days` is at least 1.
- The five mails register with email-templates' template registry (occasion, event,
  placeholders with examples, defaults). The wiring screen lists Statamic's and
  Laravel's own account mails next to them.
- A due deletion that something blocks becomes `blocked`: one mail with the reasons and
  a withdraw link, event `AccountDeletionBlocked`, marked in the Control Panel, retried
  daily. Blocked and overdue requests can be withdrawn.
- The `cancel` policy cancels subscriptions right before the erasure, not at the
  request. `AccountDeleting` fires after a successful erasure, inside the transaction
  that also deletes the user.
- Withdrawing (deletion, address change) and every export are closed during an
  impersonation. This addon's ledger entries carry ids only, no addresses; deletion
  rows keep no name. Blockers are phrased in the third person in the Control Panel,
  with the portal URL as a link. A site that turns elevated sessions on without the
  confirmation route gets a 403 instead of a 500.
- The purge checks that the user is really gone (a `UserDeleting` veto, a user file
  that could not be removed) and otherwise rolls back: no `completed`, no last mail.
  A subscription cancelled before a failed run is recorded (request meta, ledger
  `accounts.subscriptions_cancelled`) and named when the request is withdrawn. The
  blocked state is saved only after its mail went out; the blocked mail says how long
  its button works. A request whose account is already gone is logged, not closed
  silently. The customer list shows since when a deletion is blocked.
- One confirmation mail: `verification.mail = auto` sends Laravel's `VerifyEmail`
  (`core-verify-email`) for `MustVerifyEmail` models and this addon's mail otherwise;
  Laravel's `Verified` event becomes `EmailVerified`.
