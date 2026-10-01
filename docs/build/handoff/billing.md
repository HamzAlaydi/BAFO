# Billing handoff (plans, subscriptions, checkout, invoices, R4 sponsorship, AccessPolicy)

Written by the Billing engineer on 2026-09-29. It covers what the module provides, the choices made
where the contract is silent (`CONTRACT-GAP` in code), and what other owners need to do.

## 1. Checks

| Check | Command | Result |
|---|---|---|
| Billing tests | `scripts/test-api.sh billing tests/Feature/Billing tests/Unit/Billing` | 227 passed (1,060 assertions). This includes 2 cross-module tests that run the real Competitions `PublishCompetition` and `JoinCompetition` (group `cross-module`). |
| Other suites run on `bafo_billing_test` | `scripts/test-api.sh billing tests/Feature/<Module>` | Competitions 153/153, Integrations 135/135, Identity 196/196, Bidding 181/181, Notifications 188/188, Catalog 27/27, `tests/Unit/Schema` 253/253 |
| PHPStan level 6 | `vendor/bin/phpstan analyse app/Modules/Billing --memory-limit=1G` | 0 errors. On all of `app/`, the only errors are 10 in Integrations. |
| Pint | `vendor/bin/pint --test app/Modules/Billing routes/app_v1/billing.php lang/*/billing.php tests/*/Billing` | clean |
| Migrations and seeds | On a scratch DB (dropped afterwards): `migrate:fresh --seed`, then `db:seed --class=DemoSeeder` | OK. Plans seeded. Issuer Co has pro, Supplier A single, Supplier C a plus trial, Buyer D plus. 3 invoices were cleared, each with a PDF. |
| Schedule | `php artisan schedule:list` | The 4 billing commands are listed (§12) |

## 2. What exists

**Contracts** (`App\Modules\Billing\Contracts`, bound as singletons in `BillingServiceProvider`):

| Interface | Implementation | Notes |
|---|---|---|
| `AccessPolicy` | `Services\DbAccessPolicy` | Follows §3.6 and §8.4 exactly. `participationAccess()` returns `Data\ParticipationAccess`; its `toArray()` is the `access` object of API.md §2.6. `coverageFor()` returns `array<int, Enums\Coverage>` keyed by invitation id. `resolveJoin()` locks the reserved pass: with an own plan the pass is released (`covered_by_own_plan`), otherwise it becomes `joined`; with neither it throws 403 `plan_required` with `details.access`. |
| `SponsorshipService` | `Services\DbSponsorshipService` | `reserveForPublish()` and `reserveForInvitations()` lock the sponsorship row and throw 409 `sponsorship_payment_required` with `details.quote` (a SponsorshipQuote) without writing anything. `reserveForInvitations()` does nothing on a draft: the passes are reserved at publish. |
| `PaymentGateway` | `Services\Gateways\FakePaymentGateway`, `MoyasarPaymentGateway` (resolved by `PaymentGatewayManager`) | An existing payment always uses the driver recorded in `payments.gateway`. Moyasar with no keys returns 503 `gateway_not_configured`; an HTTP failure returns 502 `gateway_error`. |
| `EInvoicing` | `Services\EInvoicing\FakeEInvoicing` | Always returns `cleared`, with `fake-{id}`, a UUID, and a base64 TLV QR (tags 1–5, `Services\EInvoicing\ZatcaQr`). |

**Endpoints** (`routes/app_v1/billing.php`; every name follows API.md §1.7):

- `GET /plans`, `GET /plans/custom-quote` (guest).
- `GET /billing/subscription` and `POST /billing/trial`.
- `POST /billing/coupons/validate`.
- `POST /billing/checkout/subscription` (`idempotent:optional`).
- `GET /billing/payments/{payment}` and `POST …/verify`.
- `GET /billing/invoices`, `GET /billing/invoices/{invoice}` and `GET …/pdf`.
- `GET /billing/vouchers`.
- `POST /billing/gateway-webhooks/{gateway}` (guest).
- `GET|PUT /competitions/{competition}/sponsorship`, `GET …/sponsorship/quote` and `POST …/sponsorship/checkout` (`idempotent:optional`).

**Web.** `GET /pay/fake/{payment}`, plus `POST …/approve` and `POST …/decline`. The route names are `billing.fake-pay.*`. They exist only while the driver is `fake` outside production. The page is in Arabic or English (`?lang=`, otherwise the payer's locale). With `PAYMENT_FAKE_AUTO_APPROVE=true` it auto-approves. Approve and decline answer 303 to `return_url?payment={id}`; a settled payment gets 409 with the page.

**Actions** (`App\Modules\Billing\Actions`). Admin and jobs call these:

| Area | Actions |
|---|---|
| Subscriptions | `StartTrial`, `GrantSubscription`, `ExpireSubscriptions`, `SendSubscriptionReminders` |
| Checkout | `CreateSubscriptionCheckout`, `CreateSponsorshipCheckout`, `OpenGatewayCheckout` |
| Payment results | `HandleGatewayResult` (the single entry point), `CompleteSponsorshipIntent`, `ReconcilePayment` (also used by verify and webhooks; it handles late `expired → succeeded`), `VoidExpiredPassHolds` |
| Admin | `MarkPaymentPaid`, `RecordRefund`, `RecordCreditNote` |
| Invoices | `InvoicePayment` (creates the invoice, once per payment), `IssueEInvoice` (e-invoice + PDF; retry) |
| Sponsorship | `ConfigureSponsorship`, `ReleaseSponsoredPass`, `SettleCompetitionSponsorship`, `IssueSponsorshipVoucher`, `GrantSponsoredPass` |

**Events** (§10, `final readonly`, `SerializesModels`):

- `PaymentSucceeded`
- `PaymentFailed`
- `SubscriptionActivated`
- `SubscriptionExpiring($subscription, $daysLeft)`
- `SubscriptionExpired`
- `InvoiceIssued`
- `SponsorshipSettled`
- `SponsorshipPublishFailed($sponsorship, $payment, $errorCode)`
- `VoucherIssued($voucher)`

**Listeners:**

| Listener | Event | How it runs |
|---|---|---|
| `IssueInvoiceForPayment` | `PaymentSucceeded` | queued on `billing`, after commit |
| `ReleasePassForInvitation::onDeclined` / `onRevoked` | Competitions `InvitationDeclined` / `InvitationRevoked` | sync, inside the transaction |
| `SettleSponsorship` | Competitions `CompetitionClosed` and `CompetitionCancelled` | queued on `billing`, after commit |

**Commands and schedule** (registered in the provider):

| Command | Schedule |
|---|---|
| `billing:expire-subscriptions` | every 5 minutes |
| `billing:subscription-reminders` | 06:00 UTC |
| `billing:reconcile-payments` | every 5 minutes |
| `billing:retry-einvoices` | every 5 minutes |

The three five-minute commands run `withoutOverlapping`.

**Settings.** The §15.3 defaults are registered from `config.php` (`bafo.billing.settings`). VAT is config: `bafo.billing.vat_rate_bp` = 1500.

**Env keys.** No new keys. All §15.2 Billing keys are already in `.env.example`.

**Other pieces:**

- File rule `invoice_pdf`: members of the owning organization with `billing.view`.
- Policies: `PaymentPolicy`, `InvoicePolicy`, `SubscriptionPolicy` and `CouponPolicy`.
- Gate abilities `billing.sponsorship.view|manage|checkout` (`SponsorshipPolicy`).

**Embeddable pieces** for other modules:

- `Http\Resources\PlanSummaryResource` (`{id, code, name}`) and `SubscriptionResource`, for `Me.subscription`.
- `Services\SponsorshipPresenter::summary()`, the issuer projection's `sponsorship`.
- `Services\SubscriptionLookup`: `current()`, `upcoming()`, `hasPaidHistory()`, `seatsUsed()`.

**Seeders:**

- `BillingReferenceSeeder`: the four plans with the §5.7 prices.
- `BillingDemoSeeder`: plans through the real checkout and fake approval (with invoices), the trial for Supplier C, and the `LAUNCH10` coupon.

## 3. Contract gaps (marked `CONTRACT-GAP` in code)

1. **Return URL allow-list.** The scheme, host and port must match exactly, and the path must start with the allowed path. A plain "starts with" test would accept `http://localhost:3000.evil.test`. URLs with user info are refused.
2. **Upgrade comparison.** "Higher monthly equivalent" compares the plan-line subtotal (`unit × quantity`) per month. For fixed plans this equals `unit / months`; it also gives a correct result for the custom plan.
3. **One queued renewal at a time.** While a renewal is queued, any purchase answers 409 `subscription_renewal_too_early`, with `renewable_from` = the queued period's end minus the window.
4. **Trial.** It is also refused while any subscription is current (for example a grant). Billing writes `organizations.trial_used_at` directly, because §13.3 assigns that write to the trial flow and Identity has no contract for it.
5. **Own-plan coverage for drafts.** The deadline is `invitation_cutoff_at`, else `scheduled_close_at`, else now. Queued renewals extend the coverage.
6. **`reserveForPublish` candidates.** The candidates are the draft, sent and viewed invitations. The result then does not depend on whether PublishCompetition sends the invitations before or after the call.
7. **Pass source.** A reserved pass is `purchase` while funded slots were never assigned, and `freed_slot` afterwards.
8. **Invite-intent rows.** They are validated by Billing's `InvitationRowResolver`, which mirrors §13.11 and returns the same item codes. §3.6 gives Billing no Competitions validator. InviteParticipants re-checks the rows after payment.
9. **`SubscriptionExpired`.** It is dispatched only when no other subscription becomes current. A queued renewal that takes over is silent.
10. **Subscription history** in `GET /billing/subscription`: superseded and expired rows, newest first, at most 50.
11. **Plan seeding.** `firstOrCreate` by code, so admin edits of prices survive a re-seed.
12. **Credit notes** are a full credit of the original invoice, once per invoice, recorded as `reported` with the provider's reference. They have no PDF, because they are issued in the provider portal.
13. **Payment lines** reference the subscription (morph `subscription`) or the sponsorship (morph `competition_sponsorship`). Plans have no morph alias in §4.9.
14. **Expired payments** do not dispatch `PaymentFailed`: the checkout was abandoned, not declined.
15. **`EntitlementSource`.** Billing owns this enum (the schema task's choice).

## 4. Requests to other owners

**Platform**

- Add `chillerlan/php-qrcode` (^5) to `composer.json` `require`. The invoice PDF draws the ZATCA QR with it, and today it is installed only as a Filament dependency. The QR image is skipped if the class is missing.
- Tests in any module that complete a payment write invoice PDFs. They should call `Storage::fake('private')`.

**Competitions**

- In `PublishCompetition`, call `bindKnownOrganizations()` **before** `SponsorshipService::reserveForPublish()`. Otherwise an invitee whose e-mail matches an organization with its own plan is not seen as `own_plan`, and the issuer is asked to buy a pass that is only released (`covered_by_own_plan`) at join.
- `FormRequest::validated()` returns wildcard rows (`invitations.*`) rule by rule, so their order can differ from the input while the numeric keys stay the same. Billing `ksort`s the rows before resolving them, so `details.item_codes` paths match the input. Check `POST …/invitations` for the same issue.
- Demo competition #12 ("sponsored live tender", DEMO.md) runs after `BillingDemoSeeder`. Build it in this order:
  1. `ConfigureSponsorship::handle($c, SponsorshipMode::All, null, $actor)`;
  2. `GrantSponsoredPass::handle($invitationForSupplierB, $actor)`, or a sponsorship checkout;
  3. `PublishCompetition`;
  4. `JoinCompetition` for Supplier B, who has no plan and so joins on the pass.

**Identity**

- `Me.subscription`: use `SubscriptionResource` or `PlanSummaryResource` together with `AccessPolicy::activeSubscription()`.
- `entitlements.seats_total`: use `AccessPolicy::seatLimit()`. `seats_used` = `SubscriptionLookup::seatsUsed()`.
- Organization `trial_available`: `trial_used_at === null`, no paid history (`SubscriptionLookup::hasPaidHistory()`) and no current subscription.

**Notifications**

- Listen to the Billing events in §2. `SubscriptionExpiring::$daysLeft` is 7, 3 or 1, or the smaller real number of days when a run was missed.
- `SponsorshipPublishFailed::$errorCode` is the Competitions error code, for example `live_event_capacity_reached`, or `server_error`.
- "Billing users" = active members with `billing.view`.

**Admin (Filament)**

Call the Actions with `Actor::forAdmin()`:

| Admin action | Billing Action |
|---|---|
| Grant subscription | `GrantSubscription` |
| Reconcile now | `ReconcilePayment` |
| Mark paid manually | `MarkPaymentPaid` |
| Record refund | `RecordRefund` |
| Retry e-invoice | `IssueEInvoice` |
| Record credit note | `RecordCreditNote` |
| Issue voucher | `IssueSponsorshipVoucher` |
| Grant pass | `GrantSponsoredPass` |

Settled sponsorships that still need a voucher: `status = settled AND unused_count > 0 AND voucher_coupon_id IS NULL`. Payments needing review: `metadata->needs_manual_review = true`.

**Web**

- Checkout returns to `return_url?payment={id}`.
- The hosted page is `{APP_URL}/pay/fake/{id}`.
- Poll `GET /billing/payments/{id}`, then call `POST …/verify` once.

## 5. Cross-module failures seen during this task (not Billing code)

- `App\Modules\Integrations\Http\Requests\StoreExportRequest::format()` is incompatible with `Illuminate\Http\Request::format($default = 'html')`. This is a PHP fatal error. It kills `tests/Unit/ArchTest.php`, and so the full suite, with no output.
- `tests/Feature/Platform/KernelMiddlewareTest` (it expects the app_v1 stack without Identity's `EnsureAccountActive`), `IdempotentRequestTest` (API-client scope) and `tests/Feature/Support/ApiConventionsTest` (public API without a client) fail on 401 `invalid_token`. These come from the new Identity and Integrations middleware.
- `tests/Unit/Schema/EnumsTest` fails for enums with missing labels in `integrations.php` and `notifications.php`.
