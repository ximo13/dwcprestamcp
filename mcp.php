<?php
/**
 * DWC PrestaShop MCP - Standalone HTTP endpoint (physical file).
 *
 * This is the recommended MCP endpoint URL to give to an AI client:
 *   https://your-shop.tld/modules/dwcprestamcp/mcp.php
 *
 * Unlike the friendly-URL front controller (/module/dwcprestamcp/mcp), this
 * physical file is governed by the module's own .htaccess, which grants access
 * to just this file and exempts it from ModSecurity/WAF rules that block
 * cookieless/referer-less POSTs (common on LiteSpeed/OVH shared hosting).
 * The endpoint is still protected by the module's Bearer-token auth.
 *
 * @author  DWC
 * @license MIT
 */

// Bootstrap PrestaShop so tools have a real store context.
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

$moduleDir = __DIR__;

$autoload = $moduleDir . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => ['code' => -32603, 'message' => 'MCP dependencies are not installed. Run "composer install" in the module directory.'],
        'id' => null,
    ]);
    exit;
}
require_once $autoload;

$module = Module::getInstanceByName('dwcprestamcp');
$version = ($module instanceof Module) ? (string) $module->version : '2.5.0';

DWC\PrestaMcp\Http\McpHttpHandler::handle($moduleDir, $version);
