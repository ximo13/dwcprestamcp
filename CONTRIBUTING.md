# Contributing to DWC PrestaShop MCP

Thanks for your interest in improving this module! It is a **standalone MCP
server** for PrestaShop built on the open-source `mcp/sdk` (Apache-2.0).

## Prerequisites

- PHP **8.1+**
- [Composer](https://getcomposer.org/) 2.x
- (To test end-to-end) a PrestaShop **8.2+ / 9.x** install.

## Environment setup

```bash
git clone https://github.com/ximo13/dwcprestamcp.git
cd dwcprestamcp
composer install
```

`composer install` pulls the runtime SDK (`mcp/sdk`, `nyholm/psr7`,
`symfony/finder`, …) into `vendor/`, plus `phpstan/phpstan` for analysis.
`vendor/` is git-ignored; `composer.lock` is committed for reproducible builds.

## Running the checks locally

The same checks run in CI on every PR:

```bash
# PHP syntax
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# Static analysis (level 6, must be clean)
composer exec phpstan analyse
```

PHPStan analyses `src/` only. `tests/bootstrap.php` provides minimal stubs of the
few PrestaShop core classes the tools use, so analysis runs without a full
PrestaShop install. The controllers and the main module file are checked with
`php -l` (they lean heavily on PrestaShop core).

## How the server works

- `controllers/front/mcp.php` is the HTTP endpoint. It authenticates the Bearer
  token, builds a PSR-7 request, applies CORS + DNS-rebinding middleware, and
  runs the MCP server (Streamable HTTP transport, file-backed sessions).
- `src/Server/McpServerFactory.php` builds the server via `Mcp\Server::builder()`
  and discovers tool classes under `src/Tools`.
- `bin/mcp-stdio.php` runs the same server over STDIO for local clients.

## Adding a new tool

1. Create a class under [`src/Tools/`](src/Tools/) in the `DWC\PrestaMcp\Tools`
   namespace (one file per class, matching the class name).
2. Annotate a **public** method with `#[McpTool]` (from the open SDK).
3. Describe it well — the AI chooses tools from the `description` and DocBlock.
4. Declare parameter constraints with `#[Schema]` when useful.

```php
namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

class MyTool
{
    /**
     * One-line summary the AI will read.
     *
     * @param int $limit Maximum rows to return.
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_my_tool',
        title: 'My tool',
        description: 'Clear description of what this returns and when to use it.',
        annotations: new ToolAnnotations(readOnlyHint: true)
    )]
    public function run(
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        return [];
    }
}
```

The tool is discovered automatically — no registration needed.

### The `readOnlyHint` / `destructiveHint` convention

Setting these correctly is **mandatory** — AI clients use them to decide what to
run automatically vs. what to confirm with a human.

| Tool behaviour                      | `readOnlyHint` | `destructiveHint` | `idempotentHint` |
|-------------------------------------|:--------------:|:-----------------:|:----------------:|
| Only reads data (reports, lookups)  | `true`         | `false`           | `true`           |
| Creates/adds data (non-destructive) | `false`        | `false`           | depends          |
| Updates/deletes existing data       | `false`        | `true`            | depends          |

- **`readOnlyHint: true`** → must not modify anything. Most bundled tools are read-only; the write tools are listed in the README.
- **`destructiveHint: true`** → can overwrite/delete data. Validate every input.
- **`idempotentHint`** matters only when `readOnlyHint` is `false`.
- Keep `openWorldHint: false` unless the tool reaches external services.

If in doubt, prefer **read-only** and open an issue to discuss write access.

### Naming

- Tool `name`: `dwc_` prefix, `snake_case`, verb-first (e.g. `dwc_get_orders_by_status`).
- One responsibility per tool; small tools compose better for the agent.

## Testing end-to-end

1. `composer install` in the module, install it in PrestaShop.
2. Copy the endpoint URL + token from the config page.
3. Handshake with any MCP client, or with curl:

```bash
curl -s -X POST "$URL" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'
```

Reuse the returned `Mcp-Session-Id` header on subsequent `tools/list` /
`tools/call` requests (add `-H "Mcp-Session-Id: …"` and
`-H "Mcp-Protocol-Version: 2025-06-18"`).

## Building a merchant ZIP (with vendor)

Merchants who don't use Composer need `vendor/` inside the ZIP:

```bash
composer install --no-dev --optimize-autoloader
zip -r dwcprestamcp.zip . -x ".git/*" "tests/*" ".github/*" "*.dist"
```

## Pull requests

- Branch off `main`, keep PRs focused.
- Make sure `php -l` and PHPStan pass locally (CI enforces both).
- Update the tools table in [`README.md`](README.md) when you add a tool.
- Describe the behaviour and the read-only/destructive hints in the PR body.

## License

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE).
