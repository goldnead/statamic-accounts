# Changelog

## Unreleased

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
