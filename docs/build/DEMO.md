# BAFO demo data

The demo scenario of ARCHITECTURE §17. The source of truth is
`apps/api/database/seeders/DemoSeeder.php`: its constants hold the organizations, the users and the
credentials below. Keep this file in sync with it. Local and demo environments only: the seeder refuses
to run in production, and none of these credentials may be reused anywhere real.

## How to load it

```sh
scripts/reset-db.sh --yes            # migrate:fresh --seed, then db:seed --class=DemoSeeder
# or, by hand, from apps/api:
php artisan migrate:fresh --seed     # reference data only (every <Module>ReferenceSeeder)
php artisan db:seed --class=DemoSeeder
```

`DemoSeeder` first re-runs the reference seeders (they are idempotent), then every
`App\Modules\<Module>\Database\Seeders\<Module>DemoSeeder` that exists, in this order:
Platform, Catalog, Identity, Billing, Integrations, Competitions, Bidding, Notifications, Admin.
Module demo seeders are not idempotent: run them on a freshly migrated database.

The seed queues follow-up work on Redis (invoice PDFs, notifications, webhook deliveries, result
reports). Start a queue worker afterwards (`scripts/dev.sh` starts one) so it is processed.

**State of the dev database (QA check, 2026-09-29):** `bafo` is migrated but holds no demo data
(no admin, plans, organizations or users), so the sign-ins below do not work until
`scripts/reset-db.sh --yes` is run. The whole demo chain is verified on the test database by
`tests/Feature/Competitions/CompetitionsDemoSeederTest.php` and `tests/Feature/Admin/DemoDataPanelTest.php`.

## Sign-in

| Who | Where | E-mail | Password |
|---|---|---|---|
| Platform super admin | `http://localhost:8000/admin` | `admin@demo.bafo.test` (or `ADMIN_SEED_EMAIL`) | `Bafo-Admin-2026` (or `ADMIN_SEED_PASSWORD`) |
| Every organization user below | web `http://localhost:3000/ar/auth/login`, mobile | see the table | `Bafo-Demo-2026` |

OTP e-mails go to the `log` mailer. With `OTP_FAKE_CODE=123456` in `apps/api/.env`, every OTP is `123456`.

## Organizations and users

| Key | Organization | CR | City | Plan | Flags | Users (key: role, e-mail) |
|---|---|---|---|---|---|---|
| `issuer` | Issuer Co (شركة الطارح التجريبية) | 1010000001 | الرياض | pro | `api_enabled`, `auction_enabled`, `sponsorship_enabled` | `owner`: owner, `issuer.owner@demo.bafo.test` · `admin`: admin (can award), `issuer.admin@demo.bafo.test` · `member`: member, `issuer.member@demo.bafo.test` |
| `supplier_a` | Supplier A (مورد أ التجريبي) | 1010000002 | جدة | single | – | `owner`: `supplier-a.owner@demo.bafo.test` |
| `supplier_b` | Supplier B (مورد ب التجريبي) | 1010000003 | الدمام | none | – | `owner`: `supplier-b.owner@demo.bafo.test` |
| `supplier_c` | Supplier C (مورد ج التجريبي) | 1010000004 | مكة المكرمة | trial | – | `owner`: `supplier-c.owner@demo.bafo.test` |
| `buyer_d` | Buyer D (المشتري د التجريبي) | 1010000005 | الخبر | plus | – | `owner` (auction bidder): `buyer-d.owner@demo.bafo.test` |

Module demo seeders read these with `DemoSeeder::user('issuer.owner')` and `DemoSeeder::ORGANIZATIONS`.

## What each module seeds

Each module fills in its row when its `<Module>DemoSeeder` lands.

| Module | Demo seeder | Seeds | Status |
|---|---|---|---|
| Platform | `PlatformDemoSeeder` | Reference data: settings defaults and the placeholder legal documents (every code, AR and EN, version `2026-10-01`, published; each body opens with a draft notice in its own language). Demo: **placeholder** `app.store_links` (`https://bafo.example/placeholder/…`) and `app.support` (`support@bafo.example`, dummy phone and WhatsApp `+966500000000`) so `GET /app-config` shows the store button and support contacts in the apps. Replace them with real values in the admin settings before release. On an existing database: `php artisan db:seed --class='App\Modules\Platform\Database\Seeders\PlatformDemoSeeder'`. | done |
| Catalog | – | – (reference data only: `CatalogReferenceSeeder`, 13 regions, 14 categories, 15 close reasons, 3 presets) | done |
| Identity | `IdentityDemoSeeder` | The organizations and users above: verified e-mails, the listed roles and flags, the organization flags, complete billing profiles (VAT registered), categories, terms and privacy consents | done |
| Billing | `BillingDemoSeeder` | The 4 plans. Subscriptions per the `plan` column: Issuer Co pro, Supplier A single and Buyer D plus are paid monthly through the real checkout (fake gateway, approved), each with a cleared tax invoice and PDF. Supplier C has the 30-day trial on plus; Supplier B has none. The public coupon `LAUNCH10` gives 10% off any purchase, once per organization. Competition #12 is built by Competitions with `ConfigureSponsorship` and `GrantSponsoredPass` (see `docs/build/handoff/billing.md`). Test card flow: open the checkout's `redirect_url` (`/pay/fake/{id}`) and choose Approve or Decline. | done |
| Integrations | `IntegrationsDemoSeeder` | For Issuer Co: the API client **ERP demo** (every scope; client secret `bafo-demo-client-secret-0000000000000001`; its `client_id` is the client's id in the dashboard or `GET /api/public/v1/client`), the API key `bafo_test_demo0001_DemoKey0000000000000000000000001` (`bafo_live_…` when `API_KEY_ENV=live`), a webhook endpoint at `http://localhost:9999/webhooks` for every event with the signing secret `whsec_YmFmby1kZW1vLXdlYmhvb2stc2VjcmV0LTMyYnl0ZXM=`, and 4 vendors: Supplier A, B and C (linked to their organizations; SAP supplier keys `100045`–`100047`) and Al Ofoq Supplies (not on BAFO, `100048`). Try it: `curl -H 'Authorization: Bearer <the key>' http://localhost:8000/api/public/v1/client`; the reference is at `http://localhost:8000/docs/api` | done |
| Competitions | `CompetitionsDemoSeeder` | The 12 Issuer Co competitions below (tenders and auctions, every status), their invitations, joined participants, attachments (a PDF and a link) and Q&A on #3. Built through the Competitions Actions; offers, the BAFO round and the award through Bidding's Actions; #12 through Billing's `ConfigureSponsorship` and `GrantSponsoredPass`. Titles: `CompetitionsDemoSeeder::TITLES`, lookup `CompetitionsDemoSeeder::find('<key>')`. | done |
| Bidding | – (no separate seeder) | Offers, standings, the BAFO round and the award for the competitions below are placed by `CompetitionsDemoSeeder` through Bidding's Actions (`SubmitOffer`, `StartBafoRound`, `IssueAward`), so a `BiddingDemoSeeder` would only duplicate them. `tests/Feature/Competitions/CompetitionsDemoSeederTest.php` runs the whole chain and checks every scenario below | done |
| Notifications | `NotificationsDemoSeeder` | Up to 4 in-app notifications per demo user, built from the seeded data (issuer: new offers, participant joined, competition closed; suppliers: invitation, offers open, award; billing users: subscription active, invoice issued). The newest is unread. In-app only: no push, no mail. | done |
| Admin | `AdminReferenceSeeder` (reference, not demo) | The super admin above: `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD`, else `DemoSeeder::ADMIN_EMAIL` / `ADMIN_PASSWORD` in local and testing. It runs with `php artisan db:seed` too; an existing admin is left unchanged. MFA is optional locally (`ADMIN_MFA_REQUIRED=false`); set it up from the profile page | done |

## Competitions of Issuer Co

Reference numbers follow the publish order on a fresh database (`BAFO-T-2026-000001` …); ids differ per run, so look them up with `CompetitionsDemoSeeder::find('<key>')`.

| # | Key | State | Title | Participants and offers | Seeded by |
|---|---|---|---|---|---|
| 1 | `draft` | draft | توريد أجهزة حاسب محمول للإدارة العامة | Draft invitations: Supplier A, Supplier C | Competitions |
| 2 | `scheduled` | scheduled (opens tomorrow) | صيانة وتشغيل المباني الإدارية 2027 | Invited: A (viewed), C, Buyer D; an invitation document | Competitions |
| 3 | `live_initial` | live tender, phase `initial` | توريد مستلزمات مكتبية للعام المالي 2027 | Joined: A, C; invited: D; a PDF and a link; Q&A (A's question with the issuer's reply, C's question) | Competitions |
| 4 | `live_final_window` | live tender, phase `final_window` | توريد أجهزة ومستلزمات طبية للمستودع المركزي | Joined: A, C, D, each with one offer (C leads) | Competitions (+ Bidding's `SubmitOffer`) |
| 5 | `live_auction` | live auction, `show_prices` on | بيع معدات صناعية فائضة | Joined: Buyer D, A; D bids the opening price, A leads | Competitions (+ Bidding) |
| 6 | `live_sealed` | live sealed | تطوير بوابة إلكترونية لخدمات العملاء (عروض مغلقة) | Joined: A, C; one sealed offer (A) | Competitions (+ Bidding) |
| 7 | `closed` | closed (evaluation) | نقل وتخزين البضائع بين المستودعات | Joined: A, C, D, each with one offer | Competitions (+ Bidding) |
| 8 | `bafo_round` | `bafo_round` (2 hours) | توريد خوادم ومعدات مركز البيانات | Offers from A, C, D; shortlist C and D | Competitions (+ Bidding's `StartBafoRound`) |
| 9 | `awarded` | awarded | توريد أثاث مكتبي للمقر الرئيسي | Offers from A, C; awarded to C (the leading offer, reserve met) | Competitions (+ Bidding's `IssueAward`) |
| 10 | `not_awarded` | not_awarded (auction) | بيع خردة حديد ومعادن | Joined: D (one offer); invited: A; reason `not_awarded_prices_above_budget` | Competitions |
| 11 | `cancelled` | cancelled (was scheduled) | خدمات تسويق وطباعة المواد الترويجية | Invitations expired; reason `cancel_budget_withdrawn` | Competitions |
| 12 | `sponsored_live` | sponsored live tender | خدمات النظافة للفروع (رسوم مغطّاة) | Sponsorship `selected`; Supplier B joined on a granted pass, A on its plan | Competitions (+ Billing's Actions) |
