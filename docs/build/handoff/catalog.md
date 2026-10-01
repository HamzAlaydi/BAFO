# Catalog handoff

Written by the Identity and Catalog engineer on 2026-09-29. See `identity.md` for the shared test
results and the cross-module notes.

## 1. What is built

| Item | Detail |
|---|---|
| `GET /api/app/v1/lookups` (`app.v1.lookups.index`, guest) | `{regions, categories, close_reasons, presets}`: active rows, ordered by `sort_order`, names in the request locale. Sends `ETag` and `Vary: Accept-Language`; a matching `If-None-Match` answers 304 without a body. The ETag covers the data and the locale, never `meta.server_time`. |
| `GET /api/app/v1/lookups/{type}` (`app.v1.lookups.show`, guest) | `regions`, `categories`, `close-reasons` (`?kind=cancel\|not_awarded\|award_justification\|void_offer`; another value → 422) or `presets`; an unknown type → 404. Also sends an ETag. |
| `GET /api/public/v1/lookups/{type}` (`public.v1.lookups.show`, `api.scope:lookups:read`) | `regions`, `categories`, `close-reasons` with `name: {ar, en}` and the type fields; presets are not on the public API (404). |
| `CatalogReferenceSeeder` | The 13 administrative regions (official order, AR/EN), 14 categories (`vehicles` and `real_estate` with `auction_allowed = false`, `other` with `is_other = true` and sorted last), 15 close reasons (the "Other" ones require a note) and the 3 presets of §5.2. Idempotent `updateOrCreate` by `code`; `DatabaseSeeder` runs it. |
| Embeddable resources | `RegionResource` (`{id, code, name}`) and `CategoryResource` (`{id, code, name, is_other, auction_allowed}`) for other modules; `PresetResource::rules()` gives the RulesInput shape without prices. |
| Query | `App\Modules\Catalog\Queries\Lookups` (active rows, ordered) |

There is no `CatalogDemoSeeder`: the reference data is the demo data.

## 2. Contract gaps (marked `CONTRACT-GAP` in code)

1. The single lists (`GET /lookups/{type}`) send an ETag too; API.md names it on `GET /lookups` only.
2. The public close-reasons list accepts `?kind=` with the same meaning as the app API.
3. The public lists return active rows only, ordered by `sort_order`, like the app API.
4. `preset.rules` always has every RulesInput key except the prices, with `null` (or `enabled: false`) for keys missing from the stored JSON, so an admin edit cannot change the shape.

## 3. Requests to other owners

- **Admin:** the Lookups CRUD (§16) edits these tables directly. Re-running `CatalogReferenceSeeder` restores the seeded names and flags of the seeded codes (the contract says `updateOrCreate`), so admin edits of seeded rows do not survive a re-seed.
- **Competitions:** use `Category::auction_allowed` / `is_other` and `CloseReason::ofKind()`; embed `CategoryResource` and `RegionResource`.
