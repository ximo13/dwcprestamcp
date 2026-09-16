#!/usr/bin/env php
<?php
/**
 * DWC PrestaShop MCP - STDIO transport for local development.
 *
 * Runs the standalone MCP server over STDIO so a local client (e.g. Claude
 * Desktop) can spawn it directly. It bootstraps PrestaShop so the tools have a
 * real store context, then serves the MCP protocol on stdin/stdout.
 *
 * Configure it in Claude Desktop like:
 *   {
 *     "mcpServers": {
 *       "prestashop-dwc-local": {
 *         "command": "php",
 *         "args": ["/absolute/path/to/modules/dwcprestamcp/bin/mcp-stdio.php"]
 *       }
 *     }
 *   }
 *
 * @author  DWC
 * @license MIT
 */

declare(strict_types=1);

use DWC\PrestaMcp\Server\McpServerFactory;
use Mcp\Server\Transport\StdioTransport;

$moduleDir = dirname(__DIR__);

// PrestaShop root is modules/<name>/../../
$psRoot = dirname($moduleDir, 2);

require $moduleDir . '/vendor/autoload.php';

// --- Bootstrap PrestaShop so tools can read real store data. ---
$parametersFile = $psRoot . '/app/config/parameters.php';
if (is_file($parametersFile)) {
    $parameters = require $parametersFile;
    $parameters = $parameters['parameters'] ?? [];
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $parameters['database_host'], $parameters['database_name']),
            $parameters['database_user'],
            $parameters['database_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $prefix = $parameters['database_prefix'] ?? 'ps_';
        $row = $pdo->query(sprintf('SELECT domain, physical_uri, virtual_uri FROM %sshop_url WHERE active = 1 AND main = 1 ORDER BY id_shop_url LIMIT 1', $prefix))->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SERVER['HTTP_HOST'] = $row['domain'];
            $_SERVER['SERVER_NAME'] = $row['domain'];
            $_SERVER['REQUEST_URI'] = $row['physical_uri'] . $row['virtual_uri'];
            $_SERVER['SCRIPT_NAME'] = $row['physical_uri'] . 'index.php';
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, 'PrestaShop bootstrap warning: ' . $e->getMessage() . "\n");
    }
}

if (!defined('_PS_ADMIN_DIR_')) {
    // A best-effort admin dir; required by config.inc.php in some setups.
    foreach (['extranet', 'admin', 'adminps'] as $adminCandidate) {
        if (is_dir($psRoot . '/' . $adminCandidate)) {
            define('_PS_ADMIN_DIR_', $psRoot . '/' . $adminCandidate);
            break;
        }
    }
}

require $psRoot . '/config/config.inc.php';

// --- Build and run the MCP server over STDIO. ---
$version = '2.0.0';
$factory = new McpServerFactory($moduleDir, $version);
$server = $factory->buildServer();
$server->run(new StdioTransport());
