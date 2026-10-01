# BAFO security review (final hardening round)

**Date:** 2026-09-30 · **Scope:** `apps/api` (Laravel 13), with the matching client changes in `apps/web` and `apps/mobile` · **Method:** adversarial review against the contract (ARCHITECTURE §7.9, §8, §9, §13, §14; API.md). Each suspected issue got a failing test first, then a fix. The test stays in the suite.

The review took four attacker roles:

- **Malicious participant.** Reads other bidders' data and games the engine.
- **Curious competitor.** Intercepts invitations and enumerates accounts.
- **Rogue branch user.** Escalates inside its own organization.
- **Public API key holder.** Crosses tenants and outlives revocation.

All security tests live in `apps/api/tests/Feature/Security/` (15 files, 145 tests with the unit test below) and in `apps/api/tests/Unit/Integrations/WebhookEmbeddedIpv4Test.php`. Run them with `scripts/test-api.sh security tests/Feature/Security`.

## 1. Findings

| ID | Finding | Severity | Status |
|---|---|---|---|
| S-12 | Invitation hijack: another company's e-mail or CR number could bind its invitations to the attacker's organization | **High** | Fixed |
| S-02 | `team.manage` could grant `can_award` / `can_purchase` that the editor does not hold, to a colleague or a sock-puppet account | **High** | Fixed (API, web, mobile) |
| S-01 | Account enumeration through `POST /auth/otp/send` (body and rate limits) | Medium | Fixed |
| S-03 | Participant competition list showed `is_leading` during a sealed BAFO round | Medium | Fixed |
| S-04 | Coupon limits multiplied by parallel open checkouts; voucher-code existence oracle | Medium | Fixed |
| S-07 | CSV/XLSX formula injection: admin payments CSV unguarded; exports missed tab/CR triggers | Medium | Fixed |
| S-08 | One-time secrets stored in clear: OTP codes and team-invite tokens on the queue, webhook secrets in the idempotency store | Medium | Fixed |
| S-11 | A stolen token was a password-guessing oracle (current-password checks at 300/min) | Low–Medium | Fixed |
| S-06 | Reverb channel auth ignored suspended organizations and unverified e-mails; client events were on | Low | Fixed |
| S-05 | No hardening headers on API or web (clickjacking, MIME sniffing, no HSTS) | Low | Fixed |
| S-09 | SSRF guard did not judge IPv6 forms that embed an IPv4 address (NAT64, 6to4, IPv4-compatible) | Low | Fixed |
| S-10 | The fake payment gateway could be selected in production (default `PAYMENT_GATEWAY=fake`) | Low | Fixed |

### S-12 Invitation hijack by claiming someone else's identity (High)

`InviteParticipants` bound an invitation to an organization in two ways. For a raw e-mail, it used **any** user with that e-mail, including a *pending* team member that another organization had created. For a vendor, it used `vendor.linked_organization_id`. That link is matched on the organization's **self-declared** contact e-mail, on its CR number (even before the platform verifies the organization), or on any member's e-mail (`VendorDirectory::matchOrganization`, `LinkVendorsToOrganization`).

A competitor could plant the target company's procurement address as a pending team member, or as its own contact e-mail. It could also register first with the target's CR number. After that, the issuer's invitation to the target was bound to the competitor. The competitor could then see the competition, join it and bid. The real addressee's claim was refused with 409 `invitation_belongs_to_another_organization`.

**Fix.**

- `User::provenOrganizationIdFor($email)` returns an organization only for an active user who verified the address, through an active membership of an active organization.
- `Organization::isProvenRecipient($email, $cr)` accepts that case, or a platform-verified organization whose CR number the vendor carries.
- `InviteParticipants` and the Billing mirror `InvitationRowResolver` bind only on these proven identities. Otherwise the invitation stays unbound, and the addressee claims it with the existing token + OTP flow (§13.11).
- ARCHITECTURE §13.11 is updated.

**Test:** `InvitationHijackTest`.

### S-02 Flag escalation through team management (High)

An admin whose own membership has `can_award = false` and `can_purchase = false` could still:

- `PATCH` a colleague to `can_award: true`;
- add a new admin, which receives both flags by default, for a second address it controls; the sock-puppet account can then award competitions and buy plans.

**Fix.** `MembershipFlagGuard`, used by `AddTeamMember` and `UpdateTeamMember`, blocks a grant from `false` to `true` unless the editor holds `competitions.award` / `billing.purchase` itself. A blocked grant is 422 on the field (`identity.validation.flag_not_held`). The admin defaults stop at the inviter's own flags. Revoking a flag, or resending a value that does not change, is still allowed, so the apps can keep sending the whole form.

On the clients, the web `MemberDrawer` and the mobile team form default a flag the signed-in user lacks to off, and disable its switch. ARCHITECTURE §8.1 is updated.

**Tests:**

- `PrivilegeEscalationTest`;
- web: `tests/nuxt/pages-dashboard.spec.ts` (restricted admin);
- mobile: `test/features/account/account_screens_test.dart` (restricted admin).

### S-01 Account enumeration via `otp/send` (Medium)

`password/forgot` and `login` did not reveal whether an account exists. `otp/send` did, in two ways:

- the answer was `otp_expires_at: null` for an unknown e-mail and a timestamp for a real one;
- only real accounts hit the 60-second cooldown and the hourly cap (429).

**Fix.** Addresses that get no code (unknown, already verified, or a pending team member) now get a plausible expiry. The same cooldown and hourly cap apply to them, tracked in the cache (`SendOtpCode::decoy`). No mail is sent and no OTP row is written. API.md §1.3 is updated. Clients already accepted both shapes.

**Tests:**

- `AccountEnumerationTest` (otp/send for both purposes, the hourly cap, password/forgot, login);
- three assertions in `Identity/OtpTest` that encoded the old `null`.

### S-03 `is_leading` in the participant list during a BAFO round (Medium)

ARCHITECTURE §7.3 treats a running BAFO round as sealed, and the live snapshot already hid the flag. `CompetitionListPresenter::leadingVisible` did not: it included the `bafo_round` status. `Ranking::recompute` runs after each sealed BAFO offer, so a participant polling `GET /competitions?role=participant` could see its flag flip. That told it a rival had submitted a better sealed offer.

**Fix.** The flag is hidden for the whole round and comes back when the round ends.

**Test:** `VisibilityLeakTest`.

### S-04 Coupon abuse (Medium)

Check 5 counted only redeemed uses. An organization could open several checkouts with a once-per-organization coupon, or with a `max_redemptions` coupon, before paying any of them, then pay them all.

In addition, check 2 (validity) ran before check 3 (ownership). Another organization's *expired* voucher therefore answered `coupon_expired` rather than `coupon_invalid`, which revealed that the code exists.

**Fix.**

- The organization's own pending, unexpired checkouts that carry the coupon count as uses against both limits, as they already did for vouchers.
- The ownership check now runs with check 1.
- A declined or expired checkout releases the hold.

ARCHITECTURE §13.4 is updated.

**Test:** `CouponAbuseTest`.

### S-07 Spreadsheet formula injection (Medium)

The admin `PaymentsCsv` wrote organization names and references raw. Any tenant chooses its own name, for example `=HYPERLINK(...)`, which then runs in a platform admin's spreadsheet. `SpreadsheetWriter::guard` (dashboard exports) missed the tab and carriage-return triggers.

**Fix.** Both now prefix `'` to any cell that starts with `= + - @ \t \r`. In the payments CSV this applies to free-text columns only; amounts and ids are generated by the platform.

**Test:** `SpreadsheetInjectionTest`.

### S-08 Secrets at rest (Medium)

- **Queued mails.** `OtpCodeMail` and `TeamInvitationMail` were serialized in clear onto the queue (Redis, and `failed_jobs` after a failure). `CompetitionInvitationMail` was already encrypted. The two mails now implement `ShouldBeEncrypted`. A worker still running the old code decrypts them anyway: the framework decides by the payload.
- **Idempotency store.** `IdempotentRequest` kept replayable response bodies for 24 h, including webhook `whsec_` secrets, which the endpoint table itself stores encrypted. The body is now sealed with `Crypt` inside the jsonb column (`{"sealed": …}`). Rows written earlier in plain JSON are still replayed. A replay is now byte-identical to the original response.

**Test:** `SecretsAtRestTest`.

### S-11 Password oracle for a stolen token (Low–Medium)

`PUT /me/password` and `POST /account/deletion` check the current password. The only limit on them was the general 300/min.

**Fix.** A new `password-confirm` limiter allows 5 attempts per minute per user.

**Test:** `PasswordOracleTest`.

### S-06 Realtime channel authorisation (Low)

`/broadcasting/auth` does not run the account gate. `ChannelMembership` checked the user and the membership, but not the organization status or e-mail verification, so the members of a suspended organization kept receiving live updates.

**Fix.**

- The same conditions as the gate now apply.
- Reverb client events (`client-*` whispers) now default to `none`. No client uses them.

**Test:** `ChannelAuthorizationTest`. It also covers participant-to-participant, participant-to-issuer, look-alike channel names, another competition's issuer, and other users' notification channels.

### S-05 Hardening headers (Low)

- **API.** A global `SecurityHeaders` middleware sets `nosniff`, `Referrer-Policy: same-origin`, and `X-Frame-Options` (DENY for the API, SAMEORIGIN for Filament). It adds HSTS on HTTPS, and `CSP: default-src 'none'; frame-ancestors 'none'` on `api/*` and `broadcasting/*` responses, error responses included.
- **Web.** Nuxt serves `X-Frame-Options: SAMEORIGIN`, `frame-ancestors 'self'`, `nosniff`, `Referrer-Policy: same-origin` and a restrictive `Permissions-Policy`. SAMEORIGIN rather than DENY keeps Nuxt DevTools working. Clickjacking the award, publish and payment buttons from another site is blocked.
- **Session cookie.** The admin session cookie is now `Secure` by default in production.

**Test:** `SecurityHeadersTest`. The web headers were checked on a private build.

### S-09 SSRF guard and embedded IPv4 (Low)

Some IPv6 forms carry an IPv4 address: `64:ff9b::a9fe:a9fe` (NAT64, which reaches 169.254.169.254 through a NAT64 gateway), `2002:7f00:1::` (6to4) and `::127.0.0.1` (IPv4-compatible). These are now judged by the embedded IPv4 address. The NAT64 local-use prefix `64:ff9b:1::/48` is blocked.

**Test:** `Unit/Integrations/WebhookEmbeddedIpv4Test`.

### S-10 Fake gateway in production (Low)

`PAYMENT_GATEWAY` defaults to `fake`. The fake hosted page is not routed in production, so a forgotten variable silently produced checkouts that nobody could pay.

**Fix.** `PaymentGatewayManager` refuses the fake driver in production with 503 `gateway_not_configured`.

**Test:** `PaymentIntegrityTest`. It also shows that forged gateway webhooks (fake and Moyasar) are refused, that `verify` asks the gateway, and that client-sent amounts or status on checkout are ignored.

## 2. Areas verified without findings (regression tests added)

**Cross-tenant sweep** (`CrossTenantAccessTest`). The test reads every app v1 route with an id parameter from the router: 65 route-method pairs. The caller is the **owner** of another organization. The public v1 routes (28 pairs) are called by that organization's API client holding **every scope**. Organization A's ids are used throughout:

- competitions, invitations, attachments, awards, payments, invoices, files;
- vendors and external ids, API clients and keys, webhook endpoints and deliveries;
- imports and exports, team members, devices and notifications.

Every answer is 403 or 404, and a fingerprint of A's data is unchanged afterwards. A route added later without a fixture id fails the test.

**Role sweep.** A plain member (no `can_award`, no `can_purchase`, not the creator) is refused 57 issuer, billing, team, organization and integrations writes in its own organization.

**Public API.**

- An access token stops working at once when its client is suspended or revoked, or when its organization is suspended.
- API access switched off answers 403.
- A revoked key answers 401.
- A route inventory checks that every public route declares `api.scope:` (`PublicApiRevocationTest`).

**Files** (`FileUploadAbuseTest`).

- **Names.** Paths are chosen by the server whatever the upload name holds: `../`, `\`, NUL or CR/LF.
- **Content.** PHP, HTML or SVG behind an allowed extension, SVG, HTML and EXE uploads are all refused.
- **Size.** Uploads over the purpose limit are refused.
- **Access.** An invitee sees only invitation documents, and outsiders see nothing.

**One-time secrets** (`OneTimeSecretReuseTest`). A team invitation link cannot be replayed after it is accepted. A password-reset code works once. Sign-out revokes the token.

**Existing coverage, reviewed and kept:**

- **Bidding integrity:**
  - concurrent offers are serialized by the competition row lock;
  - `Idempotency-Key` replays with another amount answer 422;
  - offers after close are refused under the lock with the DB clock;
  - negative, float, huge and 30-digit amounts are refused;
  - must-beat and step bounds hold;
  - BAFO offers are limited to one per shortlisted participant;
  - the per-participant rate is limited.
- **VisibilityProjector:** sealed amounts, `show_prices=false`, the reserve price and identities, in REST and in realtime.
- **Other:**
  - projected comment authors and live-update recipient sets;
  - Blade-escaped mail templates, and push and mail without amounts;
  - webhook signing, DNS pinning and no redirects;
  - OTP attempt limits;
  - token revocation on password change, reset and deletion;
  - Filament panel isolation;
  - mass assignment: every write goes through validated DTOs, and published competitions answer 409 on fields that are not editable.

## 3. Residual risks (accepted, with a recommendation)

1. **E-mail squatting (DoS).** An organization can add another company's address as a *pending* team member. Since S-12 this no longer hijacks invitations, but the address cannot register while the pending user exists. *Recommendation:* let registration take over a never-accepted, expired pending user after the OTP proves the address, or purge such users when the 7-day invite expires.
2. **Vendor auto-linking still trusts self-declared identifiers** (org contact e-mail, CR of an unverified organization). It no longer binds invitations (S-12), but a wrong link still shows up in:
   - the issuer's vendor list;
   - the vendor refs of standings, results and award webhooks (ERP supplier ids).

   *Recommendation:* link only on a verified member e-mail, or on the CR of a platform-verified organization.
3. **Client-secret rotation** does not revoke OAuth access tokens already issued. They stay valid for up to 30 minutes, as the contract says (§14.1). For an emergency, suspend or revoke the client: that takes effect on the next request (tested).
4. **Coupon `max_redemptions` across organizations** can still be exceeded by concurrent open checkouts of *different* organizations. Holds are counted per organization so that one tenant cannot lock a public coupon. Validation is also not serialized, which leaves a millisecond race window within one organization.
5. **Authentication hardening that remains:**
   - login allows 5/min per e-mail and 10/min per IP, with no lockout;
   - registration answers `email_taken`;
   - invitation `lookup` returns `next_step` (register or login) to the holder of a token;
   - the `otp/send` decoy answers faster than a real send, a timing side channel;
   - the `oauth/token` limiter is keyed by IP and `client_id` (secrets are 40 random characters).
6. **Web token storage.** The Sanctum token sits in a cookie that JS can read, which SSR and the Bearer header need. There is no script CSP on the web. Any XSS would expose the token. Today the only `v-html` is sanitized Markdown (`html: false`, link allow-list).
7. **Realtime:**
   - an existing WebSocket subscription is not re-authorized when a member is deactivated or an organization is suspended, until the client reconnects;
   - Reverb `rate_limiting` is off and `allowed_origins` is `*`;
   - `/broadcasting/auth` has no throttle.
8. **Idempotency encryption uses `APP_KEY`.** Rotating the key makes stored replays undecryptable for their remaining 24 h: the replay answers 500.
9. **Admin-trusted settings.** For example, `bidding.max_amount_minor` set near `PHP_INT_MAX` could overflow the step arithmetic. Admins are trusted; bound the Filament field if needed.
10. **Business-level:**
    - an organization can rename itself to resemble another (the "verified" badge is the control);
    - issuers can edit the title and description while a competition is live (by contract);
    - in non-production environments, the fake checkout page lets anyone with a payment id approve that payment (by design).

## 4. Verification

- **API:** `scripts/test-api.sh security --parallel`: 2231 tests pass. Pint and PHPStan pass on every changed file.
- **Web:** `pnpm lint` passes on the changed files. One unrelated error remains in another engineer's `tests/e2e/_probe3.spec.ts`. `pnpm typecheck`, `pnpm test` (512 tests) and `pnpm build` pass. The headers were checked on a private build on port 3451.
- **Mobile:** `flutter analyze` is clean, and `flutter test test/features/account` passes (77 tests).
- **Live stack:** the shared stack on :8000 serves the new headers and stays healthy. The emulator, the shared processes and the `bafo` database were not touched.

**Changed production files:**

- **API:**
  - `bootstrap/app.php`, `config/reverb.php`, `config/session.php`, `routes/app_v1/identity.php`;
  - `lang/{ar,en}/identity.php`;
  - `app/Support/Http/Middleware/{SecurityHeaders,IdempotentRequest}.php`;
  - `app/Modules/Identity/{Actions/AddTeamMember,Actions/UpdateTeamMember,Actions/SendOtpCode,Http/Controllers/AppV1/OtpController,Mail/OtpCodeMail,Mail/TeamInvitationMail,IdentityServiceProvider,Models/User,Models/Organization,Services/MembershipFlagGuard}.php`;
  - `app/Modules/Competitions/{Actions/InviteParticipants,Services/CompetitionListPresenter}.php`;
  - `app/Modules/Billing/Services/{CouponValidator,InvitationRowResolver,Gateways/PaymentGatewayManager}.php`;
  - `app/Modules/Bidding/Broadcasting/Channels/ChannelMembership.php`;
  - `app/Modules/Integrations/Services/{Spreadsheets/SpreadsheetWriter,Webhooks/WebhookUrlGuard}.php`;
  - `app/Modules/Admin/Support/PaymentsCsv.php`.
- **Web:** `nuxt.config.ts`, `app/components/team/MemberDrawer.vue`, `app/services/identity.ts` (comment).
- **Mobile:** `lib/features/account/presentation/team/team_member_form_screen.dart`, `lib/features/auth/domain/auth_models.dart` (comment).
- **Docs:** `API.md` §1.3; `ARCHITECTURE.md` §8.1, §9.2, §13.4, §13.11.
