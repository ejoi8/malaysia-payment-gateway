# Handover — `ejoi8/malaysia-payment-gateway` v2 refactor

**Branch:** `refactor/v2-consistent-gateways`  ·  **Status:** all changes **unstaged** (review then commit)
**Tests:** `114 passed (261 assertions)` — up from a `70`-test baseline, all green
**Working copy:** `C:\laragon\www\mpg-inspect` (a clone of the published package)

---

## 1. TL;DR — what this branch does

A four-part overhaul of the package, kept backward-compatible at the array level so the existing consumer app (`tempahan-bilik-mesyuarat`, which depends on this package) upgrades without a rewrite:

1. **Consistency refactor (v2):** one `AbstractGateway` base class, typed result objects, shared helpers, a facade. The 5 gateways lost ~half their code.
2. **Flexibility + doc-grounded correctness:** new response types for non-redirect gateways, a signing helper, a shared HTTP client, and **real webhook signature verification** (was stubbed). All verified against the official CHIP/ToyyibPay/Stripe/PayPal docs.
3. **Notifications:** optional merchant/admin email + an optional **signed outgoing webhook** to your own backend.
4. **Correctness fixes:** refunds now update status, the initiation transaction id is persisted, and concurrent duplicate webhooks are serialized.

It is a **v2.0** (the canonical return type changed from `array` to an object) but the objects implement `ArrayAccess`, so old `$r['url']` / `$r['session_id']` access keeps working.

---

## 2. Architecture after the refactor

```
src/
  Contracts/
    GatewayInterface.php      initiate(): PaymentResponse, verify()/refund(): VerificationResult
    PayableInterface.php      (unchanged; optional hooks documented below)
  Gateways/
    AbstractGateway.php       ★ base: config resolver, http(), response builders, capability defaults
    ChipGateway / ToyyibPayGateway / StripeGateway / PayPalGateway / ManualProofGateway   (slimmed)
  Responses/
    PaymentResponse.php       ★ initiate() result — readonly + ArrayAccess + JsonSerializable
    VerificationResult.php    ★ verify()/refund() result — same pattern
  Support/
    Money.php                 ★ cents → decimal string
    LineItems.php             ★ summaryName() — the single gateway line name + "(N items)"
    Signature.php             ★ hmac / hash / rsaVerify / constant-time equals
    GatewayFactory.php        (unchanged)
  Facades/
    Payment.php               ★ Payment::initiate(...), Payment::gateway('chip')
  Listeners/
    UpdatePaymentStatus.php   success/failed/refunded → persist status
    SendPaymentNotification.php  customer emails + admin email
    DispatchPaymentWebhook.php   ★ signed outgoing webhook
    PersistInitiationId.php   ★ store gateway txn id at initiation
    Concerns/PersistsPayableState.php  ★ shared write-back trait
  Mail/ Events/ Enums/ Models/ Http/Controllers/   (Mail + Models + Controllers edited)
resources/views/
    auto-submit.blade.php     ★ renders a form-POST PaymentResponse
    mail/payment_admin.blade.php  ★ admin alert
```
★ = new in this branch.

### Key flow (also in README "How It Works")
`initiate()` → `PaymentResponse` (redirect / form / client_token / instructions / error) → customer pays → gateway hits the single route `/payment/webhook/{driver}` → controller verifies signature + payment → fires `PaymentSucceeded`/`PaymentFailed` → listeners update status, send emails, POST the outgoing webhook.

---

## 3. New public API surface

| Thing | Use |
| ----- | --- |
| `Payment` facade | `Payment::initiate($driver, $payable)`, `Payment::gateway($driver)`, `Payment::refund(...)` |
| `PaymentResponse` | `->isRedirect()/isFormPost()/isClientToken()/isInstructions()/isError()`, `->redirectUrl()`, `->formAction()/formFields()`, `->token()`, `->errorMessage()`; **array access preserved** |
| `VerificationResult` | `->success`, `->transactionId`, `->error`, `->meta`; array access preserved |
| `AbstractGateway` | extend it; implement `getName/getType/initiate/verify/getPaymentIdFromRequest`; use `$this->setting/http/redirect/form/clientToken/fail/verified/rejected/appendReference` |
| `Support\Signature` | `hmac()`, `hash()`, `rsaVerify()`, `equals()` |
| Response types `form` / `client_token` | unblock iPay88/senangPay (form POST) and Midtrans/Razorpay (JS SDK) |

### Optional model hooks (on the configured payable model)
- `applyPaymentGatewayUpdate(array $attributes): void` — custom column mapping / non-Eloquent.
- `findByTransactionId(string $id): ?self` — **required for automatic refund-status updates.**
- `createForSandbox(array $attributes): static` — sandbox support for custom models.

The built-in `Models\Payment` implements all three.

---

## 4. Config additions (`config/payment-gateway.php`)

| Key | Purpose |
| --- | ------- |
| `gateways.chip.public_key` (`CHIP_PUBLIC_KEY`) | PEM key for CHIP RSA webhook verification |
| `gateways.paypal.webhook_id` (`PAYPAL_WEBHOOK_ID`) | enables PayPal webhook verification |
| `http.timeout` / `http.connect_timeout` | timeouts for all gateway calls via `AbstractGateway::http()` |
| `persist_initiation_id` (`PAYMENT_PERSIST_INITIATION_ID`, default `true`) | save txn id at initiation |
| `notifications.admin_email` (`PAYMENT_ADMIN_EMAIL`) | merchant alert on success/failure (comma-separated) |
| `outgoing_webhook.{url,secret,queue}` (`MERCHANT_WEBHOOK_*`) | signed server-to-server event forwarding |
| `callbacks.lock` / `lock_seconds` / `lock_wait` | concurrent-callback serialization |
| `redirects.success` / `redirects.failed` (`PAYMENT_SUCCESS_URL` / `PAYMENT_FAILED_URL`) | post-payment landing URLs; per-payment override via `metadata.urls.{success,failed}_redirect`; unset → built-in status page |
| `gateway_line.append_count` / `label` | the "(N items)" suffix on the single gateway line |

**Legacy flat config keys were dropped** (`chip_secret_key`, `stripe_secret_key`, …). Use the nested `gateways.<name>.<key>` form (or per-payable `getPaymentSettings()` overrides).

---

## 5. Design decisions & rationale (read before changing things)

- **Amounts are integer cents everywhere.** This is the `PayableInterface::getPaymentAmount()` contract (`5000` = RM 50.00) and is asserted in tests. ⚠️ **Two AI doc-review agents flagged a false "100× undercharge" bug for CHIP and ToyyibPay** — both were wrong (the package supplies cents, those gateways want cents). **Do not "fix" it.** PayPal is the only gateway that converts to a decimal string (via `Money::toDecimal()`).
- **Gateways are charged ONE line at `getPaymentAmount()` — never the itemised list.** The full `items` array stays on the payment record and on the merchant's own pages, but the gateway only ever sees `description + "(N items)"` @ total (`LineItems::summaryName()`). This is deliberate: forwarding items made Stripe charge the item-sum (not the declared `amount`), made PayPal throw `ITEM_TOTAL_MISMATCH` when a discount/fee meant `amount ≠ Σ(items)`, and risked provider line-item caps. Single-line makes `amount` the sole source of truth. Do not re-add itemised line items to the gateway payloads without re-introducing those risks. The `(N items)` suffix is display-only (config `gateway_line.*`); the gateway line `quantity` is always `1` (a real quantity would round wrong on mixed-price carts).
- **Typed objects + `ArrayAccess`.** Lets v2 be a clean typed API while old array access keeps the consumer app working. `PaymentInitiated::$response` deliberately **stays a plain array** (tests assert `is_array`, and arrays are safest for queued listeners) — the manager passes `$response->toArray()`.
- **Signature verification = verify-when-configured, skip-with-warning otherwise.** Keeps local dev and the webhook test working without keys. ToyyibPay's `md5` hash needs no config and verifies automatically. ⚠️ Stripe still **fails open** when no `webhook_secret` is set (documented; a strict-mode flag is a deferred item).
- **Refund persistence is best-effort.** `UpdatePaymentStatus::handleRefund()` is wrapped in try/catch so a missing/un-migrated model never breaks `GatewayManager::refund()`.
- **Idempotency lock degrades gracefully.** `Cache::lock` is used; if the cache store lacks lock support it falls back to no-lock (the existing sequential "already paid" check still applies).
- **CHIP supports refunds** (per its docs) — `supportsRefunds()` is now `true` (the only intentional test change: the old `assertFalse` became `assertTrue`).

---

## 6. How to run / verify

```bash
cd C:\laragon\www\mpg-inspect
composer install
vendor/bin/pest            # or: composer test  → 114 passed
```

- Tests use in-memory doubles (`tests/MockPayable.php`, `tests/TestEloquentPayable.php`) and `Http::fake()` / `Mail::fake()` — **no real DB migration or network**.
- RSA verification is tested with a fixed keypair in `tests/RsaTestKey.php` (so it runs even where runtime key generation is unavailable).
- Manual smoke test: set `PAYMENT_GATEWAY_SANDBOX=true`, visit `/payment-gateway/sandbox`.

---

## 7. Deferred / recommended next work (not done on this branch)

Priority order:

1. **CI (GitHub Actions)** — the `composer.json` claims Laravel 10–13 × PHP 8.2–8.5 but nothing tests that matrix. Highest ROI for a published package.
2. **Static analysis** — add `phpstan.neon` (Larastan) at level 5–6 + a CI step.
3. ~~Live `checkStatus()`~~ — **done.** Implemented for all 4 gateways + the `payment:reconcile` Artisan command (recovers missed webhooks). Transaction id resolved via the model's `transaction_id` column or an optional `getPaymentTransactionId()` hook (no interface break). Schedule `payment:reconcile` every few minutes.
4. **`CHANGELOG.md` + tag `v2.0.0`.**
5. **More controller tests** — webhook 403 signature-rejection path, GET-return redirect, `PaymentStatusController` pages.
6. **`refund($payable)` convenience** that reads driver + transaction id off the payable.
7. **Optional:** strict-signature mode (fail-closed in prod), throttle middleware on webhook/status routes, Artisan helpers (`payment:status {ref}`, fetch CHIP public key).
8. **Skip:** zero-decimal currency (JPY/KRW) handling — irrelevant for an MYR-focused package.

---

## 8. Changed files

**New (22):** `src/Gateways/AbstractGateway.php`, `src/Responses/{PaymentResponse,VerificationResult}.php`, `src/Support/{Money,LineItems,Signature}.php`, `src/Facades/Payment.php`, `src/Listeners/{DispatchPaymentWebhook,PersistInitiationId}.php`, `src/Listeners/Concerns/PersistsPayableState.php`, `src/Mail/PaymentAdminMail.php`, `resources/views/auto-submit.blade.php`, `resources/views/mail/payment_admin.blade.php`, plus tests: `tests/RsaTestKey.php`, `tests/Unit/{MoneyTest,LineItemsTest,ResponsesTest,SignatureTest,PersistInitiationIdTest}.php`, `tests/Feature/{PaymentFacadeTest,AdminNotificationTest,OutgoingWebhookTest}.php`.

**Modified:** the 5 gateways, `GatewayInterface`, `GatewayManager`, `PaymentGatewayServiceProvider`, `PaymentWebhookController`, `SendPaymentNotification`, `UpdatePaymentStatus`, `Models/Payment`, `config/payment-gateway.php`, `README.md`, and the corresponding tests/helpers.

---

## 9. Delivery checklist

- [ ] `git diff main` review (note: repo is LF + `autocrlf=true`, so the recorded diff is clean despite working-copy CRLF warnings)
- [ ] `vendor/bin/pest` green
- [ ] Commit on `refactor/v2-consistent-gateways`
- [ ] Update the consumer app only if it type-hints `array` on `initiate()`/`verify()` returns or constructs gateways with positional args (otherwise no change needed)
- [ ] Tag `v2.0.0`, push, open PR
- [ ] `HANDOVER.md` (this file) is for the team — gitignore it if you don't want it in the release

🤖 Generated with [Claude Code](https://claude.com/claude-code)
