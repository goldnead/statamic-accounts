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
