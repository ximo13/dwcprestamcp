<?php
/**
 * DWC PrestaShop MCP - ProductCatalogTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only catalog insights: full product sheet, catalog gaps, unsold stock,
 * unavailable products, discounts and low-stock combinations.
 */
class ProductCatalogTools
{
    private const NO_DATE = '0000-00-00 00:00:00';

    /**
     * Full product sheet: categories, brand, supplier, prices with and without
     * tax, identifiers (EAN/UPC/ISBN/MPN), images, combinations and features.
     *
     * @param int $id_product The product ID.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_product_details',
        title: 'Get product details',
        description: 'Returns the full sheet of a product: categories, brand, supplier, prices (tax excl./incl., with and without discount), EAN/UPC/ISBN/MPN, images, combinations with stock, and features.',
        annotations: new ToolAnnotations(title: 'Get product details', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductDetails(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product
    ): array {
        [$idLang, $idShop] = ProductQueryTools::ctx();
        $product = new \Product($id_product);
        if (!\Validate::isLoadedObject($product)) {
            return ['id_product' => $id_product, 'found' => false, 'message' => 'Product not found.'];
        }
        $db = \Db::getInstance();
        $lang = $db->executeS(
            'SELECT name, link_rewrite FROM `' . _DB_PREFIX_ . 'product_lang`
             WHERE id_product = ' . $id_product . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop
        );
        $lang = is_array($lang) && $lang !== [] ? $lang[0] : ['name' => '', 'link_rewrite' => ''];

        $categories = [];
        foreach ((array) \Product::getProductCategoriesFull($id_product, $idLang) as $c) {
            $categories[] = [
                'id_category' => (int) $c['id_category'],
                'name' => (string) $c['name'],
                'is_default' => (int) $c['id_category'] === (int) $product->id_category_default,
            ];
        }

        $context = \Context::getContext();
        $link = $context !== null ? $context->link : null;
        $images = [];
        foreach ((array) \Image::getImages($idLang, $id_product) as $img) {
            $idImage = (int) $img['id_image'];
            $images[] = [
                'id_image' => $idImage,
                'cover' => (bool) $img['cover'],
                'legend' => (string) ($img['legend'] ?? ''),
                'url' => $link !== null ? $link->getImageLink((string) $lang['link_rewrite'], (string) $idImage) : null,
            ];
        }

        $combinations = [];
        $combRows = $db->executeS(
            'SELECT pa.id_product_attribute, pa.reference, pa.ean13, pa.price, pa.default_on, COALESCE(sa.quantity, 0) AS quantity
             FROM `' . _DB_PREFIX_ . 'product_attribute` pa
             LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON sa.id_product = pa.id_product AND sa.id_product_attribute = pa.id_product_attribute AND sa.id_shop = ' . $idShop . '
             WHERE pa.id_product = ' . $id_product . '
             ORDER BY pa.id_product_attribute ASC'
        );
        foreach (is_array($combRows) ? $combRows : [] as $r) {
            $idpa = (int) $r['id_product_attribute'];
            $combinations[] = [
                'id_product_attribute' => $idpa,
                'combination' => ProductQueryTools::combinationLabel($db, $idpa, $idLang),
                'reference' => (string) $r['reference'],
                'ean13' => (string) $r['ean13'],
                'price_impact' => round((float) $r['price'], 2),
                'quantity' => (int) $r['quantity'],
                'is_default' => (bool) $r['default_on'],
            ];
        }

        $features = [];
        foreach ((array) \Product::getFrontFeaturesStatic($idLang, $id_product) as $f) {
            $features[] = ['name' => (string) $f['name'], 'value' => (string) $f['value']];
        }

        $noSpecific = null;

        return [
            'id_product' => $id_product,
            'found' => true,
            'name' => (string) $lang['name'],
            'reference' => (string) $product->reference,
            'ean13' => (string) $product->ean13,
            'upc' => (string) $product->upc,
            'isbn' => (string) $product->isbn,
            'mpn' => (string) $product->mpn,
            'active' => (bool) $product->active,
            'visibility' => (string) $product->visibility,
            'condition' => (string) $product->condition,
            'on_sale' => (bool) $product->on_sale,
            'brand' => $product->id_manufacturer ? ['id' => (int) $product->id_manufacturer, 'name' => (string) \Manufacturer::getNameById((int) $product->id_manufacturer)] : null,
            'supplier' => $product->id_supplier ? ['id' => (int) $product->id_supplier, 'name' => (string) \Supplier::getNameById((int) $product->id_supplier)] : null,
            'prices' => [
                'wholesale_price' => round((float) $product->wholesale_price, 2),
                'price_tax_excl' => round(\Product::getPriceStatic($id_product, false, null, 6, null, false, false, 1, false, null, null, null, $noSpecific, true, false), 2),
                'price_tax_incl' => round(\Product::getPriceStatic($id_product, true, null, 6, null, false, false, 1, false, null, null, null, $noSpecific, true, false), 2),
                'final_price_tax_excl' => round(\Product::getPriceStatic($id_product, false, null, 6, null, false, true), 2),
                'final_price_tax_incl' => round(\Product::getPriceStatic($id_product, true, null, 6, null, false, true), 2),
                'tax_rate' => round((float) $product->getTaxesRate(), 2),
            ],
            'quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($id_product, 0, $idShop),
            'weight' => round((float) $product->weight, 3),
            'categories' => $categories,
            'images' => $images,
            'combinations' => $combinations,
            'features' => $features,
            'date_add' => (string) $product->date_add,
            'date_upd' => (string) $product->date_upd,
        ];
    }

    /**
     * Catalog audit: products without image, without a real category (only
     * Home/root) or without EAN (neither on the product nor on any combination).
     *
     * @param string $issue       One of: no_image, no_category, no_ean.
     * @param bool   $only_active Only check active products. Defaults to true.
     * @param int    $limit       Max products (1-200). Defaults to 50.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_products_with_catalog_issues',
        title: 'Get products with catalog issues',
        description: 'Catalog audit: lists products without image (no_image), without a real category — only Home or none (no_category), or without EAN on the product nor any combination (no_ean).',
        annotations: new ToolAnnotations(title: 'Get products with catalog issues', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductsWithCatalogIssues(
        #[Schema(type: 'string', enum: ['no_image', 'no_category', 'no_ean'])]
        string $issue,
        bool $only_active = true,
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = ProductQueryTools::ctx();

        switch ($issue) {
            case 'no_image':
                $cond = 'NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'image` i WHERE i.id_product = p.id_product)';
                break;
            case 'no_category':
                $excluded = (int) \Configuration::get('PS_ROOT_CATEGORY') . ',' . (int) \Configuration::get('PS_HOME_CATEGORY');
                $cond = 'NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                          WHERE cp.id_product = p.id_product AND cp.id_category NOT IN (' . $excluded . '))';
                break;
            case 'no_ean':
                $cond = '(p.ean13 IS NULL OR p.ean13 = \'\')
                         AND NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'product_attribute` pa
                             WHERE pa.id_product = p.id_product AND pa.ean13 IS NOT NULL AND pa.ean13 <> \'\')';
                break;
            default:
                return ['error' => 'Invalid issue. Use no_image, no_category or no_ean.'];
        }

        $from = ' FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                WHERE ' . $cond . ($only_active ? ' AND ps.active = 1' : '');

        $db = \Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS('SELECT p.id_product, pl.name, p.reference, ps.active' . $from . ' ORDER BY p.id_product ASC LIMIT ' . $limit);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'reference' => (string) $r['reference'],
                'active' => (bool) $r['active'],
            ];
        }

        return ['issue' => $issue, 'total' => $total, 'returned' => count($out), 'products' => $out];
    }

    /**
     * Unsold (dead) stock: active products with stock that have had no sales in
     * the last N days.
     *
     * @param int $days         Period without sales, in days (1-3650). Defaults to 90.
     * @param int $min_quantity Minimum stock to be considered (>= 1). Defaults to 1.
     * @param int $limit        Max products (1-200). Defaults to 50.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_unsold_products',
        title: 'Get unsold products (dead stock)',
        description: 'Lists active products with stock that have had no sales in the last N days (dead stock), with their stock, last sale date and creation date. Sorted by stock, highest first.',
        annotations: new ToolAnnotations(title: 'Get unsold products (dead stock)', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getUnsoldProducts(
        #[Schema(type: 'integer', minimum: 1, maximum: 3650)]
        int $days = 90,
        #[Schema(type: 'integer', minimum: 1)]
        int $min_quantity = 1,
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        $days = min(3650, max(1, $days));
        $minQty = max(1, $min_quantity);
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = ProductQueryTools::ctx();

        $from = ' FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                    ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . $idShop . '
                WHERE ps.active = 1 AND sa.quantity >= ' . $minQty . '
                  AND NOT EXISTS (
                      SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail` od
                      INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = od.id_order
                      WHERE od.product_id = p.id_product AND o.date_add >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
                  )';

        $db = \Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS(
            'SELECT p.id_product, pl.name, p.reference, sa.quantity, ps.date_add,
                    (SELECT MAX(o2.date_add) FROM `' . _DB_PREFIX_ . 'order_detail` od2
                     INNER JOIN `' . _DB_PREFIX_ . 'orders` o2 ON o2.id_order = od2.id_order
                     WHERE od2.product_id = p.id_product) AS last_sale' . $from . '
             ORDER BY sa.quantity DESC, p.id_product ASC
             LIMIT ' . $limit
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'reference' => (string) $r['reference'],
                'quantity' => (int) $r['quantity'],
                'last_sale' => $r['last_sale'] !== null ? (string) $r['last_sale'] : null,
                'date_add' => (string) $r['date_add'],
            ];
        }

        return ['days_without_sales' => $days, 'total' => $total, 'returned' => count($out), 'products' => $out];
    }

    /**
     * Products that cannot be bought: disabled products, or active products
     * with zero (or negative) stock.
     *
     * @param string $type  inactive | active_out_of_stock. Defaults to active_out_of_stock.
     * @param int    $limit Max products (1-200). Defaults to 50.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_unavailable_products',
        title: 'Get unavailable products',
        description: 'Lists disabled products (inactive) or active products with stock 0 or below (active_out_of_stock). For the latter, tells whether back-orders are allowed, i.e. whether customers can still order it.',
        annotations: new ToolAnnotations(title: 'Get unavailable products', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getUnavailableProducts(
        #[Schema(type: 'string', enum: ['inactive', 'active_out_of_stock'])]
        string $type = 'active_out_of_stock',
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = ProductQueryTools::ctx();

        if ($type === 'inactive') {
            $cond = 'ps.active = 0';
        } elseif ($type === 'active_out_of_stock') {
            $cond = 'ps.active = 1 AND COALESCE(sa.quantity, 0) <= 0';
        } else {
            return ['error' => 'Invalid type. Use inactive or active_out_of_stock.'];
        }

        $from = ' FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                    ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . $idShop . '
                WHERE ' . $cond;

        $db = \Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS(
            'SELECT p.id_product, pl.name, p.reference, COALESCE(sa.quantity, 0) AS quantity, sa.out_of_stock, ps.date_upd'
            . $from . ' ORDER BY ps.date_upd DESC LIMIT ' . $limit
        );

        $defaultAllow = (bool) \Configuration::get('PS_ORDER_OUT_OF_STOCK');
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            // out_of_stock: 0 = deny, 1 = allow, 2 = shop default.
            $oos = (int) ($r['out_of_stock'] ?? 2);
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'reference' => (string) $r['reference'],
                'quantity' => (int) $r['quantity'],
                'allows_backorders' => $oos === 1 || ($oos === 2 && $defaultAllow),
                'date_upd' => (string) $r['date_upd'],
            ];
        }

        return ['type' => $type, 'total' => $total, 'returned' => count($out), 'products' => $out];
    }

    /**
     * Discounts (specific prices): which products have a price reduction and
     * until when.
     *
     * @param string   $status     active (now) | upcoming | all. Defaults to active.
     * @param int|null $id_product Only discounts for this product. Null = any.
     * @param int      $limit      Max discounts (1-200). Defaults to 50.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_product_discounts',
        title: 'Get product discounts',
        description: 'Lists product discounts (specific prices): product, reduction (percentage or amount), fixed price, minimum quantity, start and end dates, and whether it is restricted to a customer, group, country or currency.',
        annotations: new ToolAnnotations(title: 'Get product discounts', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductDiscounts(
        #[Schema(type: 'string', enum: ['active', 'upcoming', 'all'])]
        string $status = 'active',
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_product = null,
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = ProductQueryTools::ctx();
        $noDate = '\'' . self::NO_DATE . '\'';

        $where = 'sp.id_shop IN (0, ' . $idShop . ')';
        if ($status === 'active') {
            $where .= ' AND (sp.`from` = ' . $noDate . ' OR sp.`from` <= NOW()) AND (sp.`to` = ' . $noDate . ' OR sp.`to` >= NOW())';
        } elseif ($status === 'upcoming') {
            $where .= ' AND sp.`from` <> ' . $noDate . ' AND sp.`from` > NOW()';
        } elseif ($status !== 'all') {
            return ['error' => 'Invalid status. Use active, upcoming or all.'];
        }
        if ($id_product !== null) {
            $where .= ' AND sp.id_product = ' . (int) $id_product;
        }

        $from = ' FROM `' . _DB_PREFIX_ . 'specific_price` sp
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = sp.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = sp.id_product
                WHERE ' . $where;

        $db = \Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS(
            'SELECT sp.*, pl.name, p.price AS base_price' . $from . '
             ORDER BY (sp.`to` = ' . $noDate . ') ASC, sp.`to` ASC, sp.id_specific_price ASC
             LIMIT ' . $limit
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $idpa = (int) $r['id_product_attribute'];
            $isPct = $r['reduction_type'] === 'percentage';
            $out[] = [
                'id_specific_price' => (int) $r['id_specific_price'],
                'id_product' => (int) $r['id_product'],
                'product' => (int) $r['id_product'] === 0 ? 'All products' : (string) $r['name'],
                'combination' => $idpa > 0 ? ProductQueryTools::combinationLabel($db, $idpa, $idLang) : null,
                'base_price_tax_excl' => $r['base_price'] !== null ? round((float) $r['base_price'], 2) : null,
                'reduction' => $isPct ? round((float) $r['reduction'] * 100, 2) : round((float) $r['reduction'], 2),
                'reduction_type' => $isPct ? 'percentage' : 'amount',
                'reduction_tax_included' => $isPct ? null : (bool) $r['reduction_tax'],
                'fixed_price' => (float) $r['price'] >= 0 ? round((float) $r['price'], 2) : null,
                'from_quantity' => (int) $r['from_quantity'],
                'from' => $r['from'] === self::NO_DATE ? null : (string) $r['from'],
                'to' => $r['to'] === self::NO_DATE ? null : (string) $r['to'],
                'restricted_to' => array_filter([
                    'id_customer' => (int) $r['id_customer'],
                    'id_group' => (int) $r['id_group'],
                    'id_country' => (int) $r['id_country'],
                    'id_currency' => (int) $r['id_currency'],
                ]),
                'from_catalog_rule' => (int) $r['id_specific_price_rule'] > 0,
            ];
        }

        return ['status' => $status, 'total' => $total, 'returned' => count($out), 'discounts' => $out];
    }

    /**
     * Combinations (size/colour…) at or below a stock threshold, for active
     * products. Useful to spot sizes or colours that are running out.
     *
     * @param int      $threshold  Quantity at or below which a combination is low. Defaults to 2.
     * @param int|null $id_product Only this product. Null = whole catalog.
     * @param int      $limit      Max combinations (1-200). Defaults to 100.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_low_stock_combinations',
        title: 'Get low-stock combinations',
        description: 'Lists product combinations (sizes, colours…) of active products whose stock is at or below a threshold, so the merchant can reorder specific variants.',
        annotations: new ToolAnnotations(title: 'Get low-stock combinations', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getLowStockCombinations(
        #[Schema(type: 'integer', minimum: 0, maximum: 100000)]
        int $threshold = 2,
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_product = null,
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 100
    ): array {
        $threshold = max(0, $threshold);
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = ProductQueryTools::ctx();

        $from = ' FROM `' . _DB_PREFIX_ . 'stock_available` sa
                INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.id_product_attribute = sa.id_product_attribute
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = sa.id_product AND ps.id_shop = ' . $idShop . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = sa.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                WHERE sa.id_product_attribute > 0 AND sa.id_shop = ' . $idShop . '
                  AND ps.active = 1 AND sa.quantity <= ' . $threshold
                . ($id_product !== null ? ' AND sa.id_product = ' . (int) $id_product : '');

        $db = \Db::getInstance();
        $total = (int) $db->getValue('SELECT COUNT(*)' . $from);
        $rows = $db->executeS(
            'SELECT sa.id_product, sa.id_product_attribute, sa.quantity, pl.name, pa.reference' . $from . '
             ORDER BY sa.quantity ASC, sa.id_product ASC, sa.id_product_attribute ASC
             LIMIT ' . $limit
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $idpa = (int) $r['id_product_attribute'];
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'id_product_attribute' => $idpa,
                'combination' => ProductQueryTools::combinationLabel($db, $idpa, $idLang),
                'reference' => (string) $r['reference'],
                'quantity' => (int) $r['quantity'],
            ];
        }

        return ['threshold' => $threshold, 'total' => $total, 'returned' => count($out), 'combinations' => $out];
    }
}
