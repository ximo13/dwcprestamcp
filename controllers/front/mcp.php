<?php
/**
 * DWC PrestaShop MCP - HTTP transport front controller (friendly URL).
 *
 * Exposes the standalone MCP server at /module/dwcprestamcp/mcp. Note: on hosts
 * with a WAF that blocks POST requests without a Referer/Cookie, this friendly
 * URL (served by the root dispatcher) may be blocked. The physical endpoint
 * modules/dwcprestamcp/mcp.php ships its own .htaccess to work around that.
 *
 * @author  DWC
 * @license MIT
 */

use DWC\PrestaMcp\Http\McpHttpHandler;

class DwcprestamcpMcpModuleFrontController extends ModuleFrontController
{
    /** @var bool Skip the theme header/footer; this is a raw API endpoint. */
    public $ajax = true;

    public function initContent(): void
    {
        $autoload = _PS_MODULE_DIR_ . 'dwcprestamcp/vendor/autoload.php';
        if (!is_file($autoload)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'MCP dependencies are not installed. Run "composer install" in the module directory.'], 'id' => null]);
            exit;
        }
        require_once $autoload;

        McpHttpHandler::handle(rtrim(_PS_MODULE_DIR_ . 'dwcprestamcp', '/'), (string) $this->module->version);
    }
}
