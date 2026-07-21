# ShopMGR

Self-hosted e-commerce by [ScriptGain](https://scriptgain.com).

Two halves in one application: a merchant back office for running the store, and
a customer-facing storefront for buying from it. Products with variants, SKUs and
inventory, collections, orders and fulfilment, customers, discounts, shipping
zones and rates, tax rules, and a themeable storefront with cart, checkout, and
customer accounts.

## Install

```
curl -fsSL https://install.scriptgain.com | sudo bash -s -- shop-mgr DOMAIN=shop.example.com SSL=1 EMAIL=you@example.com
```

Or from a checkout on a fresh Debian/Ubuntu host, as root:

```
DOMAIN=shop.example.com SSL=1 EMAIL=you@example.com ./deploy/install-master.sh
```

Then finish setup at `https://your.domain/setup` to create the first admin
account and enter your license key.

## Layout

- Storefront: `/`
- Merchant panel: `/admin`
- First-run wizard: `/setup`

## Commands

| Command | What it does |
| --- | --- |
| `php artisan shop:bootstrap` | Seeds baseline settings, roles, and store defaults. Idempotent. |
| `php artisan shop:license <key>` | Sets or re-checks the ScriptGain license key. |
| `php artisan app:update` | Applies a signed release from ScriptGain. |
| `php artisan db:backup:run` | Runs a database backup. |
| `php artisan housekeeping` | Prunes expired carts, sessions, and stale records. |
| `php artisan firewall:clear` | Clears firewall rules when locked out. |

## Requirements

PHP 8.3, MySQL or MariaDB, and a web server with the document root set to
`public/`. The dependency lock is pinned to PHP 8.3 via `config.platform`;
resolving against a newer PHP produces a tree that will not load at runtime.

## Licensing

ShopMGR validates against `https://scriptgain.com/v1`. One activation per
license by default.
