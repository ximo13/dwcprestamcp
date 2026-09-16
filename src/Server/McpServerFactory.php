<?php
/**
 * DWC PrestaShop MCP - McpServerFactory.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Server;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Stateless\StatelessProtocol;

/**
 * Builds the standalone MCP server for this module.
 *
 * The server is powered by the open-source mcp/sdk (Apache-2.0) and discovers
 * tool classes under src/Tools annotated with #[McpTool] & co. It has no
 * dependency on the official ps_mcp_server module.
 */
class McpServerFactory
{
    public const SERVER_NAME = 'DWC PrestaShop MCP';

    /**
     * Protocol revisions this server is willing to speak. Kept broad so that
     * different MCP clients (Claude Desktop, ChatGPT, Gemini, MCP Inspector)
     * can negotiate a common version.
     *
     * @var ProtocolVersion[]
     */
    private const SUPPORTED_VERSIONS = [
        ProtocolVersion::V2024_11_05,
        ProtocolVersion::V2025_03_26,
        ProtocolVersion::V2025_06_18,
        ProtocolVersion::V2025_11_25,
        ProtocolVersion::V2026_07_28,
    ];

    private string $moduleDir;

    private string $version;

    public function __construct(string $moduleDir, string $version)
    {
        $this->moduleDir = rtrim($moduleDir, '/');
        $this->version = $version;
    }

    /**
     * Build a stateless MCP protocol handler. Stateless fits a PrestaShop front
     * controller: each HTTP request is handled on its own, no server-side
     * session storage required.
     */
    public function buildStateless(): StatelessProtocol
    {
        return $this->builder()->buildStateless(self::SUPPORTED_VERSIONS);
    }

    /**
     * Build a full (session-aware) server. Used by the HTTP transport (with a
     * persistent file session store so sessions survive across requests) and by
     * the STDIO transport for local clients such as Claude Desktop.
     *
     * @param string|null $sessionDir Writable directory for persisting sessions.
     *                                When null, sessions live in memory (fine for
     *                                a single long-lived STDIO process).
     */
    public function buildServer(?string $sessionDir = null): Server
    {
        $builder = $this->builder();

        if ($sessionDir !== null) {
            if (!is_dir($sessionDir)) {
                @mkdir($sessionDir, 0770, true);
            }
            $builder->setSession(new FileSessionStore($sessionDir));
        }

        return $builder->build();
    }

    private function builder(): Server\Builder
    {
        return Server::builder()
            ->setServerInfo(
                self::SERVER_NAME,
                $this->version,
                'Standalone MCP server for PrestaShop by DWC.'
            )
            // Discover tool/prompt/resource classes under src/Tools.
            ->setDiscovery($this->moduleDir, ['src/Tools']);
    }
}
