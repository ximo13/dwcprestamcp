# DWC PrestaShop MCP (`dwcprestamcp`)

[![CI](https://github.com/ximo13/dwcprestamcp/actions/workflows/ci.yml/badge.svg)](https://github.com/ximo13/dwcprestamcp/actions/workflows/ci.yml)

Custom **MCP (Model Context Protocol)** tools for PrestaShop, community-made and
open source. This module declares tools, prompts and resources that are
**discovered and served by the official [`ps_mcp_server`](https://addons.prestashop.com/) module**,
so AI agents (Claude, ChatGPT, Gemini…) can call them.

> ⚠️ This is an **independent community project** and is **not affiliated with
> PrestaShop SA**. The `ps_mcp_server` module and its attribute classes remain
> the property of PrestaShop SA.

## How it works

`ps_mcp_server` scans every installed module that exposes a public
`isMcpCompliant()` method returning `true`. For those modules it reads the
`src/` directory and discovers any class method annotated with the MCP
attributes (`#[PsMcpTool]`, `#[PsMcpPrompt]`, `#[PsMcpResource]`, …). Each
discovered tool is then exposed to connected AI agents.

This module:

1. Declares `isMcpCompliant(): bool` in [`dwcprestamcp.php`](dwcprestamcp.php).
2. Registers a small PSR-4 autoloader for `DWC\PrestaMcp\` → `src/`.
3. Ships example tools in [`src/Tools/`](src/Tools/).

## Requirements

- PrestaShop **8.2+** or **9.x**
- PHP **8.1+**
- The official **`ps_mcp_server`** module installed and enabled
  (plus its dependencies: `ps_accounts`, `ps_eventbus`)

## Install (merchant)

1. Zip the `dwcprestamcp/` folder and upload it in
   **Modules > Module Manager**, or drop it in `modules/` and install it.
2. Make sure `ps_mcp_server` is installed and enabled.
3. Open the **MCP Server** configuration page and run a **discovery**.
   The tools of this module appear in the tool list.

## Included tools

| Tool name                   | Type      | Description                                                        |
|-----------------------------|-----------|-------------------------------------------------------------------|
| `dwc_get_store_info`        | read-only | Basic store info: name, PS/PHP version, language, currency.       |
| `dwc_get_low_stock_products`| read-only | Products at or below a stock threshold (params: `threshold`, `limit`). |

## Develop your own tools

Add a class under `src/` (namespace `DWC\PrestaMcp\...`) and annotate a public
method:

```php
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpTool;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpToolAnnotations;

#[PsMcpTool(
    name: 'dwc_my_tool',
    description: 'What the tool does.',
    annotations: new PsMcpToolAnnotations(readOnlyHint: true)
)]
public function myTool(string $someArg): array
{
    return ['ok' => true];
}
```

Mark tools that only read data with `readOnlyHint: true`. Tools that create,
update or delete data must set `readOnlyHint: false` and, when relevant,
`destructiveHint: true`.

### Local dev setup (static analysis)

The MCP attribute classes exist only at runtime (inside `ps_mcp_server`). For
IDE autocompletion and PHPStan, install the official **stubs** as a dev
dependency:

```bash
composer install
composer exec phpstan analyse
```

`composer.json` already declares `prestashop/ps-mcp-server-stubs` under
`require-dev`, and `phpstan.neon.dist` points PHPStan at them.

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for the dev
setup, how to add a tool, and the `readOnlyHint` / `destructiveHint`
convention. In short: keep tools small and single-purpose, document them with
clear descriptions (the AI relies on them), and always set the correct
read-only / destructive hints. CI runs `php -l` and PHPStan on every PR.

## License

MIT — see [LICENSE](LICENSE).
