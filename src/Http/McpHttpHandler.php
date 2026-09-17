<?php
/**
 * DWC PrestaShop MCP - McpHttpHandler.
 *
 * Shared HTTP handling for the MCP endpoint. Assumes PrestaShop is already
 * bootstrapped (Configuration/Tools/Context available) and the module's
 * vendor/autoload.php is loaded. Used by both the module front controller
 * (friendly URL) and the standalone physical endpoint (mcp.php).
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Http;

use DWC\PrestaMcp\Server\McpServerFactory;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

class McpHttpHandler
{
    public const TOKEN_KEY = 'DWCPRESTAMCP_TOKEN';

    /**
     * Handle the current HTTP request as an MCP call and emit the response.
     * Terminates the request (exit).
     */
    public static function handle(string $moduleDir, string $version): void
    {
        if (!self::isAuthorized()) {
            header('WWW-Authenticate: Bearer');
            self::sendJsonError(401, 'Unauthorized: missing or invalid MCP token.');
        }

        $psr17 = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        $request = $creator->fromGlobals();

        // Some SAPI/proxy setups produce a duplicated Host header joined with a
        // comma (e.g. "shop.tld, shop.tld"), which breaks host validation.
        $rawHost = $request->getHeaderLine('Host');
        if (strpos($rawHost, ',') !== false) {
            $request = $request->withHeader('Host', trim(explode(',', $rawHost)[0]));
        }

        $factory = new McpServerFactory(rtrim($moduleDir, '/'), $version);
        $sessionDir = rtrim((string) _PS_CACHE_DIR_, '/') . '/dwcprestamcp_mcp_sessions';

        $middleware = [
            new CorsMiddleware(),
            new DnsRebindingProtectionMiddleware(self::allowedHosts()),
        ];

        $transport = new StreamableHttpTransport($request, $psr17, $psr17, null, $middleware);
        $server = $factory->buildServer($sessionDir);

        /** @var ResponseInterface $response */
        $response = $server->run($transport);

        self::emit($response);
    }

    private static function isAuthorized(): bool
    {
        $expected = (string) \Configuration::get(self::TOKEN_KEY);
        if ($expected === '') {
            return false;
        }

        $provided = self::extractBearerToken();
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private static function extractBearerToken(): string
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
     */
    private static function allowedHosts(): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]'];

        foreach (['PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL'] as $key) {
            $domain = (string) \Configuration::get($key);
            if ($domain !== '') {
                $hosts[] = strtolower((string) preg_replace('/:\d+$/', '', $domain));
            }
        }

        $shopHost = \Tools::getHttpHost(false, false);
        if ($shopHost !== '') {
            $hosts[] = strtolower((string) preg_replace('/:\d+$/', '', $shopHost));
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private static function emit(ResponseInterface $response): void
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

    private static function sendJsonError(int $status, string $message): void
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
