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
      "url": "https://your-shop.tld/module/dwcprestamcp/mcp",
      "headers": { "Authorization": "Bearer YOUR_TOKEN" }
    }
  }
}
```

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
| `dwc_get_store_info`         | read-only | Basic store info: name, PS/PHP version, language, currency.       |
| `dwc_get_low_stock_products` | read-only | Products at or below a stock threshold (params: `threshold`, `limit`). |

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
AI client ──HTTP(S)+Bearer──▶ controllers/front/mcp.php
                                    │  (auth, PSR-7, middleware)
                                    ▼
                         Mcp\Server (mcp/sdk, Apache-2.0)
                                    │  discovers #[McpTool] in src/Tools
                                    ▼
                         DWC\PrestaMcp\Tools\* ──▶ PrestaShop data
```

## Develop your own tools

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: drop a class in `src/Tools/`
with a public method annotated `#[McpTool]`; it is discovered automatically.

## License

MIT — see [LICENSE](LICENSE). Bundled dependency `mcp/sdk` is Apache-2.0.
