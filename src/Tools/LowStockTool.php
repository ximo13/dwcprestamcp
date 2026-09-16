<?php
/**
 * DWC PrestaShop MCP - LowStockTool.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpSchema;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpTool;
use PrestaShop\Module\PsMcpServer\Server\Attributes\PsMcpToolAnnotations;

/**
 * Read-only tools related to product stock levels.
 */
class LowStockTool
{
    /**
     * List products whose available quantity is at or below a threshold.
     *
     * Useful when an AI agent needs to spot items that are about to run out of
     * stock so the merchant can reorder. This tool only reads data; it never
     * modifies stock.
     *
     * @param int $threshold Quantity at or below which a product is "low stock". Defaults to 5.
     * @param int $limit      Maximum number of products to return (1-200). Defaults to 50.
     *
     * @return array<int, array<string, int|string>> List of low-stock products.
     */
    #[PsMcpTool(
        name: 'dwc_get_low_stock_products',
        title: 'Get low-stock products',
        description: 'Returns products whose available quantity is at or below a given threshold, so the merchant can reorder.',
        annotations: new PsMcpToolAnnotations(
            title: 'Get low-stock products',
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        )
    )]
    public function getLowStockProducts(
        #[PsMcpSchema(type: 'integer', minimum: 0, maximum: 100000)]
        int $threshold = 5,
        #[PsMcpSchema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        // Clamp inputs defensively even though the schema advertises bounds.
        $threshold = max(0, $threshold);
        $limit = min(200, max(1, $limit));

        $context = \Context::getContext();
        $idLang = ($context !== null && $context->language !== null)
            ? (int) $context->language->id
            : (int) \Configuration::get('PS_LANG_DEFAULT');
        $idShop = ($context !== null && $context->shop !== null)
            ? (int) $context->shop->id
            : (int) \Configuration::get('PS_SHOP_DEFAULT');

        $db = \Db::getInstance();

        $sql = 'SELECT sa.id_product, sa.quantity, pl.name, p.reference
                FROM `' . _DB_PREFIX_ . 'stock_available` sa
                INNER JOIN `' . _DB_PREFIX_ . 'product` p
                    ON p.id_product = sa.id_product
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = sa.id_product
                    AND pl.id_lang = ' . $idLang . '
                    AND pl.id_shop = ' . $idShop . '
                WHERE sa.id_product_attribute = 0
                    AND sa.quantity <= ' . $threshold . '
                ORDER BY sa.quantity ASC, sa.id_product ASC
                LIMIT ' . $limit;

        /** @var array<int, array<string, string>>|false $rows */
        $rows = $db->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id_product' => (int) $row['id_product'],
                'name' => (string) ($row['name'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'quantity' => (int) $row['quantity'],
            ];
        }

        return $result;
    }
}
