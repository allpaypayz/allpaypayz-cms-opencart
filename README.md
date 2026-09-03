# Allpaypayz for OpenCart

**[⬇ Download the latest version](https://github.com/allpaypayz/allpaypayz-cms-opencart/archive/refs/heads/main.zip)** · [Browse the code](https://github.com/allpaypayz/allpaypayz-cms-opencart) · [MIT](LICENSE)

<sub>The archive is a snapshot of `main` — the current state of the plugin. Tagged releases will appear on the Releases page once the code leaves alpha.</sub>


OpenCart 3.x / 4.x payment extension that lists Allpaypayz on the checkout and
redirects the customer to the hosted checkout flow. Webhooks are verified by
the bundled PHP SDK and applied to the order.

> Status: **alpha** (v0.1.0). Targets OpenCart 3.0.x; 4.x is supported with
> the same code (paths normalise via the OCMOD installer).

## Install

1. Package the `upload/` tree as `allpaypayz.ocmod.zip` and upload via
   **Extensions → Installer**.
2. Navigate to **Extensions → Payments**, locate **Allpaypayz Payments**, click
   the green install button.
3. Open the edit screen and fill in:
   - **API key** — `sk_...` token.
   - **Webhook sign key** — symmetric secret.
   - **API environment** — Production / Staging.
   - **Payment method** — `card`, `redirect`, etc.
   - **Order status (paid)** / **Order status (failed)**.
4. From a shell inside `system/library/allpaypayz/` run:
   ```bash
   composer require allpaypayz/sdk guzzlehttp/guzzle
   ```
   The catalog controller loads `vendor/autoload.php` from that directory.

Webhook URL: `https://your-shop.example.com/index.php?route=extension/payment/allpaypayz/webhook`.

## How it works

- `admin/controller/extension/payment/allpaypayz.php` — settings page; saves
  configuration via `setting/setting`.
- `catalog/controller/extension/payment/allpaypayz.php`:
  - `index()` renders the **Pay now** button on the checkout review page.
  - `confirm()` is the AJAX endpoint hit when the customer clicks it. It
    calls `client->payments->createRedirect(...)` with
    `merchant_reference: OC-<order_id>` and returns the `checkout_url` as a
    JSON redirect.
  - `webhook()` verifies the `Callback-Signature` header via
    `Allpaypayz\Webhooks::verify`, then promotes the order to the configured
    "paid" / "failed" status depending on the v4 `event.type`.
- `catalog/model/extension/payment/allpaypayz.php` — registers the payment
  method on the checkout-method list.
- `system/library/allpaypayz/` — bundled PHP SDK + composer vendor.

## Event-to-status mapping

| v4 `event.type` | OpenCart action |
|---|---|
| `payment.succeeded`, `order.completed` | `order_status_success` (e.g. *Processing* / *Complete*) |
| `payment.failed`, `payment.cancelled`, `order.cancelled`, `order.expired` | `order_status_failed` (e.g. *Cancelled* / *Failed*) |
| `payment.refunded`, `payment.partially_refunded`, `refund.succeeded` | logged for operator follow-up |

## Files

```
cms-opencart/
├── README.md
├── composer.json
├── install.json
└── upload/
    ├── admin/
    │   ├── controller/extension/payment/allpaypayz.php
    │   ├── language/{en-gb,ru-ru}/extension/payment/allpaypayz.php
    │   └── view/template/extension/payment/allpaypayz.twig
    ├── catalog/
    │   ├── controller/extension/payment/allpaypayz.php   — checkout + webhook
    │   ├── language/{en-gb,ru-ru}/extension/payment/allpaypayz.php
    │   ├── model/extension/payment/allpaypayz.php
    │   └── view/theme/default/template/extension/payment/allpaypayz.twig
    └── system/library/allpaypayz/                        — bundled SDK
```

## License

MIT
