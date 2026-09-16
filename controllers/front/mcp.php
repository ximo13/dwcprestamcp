<?php
/**
 * DWC PrestaShop MCP - HTTP transport front controller.
 *
 * Exposes the standalone MCP server over HTTP (Streamable HTTP transport).
 * Authentication is a Bearer token stored in Configuration. This endpoint is
 * the URL you give to an MCP client (Claude, ChatGPT, Gemini, MCP Inspector).
 *
 * @author  DWC
 * @license MIT
 */

use DWC\PrestaMcp\Server\McpServerFactory;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

class DwcprestamcpMcpModuleFrontController extends ModuleFrontController
{
    /** @var bool Skip the theme header/footer; this is a raw API endpoint. */
    public $ajax = true;

    public function initContent(): void
    {
        // Load the module's bundled MCP SDK.
        $autoload = _PS_MODULE_DIR_ . 'dwcprestamcp/vendor/autoload.php';
        if (!is_file($autoload)) {
            $this->sendJsonError(500, 'MCP dependencies are not installed. Run "composer install" in the module directory.');
        }
        require_once $autoload;

        if (!$this->isAuthorized()) {
            // 401 with WWW-Authenticate as per bearer token conventions.
            header('WWW-Authenticate: Bearer');
            $this->sendJsonError(401, 'Unauthorized: missing or invalid MCP token.');
        }

        $psr17 = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $request = $creator->fromGlobals();

        // Some SAPI/proxy setups produce a duplicated Host header joined with a
        // comma (e.g. "shop.tld, shop.tld"), which breaks host validation.
        // Normalize it to its first value.
        $rawHost = $request->getHeaderLine('Host');
        if (strpos($rawHost, ',') !== false) {
            $request = $request->withHeader('Host', trim(explode(',', $rawHost)[0]));
        }

        $moduleDir = rtrim(_PS_MODULE_DIR_ . 'dwcprestamcp', '/');
        $factory = new McpServerFactory($moduleDir, (string) $this->module->version);

        $sessionDir = rtrim(_PS_CACHE_DIR_, '/') . '/dwcprestamcp_mcp_sessions';

        $middleware = [
            new CorsMiddleware(),
            new DnsRebindingProtectionMiddleware($this->allowedHosts()),
        ];

        $transport = new StreamableHttpTransport($request, $psr17, $psr17, null, $middleware);
        $server = $factory->buildServer($sessionDir);

        /** @var ResponseInterface $response */
        $response = $server->run($transport);

        $this->emit($response);
    }

    /**
     * Constant-time comparison of the Bearer token against the stored one.
     */
    private function isAuthorized(): bool
    {
        $expected = (string) Configuration::get('DWCPRESTAMCP_TOKEN');
        if ($expected === '') {
            return false;
        }

        $provided = $this->extractBearerToken();
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function extractBearerToken(): string
    {
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $header = (string) $value;
                    break;
                }
            }
        }

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }

    /**
     * @return string[] Hostnames allowed by the DNS-rebinding protection.
     *                 Derived from the shop's configured domains (authoritative),
     *                 plus the current request host and localhost variants.
     */
    private function allowedHosts(): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];

        foreach (['PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL'] as $key) {
            $domain = (string) Configuration::get($key);
            if ($domain !== '') {
                $hosts[] = strtolower((string) preg_replace('/:\d+$/', '', $domain));
            }
        }

        $shopHost = Tools::getHttpHost(false, false);
        if ($shopHost !== '') {
            $hosts[] = strtolower((string) preg_replace('/:\d+$/', '', $shopHost));
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
        }

        echo (string) $response->getBody();
        exit;
    }

    private function sendJsonError(int $status, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => $status === 401 ? -32001 : -32603, 'message' => $message],
            'id' => null,
        ]);
        exit;
    }
}
