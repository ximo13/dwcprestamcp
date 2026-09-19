<?php
/**
 * DWC PrestaShop MCP - ProductQueryTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only product queries: search and detailed stock.
 */
class ProductQueryTools
{
    /**
     * Search products by name or reference (SKU). Returns price, stock and
     * active status for each match.
     *
     * @param string $query Text to match in the product name or reference.
     * @param int    $limit Maximum products to return (1-100). Defaults to 25.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_search_products',
        title: 'Search products',
        description: 'Search products by name or reference (SKU); returns id, name, reference, price (tax excl.), stock and active status.',
        annotations: new ToolAnnotations(title: 'Search products', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function searchProducts(
        #[Schema(type: 'string', minLength: 1, maxLength: 128)]
        string $query,
        #[Schema(type: 'integer', minimum: 1, maximum: 100)]
        int $limit = 25
    ): array {
        $limit = min(100, max(1, $limit));
        [$idLang, $idShop] = self::ctx();
        $like = pSQL($query, true);

        $sql = 'SELECT p.id_product, pl.name, p.reference, p.price, p.active, COALESCE(sa.quantity, 0) AS quantity
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                    ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . $idShop . '
                WHERE pl.name LIKE \'%' . $like . '%\' OR p.reference LIKE \'%' . $like . '%\'
                ORDER BY pl.name ASC
                LIMIT ' . $limit;

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'reference' => (string) $r['reference'],
                'price' => round((float) $r['price'], 2),
                'quantity' => (int) $r['quantity'],
                'active' => (bool) $r['active'],
            ];
        }

        return $out;
    }

    /**
     * Detailed stock for a product, including per-combination (size/colour…)
     * quantities when the product has combinations.
     *
     * @param int $id_product The product ID.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_product_stock',
        title: 'Get product stock',
        description: 'Returns the stock of a product, broken down by combination (size/colour) when it has any.',
        annotations: new ToolAnnotations(title: 'Get product stock', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductStock(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product
    ): array {
        [$idLang, $idShop] = self::ctx();
        $db = \Db::getInstance();

        $rows = $db->executeS(
            'SELECT id_product_attribute, quantity
             FROM `' . _DB_PREFIX_ . 'stock_available`
             WHERE id_product = ' . (int) $id_product . ' AND id_shop = ' . $idShop . '
             ORDER BY id_product_attribute ASC'
        );
        if (!is_array($rows) || $rows === []) {
            return ['id_product' => (int) $id_product, 'found' => false, 'message' => 'No stock data for this product.'];
        }

        $total = 0;
        $breakdown = [];
        foreach ($rows as $r) {
            $idpa = (int) $r['id_product_attribute'];
            $qty = (int) $r['quantity'];
            if ($idpa === 0) {
                $total += $qty;
                $breakdown[] = ['id_product_attribute' => 0, 'combination' => 'Base', 'quantity' => $qty];
                continue;
            }
            $label = self::combinationLabel($db, $idpa, $idLang);
            $total += $qty;
            $breakdown[] = ['id_product_attribute' => $idpa, 'combination' => $label, 'quantity' => $qty];
        }

        return [
            'id_product' => (int) $id_product,
            'found' => true,
            'total_quantity' => $total,
            'breakdown' => $breakdown,
        ];
    }

    public static function combinationLabel(\Db $db, int $idProductAttribute, int $idLang): string
    {
        $rows = $db->executeS(
            'SELECT agl.name AS group_name, al.name AS attr_name
             FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
             INNER JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute = pac.id_attribute
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON al.id_attribute = a.id_attribute AND al.id_lang = ' . $idLang . '
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = ' . $idLang . '
             WHERE pac.id_product_attribute = ' . (int) $idProductAttribute
        );
        if (!is_array($rows) || $rows === []) {
            return 'Combination #' . $idProductAttribute;
        }
        $parts = [];
        foreach ($rows as $r) {
            $parts[] = (string) $r['group_name'] . ': ' . (string) $r['attr_name'];
        }

        return implode(', ', $parts);
    }

    /**
     * @return array{0:int,1:int} [id_lang, id_shop]
     */
    public static function ctx(): array
    {
        $context = \Context::getContext();
        $idLang = ($context !== null && $context->language !== null) ? (int) $context->language->id : (int) \Configuration::get('PS_LANG_DEFAULT');
        $idShop = ($context !== null && $context->shop !== null) ? (int) $context->shop->id : (int) \Configuration::get('PS_SHOP_DEFAULT');

        return [$idLang, $idShop];
    }
}
