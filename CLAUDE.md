# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that syncs WooCommerce product prices from an external CRM API (`poloniacup.eu/api/getProductDetails`). It uses **Action Scheduler** (bundled with WooCommerce) to run background jobs every 6 hours.

## Commands

```bash
# Install/update PHP dependencies
composer install
composer update

# Dump optimized autoloader after adding new classes
composer dump-autoload -o
```

There are no automated tests in this project.

## Architecture

### Flow

1. Every 6 hours, `crm_sync_dispatch_price_jobs` fires — queries all published WooCommerce products with a SKU and enqueues one async `crm_sync_update_price` action per product.
2. Each `crm_sync_update_price` action instantiates `PriceSync` and calls `updateProductPrice()`, which hits the external API and updates `regular_price` on the WC product.
3. Action Scheduler handles retries automatically on failure (jobs re-throw exceptions intentionally).

### Key design decisions

- **Scheduling guard** (`crm_sync_ensure_scheduled`): runs on `action_scheduler_init` (not `init`) to ensure AS store is ready. Deduplicates pending actions — unschedules all then recreates one — to avoid duplicate runs.
- **No API key = no schedule**: if the API key option is empty, any existing scheduled actions are removed.
- **IPv4 forced** via `CURLOPT_IPRESOLVE` on each API request to work around IPv6 connectivity issues on the host.
- **`PriceSync`** (`src/PriceSync.php`): handles HTTP + price extraction logic. Returns `null` for missing price (skips silently) vs. throws `RuntimeException` for API errors (triggers AS retry).
- **`ProductModel`** (`src/ProductModel.php`): readonly DTO mapping the API response `content` field.

### WordPress option

`crm_sync_api_key` — stored via WordPress Settings API, managed on the admin page under **CRM Sync** menu.

### Action Scheduler groups/hooks

| Hook | Group | Purpose |
|------|-------|---------|
| `crm_sync_dispatch_price_jobs` | `crm-sync` | Recurring dispatcher (6h interval) |
| `crm_sync_update_price` | `crm-sync` | Per-product async update job |

### Autoloading

PSR-4: `CrmSync\` → `src/`. Add new classes to `src/` and they are picked up automatically.
