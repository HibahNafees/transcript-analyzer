# Quantified permissions

What each kind of caller is allowed to consume, with the cap and the window it's enforced over. Every number here is in code. The file and line are given so it can be checked.

## Consumed resources

| Who | What gets consumed | Cap | Window | Enforced in |
|---|---|---|---|---|
| Logged-in account | **Fire calls** (uploads + re-analysis): 1 call per speaker in the transcript | **20 analyses** | 1 hour per account. The 60-minute window opens at the first hit and the count resets when it expires (fixed window) | `AppServiceProvider`, `RateLimiter::for('fire')` |
| ↳ per Fire call | Prompt tokens / output tokens | ≤ 24,000 chars of prompt (~6k tokens), ≤ 700 output tokens | per call | `TranscriptAnalyzer::MAX_PROMPT_CHARS`, `FireClient` `max_tokens` |
| ↳ per Fire call | Wall-clock time | 60 s | per call | `FIRE_TIMEOUT` |
| ↳ per upload request | Server time (PHP worker held during analysis) | 180 s | per request | `set_time_limit(180)`, nginx `fastcgi_read_timeout 180` |
| Logged-in account | Upload size | 4 MB `.vtt` | per request | `TranscriptController::store` (`max:4096`) |
| Any IP | Log-in and sign-up attempts (password and invite-code guessing) | 10 | 1 minute per IP | `RateLimiter::for('auth')` |
| Anyone without the code | Account creation | 0 (needs `INVITE_CODE`; empty means closed) | n/a | `RegisterController` |
| Another account | Someone else's transcript | 0 (404) | n/a | `TranscriptPolicy` |
| Stripe | Order state: `orders.paid_at` | set **once** per order; repeats are no-ops | forever | `StripeWebhookController` (`UPDATE … WHERE paid_at IS NULL`) |
| Stripe (or an attacker) | Webhook acceptance | 0 without a valid `Stripe-Signature` | signature timestamp must be ≤ 5 minutes old | `Webhook::constructEvent` default tolerance 300 s |

## Worst case per account per hour

20 analyses × (speakers per transcript) Fire calls. A normal class (~7 speakers) makes **≈140 Fire calls per hour**. Each call is at most ~6k prompt + 700 output tokens.

## Known gaps (not capped yet)

- **Speakers per transcript are unbounded.** A crafted 4 MB `.vtt` with hundreds of speaker labels makes hundreds of Fire calls in one upload, and that still counts as 1 of the 20. The next fix is a speaker cap, for example 60.
- **Number of accounts is unbounded** for anyone who has the invite code, so the per-account cap multiplies by the number of people who know it. To shrink it, rotate `INVITE_CODE`.
- **No total Fire budget** (per day or across all accounts). The only global limit is the credit balance on the Fire key itself.
- **Paid credits aren't enforced yet.** `orders.analysis_credits` records what an order bought, and the webhook marks it paid, but uploads don't spend credits yet. The 20/hour limit applies to every account, paid or not.
