# BAFO HTTP API contract

Status: **binding**. It is part of the same contract as `ARCHITECTURE.md` (rules, schema, state machines) and `CONVENTIONS.md` (the master error-code list). Where this file shows a field, the name, type and presence are exact. Where it shows an example value, the value is illustrative.

| Section | Contents |
|---|---|
| §0 | Conventions |
| §1 | App API v1 (`/api/app/v1`): web dashboard and mobile apps |
| §2 | Resource shapes (app v1) |
| §3 | Public API v1 (`/api/public/v1`): ERP integrations |
| §4 | Webhooks |
| §5 | Realtime payload reference |

---

## 0. Conventions

### 0.1 Base URLs (local)

| Surface | Base URL | Auth |
|---|---|---|
| App API v1 | `http://localhost:8000/api/app/v1` (Android emulator: `http://10.0.2.2:8000/api/app/v1`) | Sanctum personal access token: `Authorization: Bearer <token>` |
| Public API v1 | `http://localhost:8000/api/public/v1` | OAuth2 client-credentials access token, or an API key: `Authorization: Bearer <token-or-key>` |
| Channel auth | `http://localhost:8000/broadcasting/auth` | Sanctum bearer |
| Realtime | `ws://localhost:8085` (Reverb, Pusher protocol) | via channel auth |

### 0.2 Request headers

| Header | Where | Rule |
|---|---|---|
| `Accept: application/json` | all | Sent by clients (the server forces JSON anyway) |
| `Content-Type` | writes | `application/json`, or `multipart/form-data` for uploads |
| `Accept-Language` | all | `ar` (default) or `en`. It localises `message`, lookup names, rules summaries and in-app notification text. |
| `Authorization` | authenticated | `Bearer …`. **Tokens never appear in URLs or query strings.** |
| `X-Platform` | app v1 | `web`, `ios` or `android`. Web sends `web`. It drives version checks, the offer `channel` and the purchase ban on mobile. |
| `X-App-Version` | app v1 mobile | semver, for example `1.0.0`. Below the minimum → **426** `app_version_unsupported`. |
| `X-Request-Id` | optional | Echoed back; generated when absent |
| `Idempotency-Key` | where stated | 8–64 chars `[A-Za-z0-9_-]`, one per user intent (generate a UUID or ULID). **Required** for `POST …/offers` and for public API POSTs that create or act. Optional on checkout. |

### 0.3 Response headers

`X-Request-Id`, `Content-Language`, `Retry-After` (on 429 and 503), `Idempotent-Replayed: true` (on replays).

### 0.4 Envelopes

**Success:**

```json
{ "data": { }, "meta": { "server_time": "2026-10-01T09:00:00.000Z" } }
```

- `data` is an object, an array, or `null`.
- `meta` is always an object. **App v1 always includes `meta.server_time`.**
- 204 responses have no body.

**Error** (all non-2xx):

```json
{
  "message": "لا يمكن تقديم عرض أقل من الحد الأدنى للتخفيض.",
  "code": "offer_step_not_met",
  "errors": {},
  "details": { "required_amount_minor": 9750000 }
}
```

- `message` is localised and human-readable. Clients may show it directly, or map `code` to their own i18n key `errors.<code>`.
- `code` is stable and snake_case. The master list is in CONVENTIONS.md §8.
- `errors` is always present; it maps a field path to messages (`{}` when there are none). Array fields use dotted indexes, for example `invitations.2.email`.
- `details` is present **only when non-empty**. It carries machine-readable extras that each endpoint documents.

**Standard errors** (any endpoint, not repeated below):

| Code | Status | Applies to |
|---|---|---|
| `validation_failed` | 422 | All |
| `unauthenticated` | 401 | App v1, authenticated routes |
| `forbidden` | 403 | All |
| `not_found` | 404 | All |
| `too_many_requests` | 429 | All |
| `server_error` | 500 | All |
| `maintenance` | 503 | App v1 |
| `app_version_unsupported` | 426 | App v1 |
| `account_inactive`, `email_not_verified`, `organization_suspended` | 403 | App v1, authenticated routes |
| `invalid_token` | 401 | Public v1 |
| `insufficient_scope` | 403 | Public v1 |
| `api_access_disabled` | 403 | Public v1 |

### 0.5 Pagination

**Page-based** (app v1 lists): `?page=1&per_page=20` (default 20, max 100).

```json
"meta": { "pagination": { "type": "page", "current_page": 1, "per_page": 20, "has_more": true, "total": 57, "last_page": 3 }, "server_time": "…" }
```

**Cursor-based** (public v1 lists): `?per_page=50&cursor=<opaque>` (default 50, max 200). Ordering is stable: `updated_at, id` ascending.

```json
"meta": { "pagination": { "type": "cursor", "per_page": 50, "next_cursor": "eyJ…", "prev_cursor": null, "has_more": true } }
```

**Sequence-based** (offer logs only): `?after_seq=0&limit=200` (max 500). The response carries `meta.last_seq` and `meta.has_more`.

### 0.6 Data types

| Type | Rule |
|---|---|
| **IDs** | `id` is a **26-char lowercase ULID** (`public_id`). Input ids are accepted in any case. Unknown or malformed ids → 404. |
| **Timestamps** | RFC 3339 UTC with milliseconds and `Z`: `2026-10-01T12:59:58.412Z`. Inputs accept any RFC 3339 offset and are converted to UTC. Dates: `YYYY-MM-DD`. |
| **Money** | Integer halalas in fields ending in `_minor`, plus `currency: "SAR"`. Prices are **excl. VAT**. VAT rate is in basis points (`vat_rate_bp: 1500` = 15%). **Never floats or strings.** |
| **Percentages** | Basis points as integers (`*_bps`; 50 = 0.5%) |
| **Enums** | Lowercase snake_case strings, listed per field |
| **Nulls and booleans** | Absent optional values are `null`, never omitted, unless a field is explicitly "omitted when …". Booleans are `true` or `false`. |
| **Localised lookups** | In app v1, `name` is a string in the request locale. In public v1, `name` is `{"ar": "…", "en": "…"}`. |
| **Phones** | E.164 Saudi mobile: `+9665XXXXXXXX` (regex `^\+9665\d{8}$`) |
| **CR** | `^\d{10}$` |
| **VAT** | `^3\d{13}3$` |

### 0.7 Files

- **Uploads** are `multipart/form-data` with a `file` part (plus the documented fields). Limits are in ARCHITECTURE §4.6. Wrong type → 422 `file_type_not_allowed`; too big → 422 `file_too_large`.
- **Private downloads** use `GET /api/app/v1/files/{file}/download` (or the resource-specific path given) with the bearer header. The response streams with `Content-Disposition: attachment`. Web clients fetch it as a blob and save it.
- **Public images** (logos, avatars) come as absolute URLs (`logo_url`, `avatar_url`).

### 0.8 Rate limits (per route group)

| Limiter | Limit | Used by |
|---|---|---|
| `app` | 300 per minute per user (guests: per IP) | all of app v1 |
| `auth` | 10 per minute per IP and 5 per minute per e-mail | login, register, OTP, password, team-invitation, invitation lookup, claim |
| `guest-forms` | 5 per hour per IP | contact |
| `guest-actions` | 10 per minute per IP | invitation decline-by-token |
| Offers | 1 per 2 s per participant (engine) and 60 per minute per user | `POST …/offers` |
| `public-api` / `oauth-token` | ARCHITECTURE §14.3 | public v1 |

**Bot protection on guest forms.** Register, contact and invitation lookup accept a honeypot field `website_url`, which must be absent or empty. A non-empty value → 422 `validation_failed` on `website_url`.

### 0.9 Notation in this document

- Each endpoint shows: **auth** (`guest` / `user` / `perm:<permission>` / `scope:<scope>`), **request fields** (type · rules), **response**, **errors** (beyond the standard ones) and the **route name**.
- "Returns `X`" means `data` is resource `X` from §2.
- Field rules use Laravel validation vocabulary.

---

## 1. App API v1 (`/api/app/v1`)

Route files and owners:

| File | Module |
|---|---|
| `routes/app_v1/platform.php` | Platform |
| `catalog.php` | Catalog |
| `identity.php` | Identity |
| `competitions.php` | Competitions |
| `bidding.php` | Bidding |
| `billing.php` | Billing |
| `notifications.php` | Notifications |
| `integrations.php` | Integrations |

**Release scope** (`RELEASE_SCOPE.md` §1): the admin setting `platform.release_scope` (`core` by default, or `full`) derives the boolean `features.flags` of `GET /app-config` (§2.13). A route of a hidden feature carries `feature:<flag>` and answers **404 `feature_disabled`** with `details.feature = "<flag>"` before binding and authorization. A hidden value on an otherwise visible endpoint is a **422 `validation_failed`** with the shared message `errors.feature_disabled_field` («هذا الخيار غير متاح في هذا الإصدار.» / "This option is not available in this release.") on the field path. The per-flag rows are `RELEASE_SCOPE.md` §1.5; the endpoints below note them where they apply.

### 1.1 Platform

| Method and path | Auth | Name | Summary |
|---|---|---|---|
| `GET /app-config` | guest | `app.v1.app-config` | Returns `AppConfig` (§2.13). Not blocked by maintenance or version checks. |
| `GET /time` | guest | `app.v1.time` | `{"server_time": "…"}` |
| `GET /health` | guest | `app.v1.health` | `{"status": "ok", "checks": {"database": "ok", "redis": "ok", "queue": "ok"}}`; 503 with the same shape when degraded |
| `POST /contact` | guest, `guest-forms` | `app.v1.contact.store` | Contact form |
| `GET /legal/{code}` | guest | `app.v1.legal.show` | The latest **published** legal document in the request locale |
| `GET /files/{file}/download` | user | `app.v1.files.download` | Streams a private file. Allowed by `FileAccessRegistry`, otherwise 403. |

**`POST /contact`**

| Field | Rules |
|---|---|
| `name` | required, string, max:150 |
| `email` | required, email, max:255 |
| `phone` | nullable, E.164 KSA |
| `company` | nullable, max:150 |
| `subject` | required, max:150 |
| `message` | required, max:5000 |
| `website_url` | honeypot |

Response 201 `{"id": "01j…"}`.

**`GET /legal/{code}`**: `code` is one of `terms`, `privacy`, `refund`, `competition_rules` or `api_terms`.

```json
{"data": {"code": "terms", "locale": "ar", "version": "2026-10-01", "title": "الشروط والأحكام", "body_markdown": "…", "published_at": "…"}}
```

Error: 404 `not_found` when no published version exists.

### 1.2 Catalog

| Method and path | Auth | Name | Returns |
|---|---|---|---|
| `GET /lookups` | guest | `app.v1.lookups.index` | `{"regions": [Region], "categories": [Category], "close_reasons": [CloseReason], "presets": [Preset]}`. Active rows only, ordered by `sort_order`. Presets follow the release scope: a sealed preset needs `sealed_format`, a BAFO-round preset `bafo_round`, a final-window preset `final_pricing_window`, an untiered preset (`tier = null`) `advanced_rules` (so `core` lists the six tier presets only). Sends `ETag` over the body (a scope change changes it); `If-None-Match` → 304. |
| `GET /lookups/{type}` | guest | `app.v1.lookups.show` | `type` ∈ `regions`, `categories`, `close-reasons`, `presets` → the array. `close-reasons` accepts `?kind=cancel\|not_awarded\|award_justification\|void_offer`. |

### 1.3 Identity

#### Authentication

**`POST /auth/register`** · guest, `auth`, honeypot · `app.v1.auth.register`

| Field | Rules |
|---|---|
| `name` | required, max:150 (the contact person) |
| `email` | required, email:rfc, max:255, unique among non-deleted users (422 `validation_failed` on `email`, message key `identity.validation.email_taken`) |
| `phone` | required, E.164 KSA |
| `password`, `password_confirmation` | required, confirmed, the password rule (ARCHITECTURE §13.9) |
| `locale` | nullable, in:ar,en (default from `Accept-Language`) |
| `organization.name` | required, max:150 |
| `organization.cr_number` | required, `^\d{10}$`, unique (422 `validation_failed` on `organization.cr_number`, key `identity.validation.cr_number_taken`) |
| `organization.region_id` | required, an active region id |
| `organization.city` | required, max:100 |
| `organization.vat_registered` | boolean, default false |
| `organization.vat_number` | required_if vat_registered, `^3\d{13}3$` |
| `organization.legal_name_ar` | nullable, max:200 |
| `organization.legal_name_en` | nullable, max:200 |
| `organization.website` | nullable, url (https) |
| `organization.national_address.{building_number, street, district, postal_code, additional_number, short_address}` | all nullable (4 digits / max:150 / max:150 / 5 digits / 4 digits / `^[A-Z]{4}\d{4}$`) |
| `organization.category_ids[]` | nullable, array of category ids (max 20) |
| `organization.visible_in_suggestions` | boolean, default true |
| `accept_terms`, `accept_privacy` | required, accepted |
| `invitation_token` | nullable, string (from the invitation link fragment) |
| `website_url` | honeypot |

Response 201:

```json
{"data": {"email": "owner@acme.sa", "verification_required": true, "otp_expires_at": "2026-10-01T09:10:00.000Z"}}
```

Errors: `invitation_email_mismatch` (422). An unknown or expired `invitation_token` → 422 `validation_failed` on `invitation_token`.

**`POST /auth/otp/send`** · guest, `auth` · `app.v1.auth.otp.send`

- Request: `email` (required, email) and `purpose` (in:email_verification,password_reset).
- Response 202 `{"otp_expires_at": "…"}`. The answer is the same for unknown, already verified or pending addresses (no mail is sent to them): a plausible expiry, and the same 60-second cooldown and hourly cap (SECURITY_REVIEW S-01). Clients keep accepting `null` from older servers.
- Errors: `otp_resend_cooldown` (429, `details.retry_after_seconds`).

**`POST /auth/otp/verify`** · guest, `auth` · `app.v1.auth.otp.verify`

- Request:

  | Field | Rules |
  |---|---|
  | `email` | required |
  | `code` | required, digits:6 |
  | `purpose` | in:email_verification |
  | `device_name` | required, max:120 (e.g. "Pixel 8 · android") |

- Returns `AuthTokenPayload` (§2.3), 200.
- Errors: `otp_invalid` (422), `otp_expired` (422), `otp_too_many_attempts` (429).

**`POST /auth/otp/check`** · guest, `auth` · `app.v1.auth.otp.check`

- Request: `email`, `code` and `purpose` (in:password_reset).
- Response 200 `{"valid": true}`. The code is not consumed.
- Errors: as for verify.

**`POST /auth/login`** · guest, `auth` · `app.v1.auth.login`

- Request: `email`, `password` and `device_name` (all required).
- Returns `AuthTokenPayload`.
- Errors: `invalid_credentials` (401), `email_not_verified` (403, `details.otp_expires_at`; an OTP was sent), `account_inactive` (403), `organization_suspended` (403).

**`POST /auth/logout`** · user · `app.v1.auth.logout` → 204. It deletes the current token.

**`POST /auth/password/forgot`** · guest, `auth` · `app.v1.auth.password.forgot`: `email` → 202 `{}`.

**`POST /auth/password/reset`** · guest, `auth` · `app.v1.auth.password.reset`

- Request: `email`, `code`, `password` and `password_confirmation`.
- Response 204. **All tokens of the user are revoked.**
- Errors: `otp_invalid`, `otp_expired`, `otp_too_many_attempts`.

**`POST /auth/team-invitations/lookup`** · guest, `auth` · `app.v1.auth.team-invitations.lookup`

- Request: `token`.
- Response 200:

  ```json
  {"email": "m@acme.sa", "name": "Mona", "organization": {"name": "Acme", "logo_url": null}, "role": "member", "expires_at": "…"}
  ```

- Errors: `team_invitation_invalid` (422).

**`POST /auth/team-invitations/accept`** · guest, `auth` · `app.v1.auth.team-invitations.accept`

- Request: `token`, `password`, `password_confirmation`, `device_name`, and `accept_terms` (accepted).
- Returns `AuthTokenPayload`.
- Errors: `team_invitation_invalid`.

#### Current user and organization

| Method and path | Auth | Name | Request | Returns / notes |
|---|---|---|---|---|
| `GET /me` | user | `app.v1.me.show` | – | `Me` (§2.3) |
| `PATCH /me` | user | `app.v1.me.update` | `name` (sometimes, max:150), `phone` (sometimes, E.164), `locale` (sometimes, in:ar,en) | `Me` |
| `PUT /me/password` | user | `app.v1.me.password` | `current_password`, `password`, `password_confirmation` | 204. Revokes all **other** tokens. Error: `password_incorrect` (422). |
| `POST /me/avatar` | user | `app.v1.me.avatar.store` | multipart `file` (png, jpg, jpeg, webp; ≤ 2 MB) | `Me` |
| `DELETE /me/avatar` | user | `app.v1.me.avatar.destroy` | – | `Me` |
| `GET /organization` | user | `app.v1.organization.show` | – | `Organization` (own view, §2.2) |
| `PATCH /organization` | perm:`organization.update` | `app.v1.organization.update` | Any of the `organization.*` register fields **except `cr_number`**, which is immutable (sending it → 422 `validation_failed` on `cr_number`) | `Organization` |
| `POST /organization/logo` / `DELETE` | perm:`organization.update` | `app.v1.organization.logo.store` / `.destroy` | multipart `file` (images ≤ 2 MB) | `Organization` |
| `POST /organization/profile-document` / `DELETE` | perm:`organization.update` | `app.v1.organization.profile-document.store` / `.destroy` | multipart `file` (pdf ≤ 20 MB) | `Organization` |

#### Team (branch users)

| Method and path | Auth | Name | Request | Returns |
|---|---|---|---|---|
| `GET /team/members` | perm:`team.manage` | `app.v1.team.members.index` | `?status=invited\|active\|inactive` | `[Membership]` (§2.3). Not paginated (≤ 50) plus `meta.seats = {"used": 3, "total": 3}`. |
| `POST /team/members` | perm:`team.manage` | `app.v1.team.members.store` | `name` (required), `email` (required, unique among users → 422 `validation_failed` on `email`), `phone` (nullable), `role` (in:admin,member), `can_award` (bool, nullable), `can_purchase` (bool, nullable) | 201 `Membership` (status `invited`). Error: `seat_limit_reached` (409, `details.seats`). |
| `PATCH /team/members/{membership}` | perm:`team.manage` | `app.v1.team.members.update` | `role`, `can_award`, `can_purchase`, `status` (in:active,inactive), each `sometimes` | `Membership`. Errors: `cannot_modify_owner` (409), `cannot_modify_self` (409), `seat_limit_reached` (409). |
| `DELETE /team/members/{membership}` | perm:`team.manage` | `app.v1.team.members.destroy` | – | 204. Errors: `cannot_modify_owner`, `cannot_modify_self`. |
| `POST /team/members/{membership}/resend-invitation` | perm:`team.manage` | `app.v1.team.members.resend` | – | 204. Error: `invalid_state_transition` (409) if the member is not invited. |

#### Account deletion (store requirement)

| Method and path | Auth | Name | Request | Returns |
|---|---|---|---|---|
| `POST /account/deletion` | user | `app.v1.account.deletion.store` | `password` (required), `reason` (nullable, max:1000) | 201 `AccountDeletionRequest` `{id, scope: "user"\|"organization", status: "pending", scheduled_for, created_at}`. Errors: `password_incorrect` (422), `account_deletion_blocked` (409, `details.blockers = [{"type": "issued_competition"\|"participation", "competition_id": "…", "title": "…"}]`), `account_deletion_pending` (409). |
| `GET /account/deletion` | user | `app.v1.account.deletion.show` | – | `AccountDeletionRequest` or `null` |
| `DELETE /account/deletion` | user | `app.v1.account.deletion.destroy` | – | 204 (cancels the pending request) |

### 1.4 Competitions (issuer and shared)

**`GET /home`** · user · `app.v1.home`

- Returns `Home` (§2.13).

**`GET /competitions`** · user · `app.v1.competitions.index`

| Query | Rules |
|---|---|
| `role` | **required**, in:issuer,participant |
| `status` | nullable, a comma list of competition statuses |
| `status_group` | nullable, in:active,draft,ended,all (default `all`). `active` = scheduled, live, closed, bafo_round; `draft` = draft; `ended` = awarded, not_awarded, cancelled. |
| `direction` | nullable, in:tender,auction |
| `q` | nullable, max:100 (title search, case-insensitive) |
| `sort` | in:-updated_at,effective_close_at,-created_at (default `-updated_at`) |
| `page`, `per_page` | – |

- **`role=issuer`**: the caller organization's competitions, as `[CompetitionListItem]` (issuer variant, §2.6).
- **`role=participant`**: competitions where the caller's organization has an invitation in status sent, viewed, joined, declined or expired, as `[CompetitionListItem]` (participant variant, with `invitation` and `access`). Drafts and revoked invitations never appear.

**`POST /competitions`** · perm:`competitions.create` · `app.v1.competitions.store`

| Field | Rules |
|---|---|
| `title` | required, max:200 |
| `description` | nullable, max:20000 |
| `category_id` | required, active category |
| `category_other_text` | required_if the category is_other, max:150 |
| `region_id` | required, active region |
| `direction` | required, in:tender,auction (auction → org `auction_enabled` else 403 `auction_not_enabled`; category `auction_allowed` else 422 on `category_id`) |
| `format` | required, in:live,sealed |
| `preset_code` | nullable (**required** while `advanced_rules` is off), an active preset whose direction and format equal the request; its `rules` fill every rules key not sent. A preset `GET /lookups` does not offer in the scope → 422 with `errors.feature_disabled_field` on `preset_code` |
| `rules` | object, `RulesInput` (§2.6). Keys absent and not supplied by a preset take the column defaults. Validated with ARCHITECTURE §7.2 "save" rules. |
| `bidding_opens_at` | nullable, date (RFC 3339) |
| `scheduled_close_at` | nullable, date, after:bidding_opens_at |

- Response 201 `Competition` (issuer projection, status `draft`).
- Errors: `issuer_plan_required` (403), `auction_not_enabled` (403).
- **Release-scope field refusals** (422 `validation_failed`, `errors.feature_disabled_field` on the path): `format = sealed` (`sealed_format`); `rules.bafo_round.enabled = true` (`bafo_round`); `rules.final_window_minutes != null` (`final_pricing_window`); `rules.reserve_price_minor != null`, `rules.amount_granularity_minor = 1`, and `rules.result_publication` / `rules.min_participants` other than the preset's value (`advanced_rules`). The same rules apply to `PATCH`, except that a value the competition already has is accepted (a record created in `full` keeps saving its other fields).

**`GET /competitions/{competition}`** · user · `app.v1.competitions.show`

- Returns `Competition`, projected for the caller (`viewer_role` = issuer, participant or invitee). Anyone else → 404.
- **Side effect:** an invitee's first view sets the invitation to `viewed`.

**`PATCH /competitions/{competition}`** · issuer, `competitions.manage` · `app.v1.competitions.update`

| Status | Fields accepted |
|---|---|
| `draft` | everything accepted by create (`direction` and `format` included) |
| `scheduled` | `title`, `description`, `category_id`, `category_other_text`, `region_id`, `bidding_opens_at`, `scheduled_close_at`. Schedule changes recompute the derived times and re-check R16 at publish-time strictness. |
| `live` | `title`, `description` |
| others | nothing → 409 `competition_not_editable` |

- Any field not allowed in the current status → 409 `competition_not_editable` with `details.fields = [..]`.
- Release-scope field refusals as for create, for values that differ from the stored ones.
- Returns `Competition`.

**`DELETE /competitions/{competition}`** · issuer, `competitions.manage` · `app.v1.competitions.destroy`

- Draft only (soft delete) → 204.
- Otherwise 409 `competition_not_editable`. Use cancel.

**`POST /competitions/{competition}/publish`** · issuer, `competitions.manage` · `app.v1.competitions.publish`

- Body: `{}`.
- Runs ARCHITECTURE §7.2 publish checks, then T1 or T2.
- Returns `Competition` (status `scheduled` or `live`).

| Error | Status | Details |
|---|---|---|
| `invalid_state_transition` | 409 | – |
| `validation_failed` | 422 | field errors: `description`, `bidding_opens_at`, `scheduled_close_at`, `start_price_minor`, `category_other_text`, `rules.*` |
| `min_participants_not_met` | 422 | `required`, `current` |
| `issuer_plan_required` | 403 | – |
| `live_event_capacity_reached` | 409 | – |
| `sponsorship_payment_required` | 409 | `quote` = `SponsorshipQuote` |

**`POST /competitions/{competition}/extend`** · issuer, `competitions.manage` · `app.v1.competitions.extend`

- Request: `new_close_at` (required, date) and `reason` (required, 5–1000).
- Returns `Competition`.
- Errors: `invalid_state_transition` (409; not live), `extend_invalid` (422; field `new_close_at`, `details.min_new_close_at`), `feature_disabled` (404; `extend_competition` off).

**`POST /competitions/{competition}/cancel`** · issuer, `competitions.manage` · `app.v1.competitions.cancel`

- Request: `close_reason_id` (required, an active close reason of kind `cancel`) and `note` (required when the reason `requires_note`, max:1000).
- Allowed in scheduled, live or bafo_round → `cancelled`.
- Returns `Competition`.
- Errors: `invalid_state_transition`.

**`POST /competitions/{competition}/close`** (close without award) · issuer, `competitions.award` · `app.v1.competitions.close`

- Request: `close_reason_id` (required, kind `not_awarded`) and `note` (as for cancel).
- Status `closed` → `not_awarded`.
- Returns `Competition`.
- Errors: `invalid_state_transition`.

**`GET /competitions/{competition}/suggestions`** · issuer, `competitions.manage` · `app.v1.competitions.suggestions`

- Query: `q` (nullable, name search), `category_id` (default the competition's), `region_id` (default the competition's), `limit` (≤ 50, default 20).
- **Candidates:** active organizations with `visible_in_suggestions` that share the category (through `organization_category`) **or** the region, excluding the issuer's own organization and organizations already invited. Ordered: category and region match first, then name.
- Returns:

  ```json
  [{"organization": {"id": "…", "name": "…", "logo_url": null, "verified": true},
    "region": Region, "categories": [Category], "has_active_plan": true,
    "match": {"category": true, "region": false}}]
  ```

**`GET /competitions/{competition}/invitations`** · issuer · `app.v1.competitions.invitations.index`

- Query: `status` (comma list).
- Returns `[Invitation]` (issuer view, §2.7). Not paginated (≤ 200).
- `meta.counts = {"draft": 0, "sent": 3, "viewed": 1, "joined": 4, "declined": 0, "revoked": 0, "expired": 0}`.

**`POST /competitions/{competition}/invitations`** · issuer, `competitions.manage` · `app.v1.competitions.invitations.store`

| Field | Rules |
|---|---|
| `invitations` | required, array, min:1, max:100 |
| `invitations.*.email` | required_without_all:organization_id,vendor_id, email |
| `invitations.*.organization_id` | nullable, from suggestions |
| `invitations.*.vendor_id` | nullable, one of the caller organization's vendors (present while `vendor_directory` is off → 422 `errors.feature_disabled_field` on the path) |
| `invitations.*.name` | nullable, max:150 |
| `invitations.*.sponsored` | boolean, default false (counts only in `selected` sponsorship mode; `true` while `sponsorship` is off → 422 `errors.feature_disabled_field` on the path, as for `PATCH …/invitations/{invitation}` unless the invitation is already sponsored) |

- Response 201 `[Invitation]`: status `draft` on a draft competition, `sent` otherwise.

| Error | Status | Notes |
|---|---|---|
| `validation_failed` | 422 | Per-item problems. `errors."invitations.{i}.<field>"` holds the messages, and `details.item_codes` maps the same paths to machine codes: `invitation_duplicate`, `cannot_invite_own_organization`, `vendor_blocked`, `vendor_not_found`. **Nothing is created** (all-or-nothing). |
| `invitation_cutoff_passed` | 409 | – |
| `max_participants_exceeded` | 422 | – |
| `sponsorship_payment_required` | 409 | `details.quote`. Nothing is created: use sponsorship checkout with `intent: invite`. |
| `invalid_state_transition` | 409 | competition not in draft, scheduled or live |

**`PATCH /competitions/{competition}/invitations/{invitation}`** · issuer, `competitions.manage` · `app.v1.competitions.invitations.update`

- Request: `name`, `sponsored`.
- Allowed while the invitation is `draft`, or (for `sponsored`) before a pass exists.
- Returns `Invitation`.
- Errors: `invalid_state_transition`.

**`DELETE /competitions/{competition}/invitations/{invitation}`** · issuer, `competitions.manage` · `app.v1.competitions.invitations.destroy`

- **draft** → hard delete (204).
- **sent or viewed** → `revoked` (200 `Invitation`).
- **Otherwise** → 409 `invalid_state_transition`.

**`POST /competitions/{competition}/invitations/{invitation}/resend`** · issuer, `competitions.manage` · `app.v1.competitions.invitations.resend`

- Allowed for `sent` or `viewed`, before the cutoff.
- A new token is issued (the old one is invalidated) and the mail is re-sent → 204.
- At most 3 per invitation per day → 429.

**`GET /competitions/{competition}/attachments`** · issuer / participant / invitee · `app.v1.competitions.attachments.index`

- Returns `[Attachment]` visible to the viewer (ARCHITECTURE §8.5). Invitees get only `invitation_document`.

**`POST /competitions/{competition}/attachments`** · issuer, `competitions.manage` · `app.v1.competitions.attachments.store`

- **File attachment** (multipart): `file` (required; pdf, doc, docx, xls, xlsx, png, jpg, jpeg or zip; ≤ 100 MB), `kind` (in:document,invitation_document), `title` (nullable, max:200).
- **Link** (JSON): `kind` = external_link, `url` (required, https url, max:1000), `title` (required, max:200).
- Allowed in draft, scheduled or live. After publish it sets `is_addendum = true` and dispatches `AttachmentAdded`.
- Response 201 `Attachment`.
- Errors: `file_type_not_allowed`, `file_too_large`, `competition_not_editable` (409).

**`PATCH /competitions/{competition}/attachments/{attachment}`** · issuer, `competitions.manage` · `app.v1.competitions.attachments.update`

- Request: `title`, `sort_order`.
- Returns `Attachment`.

**`DELETE /competitions/{competition}/attachments/{attachment}`** · issuer, `competitions.manage` · `app.v1.competitions.attachments.destroy`

- Draft or scheduled only → 204. Otherwise 409 `competition_not_editable`.

**`GET /competitions/{competition}/comments`** · issuer / participant · `app.v1.competitions.comments.index`

- Returns `[Comment]` (top-level, newest first, each with `replies` oldest first), paginated by top-level comment.

**`POST /competitions/{competition}/comments`** · issuer (any member) / participant · `app.v1.competitions.comments.store`

- Request: `body` (required, 1–2000) and `parent_id` (nullable, a top-level comment of this competition).
- **Rules:**
  - participants may post top-level questions, or replies under their own organization's questions;
  - the issuer may reply to any comment or post top-level announcements;
  - the competition must be `scheduled` or `live`, otherwise 409 `comments_closed`.
- Response 201 `Comment`.

### 1.5 Invitations (invitee side)

**`POST /invitations/lookup`** · guest, `auth`, honeypot · `app.v1.invitations.lookup`

- Request: `token` (required).
- Returns `InvitationLookup`:

  ```json
  {"invitation": {"id": "…", "status": "sent", "email_masked": "s***@acme.sa", "join_deadline": "…", "sponsored": true},
   "competition": CompetitionTeaser,
   "next_step": "register"}
  ```

  `next_step` is `register` or `login`.
- Errors: `invitation_invalid` (404).
- **Side effect:** `sent` → `viewed`.

**`POST /invitations/decline`** · guest, `guest-actions` · `app.v1.invitations.decline-by-token`

- Request: `token` and `reason` (nullable, max:500).
- Response 200 `{"status": "declined"}`.
- Errors: `invitation_invalid` (404), `invalid_state_transition` (409).

**`POST /invitations/claim`** · user, `auth` · `app.v1.invitations.claim`

- Request: `token` (required) and `code` (nullable, digits:6).
- **Responses:**
  - 200 `Invitation` (invitee view) when bound;
  - 202 `{"otp_sent_to": "s***@acme.sa", "otp_expires_at": "…"}` when an OTP was sent to the invited e-mail.
- **Errors:** `invitation_invalid` (404), `invitation_belongs_to_another_organization` (409), `otp_invalid`, `otp_expired`, `otp_too_many_attempts`.

**`POST /invitations/{invitation}/join`** · user (any active member of the invited organization) · `app.v1.invitations.join`

- Request: `accept_terms` (required, accepted).
- Returns `Competition` (participant projection).
- **Errors:**
  - `terms_not_accepted` (422);
  - `invalid_state_transition` (409);
  - `join_deadline_passed` (409);
  - `already_participating` (409);
  - `plan_required` (403, with `details.access = ParticipationAccess`; the web shows plans, iOS and Android show information only).

**`POST /invitations/{invitation}/decline`** · user · `app.v1.invitations.decline`

- Request: `reason` (nullable, max:500).
- Returns `Invitation` (invitee view).

### 1.6 Bidding

**`GET /competitions/{competition}/live`** · issuer / participant · `app.v1.competitions.live`

- Returns `IssuerLiveSnapshot` or `ParticipantLiveSnapshot` (§2.8), depending on the viewer. Available from publish onward.
- Invitees → 403 `not_a_participant`.
- **Cache-Control:** `no-store`.

**`POST /competitions/{competition}/live/heartbeat`** · participant · `app.v1.competitions.live.heartbeat`

- Response 204. It sets the presence TTL (ARCHITECTURE §9.5).
- At most 1 per 10 s per user (excess calls are also answered 204).

**`POST /competitions/{competition}/offers`** · participant, perm:`participation.submit_offers` · `throttle:offers` · header **`Idempotency-Key` required** · `app.v1.competitions.offers.store`

| Field | Rules |
|---|---|
| `amount_minor` | required, integer, min:1 |
| `confirm_outlier` | boolean, default false |

The acceptance algorithm and its order are exactly ARCHITECTURE §7.4.

Response **201** (or **200** on an idempotent replay):

```json
{
  "data": {
    "offer": {"id": "01jb…", "seq": 57, "amount_minor": 9850000, "stage": "live", "accepted_at": "2026-10-01T12:59:58.412Z", "voided": false},
    "live": { "…": "ParticipantLiveSnapshot (§2.8)" }
  },
  "meta": {"server_time": "2026-10-01T12:59:58.530Z"}
}
```

| Error | Status | `details` |
|---|---|---|
| `idempotency_key_required` | 400 | – |
| `idempotency_key_reused` | 422 | – |
| `not_a_participant` | 403 | – |
| `too_many_requests` | 429 | `retry_after_seconds` (+ `Retry-After` header) |
| `offer_amount_invalid` | 422 | – |
| `offer_amount_too_large` | 422 | `max_amount_minor` |
| `offer_granularity` | 422 | `granularity_minor` |
| `offer_not_accepting` | 409 | `status`, `opens_at` (when not yet open) |
| `offer_closed` | 409 | `closed_at` |
| `offer_not_shortlisted` | 403 | – |
| `offer_bafo_already_submitted` | 409 | – |
| `offer_start_price` | 422 | `start_price_minor` |
| `offer_step_not_met` | 422 | `required_amount_minor` |
| `offer_bafo_worse_than_reference` | 422 | `reference_amount_minor` |
| `offer_outlier_confirm_required` | 422 | `change_bps`, `reference_amount_minor` |

**`GET /competitions/{competition}/my-offers`** · participant · `app.v1.competitions.my-offers`

- Returns `[MyOffer]` (§2.8), newest first, not paginated (≤ 500).

**`GET /competitions/{competition}/offers`** · issuer · `app.v1.competitions.offers.index`

- Returns `[ParticipantStandingRow]` (§2.8), ordered by rank; participants without offers come last.
- While sealed and before unlock: amounts and ranks are `null` and `submitted` is shown.

**`GET /competitions/{competition}/offers/log`** · issuer · `app.v1.competitions.offers.log`

- Query: `after_seq` (default 0) and `limit` (≤ 500, default 200).
- Returns `[OfferLogEntry]` in ascending `seq`, with `meta.last_seq` and `meta.has_more`.

**`POST /competitions/{competition}/bafo-round`** · issuer, `competitions.award` · `app.v1.competitions.bafo-round.store`

- Request: `participant_ids` (required, array, 1–50, participant ids) and `duration_minutes` (nullable, integer, within the bounds; default the competition's).
- Returns `Competition` (status `bafo_round`).
- **Errors:**
  - `invalid_state_transition` (409);
  - `bafo_not_enabled` (409);
  - `bafo_already_used` (409);
  - `bafo_shortlist_invalid` (422, `details.invalid_participant_ids`).

**`POST /competitions/{competition}/award`** · issuer, `competitions.award` · `app.v1.competitions.award.store`

| Field | Rules |
|---|---|
| `participant_id` | required |
| `justification_reason_id` | nullable, an active reason of kind `award_justification` |
| `justification_text` | required when the reason `requires_note`, max:2000 |
| `confirm_reserve_not_met` | boolean |
| `message_to_winner` | nullable, max:2000 |
| `internal_notes` | nullable, max:5000 |

- Response 201 `Award` (§2.9).
- **Errors:**
  - `invalid_state_transition` (409);
  - `award_participant_has_no_offer` (422);
  - `award_justification_required` (422, `details.reason`: `not_leading` or `reserve_not_met`);
  - `award_reserve_confirmation_required` (422).

**`GET /competitions/{competition}/award`** · issuer (full `Award`) / participant (`{"outcome": "won"|"not_selected"|"not_awarded"|null, "winning_amount_minor": null|int, "message_to_winner": "…" (winner only)}`) · `app.v1.competitions.award.show`

- Returns `null` when there is no award.

**`POST /competitions/{competition}/award/revoke`** · issuer, `competitions.award` · `app.v1.competitions.award.revoke`

- Request: `reason` (required, 5–1000).
- Returns `Award` (status `revoked`); the competition is back in `closed`.
- Errors: `invalid_state_transition` (409).

**`GET /competitions/{competition}/report`** · issuer · `app.v1.competitions.report`

- Query: `locale` (in:ar,en; default the request locale).
- **200** `Report` `{"status": "ready", "locale": "ar", "generated_at": "…", "file": File}` when a current report exists.
- **202** `Report` `{"status": "pending", …}` when generation was queued (poll every 3 s).
- Download through `GET /files/{file}/download`.
- Before the first close → 409 `report_not_available`.

### 1.7 Billing

**`GET /plans`** · guest · `app.v1.plans.index`

- Returns `[Plan]` (§2.10). Active plans only, ordered by `sort_order`, with the custom plan last. The custom plan is omitted while `custom_plan_quote` is off (and `GET /plans/custom-quote` is 404 `feature_disabled`).

**`GET /plans/custom-quote`** · guest · `app.v1.plans.custom-quote`

- Query: `seats` (required, within the custom bounds, else 422 `seats_out_of_range`) and `interval` (in:monthly,annual).
- Returns:

  ```json
  {"seats": 6, "interval": "monthly", "unit_price_minor": 50000, "subtotal_minor": 300000,
   "vat_rate_bp": 1500, "vat_minor": 45000, "total_minor": 345000, "currency": "SAR"}
  ```

**`GET /billing/subscription`** · perm:`billing.view` · `app.v1.billing.subscription`

- Returns `SubscriptionOverview` (§2.10).

**`POST /billing/trial`** · perm:`billing.purchase` · `app.v1.billing.trial`

- Response 201 `Subscription`.
- Errors: `trial_not_available` (409).

**`POST /billing/coupons/validate`** · perm:`billing.purchase` · `app.v1.billing.coupons.validate`

| Field | Rules |
|---|---|
| `code` | required |
| `purpose` | in:subscription,sponsorship |
| `plan_id`, `interval`, `seats` | for subscription |
| `competition_id` | for sponsorship |

- Returns `CouponValidation` (§2.10).
- Errors: `coupon_invalid`, `coupon_expired`, `coupon_not_applicable`, `coupon_exhausted` (all 422).

**`POST /billing/checkout/subscription`** · perm:`billing.purchase` · optional `Idempotency-Key` · `app.v1.billing.checkout.subscription`

| Field | Rules |
|---|---|
| `plan_id` | required, an active plan |
| `interval` | required, in:monthly,annual |
| `seats` | required_if the plan is custom, integer within the bounds |
| `coupon_code` | nullable |
| `return_url` | required, url, allow-listed |

- Response 201 `Payment` (§2.10) with `redirect_url`. The client navigates there. `redirect_url` is `null` and the status `succeeded` when the total is 0.
- **Errors:**
  - `purchase_not_available_on_platform` (403);
  - `billing_profile_incomplete` (422; `errors` has the organization fields);
  - `return_url_not_allowed` (422);
  - `plan_not_available` (422);
  - `seats_out_of_range` (422);
  - `subscription_downgrade_not_allowed` (409);
  - `subscription_renewal_too_early` (409, `details.renewable_from`);
  - the coupon errors;
  - `feature_disabled` (404): a non-blank `coupon_code` while `coupons` is off, or the custom plan while `custom_plan_quote` is off (the same `coupons` rule applies to the sponsorship checkout);
  - `gateway_not_configured` (503);
  - `gateway_error` (502).

**`GET /billing/payments/{payment}`** · perm:`billing.view`, or the payer · `app.v1.billing.payments.show`

- Returns `Payment`.

**`POST /billing/payments/{payment}/verify`** · perm:`billing.view`, or the payer · `app.v1.billing.payments.verify`

- Asks the gateway now (`HandleGatewayResult`) and returns `Payment`. It is idempotent.

**`GET /billing/invoices`** · perm:`billing.view` · `app.v1.billing.invoices.index`

- Paginated `[Invoice]`, newest first.

**`GET /billing/invoices/{invoice}`** · perm:`billing.view` · `app.v1.billing.invoices.show`

- Returns `Invoice`.

**`GET /billing/invoices/{invoice}/pdf`** · perm:`billing.view` · `app.v1.billing.invoices.pdf`

- Streams the PDF.
- Error: 409 `invoice_pdf_not_ready` while the e-invoice is not cleared.

**`GET /billing/vouchers`** · perm:`billing.view` · `app.v1.billing.vouchers.index`

- Returns `[Voucher]` (§2.10): the organization's vouchers, active first.

**`POST /billing/gateway-webhooks/{gateway}`** · guest (the signature is verified by the driver) · `app.v1.billing.gateway-webhooks`

- Response 200 `{}` for a valid call, whether or not anything changed.
- 400 `invalid_webhook` for a bad signature.
- CSRF is not applicable; this is an API route.

#### Sponsorship (R4)

**`GET /competitions/{competition}/sponsorship`** · issuer · `app.v1.competitions.sponsorship.show`

- Returns `Sponsorship` (§2.10). It is `{"mode": "none", …}` when there is no row.

**`PUT /competitions/{competition}/sponsorship`** · issuer, `competitions.manage` · `app.v1.competitions.sponsorship.update`

- Request: `mode` (required, in:none,all,selected) and `max_passes` (nullable, integer 1–200).
- Returns `Sponsorship`.
- Errors: `sponsorship_not_enabled` (403; organization flag off), `sponsorship_locked` (409), `invalid_state_transition` (409; competition not in draft, scheduled or live before the cutoff), `feature_disabled` (404; `mode` all or selected while `flags.sponsorship` is off; `mode: none` stays accepted).

**`GET /competitions/{competition}/sponsorship/quote`** · issuer · `app.v1.competitions.sponsorship.quote`

- Query: `coupon_code` (nullable).
- Returns `SponsorshipQuote` for the current draft invitations (or, after publish, `passes_to_buy = 0` plus the current state).

**`POST /competitions/{competition}/sponsorship/checkout`** · issuer, `competitions.manage` + perm:`billing.purchase` · optional `Idempotency-Key` · `app.v1.competitions.sponsorship.checkout`

| Field | Rules |
|---|---|
| `intent` | required, in:publish,invite |
| `invitations` | required_if intent=invite, array 1–100 of `{email?, organization_id?, vendor_id?, name?}` (the same rules as the invitations store) |
| `coupon_code` | nullable |
| `return_url` | required, allow-listed |

- Response 201 `Payment` (with `redirect_url`). After success the competition is published, or the invitations are sent (ARCHITECTURE §13.5).
- **Errors:**
  - `sponsorship_not_enabled` (403);
  - `sponsorship_already_funded` (409; nothing to buy, so publish or invite directly);
  - `purchase_not_available_on_platform` (403);
  - `billing_profile_incomplete` (422);
  - `return_url_not_allowed` (422);
  - the coupon errors;
  - for `intent=invite`, the invitation rows are validated up front exactly like `POST …/invitations`: 422 `validation_failed` with `details.item_codes`, or 409 `invitation_cutoff_passed`.

### 1.8 Notifications and devices

| Method and path | Auth | Name | Request | Returns |
|---|---|---|---|---|
| `GET /notifications` | user | `app.v1.notifications.index` | `?unread=1`, `page`, `per_page` | Paginated `[Notification]` (§2.11), newest first, plus `meta.unread_count` |
| `GET /notifications/unread-count` | user | `app.v1.notifications.unread-count` | – | `{"unread_count": 4}` |
| `POST /notifications/{notification}/read` | user | `app.v1.notifications.read` | – | `Notification` |
| `POST /notifications/read-all` | user | `app.v1.notifications.read-all` | – | `{"unread_count": 0}` |
| `DELETE /notifications/{notification}` | user | `app.v1.notifications.destroy` | – | 204 |
| `DELETE /notifications` | user | `app.v1.notifications.destroy-all` | – | 204 |
| `POST /devices` | user | `app.v1.devices.store` | `token` (required, max:512), `platform` (in:ios,android,web), `device_name` (nullable, max:120), `app_version` (nullable, semver) | 201 `Device` (§2.11). **Upsert by token**: an existing token is re-assigned to this user and `last_seen_at` is updated. |
| `DELETE /devices/{device}` | user (the owner) | `app.v1.devices.destroy` | – | 204 |

### 1.9 Integrations (dashboard)

**Permissions for this section:**

| Group | Permission | Needs `organizations.api_enabled`? (otherwise 403 `api_access_disabled`) |
|---|---|---|
| Vendors | `competitions.create` (every issuer member) | no |
| Import and export | `integrations.manage` | no (CSV/XLSX is the no-code integration path) |
| API clients, keys, webhook endpoints, deliveries, event types | `integrations.manage` | **yes** |

**Vendors**

| Method and path | Name | Request | Returns |
|---|---|---|---|
| `GET /vendors` | `app.v1.vendors.index` | `q`, `status`, `category_id`, `region_id`, page | Paginated `[Vendor]` (§2.12) |
| `POST /vendors` | `app.v1.vendors.store` | `name` (required, max:200), `name_en`, `email` (required, unique per organization → `vendor_email_taken`), `contact_name`, `phone`, `cr_number`, `vat_number`, `region_id`, `city`, `category_ids[]`, `status` (in:active,blocked), `notes`, `external_refs[]` (`{system, type, id, number?, url?}`; uniqueness → 409 `external_ref_conflict`, `details.existing_id`) | 201 `Vendor` |
| `GET /vendors/{vendor}` | `app.v1.vendors.show` | – | `Vendor` |
| `PATCH /vendors/{vendor}` | `app.v1.vendors.update` | any create field (`sometimes`); `external_refs` replaces the whole list | `Vendor` |
| `DELETE /vendors/{vendor}` | `app.v1.vendors.destroy` | – | 200 `Vendor` with status `archived` |


**API clients and keys**

| Method and path | Name | Request | Returns |
|---|---|---|---|
| `GET /integrations/api-clients` | `app.v1.integrations.api-clients.index` | – | `[ApiClient]` |
| `POST /integrations/api-clients` | `…api-clients.store` | `name` (required, max:120), `description`, `scopes[]` (required, subset of ARCHITECTURE §14.4) | 201 `ApiClient` + **`client_secret` (shown once)** |
| `GET /integrations/api-clients/{client}` | `…show` | – | `ApiClient` |
| `PATCH /integrations/api-clients/{client}` | `…update` | `name`, `description`, `scopes[]` | `ApiClient` |
| `DELETE /integrations/api-clients/{client}` | `…destroy` | – | 204 (revoked) |
| `POST /integrations/api-clients/{client}/rotate-secret` | `…rotate-secret` | – | `ApiClient` + `client_secret` (once) |
| `POST /integrations/api-clients/{client}/keys` | `…keys.store` | `expires_in_days` (1–730, default 365) | 201 `ApiKey` + **`key` (shown once)** |
| `DELETE /integrations/api-clients/{client}/keys/{key}` | `…keys.destroy` | – | 204 (revoked) |

**Webhook endpoints** (these also exist in the public API, §3.6)

| Method and path | Name | Request | Returns |
|---|---|---|---|
| `GET /integrations/webhook-event-types` | `…webhook-event-types` | – | `[{"type": "award.issued", "description": "…"}]` (the §4.1 catalogue, localised descriptions) |
| `GET /integrations/webhook-endpoints` | `…webhook-endpoints.index` | – | `[WebhookEndpoint]` |
| `POST /integrations/webhook-endpoints` | `…store` | `url` (required, max:1000; SSRF guard → 422 `webhook_url_invalid`), `event_types[]` (required, catalogue types or `["*"]`), `description` | 201 `WebhookEndpoint` + **`secret` (once)** |
| `GET /integrations/webhook-endpoints/{endpoint}` | `…show` | – | `WebhookEndpoint` |
| `PATCH /integrations/webhook-endpoints/{endpoint}` | `…update` | `url`, `event_types[]`, `description`, `status` (in:active,disabled) | `WebhookEndpoint`. Re-enabling clears `failing_since`. |
| `DELETE /integrations/webhook-endpoints/{endpoint}` | `…destroy` | – | 204 (soft delete) |
| `POST /integrations/webhook-endpoints/{endpoint}/test` | `…test` | – | 202 `{"event_id": "…"}` |
| `POST /integrations/webhook-endpoints/{endpoint}/rotate-secret` | `…rotate-secret` | – | `WebhookEndpoint` + `secret` (once) |
| `GET /integrations/webhook-endpoints/{endpoint}/deliveries` | `…deliveries` | `status`, page | Paginated `[WebhookDelivery]`, newest first |
| `POST /integrations/webhook-deliveries/{delivery}/redeliver` | `…redeliver` | – | `WebhookDelivery` (status `pending`) |

**Import and export**

| Method and path | Name | Request | Returns |
|---|---|---|---|
| `GET /integrations/imports/templates/{type}` | `…imports.template` | `type` = vendors; `?format=csv\|xlsx` | A file stream |
| `POST /integrations/imports` | `…imports.store` | multipart `file` (csv or xlsx ≤ 20 MB), `type` (in:vendors), `mode` (in:validate,commit) | 202 `ImportJob` |
| `GET /integrations/imports/{job}` | `…imports.show` | – | `ImportJob` |
| `POST /integrations/exports` | `…exports.store` | `type` (in:results,offer_log,awards,vendors), `format` (in:csv,xlsx), `competition_id` (required for results and offer_log; the caller must be its issuer), `from`/`to` (dates; awards only) | 202 `ExportJob` |
| `GET /integrations/exports/{job}` | `…exports.show` | – | `ExportJob`. Download through `/files/{file}/download` when `status = completed`. |

---

## 2. Resource shapes (app v1)

**How to read this section:**

- `// issuer only` means that the field is **omitted** (not null) in other projections.
- Every other field is always present.
- Each JsonResource lives in its owning module. Other modules may embed it read-only (ARCHITECTURE §3.3).

### 2.1 User

```json
{
  "id": "01j9zq4m1x2a3b4c5d6e7f8g9h",
  "name": "سارة العتيبي",
  "email": "sara@issuer.sa",
  "phone": "+966501234567",
  "locale": "ar",
  "avatar_url": null,
  "status": "active",
  "email_verified_at": "2026-10-01T08:00:00.000Z",
  "created_at": "2026-10-01T07:55:00.000Z"
}
```

### 2.2 Organization

**Own view** (`GET /organization`, and the `organization` key of `Me`):

```json
{
  "id": "01j9…",
  "name": "شركة المصدر",
  "legal_name_ar": "شركة المصدر للتجارة",
  "legal_name_en": "Al Masdar Trading Co.",
  "cr_number": "1010123456",
  "vat_registered": true,
  "vat_number": "300000000000003",
  "region": {"id": "01j…", "code": "RIY", "name": "الرياض"},
  "city": "الرياض",
  "national_address": {"building_number": "1234", "street": "…", "district": "…", "postal_code": "12345", "additional_number": "5678", "short_address": "RRRD2929"},
  "website": "https://masdar.sa",
  "email": "info@masdar.sa",
  "phone": "+966501234567",
  "logo_url": "http://localhost:8000/storage/organization_logo/2026/10/01j….png",
  "profile_document": null,
  "categories": [{"id": "01j…", "code": "it_hardware", "name": "أجهزة تقنية", "is_other": false, "auction_allowed": true}],
  "visible_in_suggestions": true,
  "status": "active",
  "verified": false,
  "features": {"api_enabled": false, "auction_enabled": false, "sponsorship_enabled": false},
  "billing_profile_complete": false,
  "billing_profile_missing": ["legal_name_ar", "national_address.building_number"],
  "trial_available": true,
  "created_at": "…"
}
```

`profile_document` is a `File` (§2.5) or `null`.

**OrganizationSummary** (embedded elsewhere): `{"id", "name", "logo_url", "verified"}`.

### 2.3 Membership, Me, AuthTokenPayload

**Membership:**

```json
{"id": "01j…", "role": "member", "can_award": false, "can_purchase": false, "status": "invited",
 "joined_at": null, "invited_at": "…", "user": { "…": "User" }}
```

**Me** (`GET /me`):

```json
{
  "user": { "…": "User" },
  "organization": { "…": "Organization (own view)" },
  "membership": {"id": "01j…", "role": "owner", "can_award": true, "can_purchase": true, "status": "active"},
  "permissions": ["organization.update", "team.manage", "billing.view", "billing.purchase", "competitions.create",
                  "competitions.manage_all", "competitions.award", "participation.submit_offers",
                  "integrations.manage", "account.delete_organization"],
  "subscription": {"plan": {"id": "…", "code": "pro", "name": "باقة برو"}, "source": "paid", "status": "active",
                   "ends_at": "…", "days_left": 21, "total_days": 30} ,
  "entitlements": {"can_issue": true, "seats_used": 3, "seats_total": 3},
  "unread_notifications_count": 4
}
```

`subscription` is `null` when there is no current subscription.

**AuthTokenPayload** = `Me` plus `"token": "<sanctum plain text token>"` and `"token_type": "Bearer"`.

### 2.4 Lookups

| Resource | Fields |
|---|---|
| Region | `{"id", "code", "name"}` |
| Category | `{"id", "code", "name", "is_other", "auction_allowed"}` |
| CloseReason | `{"id", "code", "kind", "name", "requires_note"}` |
| Preset | `{"id", "code", "name", "description", "direction", "format", "tier", "rules": RulesInput (without start or reserve prices)}`. `tier` ∈ `simple`, `standard`, `protected`, or `null` for an untiered preset (`RELEASE_SCOPE.md` §2.2). |

### 2.5 File

```json
{"id": "01j…", "name": "specs.pdf", "mime_type": "application/pdf", "extension": "pdf", "size_bytes": 482133,
 "download_path": "/api/app/v1/files/01j…/download", "created_at": "…"}
```

### 2.6 Competition

#### RulesInput / Rules (the same keys for input and output)

```json
{
  "start_price_minor": 25000000,
  "reserve_price_minor": 21000000,
  "min_step_minor": null,
  "min_step_bps": 50,
  "amount_granularity_minor": 100,
  "must_beat": "own",
  "rank_visibility": "leading_flag",
  "show_prices": false,
  "auto_extend": {"enabled": true, "window_seconds": 180, "by_seconds": 180, "max_extensions": 10},
  "final_window_minutes": 60,
  "bafo_round": {"enabled": false, "duration_minutes": null},
  "min_participants": 2,
  "result_publication": "outcome_only"
}
```

**Allowed values:**

| Key | Values |
|---|---|
| `must_beat` | `own`, `best`, `null` (sealed) |
| `rank_visibility` | `full`, `leading_flag`, `none` |
| `result_publication` | `none`, `outcome_only`, `outcome_and_amount` |
| `amount_granularity_minor` | `1`, `100` |

**Mapping to columns:**

| Key | Columns |
|---|---|
| `auto_extend.*` | `auto_extend_enabled`, `auto_extend_window_seconds`, `auto_extend_by_seconds`, `auto_extend_max` |
| `bafo_round.*` | `bafo_round_enabled`, `bafo_duration_minutes` |

**Visibility.** `reserve_price_minor` is **issuer only**: it is omitted in every participant and invitee projection.

#### Competition (issuer projection, `viewer_role = "issuer"`)

```json
{
  "id": "01j9…",
  "reference_no": "BAFO-T-2026-000123",
  "title": "توريد أجهزة حاسب محمول",
  "description": "…",
  "direction": "tender",
  "format": "live",
  "status": "live",
  "phase": "final_window",
  "currency": "SAR",
  "price_basis": "excl_vat",
  "category": {"id": "…", "code": "it_hardware", "name": "أجهزة تقنية", "is_other": false, "auction_allowed": true},
  "category_other_text": null,
  "region": {"id": "…", "code": "RIY", "name": "الرياض"},
  "preset_code": "standard_live_tender",
  "rules": { "…": "Rules (with reserve_price_minor)" },
  "rules_summary": ["مناقصة: الأقل سعراً يفوز.", "…"],
  "schedule": {
    "bidding_opens_at": "2026-11-02T06:00:00.000Z",
    "scheduled_close_at": "2026-11-09T12:00:00.000Z",
    "effective_close_at": "2026-11-09T12:03:00.000Z",
    "hard_stop_at": "2026-11-09T12:30:00.000Z",
    "final_window_starts_at": "2026-11-09T11:00:00.000Z",
    "invitation_cutoff_at": "2026-11-09T11:00:00.000Z",
    "extension_count": 1,
    "published_at": "…", "opened_at": "…", "closed_at": null, "offers_opened_at": null,
    "awarded_at": null, "not_awarded_at": null, "cancelled_at": null
  },
  "issuer": {"id": "…", "name": "شركة المصدر", "logo_url": null, "verified": true},
  "counts": {"invitations": 5, "joined": 4, "declined": 0, "participants_with_offers": 3, "offers": 17,
             "comments": 2, "attachments": 3},
  "leading_amount_minor": 9800000,
  "sponsorship": {"mode": "all", "status": "active", "funded_passes": 4, "free_slots": 0},
  "bafo_round": null,
  "award": null,
  "cancellation": null,
  "not_awarded": null,
  "created_by": {"type": "user", "id": "…", "name": "سارة"},
  "source": "web",
  "permissions": {
    "can_edit": true, "can_delete": false, "can_publish": false, "can_invite": true, "can_extend": true,
    "can_cancel": true, "can_start_bafo": false, "can_award": false, "can_revoke_award": false,
    "can_close_without_award": false, "can_manage_sponsorship": true, "can_comment": true,
    "can_join": false, "can_decline": false, "can_submit_offer": false
  },
  "viewer_role": "issuer",
  "live": { "…": "IssuerLiveSnapshot (§2.8), or null in draft/scheduled" },
  "server_time": "2026-11-09T11:59:58.120Z",
  "created_at": "…",
  "updated_at": "…"
}
```

**Nested values:**

| Key | Shape | When set |
|---|---|---|
| `leading_amount_minor` | int | `null` while sealed and not yet unlocked, or when there are no offers |
| `sponsorship` | `{"mode", "status", "funded_passes", "free_slots"}` | `null` when the mode is none |
| `bafo_round` | `BafoRound` (§2.9) | `null` when there is no round |
| `award` | `AwardSummary` `{"id", "status", "participant": {"id", "alias_no", "organization": OrganizationSummary}, "amount_minor", "awarded_at"}` | `null` when there is no award |
| `cancellation` | `{"reason": CloseReason, "note", "cancelled_at"}` | set when cancelled |
| `not_awarded` | `{"reason": CloseReason, "note", "not_awarded_at"}` | set when not awarded |

**Permission values:**

- `phase` is `null` unless the status is `live`.
- `permissions` values follow ARCHITECTURE §8.3 and §6. The participant-side flags are always false for the issuer, and the reverse holds for participants.
- Release scope: `can_extend`, `can_start_bafo` and `can_manage_sponsorship` are false while `extend_competition`, `bafo_round` or `sponsorship` is off.

#### Competition (participant projection, `viewer_role = "participant"`)

The same keys as the issuer projection, **except:**

- **Omitted:** `rules.reserve_price_minor`, `counts`, `leading_amount_minor`, `sponsorship`, `award`, `created_by`, `source`.
- **Added:**

  ```json
  "participation": {"participant_id": "01j…", "alias_no": 7, "joined_at": "…", "terms_accepted_at": "…"},
  "access": {"state": "full", "coverage": "sponsored", "sponsor_name": "شركة المصدر", "join_deadline": "…"},
  "result": {"outcome": null, "winning_amount_minor": null}
  ```

- **Changed:** `live` is a `ParticipantLiveSnapshot`. `bafo_round` is `null`, or `{"status", "cutoff_at", "shortlisted": bool, "submitted": bool}`.
- **Permissions:** `can_submit_offer` = `live.accepting_offers`; `can_comment`; everything else is false.

#### Competition (invitee projection, `viewer_role = "invitee"`) = CompetitionTeaser

```json
{
  "id": "01j9…", "reference_no": "BAFO-T-2026-000123", "title": "…", "direction": "tender", "format": "live",
  "status": "scheduled", "phase": null, "currency": "SAR", "price_basis": "excl_vat",
  "category": { "…": "Category" }, "region": { "…": "Region" },
  "issuer": {"id": "…", "name": "…", "logo_url": null, "verified": true},
  "rules": { "…": "Rules without reserve_price_minor" },
  "rules_summary": ["…"],
  "schedule": {"bidding_opens_at": "…", "scheduled_close_at": "…", "effective_close_at": "…", "invitation_cutoff_at": "…"},
  "invitation": {"id": "01j…", "status": "sent", "join_deadline": "…", "sent_at": "…"},
  "access": {"state": "join_required", "coverage": "sponsored", "sponsor_name": "شركة المصدر", "join_deadline": "…"},
  "invitation_documents": [ { "…": "Attachment" } ],
  "permissions": {"can_join": true, "can_decline": true, "…other flags": false},
  "viewer_role": "invitee",
  "server_time": "…"
}
```

- **`access.state`:** `join_required`, `plan_required`, `full`, `read_only` or `unavailable`.
- **`access.coverage`:** `sponsored`, `own_plan` or `none`.
- **In the guest `InvitationLookup`**, the teaser omits `invitation_documents`, `access` and `permissions`.

#### CompetitionListItem

**Issuer variant:**

```json
{"id", "reference_no", "title", "direction", "format", "status", "phase", "category", "region",
 "schedule": {"bidding_opens_at", "effective_close_at"},
 "counts": {"invitations", "joined", "participants_with_offers"},
 "leading_amount_minor", "created_at", "updated_at"}
```

**Participant variant:**

```json
{"id", "reference_no", "title", "direction", "format", "status", "phase", "category", "region",
 "issuer": OrganizationSummary, "schedule": {"bidding_opens_at", "effective_close_at", "invitation_cutoff_at"},
 "invitation": {"id", "status", "join_deadline"}, "access": { … },
 "my_offer_amount_minor": 9850000, "is_leading": true, "result": {"outcome": null},
 "updated_at"}
```

- `my_offer_amount_minor` is `null` when there is no offer or the organization has not joined.
- `is_leading` follows the §7.9 projection rules; otherwise `null`.

### 2.7 Invitation, Attachment, Comment

**Invitation (issuer view):**

```json
{
  "id": "01j…", "email": "sales@supplier.sa", "name": "Ahmed", "status": "joined",
  "organization": {"id": "…", "name": "…", "logo_url": null, "verified": false},
  "vendor": {"id": "…", "name": "…"},
  "sponsored_requested": true,
  "coverage": "sponsored",
  "pass_status": "joined",
  "participant": {"id": "01j…", "alias_no": 7, "joined_at": "…"},
  "sent_at": "…", "viewed_at": "…", "joined_at": "…", "declined_at": null, "decline_reason": null,
  "revoked_at": null, "revoke_reason": null, "expired_at": null, "created_at": "…"
}
```

- `organization` and `vendor` are `null` when unknown.
- `pass_status` is `pending`, `reserved`, `joined`, `released`, `unused`, `void` or `null`.
- `participant` is `null` until the invitee joins.

**Invitation (invitee view):**

```json
{"id", "status", "join_deadline", "sent_at", "competition": CompetitionTeaser}
```

**Attachment:**

```json
{"id": "01j…", "kind": "document", "title": "كراسة الشروط", "file": File, "url": null,
 "is_addendum": false, "sort_order": 0, "created_at": "…"}
```

- `kind` is `document`, `invitation_document` or `external_link`.
- `file` is `null` for links; `url` is set for links only.

**Comment:**

```json
{
  "id": "01j…", "parent_id": null, "body": "هل يشمل السعر التوصيل؟",
  "author": {"kind": "participant", "alias_no": 3, "organization_name": "شركة الريادة"},
  "created_at": "…",
  "replies": [ {"id": "…", "parent_id": "01j…", "body": "نعم.", "author": {"kind": "issuer", "organization_name": "شركة المصدر"}, "created_at": "…", "replies": []} ]
}
```

**`author` projection:**

| Author | Issuer sees | The author's own organization sees | Other participants see |
|---|---|---|---|
| issuer | `{"kind": "issuer", "organization_name": issuer name}` | same | same |
| participant | `{"kind": "participant", "alias_no", "organization_name"}` | `{"kind": "me"}` | `{"kind": "participant", "alias_no"}` (**no name**) |

### 2.8 Live snapshots and offer rows

#### ParticipantLiveSnapshot

```json
{
  "v": 1842,
  "competition_id": "01j9…",
  "direction": "tender",
  "status": "live",
  "phase": "final_window",
  "server_time": "2026-11-09T11:59:58.120Z",
  "bidding_opens_at": "…",
  "effective_close_at": "2026-11-09T12:03:00.000Z",
  "hard_stop_at": "2026-11-09T12:30:00.000Z",
  "extension_count": 1,
  "accepting_offers": true,
  "start_price_minor": 25000000,
  "min_step": {"minor": null, "bps": 50},
  "amount_granularity_minor": 100,
  "my_offer": {"id": "01jb…", "seq": 57, "amount_minor": 9850000, "stage": "live", "accepted_at": "…"},
  "my_offers_count": 4,
  "is_leading": false,
  "rank": null,
  "ranked_count": null,
  "leading_amount_minor": null,
  "ladder": null,
  "required_next_amount_minor": 9800800,
  "bafo": null,
  "result": null,
  "last_change": {"kind": "offer", "reason": null}
}
```

- **`ladder`** entries are `{"alias_no": 3, "amount_minor": 9800000, "is_me": false}`, sorted by rank.
- **`bafo`** is `{"shortlisted": true, "cutoff_at": "…", "submitted": false, "reference_amount_minor": 9850000}`.
- **`result`** is `{"outcome": "won"|"not_selected"|"not_awarded", "winning_amount_minor": int|null}`.
- **`last_change.kind`** is `offer`, `extension`, `status`, `bafo`, `award`, `void` or `snapshot` (REST reads).
- **`last_change.reason`**, for an extension, is `auto`, `manual` or `admin`.
- **Visibility.** Which fields are null or set follows ARCHITECTURE §7.9 exactly.
- **`accepting_offers`** is true when the server would accept an offer from this participant now: live and open (any phase) before `effective_close_at`, or `bafo_round`, shortlisted, not yet submitted and before the cutoff.
- **Bound direction of `required_next_amount_minor`:** tender → the next offer must be **≤** it; auction → **≥** it.

#### IssuerLiveSnapshot

```json
{
  "v": 1842,
  "competition_id": "01j9…",
  "direction": "tender",
  "status": "live",
  "phase": "final_window",
  "server_time": "…",
  "bidding_opens_at": "…",
  "effective_close_at": "…",
  "hard_stop_at": "…",
  "extension_count": 1,
  "leader": {"participant_id": "01j…", "alias_no": 3, "organization": {"id": "…", "name": "شركة الريادة"}, "amount_minor": 9800000, "accepted_at": "…"},
  "reserve_met": false,
  "ranking": [
    {"participant_id": "01j…", "alias_no": 3, "organization": {"id": "…", "name": "…", "logo_url": null},
     "current_amount_minor": 9800000, "first_amount_minor": 11000000, "rank": 1, "is_leader": true,
     "offers_count": 6, "last_offer_at": "…", "submitted": true,
     "bafo": {"shortlisted": false, "submitted": false}}
  ],
  "metrics": {"offers_count": 17, "participants_joined": 4, "participants_with_offers": 3, "invitations_count": 5,
              "improvement_vs_start_bps": 6080},
  "online_participants_count": 3,
  "bafo": null,
  "last_change": {"kind": "offer", "reason": null}
}
```

**Nested values:**

| Key | Shape / rule |
|---|---|
| `leader` | `null` when there are no offers or while sealed and not yet unlocked |
| `reserve_met` | `null` without a reserve |
| `metrics.improvement_vs_start_bps` | `null` without a start price or a leader |
| `bafo` | `{"status", "cutoff_at", "shortlist_count", "submitted_count"}` |
| **Sealed, before unlock** | every `current_amount_minor`, `first_amount_minor`, `rank` and `is_leader` is `null`; `submitted` shows who has offered |

#### ParticipantStandingRow (issuer `GET …/offers`)

```json
{"participant": {"id": "01j…", "alias_no": 3, "joined_at": "…",
                 "organization": {"id": "…", "name": "…", "logo_url": null, "email": "…", "phone": "…", "cr_number": "…"},
                 "coverage": "own_plan"},
 "current_amount_minor": 9800000, "first_amount_minor": 11000000, "offers_count": 6, "last_offer_at": "…",
 "rank": 1, "is_leader": true, "change_ratio_bps": 1090, "submitted": true,
 "bafo": {"shortlisted": false, "submitted": false, "reference_amount_minor": null}}
```

#### OfferLogEntry (issuer `GET …/offers/log` and realtime `offer.accepted`)

```json
{"id": "01jb…", "seq": 57, "participant": {"id": "01j…", "alias_no": 3, "organization": {"id": "…", "name": "…"}},
 "amount_minor": 9800000, "stage": "live", "accepted_at": "…", "channel": "web", "voided": false}
```

`amount_minor` is `null` while sealed and not yet unlocked.

#### MyOffer

```json
{"id": "01jb…", "seq": 57, "amount_minor": 9850000, "stage": "live", "accepted_at": "…", "voided": false}
```

### 2.9 BafoRound, Award, Report

**BafoRound (issuer):**

```json
{"id": "01j…", "status": "running", "starts_at": "…", "cutoff_at": "…", "ended_at": null,
 "shortlist_count": 3, "submitted_count": 1}
```

**Award (issuer):**

```json
{
  "id": "01jc…", "status": "issued",
  "participant": {"id": "01j…", "alias_no": 3, "organization": {"id": "…", "name": "…", "cr_number": "…", "vat_number": "…"}},
  "amount_minor": 9800000, "currency": "SAR", "price_basis": "excl_vat",
  "is_leading_offer": true, "rank_at_award": 1, "reserve_met": true,
  "justification": null,
  "message_to_winner": "…", "internal_notes": "…",
  "offer": {"id": "01jb…", "seq": 57, "accepted_at": "…"},
  "awarded_by": {"id": "…", "name": "سارة"}, "awarded_at": "…",
  "revoked_at": null, "revoke_reason": null,
  "erp_sync": {"status": "pending", "message": null, "synced_at": null, "refs": []},
  "ledger_head_hash": "b3f1…",
  "created_at": "…"
}
```

- `justification` is `{"reason": CloseReason, "text": "…"}` when set.
- `erp_sync.refs` is `[ExternalRef]`.

**Report:**

```json
{"status": "pending", "locale": "ar", "generated_at": null, "file": null}
```

`status` is `pending`, `ready` or `failed`.

### 2.10 Billing

**Plan:**

```json
{"id": "01j…", "code": "pro", "name": "باقة برو", "description": "…", "features": ["…", "…"], "seats": 3,
 "monthly_price_minor": 150000, "annual_price_minor": 1500000,
 "monthly_list_price_minor": 300000, "annual_list_price_minor": 3000000,
 "is_custom": false, "is_featured": true, "currency": "SAR", "vat_rate_bp": 1500}
```

The custom plan has `seats`, the prices and the list prices set to `null`, and adds:

```json
"custom": {"min_seats": 4, "max_seats": 50, "seat_monthly_price_minor": 50000, "seat_annual_price_minor": 500000}
```

**Subscription:**

```json
{"id": "01j…", "plan": {"id": "…", "code": "pro", "name": "باقة برو"}, "source": "paid", "interval": "monthly",
 "seats": 3, "status": "active", "starts_at": "…", "ends_at": "…", "days_left": 21, "total_days": 30,
 "amounts": {"subtotal_minor": 150000, "credit_minor": 0, "discount_minor": 0, "vat_minor": 22500, "total_minor": 172500},
 "created_at": "…"}
```

- `source` is `paid`, `trial` or `grant`. `interval` is `null` for a trial or grant.
- `status` is `pending_payment`, `active`, `superseded`, `expired` or `cancelled`.
- `amounts` is `null` for a trial or grant.

**SubscriptionOverview:**

```json
{"current": Subscription|null, "upcoming": Subscription|null, "history": [Subscription],
 "trial_available": true, "seats_used": 2, "seats_total": 3}
```

**Payment:**

```json
{
  "id": "01j…", "purpose": "sponsorship", "status": "pending", "currency": "SAR",
  "lines": [{"kind": "sponsored_pass", "description": "تصريح مشاركة مغطّاة — BAFO-T-2026-000123", "quantity": 7,
             "unit_price_minor": 20000, "net_minor": 140000}],
  "subtotal_minor": 140000, "credit_minor": 0, "discount_minor": 0, "vat_rate_bp": 1500,
  "vat_minor": 21000, "total_minor": 161000,
  "coupon": null,
  "redirect_url": "http://localhost:8000/pay/fake/01j…",
  "failure_code": null, "failure_message": null,
  "paid_at": null, "expires_at": "…", "created_at": "…",
  "invoice_id": null,
  "context": {"competition_id": "01j9…", "intent": "publish", "subscription_id": null}
}
```

- `status` is `pending`, `succeeded`, `failed`, `expired` or `refunded` (ARCHITECTURE §6.4). A user cancelling on the hosted page is `failed`.
- Each line's `description` is localised.
- `coupon` is `{"code": "…"}` when one was applied.

**Invoice:**

```json
{"id": "01j…", "number": "BAFO-INV-2026-000042", "type": "tax_invoice", "issue_date": "2026-10-01",
 "currency": "SAR", "subtotal_minor": 140000, "discount_minor": 0, "vat_rate_bp": 1500, "vat_minor": 21000,
 "total_minor": 161000, "einvoice_status": "cleared", "zatca_uuid": "7b1c…",
 "lines": [{"description": "…", "quantity": 7, "unit_price_minor": 20000, "net_minor": 140000}],
 "pdf": {"available": true, "download_path": "/api/app/v1/billing/invoices/01j…/pdf"},
 "payment_id": "01j…", "issued_at": "…"}
```

**Voucher:**

```json
{"id": "01j…", "code": "V-7KQ2M9XA1B", "amount_minor": 40000, "balance_minor": 40000, "valid_until": "…",
 "reason": "Unused passes BAFO-T-2026-000123", "source_competition_id": "01j9…", "is_active": true}
```

**CouponValidation:**

```json
{"code": "LAUNCH10", "kind": "coupon", "discount_type": "percent", "percent_bps": 1000, "amount_minor": null,
 "balance_minor": null, "applies_to": "any", "valid_until": "…", "discount_minor": 15000}
```

`discount_minor` is computed for the given context.

**Sponsorship:**

```json
{"mode": "all", "max_passes": 8, "unit_price_minor": 20000, "vat_rate_bp": 1500, "currency": "SAR",
 "status": "active", "funded_passes": 7, "enabled": true,
 "counts": {"pending": 0, "reserved": 3, "joined": 3, "released": 1, "unused": 0, "void": 0, "free_slots": 1},
 "unused_count": null, "voucher": null}
```

- `mode` is `none`, `all` or `selected`. `status` is `draft`, `active` or `settled`.
- `enabled` means the organization flag and the global setting are both on.
- `voucher` is `{"code": "…"}` once issued.

**SponsorshipQuote:**

```json
{"mode": "all", "max_passes": 8, "unit_price_minor": 20000, "vat_rate_bp": 1500, "currency": "SAR",
 "funded_passes": 0, "free_slots": 0,
 "lines": [
   {"invitation_id": "01j…", "email": "a@acme.sa", "organization_name": null, "coverage": "sponsored", "reason": null},
   {"invitation_id": "01j…", "email": "b@delta.sa", "organization_name": "Delta", "coverage": "own_plan", "reason": "own_plan"},
   {"invitation_id": "01j…", "email": "c@omega.sa", "organization_name": null, "coverage": "none", "reason": "cap_reached"}
 ],
 "passes_to_reserve": 0, "passes_to_buy": 7,
 "subtotal_minor": 140000, "discount_minor": 0, "vat_minor": 21000, "total_minor": 161000}
```

- In invite quotes, `invitation_id` is `null` (the row is new).
- `reason` is `null`, `not_selected`, `cap_reached` or `own_plan`.

### 2.11 Notification, Device

**Notification:**

```json
{"id": "01jd…", "type": "competition.invited", "title": "دعوة للمشاركة في مناقصة",
 "body": "دعتك شركة المصدر للمشاركة في «توريد أجهزة».",
 "subject": {"type": "competition", "id": "01j9…"}, "route": "/competitions/01j9…",
 "params": {"competition_title": "…", "issuer_name": "…", "direction": "tender", "sponsored": true},
 "read_at": null, "created_at": "…"}
```

`title` and `body` are rendered in the request locale.

**Device:**

```json
{"id": "01j…", "platform": "android", "device_name": "Pixel 8", "app_version": "1.0.0", "last_seen_at": "…"}
```

### 2.12 Integrations

**ExternalRef:**

```json
{"system": "sap_s4", "type": "supplier", "id": "100045", "number": null, "url": null}
```

`id` is the ERP key.

**Vendor:**

```json
{"id": "01j…", "name": "شركة الريادة", "name_en": "Al Riyada Co.", "cr_number": "1010987654",
 "vat_number": "300000000000013", "email": "sales@riyada.sa", "contact_name": "Khalid", "phone": "+966551234567",
 "region": Region, "city": "الرياض", "categories": [Category], "status": "active",
 "linked_organization": OrganizationSummary, "source": "api", "notes": null,
 "external_refs": [ExternalRef], "created_at": "…", "updated_at": "…"}
```

`region` and `linked_organization` may be `null`.

**ApiClient:**

```json
{"id": "01j…", "client_id": "01j…", "name": "SAP CPI – PROD", "description": null,
 "scopes": ["competitions:read", "vendors:write"], "status": "active",
 "keys": [ApiKey], "last_used_at": null, "created_by": {"id": "…", "name": "…"}, "created_at": "…"}
```

- `client_id` equals `id`.
- Create and rotate responses add `"client_secret": "…"` **once**.

**ApiKey:**

```json
{"id": "01j…", "prefix": "ab12cd34", "masked": "bafo_test_ab12cd34_…WXYZ", "expires_at": "…", "revoked_at": null,
 "last_used_at": null, "created_at": "…"}
```

The create response adds `"key": "bafo_test_ab12cd34_<32>"` **once**.

**WebhookEndpoint:**

```json
{"id": "01j…", "url": "https://erp.example.sa/bafo/webhooks", "description": null,
 "event_types": ["award.issued", "competition.closed"], "status": "active", "disabled_reason": null,
 "failing_since": null, "last_success_at": null, "last_failure_at": null, "created_at": "…"}
```

Create and rotate responses add `"secret": "whsec_…"` **once**.

**WebhookDelivery:**

```json
{"id": "01j…", "event": {"id": "01jf…", "type": "award.issued", "occurred_at": "…"}, "status": "failed",
 "attempts": 9, "next_attempt_at": null, "last_attempt_at": "…", "last_http_status": 500,
 "last_error": "HTTP 500", "last_response_excerpt": "…", "last_duration_ms": 812,
 "succeeded_at": null, "failed_at": "…"}
```

**ImportJob:**

```json
{"id": "01j…", "type": "vendors", "mode": "validate", "status": "completed", "total_rows": 120,
 "valid_rows": 117, "created_rows": 0, "updated_rows": 0, "error_rows": 3,
 "errors_preview": [{"row": 5, "column": "email", "code": "invalid_format", "message": "…"}],
 "errors_file": File, "source_file": File, "failure_message": null, "finished_at": "…", "created_at": "…"}
```

`errors_file` may be `null`.

**ExportJob:**

```json
{"id": "01j…", "type": "results", "format": "xlsx", "filters": {"competition_id": "01j9…"},
 "status": "completed", "row_count": 4, "file": File, "failure_message": null, "finished_at": "…", "created_at": "…"}
```

`file` may be `null`.

### 2.13 AppConfig, Home

**AppConfig:**

```json
{
  "min_version": {"ios": "1.0.0", "android": "1.0.0"},
  "latest_version": {"ios": "1.0.0", "android": "1.0.0"},
  "store_links": {"ios": "", "android": ""},
  "maintenance": {"enabled": false, "message": ""},
  "support": {"email": "", "phone": "", "whatsapp": ""},
  "realtime": {"key": "…", "host": "localhost", "port": 8085, "scheme": "http"},
  "legal": {"terms": {"version": "2026-10-01"}, "privacy": {"version": "2026-10-01"},
            "competition_rules": {"version": "2026-10-01"}},
  "features": {
    "release_scope": "core",
    "sponsorship": false,
    "flags": {"team_management": false, "vendor_directory": false, "integrations_api": false, "csv_import_export": false,
              "sponsorship": false, "bafo_round": false, "sealed_format": false, "advanced_rules": false,
              "final_pricing_window": false, "deletion_approval": false, "deleted_competitions": false,
              "offer_report": false, "login_as": false, "google_signin": false, "dark_mode": false,
              "billing_invoices": false, "custom_plan_quote": false, "coupons": false, "qa_comments": true,
              "attachments": true, "extend_competition": false, "cancel_competition": true}
  },
  "currency": "SAR", "vat_rate_bp": 1500, "supported_locales": ["ar", "en"],
  "server_time": "…"
}
```

`features.release_scope` is for display only; clients branch on `features.flags.<name>`, which always holds the 22 keys of `RELEASE_SCOPE.md` §1.3 in that order (unknown keys ignored, missing keys read as `false`). `features.sponsorship` is kept for compatibility and always equals `features.flags.sponsorship`.

`maintenance.message` is localised. `store_links` and `support` are admin settings (empty strings until set). The local demo seed (`PlatformDemoSeeder`) fills them with clearly marked placeholders on the reserved `bafo.example` domain; real values are set in the admin before release.

**Home:**

```json
{
  "issuer": {"active_competitions": 3, "draft_competitions": 1, "live_now": 1, "awaiting_award": 1, "offers_received_30d": 42},
  "participant": {"pending_invitations": 2, "active_participations": 3, "offers_submitted_30d": 17, "awards_won": 1},
  "team": {"members": 3, "seats_total": 3},
  "subscription": {"plan": {"id": "…", "code": "pro", "name": "…"}, "status": "active", "days_left": 21, "total_days": 30, "ends_at": "…"},
  "alerts": [{"code": "subscription_expiring", "params": {"days_left": 3}}],
  "activities": [{"id": "01j…", "action": "competition.published", "occurred_at": "…",
                  "actor": {"name": "سارة"}, "subject": {"type": "competition", "id": "01j9…", "title": "…"}}]
}
```

- `subscription` is `null` when there is none.
- **`alerts[].code`:** `subscription_expiring`, `subscription_expired`, `plan_required`, `trial_available`, `billing_profile_incomplete`.
- **`alerts[].params`** is always a JSON object: `{"days_left": 3}` for `subscription_expiring`, `{}` for the other codes (never `[]`).
- **`activities[].id`** is an **opaque**, stable 26-character string (audit entries have no public ULID). Use it only as a list key; it is not accepted by any endpoint. This is the one exception to the ULID rule of §0.6.
- **`activities`:** the last 10 audit entries of the organization whose action is one of `competition.created`, `competition.published`, `competition.cancelled`, `competition.closed`, `award.issued`, `invitation.joined`, `member.added` or `subscription.activated`. Clients render them with the i18n key `home.activity.<action with . → _>`.

---

## 3. Public API v1 (`/api/public/v1`)

### 3.0 Differences from app v1

- **Authentication.** Send `Authorization: Bearer <access_token | api_key>` on every call except `POST /oauth/token` and `GET /openapi.yaml`. Get an access token from `/oauth/token` (ARCHITECTURE §14.2). API keys look like `bafo_test_ab12cd34_<32 chars>`.
- **Scopes.** Each endpoint lists its scope. A missing scope → 403 `insufficient_scope` (`details.required_scope`).
- **Tenancy.** The organization is the API client's organization. Competitions are visible only when that organization is the **issuer**; anything else is 404. Participant-side access through the public API is not in v1.
- **Envelope.** The same `{data, meta}` and error envelope, with no `meta.server_time`. **The exception is `POST /oauth/token`**, which answers in the RFC 6749 shape.
- **Lists** use cursor pagination and support `updated_since` (RFC 3339; returns rows with `updated_at ≥ updated_since`).
- **`Idempotency-Key` is required** on every `POST` that creates or acts (the `idempotent` middleware; ARCHITECTURE §4.8). Replays return the original response.
- **Lookups.** Names are `{"ar", "en"}`. Categories and regions are referenced by **code** in input (`category_code`, `region_code`).
- **Money** is integer halalas (`*_minor`), `currency: "SAR"`, `price_basis: "excl_vat"`.
- **Visibility** is exactly the issuer projection (ARCHITECTURE §7.9). Sealed amounts are `null` until the offers are unlocked.
- **Route names** are `public.v1.*`. Route files: `routes/public_v1/{integrations,identity,catalog,competitions,bidding}.php`.

### 3.1 Authentication and meta (Integrations)

**`POST /oauth/token`** · no auth · `throttle:oauth-token` · `public.v1.oauth.token`

Request (`application/x-www-form-urlencoded` or JSON):

```
grant_type=client_credentials&scope=competitions:read%20vendors:write
Authorization: Basic base64(client_id:client_secret)      (or client_id / client_secret in the body)
```

Response 200:

```json
{"access_token": "eyJ0eXAiOiJKV1Qi…", "token_type": "Bearer", "expires_in": 1800, "scope": "competitions:read vendors:write"}
```

| Error | Status |
|---|---|
| `unsupported_grant_type` | 400 |
| `invalid_scope` | 400 |
| `invalid_client` | 401 |
| `too_many_requests` | 429 |

The error body is `{"error": "<code>", "error_description": "…", "message": "…", "code": "<code>", "errors": {}}`.

| Method and path | Scope | Name | Returns |
|---|---|---|---|
| `GET /ping` | any valid credential | `public.v1.ping` | `{"pong": true, "server_time": "…"}` |
| `GET /client` | any | `public.v1.client` | `{"client_id": "…", "name": "…", "organization_id": "…", "scopes": [..], "auth": "oauth"\|"api_key", "environment": "test"\|"live", "rate_limits": {"per_minute_read": 600, "per_minute_write": 300}}` |
| `GET /openapi.yaml` | none | `public.v1.openapi` | The OpenAPI 3.1 document (`application/yaml`) |

### 3.2 Organization and lookups

| Method and path | Scope | Name | Returns |
|---|---|---|---|
| `GET /organization` (Identity) | `organization:read` | `public.v1.organization` | `{"id", "name", "legal_name_ar", "legal_name_en", "cr_number", "vat_number", "national_address": {…}, "region": {"code", "name": {"ar", "en"}}, "city", "website", "email", "phone", "features": {"auction_enabled", "sponsorship_enabled"}, "subscription": {"plan_code", "status", "ends_at"} or null}` |
| `GET /lookups/{type}` (Catalog) | `lookups:read` | `public.v1.lookups.show` | `type` ∈ `regions`, `categories`, `close-reasons`. Items: `{"id", "code", "name": {"ar", "en"}, …type fields}` (`categories` add `is_other`, `auction_allowed`; `close-reasons` add `kind`, `requires_note`). Not paginated. |

### 3.3 Vendors (Integrations): flow F1

The public `Vendor` shape is the §2.12 `Vendor`, with `region` as `{"code", "name": {"ar", "en"}}` and `categories` as `[{"code", "name": {…}}]`.

| Method and path | Scope | Name | Notes |
|---|---|---|---|
| `GET /vendors` | `vendors:read` | `public.v1.vendors.index` | Filters: `status`, `q`, `updated_since`, `external_system` + `external_id` (exact match on external refs), `linked` (bool). Cursor paginated. |
| `POST /vendors` | `vendors:write` | `public.v1.vendors.store` | Body as the app `POST /vendors`, but with `region_code` and `category_codes[]` instead of ids. 201 `Vendor`, with a `Location` header. `external_ref_conflict` (409, `details.existing_id`). `vendor_email_taken` (409, `details.existing_id`). |
| `GET /vendors/{vendor}` | `vendors:read` | `public.v1.vendors.show` | – |
| `PATCH /vendors/{vendor}` | `vendors:write` | `public.v1.vendors.update` | Partial. `external_refs`, when sent, replaces the whole list. |
| `PUT /vendors/external/{system}/{external_id}` | `vendors:write` | `public.v1.vendors.upsert` | **Idempotent upsert by ERP key** (ref type `supplier`). It creates the vendor (201) or replaces its fields (200). Body as create, without `external_refs` (the path supplies the key). |
| `GET /vendors/external/{system}/{external_id}` | `vendors:read` | `public.v1.vendors.by-external` | 404 when unknown |

`system` must match `^[a-z0-9_]+(:[a-z0-9_]+)?$`. URL-encode the `:` in `custom:<name>`.

### 3.4 Competitions, attachments, invitations (Competitions): flows F2 and F3

**PublicCompetition** = the issuer projection of §2.6, with these changes:

- `permissions`, `viewer_role` and `live` are removed;
- `category` and `region` names are `{"ar", "en"}`;
- **added:** `"external_refs": [ExternalRef]` and `"created_by": {"type": "user"|"api_client", "id", "name"}`.

| Method and path | Scope | Name | Notes |
|---|---|---|---|
| `GET /competitions` | `competitions:read` | `public.v1.competitions.index` | Filters: `status` (comma list), `direction`, `format`, `updated_since`, `external_system` + `external_id`. Cursor paginated. |
| `POST /competitions` | `competitions:write` | `public.v1.competitions.store` | Creates a **draft**. See the body below. 201 + `Location`. |
| `GET /competitions/{competition}` | `competitions:read` | `public.v1.competitions.show` | – |
| `PATCH /competitions/{competition}` | `competitions:write` | `public.v1.competitions.update` | Same status rules as the app `PATCH` (§1.4). The body uses `category_code` and `region_code`. `external_refs` replaces the list. |
| `DELETE /competitions/{competition}` | `competitions:write` | `public.v1.competitions.destroy` | Draft only (204); otherwise 409 `competition_not_editable` |
| `POST /competitions/{competition}/publish` | `competitions:publish` | `public.v1.competitions.publish` | As the app endpoint. Sponsorship needing payment → 409 `sponsorship_payment_required` (payment is web-only). |
| `POST /competitions/{competition}/extend` | `competitions:manage` | `public.v1.competitions.extend` | `{new_close_at, reason}` |
| `POST /competitions/{competition}/cancel` | `competitions:manage` | `public.v1.competitions.cancel` | `{close_reason_code, note?}` (kind `cancel`) |
| `POST /competitions/{competition}/close` | `competitions:manage` | `public.v1.competitions.close` | Close without award: `{close_reason_code, note?}` (kind `not_awarded`) |
| `GET /competitions/{competition}/attachments` | `competitions:read` | `public.v1.competitions.attachments.index` | `[Attachment]` |
| `POST /competitions/{competition}/attachments` | `competitions:write` | `public.v1.competitions.attachments.store` | Multipart `file` + `kind` + `title?`, or JSON `{kind: "external_link", url, title}`. Rules as the app endpoint. |
| `DELETE /competitions/{competition}/attachments/{attachment}` | `competitions:write` | `public.v1.competitions.attachments.destroy` | Draft or scheduled only |
| `GET /competitions/{competition}/invitations` | `invitations:read` | `public.v1.competitions.invitations.index` | `[PublicInvitation]` |
| `POST /competitions/{competition}/invitations` | `invitations:write` | `public.v1.competitions.invitations.store` | Bulk, **all-or-nothing**: `{"invitations": [InvitationInput]}` → 201 `[PublicInvitation]`. Errors as the app endpoint (per-item codes in `details.item_codes`). |
| `DELETE /competitions/{competition}/invitations/{invitation}` | `invitations:write` | `public.v1.competitions.invitations.destroy` | Draft → delete (204); sent or viewed → revoked (200) |

**`POST /competitions` body:**

```json
{
  "title": "توريد أجهزة حاسب محمول 2026",
  "description": "…",
  "category_code": "it_hardware",
  "category_other_text": null,
  "region_code": "RIY",
  "direction": "tender",
  "format": "live",
  "preset_code": "standard_live_tender",
  "rules": { "start_price_minor": 25000000, "reserve_price_minor": 21000000 },
  "bidding_opens_at": "2026-11-02T06:00:00Z",
  "scheduled_close_at": "2026-11-09T12:00:00Z",
  "external_refs": [{"system": "sap_s4", "type": "purchase_requisition", "id": "10004567"}],
  "invitations": [
    {"vendor_external": {"system": "sap_s4", "id": "100045"}, "sponsored": false},
    {"vendor_id": "01j…"},
    {"email": "sales@newsupplier.sa", "name": "Sales"}
  ],
  "sponsorship": {"mode": "none", "max_passes": null}
}
```

- `rules` is a partial `RulesInput`, filled from the preset.
- `invitations` and `sponsorship` are optional.

**InvitationInput:** exactly one of `vendor_id`, `vendor_external: {system, id}` (resolved through `external_refs` of type `supplier`; unknown → item code `vendor_not_found`), or `email`. Plus `name?` and `sponsored?`.

**PublicInvitation:**

```json
{"id", "email", "name", "status", "organization": {"id", "name"},
 "vendor": {"id", "name", "external_refs": [ExternalRef]},
 "sponsored": bool, "pass_status": "…",
 "participant": {"id", "alias_no", "joined_at"},
 "sent_at", "joined_at", "declined_at", "revoked_at", "expired_at", "updated_at"}
```

- `organization`, `vendor` and `participant` may be `null`; `pass_status` may be `null`.
- `sponsored` means the coverage is sponsored.

### 3.5 Offers, results, awards (Bidding): flows F4, F5 and F6

| Method and path | Scope | Name | Returns |
|---|---|---|---|
| `GET /competitions/{competition}/offers` | `offers:read` | `public.v1.competitions.offers.index` | `[PublicStandingRow]`: the §2.8 `ParticipantStandingRow` plus `"vendor": {"id", "external_refs"}` or null. Sealed before unlock: amounts and ranks `null`. |
| `GET /competitions/{competition}/results` | `offers:read` | `public.v1.competitions.results` | `PublicResults`. Available when the status is `closed`, `bafo_round`, `awarded`, `not_awarded` or `cancelled`; otherwise 409 `results_not_available`. |
| `GET /awards` | `awards:read` | `public.v1.awards.index` | Filters: `status` (issued, revoked), `erp_sync_status`, `competition_id`, `updated_since`, `external_system` + `external_id` (the competition's refs). Cursor paginated. **This is the main polling endpoint for firewalled ERPs.** |
| `GET /awards/{award}` | `awards:read` | `public.v1.awards.show` | `PublicAward` |
| `POST /awards/{award}/erp-sync` | `awards:sync` | `public.v1.awards.erp-sync` | Body: `{"status": "synced"\|"failed", "message": "…"?, "external_refs": [ExternalRef]}` (the refs are stored on the award, e.g. `{system: "sap_s4", type: "purchase_order", id: "4500001234"}`). Returns `PublicAward`. The award must be `issued`, otherwise 409 `award_not_active`. Dispatches `AwardErpSynced`. |

**PublicResults:**

```json
{
  "competition_id": "01j9…", "reference_no": "BAFO-T-2026-000123", "status": "closed", "direction": "tender",
  "closed_at": "…", "offers_opened_at": null,
  "leader_amount_minor": 9800000, "reserve_met": true, "improvement_vs_start_bps": 6080,
  "ranking": [
    {"participant_id": "01j…", "alias_no": 3, "organization": {"id", "name", "cr_number", "vat_number"},
     "vendor": {"id", "external_refs"}, "rank": 1, "current_amount_minor": 9800000,
     "first_amount_minor": 11000000, "offers_count": 6, "last_offer_at": "…", "is_leader": true}
  ],
  "generated_at": "…"
}
```

`vendor` may be `null`.

**PublicAward:**

```json
{
  "id": "01jc…", "object": "award", "status": "issued",
  "competition": {"id": "01j9…", "reference_no": "BAFO-T-2026-000123", "title": "…", "direction": "tender",
                  "format": "live", "external_refs": [ExternalRef]},
  "winner": {"participant_id": "01j…", "organization": {"id": "…", "name": "…", "legal_name_ar": "…", "legal_name_en": "…",
             "cr_number": "7001234567", "vat_number": "300123456700003"},
             "vendor": {"id": "01j…", "external_refs": [{"system": "sap_s4", "type": "supplier", "id": "100045"}]}},
  "amount_minor": 22150000, "currency": "SAR", "price_basis": "excl_vat", "vat_rate_bp": 1500,
  "vat_minor": 3322500, "amount_incl_vat_minor": 25472500,
  "is_leading_offer": true, "rank_at_award": 1, "reserve_met": true,
  "justification": null,
  "offer": {"id": "01jb…", "seq": 57, "accepted_at": "…"},
  "awarded_at": "…", "awarded_by": {"name": "…", "email": "…"},
  "revoked_at": null, "revoke_reason": null,
  "erp_sync": {"status": "pending", "message": null, "synced_at": null, "refs": []},
  "ledger_head_hash": "b3f1…",
  "updated_at": "…"
}
```

- `winner.vendor` is `null` when the winner is not in the vendor directory.
- `justification` is `{"code", "name": {"ar", "en"}, "text"}` when set.
- The `vat_minor` shown is informational: `Money::vat(amount_minor)`.

### 3.6 Webhook endpoints (Integrations)

**Scope:** `webhooks:manage`. Resources are the same as in §2.12. These are the same operations as the dashboard (§1.9), under these paths:

| Method and path | Name |
|---|---|
| `GET /webhook-event-types` | `public.v1.webhook-event-types` |
| `GET /webhook-endpoints` | `public.v1.webhook-endpoints.index` |
| `POST /webhook-endpoints` | `public.v1.webhook-endpoints.store` (201 + `Location`; secret once) |
| `GET /webhook-endpoints/{endpoint}` | `public.v1.webhook-endpoints.show` |
| `PATCH /webhook-endpoints/{endpoint}` | `public.v1.webhook-endpoints.update` |
| `DELETE /webhook-endpoints/{endpoint}` | `public.v1.webhook-endpoints.destroy` |
| `POST /webhook-endpoints/{endpoint}/test` | `public.v1.webhook-endpoints.test` |
| `POST /webhook-endpoints/{endpoint}/rotate-secret` | `public.v1.webhook-endpoints.rotate-secret` |
| `GET /webhook-endpoints/{endpoint}/deliveries` | `public.v1.webhook-endpoints.deliveries` (cursor) |
| `POST /webhook-deliveries/{delivery}/redeliver` | `public.v1.webhook-deliveries.redeliver` |

That makes 42 public routes in total (41 operations plus `openapi.yaml`), above the ≈ 28 of the MVP estimate. The extras are cheap CRUD siblings.

---

## 4. Webhooks

### 4.1 Event catalogue (v1)

**Delivery rules:**

- Webhooks are delivered only to the **issuer** organization's endpoints.
- Payloads are **thin**. Consumers **GET the object** (`links.object`) for full state.
- Amounts appear only where the issuer may see them now.

| Type | Emitted when (domain event) | `data.object` fields |
|---|---|---|
| `competition.published` | `CompetitionPublished` | `id, object:"competition", reference_no, status, direction, format, bidding_opens_at, scheduled_close_at, external_refs` |
| `competition.extended` | `CompetitionExtended` | `id, object, previous_close_at, effective_close_at, extension_count, kind` (auto, manual, admin) |
| `competition.closed` | `CompetitionClosed` | `id, object, status:"closed", closed_at, participants_with_offers, offers_count` |
| `competition.offers_opened` | `OffersUnsealed` (sealed format, at close) | `id, object, offers_opened_at` |
| `competition.cancelled` | `CompetitionCancelled` | `id, object, cancelled_at, reason:{code, name:{ar,en}}, note` |
| `competition.not_awarded` | `CompetitionClosedWithoutAward` | `id, object, not_awarded_at, reason:{code, name:{ar,en}}, note` |
| `invitation.accepted` | `InvitationJoined` | `id, object:"invitation", competition_id, email, organization:{id,name}, vendor:{id, external_refs}\|null, sponsored, joined_at` |
| `invitation.declined` | `InvitationDeclined` | `id, object:"invitation", competition_id, email, vendor:{id, external_refs}\|null, declined_at` |
| `offer.submitted` | `OfferAccepted` (the participant's first offer) | `id, object:"offer", competition_id, participant_id, organization:{id,name}, vendor:{id, external_refs}\|null, seq, stage, amount_minor\|null, currency, accepted_at` |
| `offer.updated` | `OfferAccepted` (a later offer) | same as `offer.submitted` |
| `award.issued` | `AwardIssued` | `id, object:"award", competition_id, status:"issued", winner:{organization:{id,name,cr_number,vat_number}, vendor:{id, external_refs}\|null}, amount_minor, currency, awarded_at` |
| `award.cancelled` | `AwardRevoked` | `id, object:"award", competition_id, status:"revoked", revoked_at, reason` |
| `webhook.test` | test button or API | `id:<endpoint id>, object:"webhook_endpoint", message:"BAFO test event"` |

**`offer.*.amount_minor`** is `null` for offers in the `sealed` stage, whose webhooks fire before `offers_opened_at`. For every other stage (initial, live, bafo) the issuer may see the amount, so it is included.

### 4.2 Envelope

```http
POST /bafo/webhooks HTTP/1.1
Host: erp-gateway.customer.sa
content-type: application/json
user-agent: BAFO-Webhooks/1.0
webhook-id: 01jf3m6k8q9r0s1t2v3w4x5y6z
webhook-timestamp: 1794215564
webhook-signature: v1,K5oZfzN95Z9UVu1EsfQmfVNQhnkZ2pj9o9NDN/H/pI4=

{
  "id": "01jf3m6k8q9r0s1t2v3w4x5y6z",
  "type": "award.issued",
  "api_version": "v1",
  "environment": "test",
  "occurred_at": "2026-11-10T09:12:44.000Z",
  "organization_id": "01j8…",
  "sequence": 1,
  "data": {
    "object": {
      "id": "01jc…", "object": "award", "competition_id": "01j9…", "status": "issued",
      "winner": {"organization": {"id": "01j…", "name": "شركة الريادة", "cr_number": "7001234567", "vat_number": "300123456700003"},
                 "vendor": {"id": "01j…", "external_refs": [{"system": "sap_s4", "type": "supplier", "id": "100045", "number": null, "url": null}]}},
      "amount_minor": 22150000, "currency": "SAR", "awarded_at": "2026-11-10T09:12:44.000Z"
    }
  },
  "links": {"object": "http://localhost:8000/api/public/v1/awards/01jc…"}
}
```

**Envelope fields:**

| Field | Meaning |
|---|---|
| `id` | Equals the `webhook-id` header. It is stable across retries: **deduplicate on it**. |
| `environment` | `config('bafo.integrations.key_environment')` |
| `sequence` | Monotonic **per object**. Drop events whose `sequence` is ≤ the last one you processed for the same `data.object.id`. |
| `links.object` | The public API URL of the object; for `webhook.test`, the endpoint URL |

### 4.3 Signature (Standard Webhooks)

- **Secret.** `whsec_<base64>`. The HMAC key is `base64_decode(<the part after "whsec_">)`.
- **Signed content.** `"{webhook-id}.{webhook-timestamp}.{raw request body}"`.
- **Header.** `webhook-signature: v1,<base64(HMAC-SHA256(key, signed_content))>`. It may contain several space-separated signatures; accept if **any** matches.

**Consumers must:**

1. compare in constant time;
2. reject if `|now − webhook-timestamp| > 300 s`;
3. deduplicate on `webhook-id`;
4. return 2xx within 15 s and process asynchronously.

**Verification example (PHP):**

```php
[$id, $ts, $sigHeader] = [$h['webhook-id'], $h['webhook-timestamp'], $h['webhook-signature']];
$key = base64_decode(substr($secret, 6));
$expected = base64_encode(hash_hmac('sha256', "$id.$ts.$rawBody", $key, true));
$ok = collect(explode(' ', $sigHeader))
    ->contains(fn ($s) => str_starts_with($s, 'v1,') && hash_equals($expected, substr($s, 3)))
    && abs(time() - (int) $ts) <= 300;
```

**Verification example (Node):**

```js
const key = Buffer.from(secret.slice(6), 'base64');
const expected = crypto.createHmac('sha256', key).update(`${id}.${ts}.${rawBody}`).digest('base64');
const ok = sigHeader.split(' ').some(s => s.startsWith('v1,') &&
  crypto.timingSafeEqual(Buffer.from(s.slice(3)), Buffer.from(expected))) && Math.abs(Date.now()/1000 - Number(ts)) <= 300;
```

### 4.4 Delivery semantics

- **Guarantees.** At least once. There is **no global ordering**; use `sequence` per object.
- **Success and timeouts.** Only 2xx counts as success. Redirects are not followed. The timeout is 15 s.
- **Retries** after the first attempt: 5 s, 1 min, 5 min, 30 min, 2 h, 5 h, 10 h, 14 h. That is 9 attempts over about 31.6 h. The delivery is then `failed` and appears in the delivery log.
- **410 Gone** disables the endpoint immediately. Five days of continuous failure also disable it, and the organization's integration users are e-mailed.
- **Manual redelivery** is possible while the event is retained (30 days).
- **Source IPs.** Locally, deliveries come from the dev machine. In production they come from published static egress IPs.

---

## 5. Realtime payload reference

**Channels and authorisation:** ARCHITECTURE §9.2. **Payloads:**

| Event name (Echo: `.name`) | Channel | Payload |
|---|---|---|
| `live.updated` | `private-competition.{competitionId}` | `IssuerLiveSnapshot` (§2.8) |
| `live.updated` | `private-competition.{competitionId}.participant.{organizationId}` | `ParticipantLiveSnapshot` (§2.8) |
| `offer.accepted` | `private-competition.{competitionId}` | `OfferLogEntry` (§2.8) |
| `competition.updated` | both competition channels | `{"competition_id", "fields": ["title", "description", "schedule", "attachments", "invitations"], "server_time"}` |
| `comment.created` | both competition channels | `Comment` (§2.7), projected per audience (so the per-participant payloads differ) |
| `invitation.updated` | `private-competition.{competitionId}` | `Invitation` (issuer view, §2.7) |
| `notification.created` | `private-user.{userId}` | `{"notification": Notification, "unread_count": 4}` |
| `notifications.unread_count` | `private-user.{userId}` | `{"unread_count": 0}` |

**Web example:**

```ts
echo.private(`competition.${competitionId}.participant.${organizationId}`)
    .listen('.live.updated', (s: ParticipantLiveSnapshot) => { if (s.v > lastV) { apply(s); lastV = s.v } })
```

**Flutter.** Subscribe to `private-competition.{id}.participant.{orgId}`, bind the event `live.updated`, and authorise through `POST /broadcasting/auth` with the bearer header.
