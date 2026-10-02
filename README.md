# Transcript Analyzer

Upload a Zoom class transcript (`.vtt`) and get, per speaker:

- how much they spoke: lines, words, share of words, talk time (computed locally)
- a 2–3 sentence summary of what they said, and one thing they contributed (from Fire's `/v1/chat`)
- flags on lines that look like **two people on one connection** (a shared laptop, a classroom Zoom Room)

Live: https://transcripts.66-135-1-187.sslip.io · Sample input: [`public/sample-class.vtt`](public/sample-class.vtt)

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

## Tests

```bash
php artisan test
```

Parser and detector tests are plain PHPUnit (no database). The feature test runs the full upload against SQLite in memory with Fire faked via `Http::fake`, so tests never spend credit.

## Deployment (current box)

- Code: `/var/www/transcripts` on the Vultr instance, nginx vhost `/etc/nginx/sites-available/transcripts`, TLS via certbot.
- Deploy: sync code, then `php artisan migrate --force && php artisan config:cache route:cache view:cache`.
- nginx `fastcgi_read_timeout` is 180 s because analysis runs inside the upload request.

## Known limits / next steps

- **Analysis is synchronous.** Fine for a class-sized transcript (≈5 s for 7 speakers); a 3-hour transcript with 40 speakers should move to a queued job.
- **No login.** Anyone with the URL can upload and view. Uploads are rate-limited to 20/hour per IP because each one spends Fire credit. Add auth before sharing widely — these are student transcripts.
- **Shared-connection detection is heuristic.** It catches self-address, self-answers and introductions; it can't hear two voices. Teachers' rhetorical "Does that make sense? Yes, so…" can false-positive.
- A Zoom label like `Unknown caller` is treated as a real speaker; only cues with no label at all become `Unknown speaker`.
- Re-attributing flagged lines to the right person (e.g. split "Priya Patel" into Priya and Sam) is not built yet; it's the natural next feature.
