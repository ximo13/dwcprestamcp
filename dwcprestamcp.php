<?php
/**
 * DWC PrestaShop MCP - Standalone MCP server for PrestaShop.
 *
 * @author    DWC
 * @license   MIT
 *
 * This module embeds its own MCP (Model Context Protocol) server, powered by the
 * open-source mcp/sdk (Apache-2.0). It exposes your store to AI agents over its
 * own authenticated HTTP endpoint and does NOT depend on the official
 * ps_mcp_server module. Independent community project, not affiliated with
 * PrestaShop SA.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Load the bundled MCP SDK and this module's classes, if installed.
$dwcAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($dwcAutoload)) {
    require_once $dwcAutoload;
}

class Dwcprestamcp extends Module
{
    /** @var string Configuration key holding the Bearer token. */
    public const TOKEN_KEY = 'DWCPRESTAMCP_TOKEN';

    public function __construct()
    {
        $this->name = 'dwcprestamcp';
        $this->tab = 'administration';
        $this->version = '2.7.0';
        $this->author = 'DWC';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.2.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('DWC PrestaShop MCP', [], 'Modules.Dwcprestamcp.Admin');
        $this->description = $this->trans(
            'Standalone MCP server: expose your store to AI agents over its own authenticated endpoint.',
            [],
            'Modules.Dwcprestamcp.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure? This will remove the MCP endpoint and its access token.',
            [],
            'Modules.Dwcprestamcp.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->regenerateToken();
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::TOKEN_KEY);

        return parent::uninstall();
    }

    /**
     * Generate a new random Bearer token and store it.
     */
    public function regenerateToken(): bool
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $token = hash('sha256', uniqid((string) mt_rand(), true) . microtime());
        }

        return (bool) Configuration::updateValue(self::TOKEN_KEY, $token);
    }

    /**
     * Absolute URL of the MCP HTTP endpoint (the URL to give an MCP client).
     *
     * Uses the physical endpoint file (modules/dwcprestamcp/mcp.php), which
     * ships its own .htaccess to survive WAF rules that block the friendly-URL
     * dispatcher on some hosts. Falls back to the friendly URL if the shop
     * context is unavailable.
     */
    public function getEndpointUrl(): string
    {
        $shop = $this->context->shop;
        if ($shop !== null) {
            return $shop->getBaseURL(true, true) . 'modules/' . $this->name . '/mcp.php';
        }

        return $this->context->link->getModuleLink($this->name, 'mcp', [], true);
    }

    /**
     * Module configuration page: endpoint URL, token, and a client snippet.
     */
    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitDwcRegenerateToken')) {
            if ($this->regenerateToken()) {
                $output .= $this->displayConfirmation($this->trans('A new token has been generated.', [], 'Modules.Dwcprestamcp.Admin'));
            } else {
                $output .= $this->displayError($this->trans('Could not generate a new token.', [], 'Modules.Dwcprestamcp.Admin'));
            }
        }

        if (!is_file(__DIR__ . '/vendor/autoload.php')) {
            $output .= $this->displayWarning($this->trans(
                'MCP dependencies are missing. Run "composer install" inside modules/dwcprestamcp before using the endpoint.',
                [],
                'Modules.Dwcprestamcp.Admin'
            ));
        }

        $endpoint = $this->getEndpointUrl();
        $token = (string) Configuration::get(self::TOKEN_KEY);

        $clientSnippet = json_encode([
            'mcpServers' => [
                'prestashop-dwc' => [
                    'url' => $endpoint,
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->context->smarty->assign([
            'dwc_endpoint' => $endpoint,
            'dwc_token' => $token,
            'dwc_client_snippet' => $clientSnippet,
            'dwc_regenerate_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        ]);

        return $output . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
}
