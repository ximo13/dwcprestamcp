<?php
/**
 * DWC PrestaShop MCP - ProductWriteTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Write tools for products: bulk price changes, discounts (specific prices),
 * combination stock, categories and brand. All of them MODIFY live store data.
 */
class ProductWriteTools
{
    /** Hard cap on how many products a single bulk price change may touch. */
    private const BULK_MAX_PRODUCTS = 2000;

    /** How many before/after rows the bulk price response shows. */
    private const BULK_SAMPLE = 20;

    /**
     * Change the base price (tax excl.) of every product in a category and/or
     * brand by a percentage or a fixed amount.
     *
     * Two-step by design: without confirm=true it only returns a PREVIEW
     * (products affected and old/new prices) and changes nothing. Show the
     * preview to the merchant and call again with confirm=true only after they
     * approve it. Combination price impacts are not changed.
     *
     * @param int|null   $id_category           Category to change. Null = any (then id_manufacturer is required).
     * @param int|null   $id_manufacturer       Brand to change. Null = any (then id_category is required).
     * @param float|null $percent               Percentage change, e.g. 5 = +5 %, -10 = -10 %. Use this OR amount.
     * @param float|null $amount                Fixed change in currency, tax excl., e.g. 2 or -1.5. Use this OR percent.
     * @param bool       $include_subcategories Also change products of subcategories. Defaults to false.
     * @param bool       $only_active           Only change active products. Defaults to true.
     * @param bool       $confirm               false = preview only; true = apply. Defaults to false.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_bulk_update_prices',
        title: 'Bulk update prices',
        description: 'Raises or lowers the base price (tax excl.) of all products in a category and/or brand by a percentage or a fixed amount. Without confirm=true it only returns a preview and changes nothing: show it to the merchant and only call again with confirm=true after explicit approval. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Bulk update prices', readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false)
    )]
    public function bulkUpdatePrices(
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_category = null,
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_manufacturer = null,
        #[Schema(type: 'number', minimum: -90, maximum: 1000)]
        ?float $percent = null,
        #[Schema(type: 'number')]
        ?float $amount = null,
        bool $include_subcategories = false,
        bool $only_active = true,
        bool $confirm = false
    ): array {
        if ($id_category === null && $id_manufacturer === null) {
            return ['success' => false, 'message' => 'Provide id_category and/or id_manufacturer.'];
        }
        if (($percent === null) === ($amount === null)) {
            return ['success' => false, 'message' => 'Provide exactly one of percent or amount.'];
        }
        if ($percent !== null && ($percent < -90 || $percent > 1000 || $percent == 0)) {
            return ['success' => false, 'message' => 'percent must be between -90 and 1000 and not 0.'];
        }
        if ($amount !== null && $amount == 0) {
            return ['success' => false, 'message' => 'amount cannot be 0.'];
        }

        [$idLang, $idShop] = ProductQueryTools::ctx();
        $db = \Db::getInstance();

        $where = [];
        if ($id_category !== null) {
            $cat = $db->executeS('SELECT nleft, nright FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . (int) $id_category);
            if (!is_array($cat) || $cat === []) {
                return ['success' => false, 'message' => sprintf('Category %d not found.', $id_category)];
            }
            $catFilter = $include_subcategories
                ? 'SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c WHERE c.nleft >= ' . (int) $cat[0]['nleft'] . ' AND c.nright <= ' . (int) $cat[0]['nright']
                : (string) (int) $id_category;
            $where[] = 'EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                        WHERE cp.id_product = p.id_product AND cp.id_category IN (' . $catFilter . '))';
        }
        if ($id_manufacturer !== null) {
            $where[] = 'p.id_manufacturer = ' . (int) $id_manufacturer;
        }
        if ($only_active) {
            $where[] = 'ps.active = 1';
        }

        $rows = $db->executeS(
            'SELECT p.id_product, pl.name, ps.price
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $idShop . '
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.id_product ASC
             LIMIT ' . (self::BULK_MAX_PRODUCTS + 1)
        );
        $rows = is_array($rows) ? $rows : [];
        if ($rows === []) {
            return ['success' => false, 'message' => 'No products match these filters.'];
        }
        if (count($rows) > self::BULK_MAX_PRODUCTS) {
            return ['success' => false, 'message' => sprintf('More than %d products match; narrow the filters.', self::BULK_MAX_PRODUCTS)];
        }

        $changes = [];
        $negative = [];
        foreach ($rows as $r) {
            $old = (float) $r['price'];
            $new = round($percent !== null ? $old * (1 + $percent / 100) : $old + (float) $amount, 6);
            if ($new < 0) {
                $negative[] = (int) $r['id_product'];
                continue;
            }
            $changes[] = ['id_product' => (int) $r['id_product'], 'name' => (string) $r['name'], 'old_price' => $old, 'new_price' => $new];
        }
        if ($negative !== []) {
            return ['success' => false, 'message' => 'The change would make some prices negative; nothing applied.', 'products_with_negative_price' => $negative];
        }

        $sample = array_map(static function (array $c): array {
            return ['id_product' => $c['id_product'], 'name' => $c['name'], 'old_price_tax_excl' => round($c['old_price'], 2), 'new_price_tax_excl' => round($c['new_price'], 2)];
        }, array_slice($changes, 0, self::BULK_SAMPLE));
        $change = $percent !== null ? sprintf('%+g %%', $percent) : sprintf('%+g (tax excl.)', $amount);

        if (!$confirm) {
            return [
                'success' => true,
                'applied' => false,
                'preview' => true,
                'change' => $change,
                'products_affected' => count($changes),
                'sample' => $sample,
                'message' => 'Preview only, nothing changed. Show this to the merchant and call again with confirm=true to apply.',
            ];
        }

        $updated = 0;
        foreach ($changes as $c) {
            $price = number_format($c['new_price'], 6, '.', '');
            $ok = $db->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET price = ' . $price . ', date_upd = NOW() WHERE id_product = ' . $c['id_product'])
                && $db->execute('UPDATE `' . _DB_PREFIX_ . 'product_shop` SET price = ' . $price . ', date_upd = NOW() WHERE id_product = ' . $c['id_product'] . ' AND id_shop = ' . $idShop);
            if ($ok) {
                ++$updated;
            }
        }

        return [
            'success' => $updated === count($changes),
            'applied' => true,
            'change' => $change,
            'products_updated' => $updated,
            'products_failed' => count($changes) - $updated,
            'sample' => $sample,
            'message' => sprintf('%d product prices updated.', $updated),
        ];
    }

    /**
     * Create a temporary discount (specific price) for a product: a percentage
     * or a fixed amount, optionally between two dates.
     *
     * @param int         $id_product           The product ID.
     * @param string      $reduction_type       percentage | amount.
     * @param float       $reduction            Percentage (e.g. 15 = 15 %) or amount in currency.
     * @param string|null $from                 Start date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS). Null = from now on.
     * @param string|null $to                   End date (same format; a bare date means end of that day). Null = no end.
     * @param int         $id_product_attribute Only this combination. 0 = all combinations.
     * @param int         $from_quantity        Minimum quantity for the discount. Defaults to 1.
     * @param bool        $tax_included         For amount discounts: the amount includes tax. Defaults to true.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_create_product_discount',
        title: 'Create product discount',
        description: 'Creates a discount (specific price) for a product: a percentage or a fixed amount, optionally between two dates, for all combinations or one. Applies to all customers. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Create product discount', readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false)
    )]
    public function createProductDiscount(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'string', enum: ['percentage', 'amount'])]
        string $reduction_type,
        #[Schema(type: 'number', minimum: 0)]
        float $reduction,
        #[Schema(type: 'string', maxLength: 19)]
        ?string $from = null,
        #[Schema(type: 'string', maxLength: 19)]
        ?string $to = null,
        #[Schema(type: 'integer', minimum: 0)]
        int $id_product_attribute = 0,
        #[Schema(type: 'integer', minimum: 1)]
        int $from_quantity = 1,
        bool $tax_included = true
    ): array {
        $product = new \Product($id_product);
        if (!\Validate::isLoadedObject($product)) {
            return ['success' => false, 'message' => sprintf('Product %d not found.', $id_product)];
        }
        if ($reduction_type !== 'percentage' && $reduction_type !== 'amount') {
            return ['success' => false, 'message' => 'reduction_type must be percentage or amount.'];
        }
        if ($reduction <= 0 || ($reduction_type === 'percentage' && $reduction > 100)) {
            return ['success' => false, 'message' => 'reduction must be > 0 (and at most 100 for a percentage).'];
        }
        if ($id_product_attribute > 0 && !self::combinationBelongs($id_product, $id_product_attribute)) {
            return ['success' => false, 'message' => sprintf('Combination %d does not belong to product %d.', $id_product_attribute, $id_product)];
        }

        $fromDate = self::parseDate($from, false);
        $toDate = self::parseDate($to, true);
        if ($fromDate === false || $toDate === false) {
            return ['success' => false, 'message' => 'Invalid date. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.'];
        }
        if ($fromDate !== null && $toDate !== null && $toDate <= $fromDate) {
            return ['success' => false, 'message' => 'The end date must be after the start date.'];
        }

        $sp = new \SpecificPrice();
        $sp->id_product = $id_product;
        $sp->id_product_attribute = $id_product_attribute;
        $sp->id_shop = 0;
        $sp->id_shop_group = 0;
        $sp->id_currency = 0;
        $sp->id_country = 0;
        $sp->id_group = 0;
        $sp->id_customer = 0;
        $sp->id_cart = 0;
        $sp->price = -1;
        $sp->from_quantity = $from_quantity;
        $sp->reduction = $reduction_type === 'percentage' ? $reduction / 100 : $reduction;
        $sp->reduction_tax = $tax_included ? 1 : 0;
        $sp->reduction_type = $reduction_type;
        $sp->from = $fromDate ?? '0000-00-00 00:00:00';
        $sp->to = $toDate ?? '0000-00-00 00:00:00';

        try {
            $saved = (bool) $sp->add();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not create the discount (a discount with the same conditions and dates may already exist).'];
        }

        return [
            'success' => $saved,
            'id_specific_price' => $saved ? (int) $sp->id : null,
            'id_product' => $id_product,
            'reduction' => $reduction,
            'reduction_type' => $reduction_type,
            'from' => $fromDate,
            'to' => $toDate,
            'message' => $saved ? sprintf('Discount created for product %d.', $id_product) : 'Could not create the discount.',
        ];
    }

    /**
     * Delete a discount (specific price) by its id. Discounts generated by
     * catalog price rules cannot be deleted here.
     *
     * @param int $id_specific_price The discount id (see dwc_get_product_discounts).
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_delete_product_discount',
        title: 'Delete product discount',
        description: 'Deletes a product discount (specific price) by its id, as listed by dwc_get_product_discounts. Discounts created by catalog price rules are refused. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Delete product discount', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function deleteProductDiscount(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_specific_price
    ): array {
        $sp = new \SpecificPrice($id_specific_price);
        if (!\Validate::isLoadedObject($sp)) {
            return ['success' => false, 'message' => sprintf('Discount %d not found.', $id_specific_price)];
        }
        if ((int) $sp->id_specific_price_rule > 0) {
            return ['success' => false, 'message' => 'This discount comes from a catalog price rule; change the rule in the back office instead.'];
        }
        if ((int) $sp->id_cart > 0) {
            return ['success' => false, 'message' => 'This discount belongs to a specific cart and cannot be deleted here.'];
        }

        $deleted = (bool) $sp->delete();

        return [
            'success' => $deleted,
            'id_specific_price' => $id_specific_price,
            'id_product' => (int) $sp->id_product,
            'message' => $deleted ? sprintf('Discount %d deleted.', $id_specific_price) : 'Could not delete the discount.',
        ];
    }

    /**
     * Set the stock of one combination (size/colour…) of a product. The
     * product total is recalculated by PrestaShop.
     *
     * @param int $id_product           The product ID.
     * @param int $id_product_attribute The combination ID (see dwc_get_product_stock).
     * @param int $quantity             New available quantity.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_combination_stock',
        title: 'Update combination stock',
        description: 'Sets the available stock of one combination (size, colour…) of a product; the product total is recalculated. Get combination ids with dwc_get_product_stock. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update combination stock', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateCombinationStock(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product_attribute,
        #[Schema(type: 'integer', minimum: 0)]
        int $quantity
    ): array {
        if ($quantity < 0) {
            return ['success' => false, 'message' => 'Quantity cannot be negative.'];
        }
        if (!self::combinationBelongs($id_product, $id_product_attribute)) {
            return ['success' => false, 'message' => sprintf('Combination %d does not belong to product %d.', $id_product_attribute, $id_product)];
        }
        [$idLang, $idShop] = ProductQueryTools::ctx();

        $before = (int) \StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute, $idShop);
        // add_movement=false: no employee exists in an API request (see ProductUpdateTool).
        \StockAvailable::setQuantity($id_product, $id_product_attribute, $quantity, null, false);

        return [
            'success' => true,
            'id_product' => $id_product,
            'id_product_attribute' => $id_product_attribute,
            'combination' => ProductQueryTools::combinationLabel(\Db::getInstance(), $id_product_attribute, $idLang),
            'old_quantity' => $before,
            'new_quantity' => $quantity,
            'product_total_quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($id_product, 0, $idShop),
            'message' => sprintf('Stock of combination %d set to %d.', $id_product_attribute, $quantity),
        ];
    }

    /**
     * Add or remove categories of a product and/or change its default
     * (main) category. The product always keeps at least one category.
     *
     * @param int        $id_product          The product ID.
     * @param int[]|null $add                 Category ids to add. Null = none.
     * @param int[]|null $remove              Category ids to remove. Null = none.
     * @param int|null   $id_category_default New main category (added if missing). Null = unchanged.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_product_categories',
        title: 'Update product categories',
        description: 'Adds and/or removes categories of a product and optionally sets its main (default) category. Get category ids with dwc_list_categories. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update product categories', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateProductCategories(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'array', items: ['type' => 'integer', 'minimum' => 1], maxItems: 50)]
        ?array $add = null,
        #[Schema(type: 'array', items: ['type' => 'integer', 'minimum' => 1], maxItems: 50)]
        ?array $remove = null,
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_category_default = null
    ): array {
        $product = new \Product($id_product);
        if (!\Validate::isLoadedObject($product)) {
            return ['success' => false, 'message' => sprintf('Product %d not found.', $id_product)];
        }
        $add = array_values(array_unique(array_map('intval', $add ?? [])));
        $remove = array_values(array_unique(array_map('intval', $remove ?? [])));
        if ($add === [] && $remove === [] && $id_category_default === null) {
            return ['success' => false, 'message' => 'Nothing to update: provide add, remove or id_category_default.'];
        }
        if ($id_category_default !== null && in_array($id_category_default, $remove, true)) {
            return ['success' => false, 'message' => 'The new main category cannot also be removed.'];
        }
        if ($id_category_default !== null && !in_array($id_category_default, $add, true)) {
            $add[] = $id_category_default;
        }

        $db = \Db::getInstance();
        $missing = [];
        foreach (array_merge($add, $remove) as $idCat) {
            if (!(int) $db->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . (int) $idCat)) {
                $missing[] = $idCat;
            }
        }
        if ($missing !== []) {
            return ['success' => false, 'message' => 'Unknown category ids.', 'unknown_categories' => $missing];
        }

        $current = array_map('intval', array_column((array) \Product::getProductCategoriesFull($id_product), 'id_category'));
        $final = array_values(array_diff(array_unique(array_merge($current, $add)), $remove));
        if ($final === []) {
            return ['success' => false, 'message' => 'The product must keep at least one category.'];
        }
        $newDefault = $id_category_default ?? (int) $product->id_category_default;
        if (!in_array($newDefault, $final, true)) {
            return ['success' => false, 'message' => 'You are removing the main category: also pass id_category_default with one of the remaining categories.', 'remaining_categories' => $final];
        }

        $toAdd = array_values(array_diff($add, $current));
        if ($toAdd !== []) {
            $product->addToCategories($toAdd);
        }
        foreach (array_intersect($remove, $current) as $idCat) {
            $product->deleteCategory((int) $idCat);
        }
        $saved = true;
        if ($newDefault !== (int) $product->id_category_default) {
            $product->id_category_default = $newDefault;
            $saved = (bool) $product->save();
        }

        return [
            'success' => $saved,
            'id_product' => $id_product,
            'added' => $toAdd,
            'removed' => array_values(array_intersect($remove, $current)),
            'categories' => $final,
            'id_category_default' => $newDefault,
            'message' => $saved ? sprintf('Categories of product %d updated.', $id_product) : sprintf('Could not save the main category of product %d.', $id_product),
        ];
    }

    /**
     * Set or remove the brand (manufacturer) of a product.
     *
     * @param int $id_product      The product ID.
     * @param int $id_manufacturer Brand id (see dwc_list_brands). 0 = remove the brand.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_set_product_brand',
        title: 'Set product brand',
        description: 'Sets the brand (manufacturer) of a product, or removes it with id_manufacturer=0. Get brand ids with dwc_list_brands. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Set product brand', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function setProductBrand(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'integer', minimum: 0)]
        int $id_manufacturer
    ): array {
        $product = new \Product($id_product);
        if (!\Validate::isLoadedObject($product)) {
            return ['success' => false, 'message' => sprintf('Product %d not found.', $id_product)];
        }
        $name = null;
        if ($id_manufacturer > 0) {
            $name = (string) \Manufacturer::getNameById($id_manufacturer);
            if ($name === '') {
                return ['success' => false, 'message' => sprintf('Brand %d not found.', $id_manufacturer)];
            }
        }

        $old = (int) $product->id_manufacturer;
        $product->id_manufacturer = $id_manufacturer;
        $saved = (bool) $product->save();

        return [
            'success' => $saved,
            'id_product' => $id_product,
            'old_id_manufacturer' => $old,
            'id_manufacturer' => $id_manufacturer,
            'brand' => $name,
            'message' => $saved
                ? ($id_manufacturer > 0 ? sprintf('Brand of product %d set to %s.', $id_product, $name) : sprintf('Brand removed from product %d.', $id_product))
                : sprintf('Could not save product %d.', $id_product),
        ];
    }

    private static function combinationBelongs(int $idProduct, int $idProductAttribute): bool
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'product_attribute`
             WHERE id_product = ' . $idProduct . ' AND id_product_attribute = ' . $idProductAttribute
        );
    }

    /**
     * @return string|false|null Normalized 'Y-m-d H:i:s', null when empty, false when invalid.
     */
    private static function parseDate(?string $value, bool $endOfDay)
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $d = \DateTime::createFromFormat('!' . $format, $value);
            if ($d !== false && $d->format($format) === $value) {
                if ($format === 'Y-m-d') {
                    $d->setTime($endOfDay ? 23 : 0, $endOfDay ? 59 : 0, $endOfDay ? 59 : 0);
                }

                return $d->format('Y-m-d H:i:s');
            }
        }

        return false;
    }
}
