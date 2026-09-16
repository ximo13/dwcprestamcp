<?php
/**
 * DWC PrestaShop MCP - StoreInfoTool.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only tools that expose basic information about the PrestaShop store.
 */
class StoreInfoTool
{
    /**
     * Return general information about the current PrestaShop store:
     * shop name, PrestaShop version, PHP version, default language and
     * default currency ISO code.
     *
     * Use this when an AI agent needs a quick, safe overview of the store
     * environment without touching any customer or order data.
     *
     * @return array<string, string> Key/value pairs describing the store.
     */
    #[McpTool(
        name: 'dwc_get_store_info',
        title: 'Get store info',
        description: 'Returns basic, non-sensitive information about the PrestaShop store (name, versions, default language and currency).',
        annotations: new ToolAnnotations(
            title: 'Get store info',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        )
    )]
    public function getStoreInfo(): array
    {
        $context = \Context::getContext();

        $shopName = (string) \Configuration::get('PS_SHOP_NAME');

        $langIso = '';
        if ($context !== null && $context->language !== null) {
            $langIso = (string) $context->language->iso_code;
        }

        $currencyIso = '';
        if ($context !== null && $context->currency !== null) {
            $currencyIso = (string) $context->currency->iso_code;
        }

        return [
            'shop_name' => $shopName !== '' ? $shopName : 'PrestaShop',
            'prestashop_version' => _PS_VERSION_,
            'php_version' => PHP_VERSION,
            'default_language' => $langIso,
            'default_currency' => $currencyIso,
        ];
    }
}
