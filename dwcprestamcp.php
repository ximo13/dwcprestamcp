<?php
/**
 * DWC PrestaShop MCP - Custom MCP tools for PrestaShop.
 *
 * @author    DWC
 * @license   MIT
 *
 * This module declares custom MCP (Model Context Protocol) tools, prompts and
 * resources that are discovered and exposed by the official `ps_mcp_server`
 * module. It is an independent, community project (not affiliated with
 * PrestaShop SA) and requires `ps_mcp_server` to be installed and active to
 * actually serve its tools to an AI agent.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Dwcprestamcp extends Module
{
    public function __construct()
    {
        $this->name = 'dwcprestamcp';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'DWC';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.2.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        // Register a lightweight PSR-4 autoloader for this module's tool classes
        // (namespace DWC\PrestaMcp\ => src/). The MCP server autoloads tool
        // classes via class_exists(..., true) during discovery, and it
        // instantiates each module (running this constructor) beforehand, so
        // registering here is enough for discovery and tool execution.
        self::registerAutoload();

        parent::__construct();

        $this->displayName = $this->trans('DWC PrestaShop MCP', [], 'Modules.Dwcprestamcp.Admin');
        $this->description = $this->trans(
            'Custom MCP tools for PrestaShop, discovered by ps_mcp_server.',
            [],
            'Modules.Dwcprestamcp.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall DWC PrestaShop MCP?',
            [],
            'Modules.Dwcprestamcp.Admin'
        );
    }

    /**
     * Register a minimal PSR-4 autoloader for the DWC\PrestaMcp\ namespace.
     */
    public static function registerAutoload(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $prefix = 'DWC\\PrestaMcp\\';
        $baseDir = __DIR__ . '/src/';

        spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }

    public function install(): bool
    {
        return parent::install();
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    /**
     * Contract used by the official ps_mcp_server module to detect modules that
     * expose MCP tools/prompts/resources. Returning true makes ps_mcp_server
     * scan this module's src/ directory for #[PsMcpTool] & co. attributes.
     */
    public function isMcpCompliant(): bool
    {
        return true;
    }

    /**
     * Simple settings screen that tells the merchant whether ps_mcp_server is
     * present, since this module is useless without it.
     */
    public function getContent(): string
    {
        $serverInstalled = (bool) Module::isInstalled('ps_mcp_server');
        $serverEnabled = (bool) Module::isEnabled('ps_mcp_server');

        if ($serverInstalled && $serverEnabled) {
            $msg = $this->trans(
                'ps_mcp_server is installed and enabled. Open the MCP Server configuration and run a discovery to expose the tools declared by this module.',
                [],
                'Modules.Dwcprestamcp.Admin'
            );

            return $this->displayConfirmation($msg);
        }

        return $this->displayWarning($this->trans(
            'The official "ps_mcp_server" module must be installed and enabled for this module\'s tools to be served.',
            [],
            'Modules.Dwcprestamcp.Admin'
        ));
    }
}
