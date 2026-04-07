# Architecture Overview

This document is a contributor orientation guide — it covers where things live, how the main features work, and what is non-standard compared to a default Laravel project.

---

## Directory Layout

```
cardnavigator/
├── app/
│   ├── Console/Commands/CreateUser.php   # php artisan user:create
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                   # Admin-only controllers (requires is_admin)
│   │   │   ├── Auth/                    # Authentication controllers (Breeze)
│   │   │   ├── WebAuthn/                # Passkey registration and assertion
│   │   │   ├── CardController.php       # CRUD for credit cards
│   │   │   ├── CategoryController.php   # Per-place category overrides
│   │   │   ├── NearbyBusinessController.php  # Google Places lookup and ranking
│   │   │   └── PercentageController.php # Per-card cashback percentage management
│   │   └── Middleware/
│   │       ├── RequireAdmin.php         # 403 unless user.is_admin = true
│   │       └── HandleForcedPasswordChange.php
│   ├── Models/
│   │   ├── Card.php                     # Credit card (name, fee, preference, image, color)
│   │   ├── Category.php                 # Google type → friendly name mapping
│   │   ├── Percentage.php               # Cashback % per card+category
│   │   └── User.php                     # Authenticatable with WebAuthn support
│   └── Services/
│       └── UsLocationDetector.php       # Determines if coordinates are outside the US
├── database/
│   ├── migrations/                      # Schema history
│   ├── seeders/
│   │   ├── DatabaseSeeder.php           # Empty — no default data
│   │   ├── CardSeeder.php               # Example only — fictional cards
│   │   ├── CategorySeeder.php           # Google Places type → friendly name mapping
│   │   └── PercentageSeeder.php         # Example only — outrageous percentages
│   └── database.sqlite                  # Local SQLite database (gitignored)
├── docs/
│   ├── api/                             # Generated API docs (gitignored; built by CI)
│   ├── architecture.md                  # This file
│   └── deployment.md                    # Deployment guide (cPanel and SSH)
├── resources/views/
│   ├── admin/                           # Admin panel views
│   ├── auth/                            # Authentication views
│   ├── components/                      # Blade components
│   └── ...
├── routes/
│   └── web.php                          # All routes
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                       # Tests, formatting, coverage, docs
│   │   ├── deploy-cpanel.yml            # cPanel deployment
│   │   └── deploy-ssh.yml               # SSH deployment
│   ├── ISSUE_TEMPLATE/                  # Bug and feature request templates
│   ├── SECURITY.md                      # Vulnerability disclosure policy
│   └── dependabot.yml                   # Automated dependency updates
├── pint.json                            # Custom Pint (PHP-CS-Fixer) ruleset
└── phpdoc.dist.xml                      # phpDocumentor configuration
```

---

## Authentication

CardNavigator uses two authentication methods, managed by the [laragear/webauthn](https://github.com/laragear/webauthn) package:

1. **Email + password** — standard Laravel Breeze authentication.
2. **WebAuthn / passkeys** — hardware key or platform authenticator (Face ID, Windows Hello, etc.).

### Key configuration

- `WEBAUTHN_ID` in `.env` must be set to the full HTTPS URL of the domain (e.g. `https://example.com`). This is the Relying Party ID.
- WebAuthn endpoints bypass CSRF protection because the spec requires it — see `routes/web.php` lines using `withoutMiddleware(PreventRequestForgery::class)`.
- Passkey registration requires an authenticated session; assertion (login) is a guest flow.

### Admin access

The `is_admin` boolean on the `users` table gates all `/admin/*` routes via `RequireAdmin` middleware. The first admin account is created via `php artisan user:create --admin`. There is no self-registration or default account.

A non-admin authenticated user visiting any `/admin/*` route receives a 403.

---

## Card Ranking Logic

`NearbyBusinessController` is the heart of the application. Here is the sequence for a search:

1. **Location received** — the browser posts latitude/longitude to `/dashboard/search`.
2. **Google Places API call** — `NearbyBusinessController::search()` calls the Places API (New) `nearbySearch` endpoint with a configurable radius.
3. **Results cached** — responses are cached using `GOOGLE_MAPS_CACHE_TTL` (minutes). The cache key includes the coordinates and radius, so different locations produce different cache entries.
4. **Normalization** — each place is processed by `normalizePlace()`:
   - The first entry in `place['types']` is looked up in the `categories` table to find a `friendly_name`.
   - The address is inspected to determine if the location is outside the US (via `UsLocationDetector`).
5. **Ranking** — for each place, all cards with a matching `Percentage` record for that category are retrieved. If the place is outside the US, cards with a `foreign_transaction_fee > 0` have their effective percentage reduced by the fee amount. Cards are sorted descending by effective percentage.
6. **Response** — the ranked card list and place metadata are returned to the Alpine.js frontend.

---

## Google Places Integration

- **Endpoint used:** `POST https://places.googleapis.com/v1/places:searchNearby` (Places API New)
- **Auth:** API key via `X-Goog-Api-Key` header, set from `GOOGLE_MAPS_API_KEY` in `.env`.
- **Field mask:** Only the fields the app needs are requested (`displayName`, `types`, `formattedAddress`, `addressComponents`, `id`).
- **Cache:** `Cache::remember()` with TTL from `GOOGLE_MAPS_CACHE_TTL`. Default is 20 minutes.
- **`placeId`** — the Google Places `id` field (e.g. `ChIJ...`). Used as the URL parameter in `/places/{placeId}` to show details and allow category overrides for a specific location.

---

## Key Models and Relationships

```
User
  - is_admin: boolean
  - has many WebAuthnCredentials (via laragear/webauthn)

Card
  - name: string
  - foreign_transaction_fee: integer (percentage points, 0 if none)
  - preference: integer (display order)
  - image_data: text|null (base64-encoded image)
  - image_mime: string|null
  - color: string (hex colour for UI)
  - has many Percentages

Category
  - name: string (Google Places API type, e.g. "grocery_store")
  - friendly_name: string (user-facing, e.g. "Grocery")
  (no foreign key — categories are global, not per-user)

Percentage
  - card_id → Card
  - category: string (matches Category.friendly_name, not Category.name)
  - percentage: integer
```

Note: `Percentage.category` stores the **friendly name** (e.g. `"Grocery"`), not the raw Google Places type. This is the join point between the category mapping and the card ranking.

---

## Admin Panel

The admin panel lives at `/admin/*` and requires `is_admin = true`.

| Route | Purpose |
|-------|---------|
| `GET /admin/users` | List users, create new users, reset passwords, delete users |
| `POST /admin/users` | Create a user (no password set — user must reset) |
| `PATCH /admin/users/{user}/password` | Reset a user's password |
| `DELETE /admin/users/{user}` | Delete a user |
| `GET /admin/database` | Database tools page |
| `POST /admin/database/migrate` | Run pending migrations |
| `POST /admin/database/seed` | Run a named example seeder |

The Database Tools page discovers seeders via `glob(database_path('seeders/*.php'))`. Each seeder is expected to implement a static `label(): string` method for its display name.

---

## CI / CD

**CI (`ci.yml`)** — runs on every push to `main`, every PR targeting `main`, and on demand via `workflow_dispatch`:
1. Pint formatting check (`vendor/bin/pint --test`)
2. Full test suite with Xdebug coverage (`php artisan test --compact --coverage-clover`)
3. Coverage upload to Codecov
4. On push to `main` only: phpDocumentor generates `docs/api/` and publishes to GitHub Pages
5. All steps run regardless of prior failures; failures are summarised in a plain-text report
6. On PR failure: a `REQUEST_CHANGES` review is posted listing each failed step

**Deployment** — see `docs/deployment.md`.
