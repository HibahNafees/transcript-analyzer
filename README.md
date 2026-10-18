# Transcript Analyzer

Upload a Zoom class transcript (`.vtt`) and get, per speaker:

- how much they spoke: lines, words, share of words, talk time (computed locally)
- a 2–3 sentence summary of what they said, and one thing they contributed (from Fire's `/v1/chat`)
- flags on lines that look like **two people on one connection** (a shared laptop, a classroom Zoom Room)

Live: https://hibah-transcripts.vercel.app (Vercel proxy in front of https://transcripts.66-135-1-187.sslip.io) · Sample input: [`public/sample-class.vtt`](public/sample-class.vtt)

## How it works

```
upload .vtt ─► VttParser ─► SharedConnectionDetector ─► Postgres (transcripts, speakers, utterances)
                                                              │
                                    TranscriptAnalyzer ◄──────┘  one Fire call per speaker, in parallel
                                              │
                                              ▼
                                   /transcripts/{id} results page
```

| File | Role |
|------|------|
| `app/Services/Vtt/VttParser.php` | WebVTT → `Cue` objects. Handles Zoom `Name: text` prefixes, `<v Name>` voice tags, CRLF/BOM, hour-less timestamps, NOTE/STYLE blocks. Unattributed cues become `Unknown speaker`. |
| `app/Services/SharedConnectionDetector.php` | Heuristic flags: speaker addressed by their own name, a speaker answering their own question within 3 s, "this is Sam" under someone else's label, talk of sharing a laptop. Each flag stores a human-readable reason. |
| `app/Services/TranscriptImporter.php` | Parses + stores everything in one DB transaction; computes line/word/time counts. |
| `app/Services/TranscriptAnalyzer.php` | Builds a per-speaker prompt, calls Fire concurrently, parses the JSON reply. Partial failures are recorded per speaker and can be retried from the page. |
| `app/Services/Fire/FireClient.php` | Thin client for `POST /v1/chat` (Bearer `fire_sk_…`). |
| `app/Http/Controllers/TranscriptController.php` | Upload, results page, retry. |

Counts are never asked of the model — only the summary and contribution are.

## Setup

Requires PHP 8.3, Composer, PostgreSQL.

```bash
composer install
cp .env.example .env && php artisan key:generate
# create the database, then fill DB_PASSWORD and FIRE_API_KEY in .env
sudo -u postgres psql -c "CREATE ROLE transcripts_user LOGIN PASSWORD '...'" -c "CREATE DATABASE transcripts OWNER transcripts_user"
php artisan migrate
php artisan serve        # or point nginx at public/
```

Without `FIRE_API_KEY` the app still parses, stores and counts; summaries are skipped and the page says so.

| Variable | Default | Notes |
|----------|---------|-------|
| `FIRE_API_KEY` | — | `fire_sk_…` token from https://prosaga.net/portal |
| `FIRE_MODEL` | `claude-haiku-4-5` | any chat `species_name` from `GET https://fire.prosaga.net/v1/models` |
| `FIRE_BASE_URL` | `https://fire.prosaga.net` | |
| `FIRE_TIMEOUT` | `60` | seconds per Fire call |
| `INVITE_CODE` | — | code required to register; empty means registration is closed |
| `STRIPE_WEBHOOK_SECRET` | — | `whsec_…` signing secret; empty means the webhook rejects everything |

## Tests

```bash
php artisan test
```

Parser and detector tests are plain PHPUnit (no database). The feature test runs the full upload against SQLite in memory with Fire faked via `Http::fake`, so tests never spend credit.

## Deployment (current box)

- Code: `/var/www/transcripts` on the Vultr instance, nginx vhost `/etc/nginx/sites-available/transcripts`, TLS via certbot.
- Transcripts uploaded before accounts existed have no owner and are hidden until `php artisan transcripts:claim you@example.com` assigns them.
- Deploy: `./deploy.sh` on the server (clears caches, runs tests, migrates, re-caches). Never run `php artisan test` while config is cached; the base TestCase refuses to, because cached config would point tests at the production database.
- nginx `fastcgi_read_timeout` is 180 s because analysis runs inside the upload request.

## Payments (Stripe webhook, test mode)

`POST /stripe/webhook` marks an `orders` row paid. It verifies the `Stripe-Signature` header with `STRIPE_WEBHOOK_SECRET`, reads `metadata.order_id` from a paid `checkout.session.completed` (or `…async_payment_succeeded`) event, and sets `paid_at` once with `UPDATE … WHERE paid_at IS NULL`. A bad or missing signature, a stale (>5 min) timestamp, or an unset secret gets a 400 and changes nothing. Every verified event gets a 200, even ones it ignores, so Stripe doesn't retry them forever. Each outcome is logged as `stripe.webhook` in `storage/logs/laravel.log`.

What each caller may consume (caps and windows) is in [PERMISSIONS.md](PERMISSIONS.md). The webhook test run and permissions are also written up as a one-page PDF: [docs/stripe-webhook-and-permissions.pdf](docs/stripe-webhook-and-permissions.pdf).

**Verification run, 18 Oct 2026 (Stripe sandbox, live endpoint through Vercel):**

| # | Sent | Response | Log line | `orders.paid_at` (order 1) |
|---|---|---|---|---|
| 1 | `stripe trigger checkout.session.completed` with `metadata.order_id=1` (event `evt_1URp7VEh0gYG3kQhC4ysObe5`) | 200 | `order 1 marked paid` | `NULL` → `2026-10-18 08:07:06` |
| 2 | `stripe events resend` of the same event | 200 | `order 1 already paid` | unchanged, `08:07:06` |
| 3 | Same event body signed with a wrong secret | 400 `Invalid signature` | `stripe.webhook.rejected: No signatures found matching…` | unchanged, `08:07:06` |

Test order 1 belongs to a dedicated test account (`stripe-test@example.invalid`).

## Known limits / next steps

- **Analysis is synchronous.** Fine for a class-sized transcript (≈5 s for 7 speakers); a 3-hour transcript with 40 speakers should move to a queued job.
- **Accounts are invite-only.** Registering needs `INVITE_CODE`; each person sees only their own transcripts (others get a 404). Uploads and re-analysis are limited to 20/hour per account, and log-in/sign-up to 10/minute per IP. There's no password reset yet (no mail is configured), so a forgotten password has to be reset from the server with tinker.
- **Shared-connection detection is heuristic.** It catches self-address, self-answers and introductions; it can't hear two voices. Teachers' rhetorical "Does that make sense? Yes, so…" can false-positive.
- A Zoom label like `Unknown caller` is treated as a real speaker; only cues with no label at all become `Unknown speaker`.
- Re-attributing flagged lines to the right person (e.g. split "Priya Patel" into Priya and Sam) is not built yet; it's the natural next feature.
