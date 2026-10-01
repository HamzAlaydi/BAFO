# Notifications module handoff

Written on 2026-09-29 by the Notifications engineer. It covers ARCHITECTURE §11 (the catalogue and its mechanics), the in-app API and devices (API.md §1.8, §2.11), the `user.{id}` channel and its two events (§9.2, §9.3), `PushNotifier` (§15.1), the shared mail theme, and `notifications:prune-devices` (§12).

## 1. Result

| Check | Command | Result |
|---|---|---|
| Module tests | `scripts/test-api.sh notifications tests/Feature/Notifications tests/Unit/Notifications` | 223 passed (1714 assertions) |
| Enum and lang sync (schema) | `scripts/test-api.sh notifications tests/Unit/Schema/EnumsTest.php` | passes, including the two new enums |
| PHPStan level 6 | `vendor/bin/phpstan analyse app/Modules/Notifications --memory-limit=1G` | 0 errors |
| Pint | `vendor/bin/pint --test` on the module, routes, lang and tests | clean |
| Caches | `route:cache` and `event:cache`, written to scratch paths | both succeed |

The Competitions, Bidding, Billing, Integrations and Identity events all exist now. My tests fire the real classes when they exist, which also checks the §10 constructors, so the 223 tests ran against them.

I ran the full `DemoSeeder` chain on a scratch database, with the sync queue and the array mailer, then dropped it. Billing's real `SubscriptionActivated` and `InvoiceIssued` events reached the billing users through the listeners, and `NotificationsDemoSeeder` added nothing twice.

The full suite does not finish at the moment because of other modules' in-progress code. See §6.

## 2. What exists

**Catalogue.** `Enums/NotificationType` holds the 31 types of §11.3. For each type it gives:

- the channel row;
- the lang key;
- the `trans_choice` count, for `minutes` and `days_left`;
- the notification class;
- a label.

`Notifications/BafoNotification` is the abstract base, and there are 31 thin `final` subclasses, `<Type>Notification`. Each subclass only names its type. Three of them add lines to the mail:

- `award.won`: the message to the winner;
- `invoice.issued`: the download hint;
- `sponsorship.publish_failed`: the refusal reason.

The base class does the following:

- `ShouldQueue`, on the `notifications` queue.
- The constructor sets `$this->id = strtolower(Str::ulid())`.
- `via()` returns the catalogue row minus the channels excluded for that recipient. It can narrow the row, never widen it.
- `toDatabase()` returns `{type, params, subject, route}`.
- `toMail()` builds a markdown `MailMessage` with the view `notifications::mail.notification`.
- `toPush()` returns a `PushMessage`: title, body, and `data` `{type, notification_id, subject_type, subject_id, route}`, all strings.

**Ids and recipients.** `Services/NotificationDispatcher` builds **one notification instance per recipient**, so every row gets its own ULID. This resolves the foundation handoff warning. It also drops duplicate recipients and skips a delivery once all of its channels are excluded. Its `allow()` method is the catalogue throttle: `Cache::add`, which is atomic on Redis.

**Rendering.** `Services/NotificationRenderer` renders the §11.4 templates in one language. It handles:

- `direction` → `:competition_type`, lowercase in English;
- the ISO times → Asia/Riyadh, through `Support/DisplayTime` («9 نوفمبر 2026، 3:30 م», "9 Nov 2026, 3:30 PM");
- `amount_minor` → `:amount`, through `Money::format`;
- `{ar, en}` maps, such as plan names and close reasons;
- the Arabic plural forms;
- the "fees covered" suffix.

In-app texts are rendered at read time in the request language. Push and mail are rendered in `users.locale`, because Laravel switches to the user's locale through `HasLocalePreference`.

**Recipients.** `Services/Recipients` returns active users with an active membership. The billing and integration sets are filtered by `billing.view` and `integrations.manage`. `Services/CompetitionAudience` returns the issuer team and the participant users.

**Notifiers.** These are typed services, one method per event: `CompetitionNotifier`, `BiddingNotifier`, `BillingNotifier` and `IntegrationsNotifier`. They hold the recipient rules, parameters, routes, throttles and heartbeat suppression of §11.3. All throttle windows are in `config.php` under `throttle`.

**Listeners.** `NotificationsServiceProvider::EVENT_LISTENERS` maps 31 §10 event class-strings to their listeners: 30 catalogue listeners plus `RemoveDeviceTokens`.

- Every listener extends `Listeners/QueuedNotificationListener`: `ShouldQueueAfterCommit` + `ShouldHandleEventsAfterCommit`, queue `notifications`, 3 tries.
- `handle(object $event)` reads the §10 property names through `Support/EventPayload`, which checks each type at runtime. A listener therefore works whenever its producing module lands, and an event that drifts from the contract fails loudly.
- `BroadcastNewNotification`, bound to `NotificationSent` for the `database` channel, sends `NotificationCreatedBroadcast`.

**Realtime.** Both events are on `private-user.{public_id}`, implement `ShouldBroadcast` and `ShouldDispatchAfterCommit`, and use queue `live`:

- `notification.created`: `{notification: Notification, unread_count}`, rendered in the recipient's language;
- `notifications.unread_count`: `{unread_count}`, after a read, read-all or delete.

The channel is `Broadcasting/UserChannel` (`user.{userPublicId}`). It accepts the user's own public id, in any case.

**Push.** `Contracts/PushNotifier` is bound to `Services/LogPushNotifier`. The driver comes from `bafo.notifications.push.driver` (`PUSH_DRIVER`), and an unknown driver throws. The log driver writes to the `push` channel: title, body, data, token count and platform counts. It **never writes the tokens**. `Channels/PushChannel` is registered as the `push` channel and skips users who have no device.

**HTTP.** API.md §1.8 exactly, with the route names `app.v1.notifications.*` and `app.v1.devices.*`:

- controllers `NotificationController` and `DeviceController`;
- requests `ListNotificationsRequest` and `StoreDeviceRequest`;
- resources `NotificationResource` and `DeviceResource`;
- policies `NotificationPolicy` (for Laravel's `DatabaseNotification`) and `DeviceTokenPolicy`, both answering 404 when the resource belongs to someone else;
- Actions `MarkNotificationRead`, `MarkAllNotificationsRead`, `DeleteNotification`, `DeleteAllNotifications`, `RegisterDevice` (upsert with `ON CONFLICT (token)`) and `RemoveDevice`.

`{notification}` is bound in the provider: any-case ULID, 404 when malformed.

**Mail theme** (`resources/views/vendor/mail/**`). It applies to **every** markdown mail, including Identity's and Competitions' token mails. I checked Identity's `OtpCodeMail`: it renders RTL. The theme provides:

- a header with the brand mark and the brand name in the mail's language;
- RTL for Arabic, with `dir` and an inline `text-align: right` on every block;
- brand colours from `bafo_colors.json` and the IBM Plex Sans Arabic stack;
- a localised footer.

The notification mail body is escaped HTML paragraphs, so a competition title can never become a link.

- **Logo:** `GET /mail/brand/bafo-mark.png`, public and cacheable, served from `Resources/brand`.
- **Local preview:** `GET /dev/mail` and `GET /dev/mail/{type}?locale=ar|en`, registered in `local` only.

**Other parts.**

- `notifications:prune-devices`, weekly: devices unseen for 90 days, and the devices of soft-deleted users.
- `NotificationsDemoSeeder`: a few in-app notifications per demo user, built from the other modules' demo data, database channel only.
- Lang files `lang/{ar,en}/notifications.php`: 31 templates, mail texts, time words, attributes, and the labels of `notification_type`, `delivery_channel` and `device_platform`.

## 3. For other modules

| Need | Use |
|---|---|
| Notify someone | Dispatch your §10 event. Never send a catalogue notification yourself. |
| The unread count (Identity `Me`) | `$user->unreadNotifications()->count()`, which you already use, or `app(NotificationInbox::class)->unreadCount($user)`. They are the same query. |
| A branded mail (token mails, §11.5) | A markdown `Mailable` whose view uses `<x-mail::message>`, sent in the recipient's language (`->locale($user->locale)` or `HasLocalePreference`). The theme and RTL apply automatically. For the HTML to be safe, put user-entered values in HTML paragraphs (`<p>{{ $value }}</p>`), not in Markdown lines. |
| Web and mobile: labels for a notification type or channel | `notifications.enums.notification_type.<type>` and `notifications.enums.delivery_channel.<channel>` (server side). The clients keep their own i18n. |

## 4. Requests to other owners

**Bidding**

- Heartbeats: I read `Cache::has("hb:{competitions.id}:{participants.id}")` on the default cache store, with **internal ids**. Please confirm, or tell me the ids you use. The key builder is `LiveHeartbeats::key()`.
- The event property names I read are: `OfferAccepted {offer, competition, context{leaderChanged, previousLeaderParticipantId}}`, `BafoRoundStarted/Ended {round, competition}`, `AwardIssued/Revoked {award, competition}` and `OfferVoided {void, offer, competition}`.
- For `standing.lost_lead`: during a BAFO round (no live phase) nothing is sent.

**Billing**

- The event property names I read are: `PaymentFailed {payment}`, `SubscriptionActivated/Expired {subscription}`, `SubscriptionExpiring {subscription, daysLeft}`, `InvoiceIssued {invoice}`, `SponsorshipSettled {sponsorship}`, `SponsorshipPublishFailed {sponsorship, payment, errorCode}` and `VoucherIssued {voucher}`.
- Dispatch `SponsorshipSettled` after `unused_count` is set. Nothing is sent when it is 0.
- The paying user is `payments.created_by_user_id`.

**Integrations**

- `ImportFinished` and `ExportFinished`: §10 gives only the type. My listener finds the `ImportJob` or `ExportJob` among the event's public properties, so your `$importJob` / `$exportJob` names work, and so would any other name.
- `WebhookEndpointDisabled {endpoint}`.
- Please fix `Http/Requests/StoreExportRequest::format()`. It clashes with `Illuminate\Http\Request::format()`, which is a PHP fatal, so `tests/Unit/ArchTest.php` stops the whole suite.

**Competitions**

- The events already match §10 and I test against them.
- `CompetitionExtensionFactory::auto()` reads `$attributes['previous_close_at']` while it is still an unresolved closure, and throws `CarbonImmutable::instance(Closure)`. My tests set the columns explicitly instead.
- `BroadcastInvitationUpdated` → `InvitationPresenter` (line 118) calls `Collection::loadMissing()`, which does not exist, when `InvitationSent` is dispatched. My tests fire events through a dispatcher that holds only the Notifications binding, so they are not affected.

**Identity**

- Nothing is blocking. `AccountDeleted` (one per user) → `RemoveDeviceTokens`.
- As a safety net, the prune command also removes the devices of soft-deleted users.

**Platform**

- No change needed. `PUSH_DRIVER` is already in `.env.example`.
- If you prefer to serve the mail logo from `public/`, point `bafo.notifications.mail.logo_path` at it. The module route can then go.

**Web and mobile**

- The push `data` and the broadcast payloads are exactly API.md §2.11 and §5.
- `route` values follow CONVENTIONS §4.3, plus `/integrations` and `/billing/invoices/{id}`.

## 5. Contract gaps (choices made; marked `CONTRACT-GAP` in code where relevant)

1. **Heartbeat key ids:** internal ids are assumed (see §4, Bidding).
2. **`ImportFinished` / `ExportFinished` property name:** the listener finds the job by type.
3. **Subjects of import and export jobs:** `import_job` and `export_job`. There is no morph alias for them in §4.9.
4. **`device_tokens.locale`:** the request language (`Accept-Language`).
5. **`POST /devices` `platform`:** required, because the column is NOT NULL.
6. **Audit (§4.5):**
   - Inbox reads and deletes are personal state and are not audited.
   - `device.registered` is written for a new registration or a token re-assigned to another user, not for a refresh.
   - `device.removed` is written on `DELETE /devices`.
   - The token never reaches the audit log.
7. **`standing.lost_lead`:** it is sent only in the `open` or `final_window` phase, never outside `live`.
8. **Throttles:**
   - `competition.updated` (10 min) covers every channel.
   - Auto extensions: at most one notification per 2 minutes per user, in-app included. Push is dropped for participants with a heartbeat. The issuer team has no heartbeat.
   - `offer.received`: one notification (in-app plus push) per 5 minutes.
   - `comment.created`: push only (5 min).
9. **`:reason`:**
   - For a cancellation it is a `{ar, en}` map: the close reason's name, plus « — note».
   - For a revoked award it is the plain text of `revoke_reason`.
   - When it is missing: «غير محدد» / "Not specified".
10. **The `sponsored` parameter:**
    - `competition.invited`: `sponsored` = a `reserved` or `joined` pass exists for the invitation.
    - `invitation.joined`: `sponsored` = `entitlement_source = sponsored_pass`.
    - Both add the line «رسوم المشاركة مغطّاة» when it is true.
11. **Copy refinements** (the keys and placeholders are unchanged):
    - The EN title of `competition.invited` is "New :competition_type invitation" ("Invitation to a auction" is not grammatical).
    - `sponsorship.unused_passes` uses the glossary term «تصاريح مشاركة مغطّاة» / "sponsored participation passes".
    - Periods were added to the plural rows.
12. **`competition.cancelled` invitees:** the users of organizations whose invitation is `sent` or `viewed` and whose `organization_id` is known.
13. **Mail logo:** it is served by the module route. `public/` is a shared (Platform) path.
14. **Per-user notification preferences:** they are not in the contract and were not built.
15. **`import.finished` / `export.finished` parameters:**
    - Imports also carry `mode` and `status`.
    - Exports carry `export_type`, `format` and `status`.

## 6. Cross-module failures seen in the full suite (not fixed; owners' code)

- `tests/Unit/ArchTest.php`: a PHP fatal in `Integrations\Http\Requests\StoreExportRequest::format()` stops the whole run.
- Other failures appear when the run skips `ArchTest.php` (`scripts/test-api.sh notifications tests/Feature tests/Unit/<each>/`):
  - `Platform/KernelMiddlewareTest` fails because Identity now appends `EnsureAccountActive` to `app_v1`.
  - `Platform/IdempotentRequestTest` and `Support/ApiConventionsTest` get 401 `invalid_token`, because Integrations' `api.client` is now on `public_v1`.
  - One `Bidding/PublicApiTest` case fails ("lists awards with a cursor and filters").
- In the same run, the only Notifications failures were the `EnumsTest` labels of `DeliveryChannel` and `NotificationType`. They are fixed now.
