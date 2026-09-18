# DWC PrestaShop MCP (`dwcprestamcp`)

[![CI](https://github.com/ximo13/dwcprestamcp/actions/workflows/ci.yml/badge.svg)](https://github.com/ximo13/dwcprestamcp/actions/workflows/ci.yml)

A **standalone MCP (Model Context Protocol) server for PrestaShop**, community-made
and open source. This module embeds its **own** MCP server — powered by the
open-source [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk)
(Apache-2.0) — and exposes your store to AI agents (Claude, ChatGPT, Gemini, MCP
Inspector) over its own authenticated HTTP endpoint.

> ⚠️ Independent community project, **not affiliated with PrestaShop SA**. It does
> **not** require and does **not** depend on the official `ps_mcp_server` module.

## Why standalone?

- **No dependency** on `ps_mcp_server` (or `ps_accounts` / `ps_eventbus`).
- Runs **inside PrestaShop**, so tools have direct access to store data
  (`Product`, `Order`, `Db`, …) — no external process, no Webservice API needed.
- One module install, one endpoint, one token.
- 100% open source: your code is MIT, the bundled SDK is Apache-2.0.

## Requirements

- PrestaShop **8.2+** or **9.x**
- PHP **8.1+**
- Composer (to install the bundled dependencies)

## Install

```bash
git clone https://github.com/ximo13/dwcprestamcp.git modules/dwcprestamcp
cd modules/dwcprestamcp
composer install --no-dev   # installs the MCP SDK into vendor/
```

Then install the module in PrestaShop (**Modules > Module Manager**, or the CLI),
open its configuration page, and copy the **endpoint URL** and **token**.

> For a merchant-friendly ZIP (no Composer needed), build a release that bundles
> `vendor/` — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Connect an AI client

### Remote (HTTP) — Claude, ChatGPT, etc.

The config page shows a ready-to-paste snippet, e.g.:

```json
{
  "mcpServers": {
    "prestashop-dwc": {
      "url": "https://your-shop.tld/modules/dwcprestamcp/mcp.php",
      "headers": { "Authorization": "Bearer YOUR_TOKEN" }
    }
  }
}
```

The exact endpoint URL is shown on the module's configuration page.

### Local (STDIO) — Claude Desktop on the same machine

```json
{
  "mcpServers": {
    "prestashop-dwc-local": {
      "command": "php",
      "args": ["/absolute/path/modules/dwcprestamcp/bin/mcp-stdio.php"]
    }
  }
}
```

## Included tools

| Tool name                    | Type      | Description                                                        |
|------------------------------|-----------|-------------------------------------------------------------------|
| `dwc_get_store_info`          | read-only | Basic store info: name, PS/PHP version, language, currency.       |
| `dwc_get_low_stock_products`  | read-only | Products at or below a stock threshold (params: `threshold`, `limit`). |
| `dwc_search_products`         | read-only | Search products by name or reference; price, stock, active status. |
| `dwc_get_product_stock`       | read-only | Stock of a product, broken down by combination (size/colour).     |
| `dwc_get_orders_by_status`    | read-only | Recent orders, optionally filtered by status name or state id.    |
| `dwc_get_sales_by_date_range` | read-only | Revenue, order count and average order value for a date range.    |
| `dwc_get_top_selling_products`| read-only | Best-selling products by quantity in a period.                    |
| `dwc_get_customers`           | read-only | Search customers by email or list the most recent.                |
| `dwc_get_abandoned_carts`     | read-only | Carts with products but no order, within the last N days.         |
| `dwc_update_product`          | **write** | Update a product: price, active, name, reference, weight, on-sale, stock (only provided fields change). |

## Security

- Access is protected by a **Bearer token** (regenerate it anytime from the
  config page). Keep it secret — anyone with it can call your tools.
- The HTTP endpoint enforces **DNS-rebinding protection** (host allowlist from
  your shop's configured domains) and CORS.
- Always serve it over **HTTPS** in production.
- All bundled tools are **read-only**. Add write tools deliberately, with the
  correct `destructiveHint` (see [CONTRIBUTING.md](CONTRIBUTING.md)).

## Architecture

```
AI client ──HTTP(S)+Bearer──▶ modules/dwcprestamcp/mcp.php   (physical endpoint)
                                    │  (own .htaccess: allow + WAF exempt)
                                    ▼
                         DWC\PrestaMcp\Http\McpHttpHandler
                                    │  (auth, PSR-7, middleware)
                                    ▼
                         Mcp\Server (mcp/sdk, Apache-2.0)
                                    │  discovers #[McpTool] in src/Tools
                                    ▼
                         DWC\PrestaMcp\Tools\* ──▶ PrestaShop data
```

The same handler is also reachable via the friendly URL
`/module/dwcprestamcp/mcp` (front controller), but the physical `mcp.php` is the
recommended endpoint: it ships its own `.htaccess` that grants access to just
that file and exempts it from ModSecurity/WAF rules which, on some hosts
(LiteSpeed/OVH), block API POSTs lacking a Referer/Cookie. The endpoint stays
protected by the Bearer token.

## Develop your own tools

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: drop a class in `src/Tools/`
with a public method annotated `#[McpTool]`; it is discovered automatically.

## License

MIT — see [LICENSE](LICENSE). Bundled dependency `mcp/sdk` is Apache-2.0.
