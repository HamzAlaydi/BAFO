# Admin handoff (Filament 5 platform admin, ARCHITECTURE §16)

Written by the Admin engineer on 2026-09-29. It covers what the panel provides, how it plugs into the
other modules, the choices made where the contract is silent (`CONTRACT-GAP` in code), and the
requests to other owners.

## 1. What is built

**Panel** (`app/Providers/Filament/AdminPanelProvider.php`, `/admin`):

- Guard `admin` with provider `admins`. `AdminServiceProvider::register()` adds both at runtime
  (§16), so `config/auth.php` is unchanged. Sessions use the configured driver (Redis locally).
- Login, and a profile page (`Filament/Auth/EditAdminProfile`) for name, e-mail, password and MFA.
  Profile saves go through the audited `UpdateAdmin` Action.
- MFA is Filament app authentication (TOTP) with recovery codes. It is required when
  `bafo.admin.mfa_required` is true (`ADMIN_MFA_REQUIRED`; the default is true only when
  `APP_ENV=production`). Filament reads the flag when it registers the routes, so a change needs a
  config reload.
- The panel is Arabic and right-to-left by default. The user menu has an English / Arabic toggle
  (`GET /admin/locale/{ar|en}`, route `admin.locale`). The choice is kept in the session, and the
  persistent middleware `ApplyAdminLocale` applies it, Livewire requests included. Navigation groups
  are the `AdminNavigationGroup` enum cases, in case order, and they are labelled when the page
  renders, so they follow the chosen language.
- Times are shown in Asia/Riyadh (`FilamentTimezone`), the offer ledger to the millisecond. Money
  uses `Money::format` (`12,500.00 ر.س` / `SAR 12,500.00`). Price inputs are typed in SAR with up to
  2 decimals and stored as integer halalas, with no floats (`Fields::money`).
- Brand colour `#0B7A55`, IBM Plex Sans Arabic.

**Authorization.**

- `PanelResourcePolicy` authorizes the resources that sit on other modules' models. Those models'
  own policies are written for organization users, so the resources never go through them.
- Every active admin can view everything. Only the abilities a resource declares are writable.
- Operators cannot open the admins, plans, coupons or settings pages (§8.7).
- `AdminPolicy` covers the `Admin` model: super admins only, and never deleting oneself.
- A `Gate::before` hook stops any admin from reaching a module policy or the `perm` gate: they are
  denied, except for abilities on the `Admin` model.

**Every §16 page:**

| Page | Content | Actions (module Action, always `Actor::forAdmin()`) |
|---|---|---|
| Dashboard | 6 counters (organizations, active subscriptions, live competitions, payments today with the amount, failed e-invoices, sponsorships awaiting a voucher), each linking to its list; table of live competitions (phase, participants, offers, close time) | – |
| Organizations | Search (name, CR, e-mail), filters (status, verified, flags); view: profile, status and flags, current subscription (`AccessPolicy::activeSubscription`); relation managers for members, subscriptions and competitions | Verify / unverify → `VerifyOrganization`; suspend (reason) / unsuspend → `SuspendOrganization` / `UnsuspendOrganization`; feature flags → `UpdateOrganizationFeatures`; resend the owner's OTP → `ResendOwnerVerificationCode`; grant subscription → `GrantSubscription`; member deactivate / reactivate → `ChangeMembershipStatus` |
| Users | Search, filters (status, membership status, role), view | Deactivate / reactivate the membership → `ChangeMembershipStatus` (409 `seat_limit_reached` is shown as a notification) |
| Account deletions | Queue with tabs (pending, completed, cancelled, all) and a scope filter | – |
| Competitions | List (status, type and format filters); view: summary, rules, timeline, outcome, sponsorship; relation managers for invitations, participants (standing), offer ledger, extensions, rejected offers and awards | Extend → `ExtendCompetition` (kind `admin`); cancel (cancel reason plus a note when the reason requires one) → `CancelCompetition`; force close (reason) → `ForceCloseCompetition`; void offer (reason of kind `void_offer`, note) → `VoidOffer`; revoke invitation → `RevokeInvitation` (reason `admin`); grant pass → `GrantSponsoredPass` |
| Plans | CRUD (super admins); code immutable; SAR prices; features `{ar, en}` | writes through `SaveReferenceRecord` / `DeleteReferenceRecord` (see §3) |
| Coupons | CRUD of kind `coupon` (super admins); code stored upper case, immutable | same |
| Vouchers | Read-only list with value and balance | – |
| Subscriptions | List, filters (status, source, plan, current) | Grant subscription (organization chosen) → `GrantSubscription` |
| Payments | List, filters (status, purpose, gateway, needs review = `metadata.needs_manual_review`, date range), view, CSV export of the filtered list (UTF-8 BOM, Riyadh times, SAR decimals) | Reconcile now → `ReconcilePayment`; mark paid manually (reference) → `MarkPaymentPaid`; record refund (reference) → `RecordRefund` |
| Invoices | List with the e-invoice status, view, PDF download (`FileStorage::download`) | Retry e-invoice → `IssueEInvoice`; record credit note (provider reference) → `RecordCreditNote` |
| Sponsorships | List with counters (funded, pending, reserved, joined, released, unused), filter "awaiting a voucher" | Issue voucher → `IssueSponsorshipVoucher`; grant pass (invitation chosen) → `GrantSponsoredPass` |
| API clients | Read-only per organization; keys shown as `prefix…last4` only | Suspend / reactivate → `SetApiClientSuspension`; revoke → `RevokeApiClient` |
| Webhook endpoints | Read-only; the signing secret is never shown | – |
| Lookups | Regions, categories (`auction_allowed`, "Other"), close reasons, presets (structured rules form): CRUD, codes (and the close-reason kind) immutable after create | writes through `SaveReferenceRecord` / `DeleteReferenceRecord` |
| Legal documents | List, view, create and edit drafts, publish now or at a set time; published versions are read-only | `SaveLegalDocumentDraft`, `PublishLegalDocument` |
| Contact inbox | List (new count in the navigation badge), view (opening a new message marks it read), change status | `ChangeContactMessageStatus` |
| Settings (super admins) | Every registered §15.3 key, grouped by prefix and typed by its default value: switches, integers, SAR amounts for `*_minor`, texts, one field per sub-key for objects, a tag list for lists | `UpdateAppSetting`, for each changed key only |
| Audit log | Read-only; filters (action, actor type, subject type, organization, dates); search (action, actor, subject id, request id); view with flattened changes and meta | – |
| Admins (super admins) | CRUD, MFA status | `CreateAdmin`, `UpdateAdmin`, `DeleteAdmin`, `ResetAdminMfa` (Admin's own Actions) |

**Error handling.** `Filament/Support/ModuleAction::run()` wraps every module Action call. A
refusal (`ApiException`, or a validation error inside the Action) becomes a danger notification with
the localised message and the first field error. It then halts the Filament action, so the modal
keeps the admin's input.

**Admin's own Actions** (`app/Modules/Admin/Actions`): `CreateAdmin`, `UpdateAdmin`, `DeleteAdmin`,
`ResetAdminMfa`, `SaveReferenceRecord` and `DeleteReferenceRecord`.

- Each one writes an audit entry.
- An admin cannot demote, deactivate or delete their own account: 409 `cannot_modify_self`.
- The last active super admin stays one: 409 `conflict`.

**Audit actions added:** `admin.signed_in` (it also stamps `admins.last_login_at`), `admin.created`,
`admin.updated`, `admin.deleted` and `admin.mfa_reset`. The reference records add
`plan|coupon|region|category|close_reason|competition_preset.created|updated|deleted`.

**Seeding.** `AdminReferenceSeeder` creates one super admin from `ADMIN_SEED_EMAIL` and
`ADMIN_SEED_PASSWORD`. When either is empty, it uses `DemoSeeder::ADMIN_EMAIL` and
`ADMIN_PASSWORD` in `local` and `testing` only; other environments seed nothing and log a warning.
It uses `firstOrCreate` by e-mail, so a password changed in the panel survives a re-seed. The
credentials are listed only in `DemoSeeder` and `docs/build/DEMO.md`.

**Lang.** `lang/{ar,en}/admin.php` (503 keys each, the same key set). They are generated from one
table, so the two files cannot drift. They hold every label, action, notification, error and enum
(`admin_role`, `admin_navigation_group`), and use the BRIEF glossary.

## 2. For other modules

- The panel calls only the Actions that your handoffs list, with the signatures in them. If you
  change a signature, the Admin tests in `tests/Feature/Admin` will fail. Tell Admin.
- The panel reads your models and enums; it uses every enum's `label()`. It reads Bidding's
  `VisibilityProjector::issuerOfferAmount()` and `amountsHiddenFromIssuer()` for the issuer
  projection of the ledger, participants and rejections, and Billing's `AccessPolicy` for the
  current subscription.
- It shows soft-deleted organizations (status `deleted`) and their payments, invoices, subscriptions,
  competitions and participations. The relations are eager-loaded without the soft-delete scope.

## 3. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **Reference CRUD without a module Action.** §16 lists CRUD for plans, coupons and the lookups,
   but names no owning-module Action (the Catalog handoff confirms that the admin edits the lookup
   tables directly). The panel writes them through their Eloquent models, inside Admin's
   `SaveReferenceRecord` / `DeleteReferenceRecord`: one transaction together with the audit entry.
   A record that other rows still reference cannot be deleted (409 `conflict`; the delete button is
   hidden), because deleting it would fail on a restrict key or silently cascade (category pivots,
   coupon redemptions). Deactivate it instead.
2. **Audit subject of plans and lookups.** These models have no §4.9 morph alias, and
   `requireMorphMap()` is on. So their audit entries have no subject; the record (noun, public id,
   code) is named in `meta`. Coupons use the `coupon` alias as the subject.
3. **"Payments today"** means succeeded payments whose `paid_at` falls on the current Riyadh
   calendar day. The counter also shows their total.
4. **Legal documents cannot be deleted.** Platform provides no delete Action, and published
   versions are immutable. A draft can be edited until it is published.
5. **What operators may do.** §8.7 restricts only admins, plans, prices, coupons and settings, so
   operators can do everything else: grant subscriptions, void offers, force close, and so on.
   Vouchers are read-only for everyone.
6. **The panel language** is kept in the session. `admins` has no locale column.
7. **API clients** also get **Revoke** (`RevokeApiClient`, from the Integrations handoff), in
   addition to §16's suspend and reactivate.
8. **Opening a new contact message marks it read** (inbox behaviour), through
   `ChangeContactMessageStatus`.
9. **Admins are hard-deleted.** The `*_admin_id` columns elsewhere are plain ids, and the audit log
   keeps the name in `actor_label`.
10. **Error codes.** Admin reuses the §8 codes `cannot_modify_self` (409) and `conflict` (409), with
    its own messages under `admin.errors.*`.
11. **Grant pass** is available in two places: on the competition's invitations and on the
    sponsorships list, where the admin picks an open invitation without a live pass.
12. **An empty plan description** is stored as `null`, not as `{ar: null, en: null}`.

## 4. Requests to other owners

**Billing (optional).** If Billing wants to own plan and coupon writes, add Actions such as
`SavePlan` and `SaveCoupon` (with their audit entries), and the panel will switch to them. Until
then the panel writes these rows through `SaveReferenceRecord`.

**Catalog (optional).** The same applies to the lookups. A reminder from the Catalog handoff:
`CatalogReferenceSeeder` (`updateOrCreate` by code) restores seeded names and flags, so admin edits
of seeded rows do not survive a re-seed.

**Platform.**

- If drafts should be deletable, add a `DeleteLegalDocumentDraft` Action; the panel will expose it.
- `.env.example` already has `ADMIN_MFA_REQUIRED`, `ADMIN_SEED_EMAIL` and `ADMIN_SEED_PASSWORD`.
  Production deployments must set `ADMIN_MFA_REQUIRED=true` explicitly, because `.env.example`
  sets it to `false`.
- The known kernel tests still fail: `KernelMiddlewareTest`, `IdempotentRequestTest` and
  `ApiConventionsTest`. They come from Identity's and Integrations' middleware pushes, not from
  Admin.

**Everyone.** The panel runs your Actions with an admin `Actor` (`type admin`, `adminId`, channel
`admin`, no organization). Actions that read `$actor->organizationId` must not assume a user actor.

## 5. Tests and checks

| Check | Result |
|---|---|
| `scripts/test-api.sh admin tests/Feature/Admin tests/Unit/Admin` | 185 passed (922 assertions) |
| `scripts/test-api.sh admin` (full suite) | 2080 tests, 2077 passed; the 3 failures are the known Platform kernel tests (§4) |
| `vendor/bin/phpstan analyse --memory-limit=1G` (all of `app/`) | 0 errors |
| `vendor/bin/pint --test` on every owned file | passed |
| `migrate:fresh --seed` on a scratch database (dropped afterwards) | `AdminReferenceSeeder` creates `admin@demo.bafo.test` (super_admin) |
| `tests/Feature/Admin/DemoDataPanelTest.php` | runs the full `DemoSeeder` (with fake disks), then opens every page, every record page and every relation manager of the demo scenario as the seeded super admin |

The tests cover the following:

- **Pages and access:** every page renders for a super admin; operators get 403 on the
  super-admin-only pages; guests and organization users are sent to the login page; an inactive
  admin gets 403.
- **Sign-in, MFA and language:** sign-in stamps `last_login_at` and the audit entry; organization
  user credentials are refused; the MFA flag is honoured; RTL by default with the English toggle,
  and the navigation follows the language.
- **Actions:** every action calls its module Action and asserts the resulting state, with the admin
  as the actor, plus the validation and refusal paths.
- **Data rules:** issuer projection of sealed amounts; CSV content; the lazy-loading guard (a
  collection of owner records); soft-deleted organizations stay visible; the settings casts; the
  SAR ↔ halalas conversion; the seeder rules.

A visual check in a browser was not done. The rendered HTML was checked: direction, language,
navigation labels and column headers in both locales.
