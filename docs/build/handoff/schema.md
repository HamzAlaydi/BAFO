# Schema handoff (module migrations, models, enums, factories)

Written by the schema engineer on 2026-09-29. It covers ARCHITECTURE §5.2–§5.9 for the eight domain modules:
Catalog, Identity, Integrations, Competitions, Bidding, Billing, Notifications and Admin. The module
implementers now own their `Database/Migrations`, `Models`, `Enums` and `Database/Factories` directories.

Checks at hand-off:

- `scripts/test-api.sh schema tests/Unit/Schema`: 240 tests pass.
- `scripts/test-api.sh schema` (the full suite): 509 tests pass.
- PHPStan on the eight module directories and on the whole app: 0 errors.
- Pint (`--test`) on the module directories, `tests/Unit/Schema` and `lang/`: clean.

## 1. What exists

| Module | Migrations (`2026_01_01_…`) | Models | Enums |
|---|---|---|---|
| Catalog | `000100`–`000103`: regions, categories, close_reasons, competition_presets | `Region`, `Category`, `CloseReason` (scope `ofKind`), `CompetitionPreset`, trait `Models\Concerns\HasTranslatedAttributes` | `CloseReasonKind` |
| Identity | `000200`–`000206`: organizations, organization_category, users, memberships (partial unique owner), otp_codes, consents, account_deletion_requests (partial unique pending) | `Organization`, `User` (Authenticatable, Sanctum `HasApiTokens`, `Notifiable`, `HasLocalePreference`), `Membership`, `OtpCode`, `Consent`, `AccountDeletionRequest` | `OrganizationStatus`, `UserStatus`, `OrgRole` (with `permissions()`), `MembershipStatus`, `OtpPurpose`, `DeletionScope`, `DeletionStatus`, `Permission` |
| Integrations | `000300`–`000309`: vendors, vendor_category, api_clients, api_keys, external_refs, webhook_endpoints, webhook_events (partial index), webhook_deliveries, import_jobs, export_jobs | `Vendor`, `ApiClient`, `ApiKey`, `ExternalRef`, `WebhookEndpoint`, `WebhookEvent`, `WebhookDelivery`, `ImportJob`, `ExportJob` | `VendorStatus`, `VendorSource`, `ApiClientStatus`, `ApiScope` (with `descriptions()` and `defaults()`), `WebhookEndpointStatus`, `WebhookDisabledReason`, `DeliveryStatus`, `ImportType`, `ImportMode`, `JobStatus`, `ExportType`, `ExportFormat` |
| Competitions | `000400`–`000405`: competitions (+ `competition_reference_seq`, CHECKs), competition_extensions, competition_attachments, invitations, participants, comments | `Competition` (`phaseAt()`, `isDraft()`, `isLive()`, `isSealed()`), `CompetitionExtension`, `CompetitionAttachment`, `Invitation`, `Participant`, `Comment` | `CompetitionSource`, `Direction` (with `sign()`), `Format`, `CompetitionStatus`, `Phase`, `MustBeat`, `RankVisibility`, `ResultPublication`, `ExtensionKind`, `AttachmentKind`, `InvitationStatus`, `RevokeReason` |
| Bidding | `000500`–`000507`: competition_live_states, offers (append-only trigger, CHECK, hash chain), offer_voids (trigger), offer_rejections, participant_standings, bafo_rounds, awards (partial unique issued), competition_reports | `CompetitionLiveState` (PK `competition_id`), `Offer` (`hashFor()`, `rankKeyFor()`), `OfferVoid`, `OfferRejection`, `ParticipantStanding` (PK `participant_id`), `BafoRound`, `Award`, `CompetitionReport` | `OfferStage`, `BafoRoundStatus`, `AwardStatus`, `ErpSyncStatus`, `ReportStatus` |
| Billing | `000600`–`000609`: plans, coupons, payments, payment_lines, coupon_redemptions, subscriptions, competition_sponsorships, sponsored_passes (partial unique), invoices (+ `invoice_number_seq`), invoice_lines | `Plan`, `Coupon`, `Payment`, `PaymentLine`, `CouponRedemption`, `Subscription` (`isCurrentAt()`), `CompetitionSponsorship`, `SponsoredPass`, `Invoice`, `InvoiceLine` | `EntitlementSource`, `CouponKind`, `DiscountType`, `CouponScope`, `PaymentPurpose`, `PaymentStatus`, `PaymentLineKind`, `SubscriptionSource`, `BillingInterval`, `SubscriptionStatus`, `SponsorshipMode`, `SponsorshipStatus`, `PassSource`, `PassStatus`, `PassReleaseReason`, `InvoiceType`, `EInvoiceStatus` |
| Notifications | `000700`–`000701`: notifications (Laravel shape, char(26) id), device_tokens | `DeviceToken`. In-app notifications use Laravel's `DatabaseNotification`; there is no custom model. | `DevicePlatform` |
| Admin | `000800`: admins | `Admin` (Authenticatable; Filament `FilamentUser`, `HasName`, `HasAppAuthentication`, `HasAppAuthenticationRecovery`; the MFA columns use the `encrypted` / `encrypted:array` casts) | `AdminRole` |

**Model conventions:**

- **Public ids.** Every model with a `public_id` column uses `HasPublicId`.
- **Casts.**
  - Enums are cast to their enum class.
  - JSONB columns use `array`.
  - Time columns use `immutable_datetime`; the invoice dates use `immutable_date`.
  - Every `money` column (`*_minor` in halalas) uses `App\Support\Money\Casts\MinorAmount`, which is int only and rejects floats.
  - `amount_granularity_minor` and the `*_bp(s)` columns are plain `integer`.
- **Precise timestamps.** The precision-6 tables use `UsesPreciseTimestamps`.
- **Types for PHPStan.** Every model carries `@property` PHPDoc for its columns.
- **Relations** exist in both directions, with generic PHPDoc. One exception: there are no relations to `Admin` from the domain modules, because the arch test forbids that dependency. The `*_admin_id` columns are plain ints.
- **E-mail columns** are lowercased by a mutator on `User`, `Organization`, `Vendor`, `Invitation`, `OtpCode` and `Admin`.
- **`coupons.code`** is uppercased by a mutator.

**Enum labels:**

- Every enum has `label(?string $locale = null)`, which reads `<module>.enums.<enum_snake>.<value>`.
- The labels live in `lang/{ar,en}/<module>.php`. I created these 16 files with an `enums` section only; `competitions.php` also has `direction.{tender,auction}` (CONVENTIONS §6.2).
- **Module implementers own these files from now on.** Add your `errors`, `validation` and `attributes` sections next to `enums`, and keep the `enums` keys.
- `tests/Unit/Schema/EnumsTest.php` checks that every case has an AR and an EN label, and that the AR and EN files have the same keys.

## 2. Factories (use them in your tests)

All factories are `App\Modules\<M>\Database\Factories\<Model>Factory`, returned by each model's `newFactory()`.

- **Realistic Saudi data** comes from `App\Modules\Identity\Database\Factories\Support\SaudiData`:
  - Arabic company and person names;
  - CR numbers, which match `^\d{10}$` and start with the registry-office prefix;
  - VAT numbers, which match `^3\d{13}3$`;
  - mobiles, which match `^\+9665\d{8}$`;
  - national-address parts;
  - SAR amounts in halalas on whole riyals.
- **The password** for every user and admin is `password`. For OTP codes it is `123456`.
- **Graphs are consistent.**
  - A `Participant` has a joined invitation of the same competition and organization, plus a joining user who is a member of that organization. Its alias is a random free one (1–99, then 100–999).
  - `Offer`, `Award`, `Comment`, `BafoRound`, `CompetitionSponsorship`, `SponsoredPass`, `WebhookDelivery`, `CouponRedemption` and `Invoice` derive their foreign keys from their parent row.
- **`Competition` lifecycle states** are:
  - `scheduled()`;
  - `live()` (phase `initial` with the default 60-minute final window);
  - `inFinalWindow()`;
  - `closed()`;
  - `inBafoRound()`;
  - `awarded()`;
  - `notAwarded()`;
  - `cancelled()`.

  Each state sets the status and the derived publish values of §7.2:
  - `reference_no` from `competition_reference_seq`;
  - `effective_close_at`;
  - `hard_stop_at`;
  - `final_window_starts_at`;
  - `invitation_cutoff_at`;
  - the lifecycle stamps.

  The rule states are `auction()` (the `surplus_sale_auction` rules; the issuer is `auction_enabled`), `sealed()`, `withoutFinalWindow()` and `withBafoRound()`. Chain rule states **before** a lifecycle state: `Competition::factory()->sealed()->closed()`. These states write rows directly; they do not run the Actions, write audit entries or dispatch events.
- **`Offer`** computes `seq` (last + 1), `prev_hash`, `rank_key` and `hash` (the §5.6 formula, microseconds included) when they are not given. `count(n)` chains correctly within the batch. The factory writes only the ledger row; standings and the live state are untouched.
- **Useful states.** Where a state takes a plain token (`sent()`, `invited()`, `withPlainKey()`), only its sha256 is stored.
  - `Organization::factory()->withOwner()`, `->apiEnabled()`, `->auctionEnabled()`, `->sponsorshipEnabled()`, `->incompleteBillingProfile()`.
  - `User::factory()->withMembership($org, OrgRole::Admin)`.
  - `Membership::factory()->owner()`, `->admin()` (both with the §8.1 `can_award` / `can_purchase` defaults), `->invited($plainToken)`.
  - `Invitation::factory()->forOrganization($org)`, `->sent($plainToken)`, `->viewed()`, `->joined()`, `->declined()`, `->revoked()`, `->expired()`.
  - `ApiKey::factory()->withPlainKey(ApiKeyFactory::makePlainKey())`.
  - `Payment::factory()->succeeded()`, `->sponsorship()`.
  - `Subscription::factory()->trial()`, `->grant()`, `->pendingPayment()`, `->expired()`.
  - `Coupon::factory()->voucher($org)`.
  - `Award::factory()->notLeading()`, `->revoked()`.
  - `ParticipantStanding::factory()->withOffer($offer)`.

## 3. Contract gaps (choices made; marked `CONTRACT-GAP` in code where they apply)

1. **Enum ownership.**
   - `Direction` and `Format` live in `Competitions\Enums`; Catalog presets read them.
   - `EntitlementSource` lives in `Billing\Enums`, next to `AccessPolicy::resolveJoin()`, and `participants` casts to it.
   - The offer `channel` uses the kernel enum `App\Support\Auth\Channel`.
2. **`webhook_endpoints.disabled_reason`** (str(20), `manual` / `failing`) is cast to a new enum, `Integrations\Enums\WebhookDisabledReason`. The contract lists the values but names no enum.
3. **`competition_presets.sort_order`** gets `default 0`, like the other lookups; §5.2 gives no default.
4. **`otp_codes` index.** It has only the composite `(email, purpose, created_at)` index, whose leading `email` column serves the "email index" note.
5. **Sequences.** The `competitions` and `invoices` migrations run `DROP SEQUENCE IF EXISTS` before `CREATE SEQUENCE … START 1`, because `migrate:fresh` drops tables but not free-standing sequences. `down()` drops them.
6. **Trigger function.** The `offers` migration re-declares `bafo_forbid_update_delete()` with `CREATE OR REPLACE`, with a body identical to the Platform `audit_logs` migration. The ledger then never depends on migration timing. Platform owns the function; the Bidding `down()` does not drop it.
7. **Triggers** are `FOR EACH ROW` (`offers_append_only`, `offer_voids_append_only`).
8. **Delete rules.** A plain `fk → t` is `ON DELETE RESTRICT` explicitly (`restrictOnDelete()`), as §5.0 says, not Laravel's default `NO ACTION`.
9. **Partial index names:**
   - `memberships_one_owner_per_organization`;
   - `account_deletion_requests_one_pending_per_user`;
   - `awards_one_issued_per_competition`;
   - `sponsored_passes_one_live_per_invitation`;
   - `webhook_events_undispatched_index`.

   CHECK names: `competitions_{start_price,reserve_price,min_step}_minor_positive`, `competitions_single_step_kind` and `offers_amount_minor_positive`.
10. **`notifications`** uses `morphs('notifiable')` as §5.8 says, which adds Laravel's `(notifiable_type, notifiable_id)` index, plus the contract's `(notifiable_type, notifiable_id, read_at)` index.
11. **`webhook_events`** uses `UsesPreciseTimestamps` so that `occurred_at` (tstz6) keeps its microseconds. Its precision-0 columns round them.
12. **Small predicates added to models and enums** (pure, no I/O beyond the loaded relation):
    - `OrgRole::permissions()` is the §8.1 matrix, in `Permission::cases()` order.
    - `User::permissions()` returns `[]` unless the membership is `active`; `User::hasPermission()` builds on it.
    - `Membership::isOwner()` and `Membership::permissions()`.
    - `Direction::sign()`.
    - `ApiScope::descriptions()`, in English for `Passport::tokensCan`, and `ApiScope::defaults()`, the §14.4 UI defaults.
    - `Competition::phaseAt()` (§6.1).
    - `Offer::hashFor()` and `Offer::rankKeyFor()`.
    - `OtpCode::hashCode()`, the HMAC with `app.key`.
    - `Organization::missingBillingProfileFields()` and `Organization::isBillingProfileComplete()` (§5.3).
    - `Subscription::isCurrentAt()`.
    - `ApiKey::isUsableAt()`.
    - `WebhookEndpoint::listensTo()`.
    - `ApiClient::hasScope()`.
13. **Translations.** `HasTranslatedAttributes::translated($attribute, $locale)` reads the `{ar, en}` JSONB columns, falling back to Arabic and then English. It lives in `Catalog\Models\Concerns`. Billing's `Plan`, `PaymentLine` and `InvoiceLine` use it too.
14. **`OfferVoidFactory`** sets `voided_by_admin_id = 1`, a placeholder for an unconstrained ref, because domain modules cannot use the Admin factory. Pass a real admin id when the test needs one. `SubscriptionFactory::grant()` leaves `granted_by_admin_id` null.

## 4. Requests to other owners

**Admin**

- Register the guard and provider in `AdminServiceProvider::register()` (§16) and set `authGuard('admin')` on the panel. `App\Modules\Admin\Models\Admin` is ready: `canAccessPanel()` returns `is_active`.
- `AdminReferenceSeeder` can use `Admin::factory()->superAdmin()`, or `Admin::query()->updateOrCreate(...)`. The `password` cast is `hashed`.

**Identity**

- `User` already has the `membership` relation that `Actor::forUser()` reads.
- Keep `hasPermission()` as the single check (§8.1).

**Integrations**

- `ApiClient::oauthClient()` is a `BelongsTo` on `Laravel\Passport\Client` through `oauth_client_id` (uuid, no FK).
- The client factory leaves `oauth_client_id` null. Create a Passport client in tests that need the OAuth path.

**Every module**

- New columns or indexes go in a **new** migration in your range with a later date prefix (§3.5). Do not edit these files after other modules depend on them.
- Keep `tests/Unit/Schema/Fixtures/columns.php` in step. It is the exact column list per table, and `MigrationsTest` fails when a table drifts from it.

**Platform**

Nothing is blocking. Platform has already done these:

- shipped `File::factory()`;
- shipped `LegalDocumentCode`, which `consents.document_code` now casts to;
- pointed `config/auth.php` at `App\Modules\Identity\Models\User`;
- registered every morph alias. They match these model class names.

## 5. Tests added

These tests are in `tests/Unit/Schema/`. Each file declares `uses(TestCase::class, RefreshDatabase::class)`, because `tests/Pest.php` only extends `Feature`.

| File | Covers |
|---|---|
| `MigrationsTest` | `migrate:fresh` produces each of the 48 tables with exactly the contract columns. Migration file names are inside each module's range. Microsecond precision applies on the precise tables only. Both sequences exist. `ref` columns are unconstrained, and a sample of `fk` delete rules is checked. Every module migration rolls back and re-applies. |
| `FactoriesTest` | Every factory (45 models) creates a persisted row. The main states are covered. Saudi data formats are checked. The derived publish schedule, `phaseAt`, the participant graph and the user ↔ membership ↔ organization links are checked, as is the Sanctum token morph alias `user`. |
| `OfferLedgerTest` | The `offers` trigger rejects UPDATE and DELETE (Eloquent and the query builder), and so does the `offer_voids` trigger. The hash chain is verified against `Offer::hashFor()` with the timestamps read back from the database. The auction `rank_key` is checked. The CHECK and both unique constraints on `offers` are checked. |
| `ConstraintsTest` | The partial uniques: one owner, one pending deletion, one issued award, one live pass. The composite uniques: invitations, participants, vendors, external refs and the payment idempotency key. The CR and e-mail uniques, with lowercasing. The competition CHECKs. RESTRICT and CASCADE deletes. Floats rejected on money columns. |
| `EnumsTest` | AR and EN labels for every enum case; AR/EN key parity of the module lang files; `Direction::sign`; the `OrgRole::permissions` matrix; the `ApiScope` defaults and descriptions; the model predicates |
