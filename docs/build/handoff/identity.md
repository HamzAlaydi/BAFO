# Identity handoff (with Catalog)

Written by the Identity and Catalog engineer on 2026-09-29. Catalog has its own short note in
`catalog.md`. This file covers what Identity provides, how other modules plug in, the choices made
where the contract is silent (`CONTRACT-GAP` in code), and the requests to other owners.

## 1. What is built

All of API.md §1.3 and the Identity part of §3.2, following ARCHITECTURE §5.3, §8.1, §10, §12, §13.8–§13.10.

| Area | Endpoints (route names) | Actions |
|---|---|---|
| Registration | `app.v1.auth.register` | `RegisterOrganization` |
| OTP | `app.v1.auth.otp.send`, `.otp.verify`, `.otp.check` | `SendOtpCode`, `VerifyEmail`, `CheckOtpCode` |
| Session | `app.v1.auth.login`, `app.v1.auth.logout` | `SignIn`, `SignOut` |
| Password reset | `app.v1.auth.password.forgot`, `.password.reset` | `RequestPasswordReset`, `ResetPassword` |
| Team invitation link | `app.v1.auth.team-invitations.lookup`, `.accept` | `AcceptTeamInvitation` |
| Me | `app.v1.me.show`, `.update`, `.password`, `.avatar.store`, `.avatar.destroy` | `UpdateProfile`, `ChangePassword`, `ReplaceUserAvatar` |
| Organization | `app.v1.organization.show`, `.update`, `.logo.store/destroy`, `.profile-document.store/destroy` | `UpdateOrganization`, `ReplaceOrganizationFile` |
| Team | `app.v1.team.members.index`, `.store`, `.update`, `.destroy`, `.resend` | `AddTeamMember`, `UpdateTeamMember`, `RemoveTeamMember`, `ResendTeamInvitation` |
| Account deletion | `app.v1.account.deletion.store`, `.show`, `.destroy` | `RequestAccountDeletion`, `CancelAccountDeletion`, `ExecuteAccountDeletion` |
| Public API | `public.v1.organization` (`api.scope:organization:read`) | – |
| Admin panel (no HTTP) | – | `VerifyOrganization`, `SuspendOrganization`, `UnsuspendOrganization`, `UpdateOrganizationFeatures`, `ResendOwnerVerificationCode`, `ChangeMembershipStatus` |

- **Account gate.** `EnsureAccountActive` is appended to `app_v1` (§2.2 item 7): 403 `email_not_verified`, `account_inactive` or `organization_suspended`. Exempt: `app.v1.auth.logout`, `app.v1.me.show`, `app.v1.account.deletion.*`. Guests pass through, and so does any authenticated model that is not the Identity `User` (the Platform kernel tests' `TestUser`).
- **Permissions.** `Gate::define('perm', …)` (§8.1). Routes use it before validation: `->middleware("can:perm,'organization.update'")` (the quotes are needed: Laravel's `can` middleware reads an unquoted argument as a route parameter). Team routes use `MembershipPolicy` (`team.manage` → 403; another organization's membership → 404).
- **Commands and schedule.** `identity:prune` (daily) and `identity:execute-account-deletions` (hourly, `withoutOverlapping`), registered in `IdentityServiceProvider`.
- **File rule.** `organization_profile` (§8.5): members of the owning organization, and members of an issuer with a competition in which the owner organization is a participant.
- **Mails** (§11.5), queued on `mail` after commit, markdown views `identity::mail.*` (they pick up the Notifications theme once it is published): `OtpCodeMail`, `TeamInvitationMail` (link `{WEB_URL}/{locale}/auth/accept-invite#t={token}`, token in the fragment only), `AccountDeletionScheduledMail`.
- **Events** (§10): `UserRegistered`, `EmailVerified`, `MemberAdded`, `MemberUpdated`, `MemberRemoved`, `OrganizationUpdated`, `AccountDeleted`.
- **Audit actions:** `organization.registered`, `organization.updated`, `organization.logo_updated/_removed`, `organization.profile_document_updated/_removed`, `organization.verified/unverified`, `organization.suspended/unsuspended`, `organization.features_updated`, `user.email_verified`, `user.signed_in`, `user.signed_out`, `user.updated`, `user.password_changed`, `user.password_reset`, `user.avatar_updated/_removed`, `user.verification_code_resent`, `member.added` (the mandatory feed action, organization = own), `member.updated`, `member.joined`, `member.removed`, `member.invitation_resent`, `account_deletion.requested/cancelled/completed`.
- **Demo data.** `IdentityDemoSeeder` builds the five organizations and seven users of `DemoSeeder::ORGANIZATIONS`: verified e-mails, the listed roles and flags, the organization flags, complete billing profiles, categories and consents. Password `DemoSeeder::PASSWORD`. Checked on a scratch database with the full `DemoSeeder` chain (Identity, then Billing).

## 2. For other modules

| Need | Use |
|---|---|
| Send or check an OTP (Competitions: the invitation claim, purpose `invitation_claim`, `context.invitation_id`) | Inject `App\Modules\Identity\Contracts\OtpCodes`: `send($email, OtpPurpose::InvitationClaim, $user, ['invitation_id' => $id], $locale, $ip)` and `consume($email, $purpose, $code)`. **Call `consume()` outside your own transaction**, so a wrong code still counts as an attempt. Errors: `otp_invalid` / `otp_expired` (422), `otp_too_many_attempts` / `otp_resend_cooldown` (429). |
| A permission check in a route | `->middleware("can:perm,'competitions.award'")`, or `$user->hasPermission(Permission::CompetitionsAward)` in a policy |
| Embed an organization or a user | `OrganizationSummaryResource` (`{id, name, logo_url, verified}`), `UserResource`, `MembershipResource`, `OrganizationResource` (own view) |
| Identity business errors | `App\Modules\Identity\Exceptions\IdentityError::make('seat_limit_reached', $details)` (the status table of CONVENTIONS §8.3) |
| Seats | `App\Modules\Identity\Services\SeatGuard` / `Entitlements::seatsUsed()` (memberships `invited` + `active`, §13.2) |
| Admin panel | The Actions in the last row of the table in §1. Build the actor with `Actor::forAdmin($admin, request())`. |

**`AccountDeleted`** is dispatched **once per deleted user**. For an organization-scope request, every other member comes first with scope `user`, and the requesting owner last with scope `organization`, so organization-level listeners (Integrations) run once.

## 3. Requests to other owners

**Platform**

- `tests/Feature/Platform/KernelMiddlewareTest.php`, "builds app_v1 in the contract order", asserts the exact `app_v1` group. ARCHITECTURE §2.2 item 7 has Identity append `EnsureAccountActive`, so the group now has a 10th entry and the test fails. Please compare the first nine entries (`array_slice(..., 0, 9)`), as the `public_v1` test next to it already does.

**Billing**

- Bind `App\Modules\Billing\Contracts\AccessPolicy` (§3.6). `App\Modules\Identity\Services\Entitlements` calls its `seatLimit()`, `canIssue()` and `activeSubscription()` as soon as the interface exists and is bound. Until then it answers from the `subscriptions` table with the §8.4 definitions (a `HANDOFF-STUB`, read-only). Identity will delete the fallback once AccessPolicy is bound.
- `trial_available` (`GET /organization`, `Me`) is computed by Identity: `trial_used_at` is null **and** no `paid` subscription was ever activated (status `active`, `superseded` or `expired`). If Billing wants to own that rule, expose it on AccessPolicy and tell Identity.
- The `subscription` block of `Me` is built by Identity from `activeSubscription()`: `{plan {id, code, name}, source, status, ends_at, days_left, total_days}`. `days_left` and `total_days` are whole days rounded up (`total_days` from `starts_at` to `ends_at`). Reuse it for `GET /home`, or tell Identity if Billing wants to own the shape.

**Competitions**

- Listen to `EmailVerified` (`AttachPendingInvitations`, §10).
- `POST /auth/register` checks `invitation_token` against `invitations.token_hash = sha256(token)` with status `sent` or `viewed`; anything else is a field error on `invitation_token`. Keep that hashing when you create tokens (§13.11).
- For the invitation claim OTP, use the `OtpCodes` contract (§2 above).
- `DeletionBlockers` reads `competitions.status` and `participants` directly (read-only).

**Notifications**

- `RemoveDeviceTokens` on `AccountDeleted`: one event per deleted user (see §2).
- `GET /me` reads the unread count with `$user->unreadNotifications()->count()` on the Laravel `notifications` table.

**Integrations**

- `RevokeOrganizationApiAccess` on `AccountDeleted` with scope `organization`, and `LinkVendorsToOrganization` on `EmailVerified` (§10).
- The public endpoint `GET /api/public/v1/organization` reads the organization from `CurrentActor` (set by `api.client`). The tests use a real API key when `api.client` is registered, which it now is.

**Admin**

- Filament Organizations: verify / unverify → `VerifyOrganization`; suspend / unsuspend → `SuspendOrganization`, `UnsuspendOrganization`; feature toggles → `UpdateOrganizationFeatures`; resend verification OTP → `ResendOwnerVerificationCode`. Users: deactivate / reactivate → `ChangeMembershipStatus`. Account deletions queue: read `account_deletion_requests`.

## 4. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **`OtpCodes` contract.** §3.6 lists no Identity contract. The invitation claim writes `otp_codes`, which §3.3 rule 2 routes through a contract, so Identity exposes `Contracts\OtpCodes`.
2. **OTP hourly cap.** 5 codes per hour per e-mail (any purpose) reuses 429 `otp_resend_cooldown`, with `retry_after_seconds` until the oldest code of the hour ages out.
3. **OTP without any code.** Verifying an e-mail that never received a code answers `otp_invalid`, so account existence is not revealed. A consumed code answers `otp_expired`.
4. **Pending team members** (no password yet) get no OTP from `/auth/otp/send` or `/auth/password/forgot`: the invitation link verifies the e-mail and sets the password.
5. **Account gate order.** A `pending_verification` user fails two §8.1 rows; `email_not_verified` wins.
6. **Consents** record the latest published version in the user's locale, else the other locale. With no published document at all, no row is written (a warning is logged).
7. **Audit subject of account deletion.** `account_deletion_requests` has no morph alias (§4.9), so the subject is the requesting user and the request id is in `meta`.
8. **Deletion blockers at execution.** §13.8 checks blockers at request time only. If an open competition or participation exists when the request falls due, the organization deletion is postponed (retried hourly).
9. **Deleted organization contact fields.** `email` and `phone` are NOT NULL: the e-mail becomes `deleted+{public_id}@invalid.bafo` and the phone an empty string. The CR and VAT numbers stay, because invoices reference them. So a deleted company cannot register again with the same CR; this needs an architect decision if it matters.
10. **`DELETE /account/deletion` with nothing pending** answers 404 `not_found`.
11. **`invited_at`** (Membership) is the creation time of memberships made by a team invitation, and null for the registering owner.
12. **VAT switch.** Setting `vat_registered` to false clears `vat_number`.
13. **Membership status by the admin.** `ChangeMembershipStatus` applies the same seat check as the team endpoint on reactivation, and may change the owner's membership.
14. **`identity:prune`** writes no audit entry (housekeeping).

## 5. Tests and checks

| Check | Result |
|---|---|
| `scripts/test-api.sh identity tests/Feature/Identity tests/Feature/Catalog tests/Unit/Identity` | 245 passed (1352 assertions) |
| `vendor/bin/phpstan analyse app/Modules/Identity app/Modules/Catalog --memory-limit=1G` | 0 errors |
| `vendor/bin/pint --test` on every owned file | passed |
| `php artisan route:list --path=api` | the names match API.md §1.2, §1.3 and §3.2 |
| `migrate:fresh --seed`, then `DemoSeeder` (scratch database `bafo_identity_scratch`, dropped afterwards) | 13 regions, 14 categories, 15 close reasons, 3 presets; 5 organizations, 7 users |
| `route:cache` / `config:cache` (to scratch paths) | succeed |

**Full suite at the time of writing** (`tests/Feature` and `tests/Unit` without `ArchTest`): 1381 tests, 1376 passed. The five failures are outside Identity and Catalog:

- `Platform/KernelMiddlewareTest` (app_v1 exact group): see the Platform request above.
- `Platform/IdempotentRequestTest` ("scopes keys of API clients…") and `Support/ApiConventionsTest` ("answers the public api without meta.server_time"): these kernel tests register ad-hoc `public_v1` routes without an API credential. They now get 401 `invalid_token` from Integrations' `api.client`.
- `Unit/Schema/EnumsTest` for the new Notifications enums `DeliveryChannel` and `NotificationType`: labels missing in `lang/*/notifications.php`.
- `tests/Unit/ArchTest.php` stops the run with a fatal error: `App\Modules\Integrations\Http\Requests\StoreExportRequest::format()` is not compatible with `Illuminate\Http\Request::format()`. Rename that method (Integrations).
