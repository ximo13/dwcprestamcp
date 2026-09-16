# Contributing to DWC PrestaShop MCP

Thanks for your interest in improving this module! This guide explains how to
set up your environment, add MCP tools, and open a pull request.

## Prerequisites

- PHP **8.1+**
- [Composer](https://getcomposer.org/) 2.x
- (To test end-to-end) a PrestaShop **8.2+ / 9.x** install with the official
  **`ps_mcp_server`** module installed and enabled.

## Environment setup

```bash
git clone https://github.com/ximo13/dwcprestamcp.git
cd dwcprestamcp
composer install
```

`composer install` pulls the **dev-only** dependencies:

- `prestashop/ps-mcp-server-stubs` — signatures for the MCP attribute classes
  (`#[PsMcpTool]`, `#[PsMcpSchema]`, …), which only exist at runtime inside
  `ps_mcp_server`. They give you IDE autocompletion and let PHPStan run without
  a full PrestaShop install.
- `phpstan/phpstan` — static analysis.

> These are **not** shipped to production. `vendor/` is git-ignored; the module
> registers its own lightweight PSR-4 autoloader at runtime.

## Running the checks locally

The same checks run in CI on every PR:

```bash
# PHP syntax
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# Static analysis (level 6, must be clean)
composer exec phpstan analyse
```

## How the module works

`ps_mcp_server` discovers this module because
[`dwcprestamcp.php`](dwcprestamcp.php) declares:

```php
public function isMcpCompliant(): bool
{
    return true;
}
```

For compliant modules, `ps_mcp_server` scans the `src/` directory and registers
every method annotated with an MCP attribute.

## Adding a new tool

1. Create a class under [`src/Tools/`](src/Tools/) in the `DWC\PrestaMcp\Tools`
   namespace (one file per class, matching the class name).
2. Annotate a **public** method with `#[PsMcpTool]`.
3. Describe it well — the AI agent chooses tools based on the `description` and
   the method's DocBlock, so be precise about what it does and returns.
4. Declare parameter constraints with `#[PsMcpSchema]` when useful.

```php
namespace DWC\PrestaMcp\Tools;

use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpSchema;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpTool;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpToolAnnotations;

class MyTool
{
    /**
     * One-line summary the AI will read.
     *
     * @param int $limit Maximum rows to return.
     * @return array<int, array<string, mixed>>
     */
    #[PsMcpTool(
        name: 'dwc_my_tool',
        title: 'My tool',
        description: 'Clear description of what this returns and when to use it.',
        annotations: new PsMcpToolAnnotations(readOnlyHint: true)
    )]
    public function run(
        #[PsMcpSchema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        // ...
        return [];
    }
}
```

### The `readOnlyHint` / `destructiveHint` convention

Setting these correctly is **mandatory** — AI clients use them to decide what to
run automatically vs. what to confirm with a human.

| Tool behaviour                         | `readOnlyHint` | `destructiveHint` | `idempotentHint` |
|----------------------------------------|:--------------:|:-----------------:|:----------------:|
| Only reads data (reports, lookups)     | `true`         | `false`           | `true`           |
| Creates/adds data (non-destructive)    | `false`        | `false`           | depends          |
| Updates/deletes existing data          | `false`        | `true`            | depends          |

- **`readOnlyHint: true`** → the tool must not modify anything. All current
  tools in this repo are read-only.
- **`destructiveHint: true`** → the tool can overwrite or delete data. Use with
  care and validate every input.
- **`idempotentHint`** is only meaningful when `readOnlyHint` is `false`: set it
  `true` when calling repeatedly with the same args has no extra effect.
- Keep `openWorldHint: false` unless the tool reaches external services.

If in doubt, prefer **read-only** and open an issue to discuss write access.

### Naming

- Tool `name`: `dwc_` prefix, `snake_case`, verb-first (e.g. `dwc_get_orders_by_status`).
- Keep one responsibility per tool; small tools compose better for the agent.

## Testing your tool end-to-end

1. Copy/symlink the module into your PrestaShop `modules/` folder and install it.
2. Ensure `ps_mcp_server` is enabled.
3. Open the **MCP Server** configuration page in the back office and run a
   **discovery** — your tool should appear in the tool list.

## Pull requests

- Branch off `main`, keep PRs focused.
- Make sure `php -l` and PHPStan pass locally (CI enforces both).
- Update the tools table in [`README.md`](README.md) when you add a tool.
- Describe the behaviour and the read-only/destructive hints in the PR body.

## License

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE).
