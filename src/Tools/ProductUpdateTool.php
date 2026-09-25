<?php
/**
 * DWC PrestaShop MCP - ProductUpdateTool.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Write tool to update an existing product.
 */
class ProductUpdateTool
{
    /**
     * Update fields of an existing product. Only the fields you provide are
     * changed; omit a field (leave it null) to keep its current value.
     *
     * This tool MODIFIES live store data, so it is not read-only and should be
     * confirmed before running.
     *
     * @param int         $id_product The product ID to update (required).
     * @param float|null  $price      New base price, tax excluded. Null = unchanged.
     * @param bool|null   $active     Publish (true) or hide (false) the product. Null = unchanged.
     * @param string|null $name       New product name, in one language only. Null = unchanged.
     * @param string|null $reference  New reference / SKU. Null = unchanged.
     * @param float|null  $weight     New weight (store's unit). Null = unchanged.
     * @param bool|null   $on_sale    Mark the product as on sale / discounted. Null = unchanged.
     * @param int|null    $quantity   New available stock quantity for the base product. Null = unchanged.
     * @param string|null $ean13             EAN-13 barcode ("" clears it). Null = unchanged.
     * @param string|null $upc               UPC barcode ("" clears it). Null = unchanged.
     * @param string|null $isbn              ISBN ("" clears it). Null = unchanged.
     * @param string|null $mpn               Manufacturer part number ("" clears it). Null = unchanged.
     * @param float|null  $wholesale_price   Cost price, tax excluded. Null = unchanged.
     * @param int|null    $id_tax_rules_group Tax rules group ID (0 = no tax). Null = unchanged.
     * @param string|null $visibility        Where it is shown: both, catalog, search or none. Null = unchanged.
     * @param bool|null   $available_for_order Whether it can be ordered. Null = unchanged.
     * @param bool|null   $show_price        Whether the price is displayed. Null = unchanged.
     * @param int|null    $minimal_quantity  Minimum quantity per order (1 or more). Null = unchanged.
     * @param string|null $condition         new, used or refurbished. Null = unchanged.
     * @param float|null  $width             Package width. Null = unchanged.
     * @param float|null  $height            Package height. Null = unchanged.
     * @param float|null  $depth             Package depth. Null = unchanged.
     * @param int|null    $out_of_stock      When out of stock: 0 deny orders, 1 allow orders, 2 store default. Null = unchanged.
     * @param string|null $link_rewrite      Friendly URL slug, in one language. Null = unchanged.
     * @param string|null $available_now     Label shown when in stock, in one language ("" clears it). Null = unchanged.
     * @param string|null $available_later   Label shown when out of stock but orderable, in one language ("" clears it). Null = unchanged.
     * @param string|null $language          Language ISO code (e.g. "en") for name, link_rewrite, available_now and available_later. Null = default language.
     *
     * @return array<string, mixed> Result with success flag, the applied changes, and a message.
     */
    #[McpTool(
        name: 'dwc_update_product',
        title: 'Update product',
        description: 'Updates an existing product. Only provided fields change: price, cost price, tax rules group, active status, name, reference, EAN/UPC/ISBN/MPN, weight and dimensions, visibility, available for order, show price, minimal quantity, condition, out-of-stock behaviour, friendly URL, availability labels, on-sale flag and stock quantity. Name, friendly URL and availability labels are saved in the default language, or in the language given by its ISO code (e.g. to save a translation); other languages keep theirs. Modifies live store data.',
        annotations: new ToolAnnotations(
            title: 'Update product',
            readOnlyHint: false,
            destructiveHint: true,
            idempotentHint: true,
            openWorldHint: false
        )
    )]
    public function updateProduct(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'number', minimum: 0)]
        ?float $price = null,
        ?bool $active = null,
        #[Schema(type: 'string', minLength: 1, maxLength: 128)]
        ?string $name = null,
        #[Schema(type: 'string', maxLength: 64)]
        ?string $reference = null,
        #[Schema(type: 'number', minimum: 0)]
        ?float $weight = null,
        ?bool $on_sale = null,
        #[Schema(type: 'integer', minimum: 0)]
        ?int $quantity = null,
        #[Schema(type: 'string', maxLength: 13)]
        ?string $ean13 = null,
        #[Schema(type: 'string', maxLength: 12)]
        ?string $upc = null,
        #[Schema(type: 'string', maxLength: 32)]
        ?string $isbn = null,
        #[Schema(type: 'string', maxLength: 40)]
        ?string $mpn = null,
        #[Schema(type: 'number', minimum: 0)]
        ?float $wholesale_price = null,
        #[Schema(type: 'integer', minimum: 0)]
        ?int $id_tax_rules_group = null,
        #[Schema(type: 'string', enum: ['both', 'catalog', 'search', 'none'])]
        ?string $visibility = null,
        ?bool $available_for_order = null,
        ?bool $show_price = null,
        #[Schema(type: 'integer', minimum: 1)]
        ?int $minimal_quantity = null,
        #[Schema(type: 'string', enum: ['new', 'used', 'refurbished'])]
        ?string $condition = null,
        #[Schema(type: 'number', minimum: 0)]
        ?float $width = null,
        #[Schema(type: 'number', minimum: 0)]
        ?float $height = null,
        #[Schema(type: 'number', minimum: 0)]
        ?float $depth = null,
        #[Schema(type: 'integer', enum: [0, 1, 2])]
        ?int $out_of_stock = null,
        #[Schema(type: 'string', minLength: 1, maxLength: 128)]
        ?string $link_rewrite = null,
        #[Schema(type: 'string', maxLength: 255)]
        ?string $available_now = null,
        #[Schema(type: 'string', maxLength: 255)]
        ?string $available_later = null,
        #[Schema(type: 'string', maxLength: 5)]
        ?string $language = null
    ): array {
        $product = new \Product((int) $id_product);
        if (!\Validate::isLoadedObject($product)) {
            return [
                'success' => false,
                'message' => sprintf('Product %d not found.', $id_product),
            ];
        }

        $changes = [];
        // Fields stored per language, written only in the requested one.
        $translated = [];

        if ($price !== null) {
            if ($price < 0) {
                return ['success' => false, 'message' => 'Price cannot be negative.'];
            }
            $product->price = (float) $price;
            $changes['price'] = (float) $price;
        }

        if ($active !== null) {
            $product->active = $active ? 1 : 0;
            $changes['active'] = (bool) $active;
        }

        if ($reference !== null) {
            $product->reference = $reference;
            $changes['reference'] = $reference;
        }

        if ($weight !== null) {
            if ($weight < 0) {
                return ['success' => false, 'message' => 'Weight cannot be negative.'];
            }
            $product->weight = (float) $weight;
            $changes['weight'] = (float) $weight;
        }

        if ($on_sale !== null) {
            $product->on_sale = $on_sale ? 1 : 0;
            $changes['on_sale'] = (bool) $on_sale;
        }

        if ($name !== null) {
            $trimmed = trim($name);
            if ($trimmed === '') {
                return ['success' => false, 'message' => 'Name cannot be empty.'];
            }
            if (!\Validate::isCatalogName($trimmed)) {
                return ['success' => false, 'message' => 'Name contains characters that are not allowed (<>;=#{}).'];
            }
            $translated['name'] = $trimmed;
        }

        foreach (['ean13' => 'isEan13', 'upc' => 'isUpc', 'isbn' => 'isIsbn', 'mpn' => 'isMpn'] as $field => $rule) {
            if ($$field === null) {
                continue;
            }
            $code = trim($$field);
            if ($code !== '' && !\Validate::$rule($code)) {
                return ['success' => false, 'message' => sprintf('Invalid %s "%s".', strtoupper($field), $code)];
            }
            $product->$field = $code;
            $changes[$field] = $code;
        }

        foreach (['wholesale_price', 'width', 'height', 'depth'] as $field) {
            if ($$field !== null) {
                if ($$field < 0) {
                    return ['success' => false, 'message' => sprintf('%s cannot be negative.', $field)];
                }
                $product->$field = (float) $$field;
                $changes[$field] = (float) $$field;
            }
        }

        if ($id_tax_rules_group !== null) {
            if ($id_tax_rules_group > 0 && !\Validate::isLoadedObject(new \TaxRulesGroup($id_tax_rules_group))) {
                return ['success' => false, 'message' => sprintf('Tax rules group %d not found.', $id_tax_rules_group)];
            }
            $product->id_tax_rules_group = $id_tax_rules_group;
            $changes['id_tax_rules_group'] = $id_tax_rules_group;
        }

        foreach (['visibility' => ['both', 'catalog', 'search', 'none'], 'condition' => ['new', 'used', 'refurbished']] as $field => $allowed) {
            if ($$field !== null) {
                if (!in_array($$field, $allowed, true)) {
                    return ['success' => false, 'message' => sprintf('%s must be one of: %s.', $field, implode(', ', $allowed))];
                }
                $product->$field = $$field;
                $changes[$field] = $$field;
            }
        }

        foreach (['available_for_order', 'show_price'] as $field) {
            if ($$field !== null) {
                $product->$field = $$field ? 1 : 0;
                $changes[$field] = (bool) $$field;
            }
        }

        if ($minimal_quantity !== null) {
            if ($minimal_quantity < 1) {
                return ['success' => false, 'message' => 'Minimal quantity must be 1 or more.'];
            }
            $product->minimal_quantity = $minimal_quantity;
            $changes['minimal_quantity'] = $minimal_quantity;
        }

        if ($link_rewrite !== null) {
            $slug = \Tools::str2url(trim($link_rewrite));
            if ($slug === '' || !\Validate::isLinkRewrite($slug)) {
                return ['success' => false, 'message' => 'Invalid friendly URL.'];
            }
            $translated['link_rewrite'] = $slug;
        }

        foreach (['available_now', 'available_later'] as $field) {
            if ($$field !== null) {
                $label = trim($$field);
                if ($label !== '' && !\Validate::isGenericName($label)) {
                    return ['success' => false, 'message' => sprintf('%s contains characters that are not allowed (<>={}).', $field)];
                }
                $translated[$field] = $label;
            }
        }

        if ($translated !== []) {
            // Only one language: writing every language would wipe out existing translations.
            $idLang = ProductQueryTools::langId($language);
            if ($idLang === null) {
                return ['success' => false, 'message' => sprintf('Language "%s" not found or inactive.', (string) $language)];
            }
            foreach ($translated as $field => $value) {
                $product->{$field}[$idLang] = $value;
                $changes[$field] = $value;
            }
            $changes['language'] = (string) \Language::getIsoById($idLang);
        }

        if ($out_of_stock !== null && !in_array($out_of_stock, [0, 1, 2], true)) {
            return ['success' => false, 'message' => 'out_of_stock must be 0 (deny), 1 (allow) or 2 (store default).'];
        }

        // Stock lives in a separate table (StockAvailable), not on the Product.
        $stockRequested = ($quantity !== null);
        if ($stockRequested && $quantity < 0) {
            return ['success' => false, 'message' => 'Quantity cannot be negative.'];
        }

        if ($changes === [] && !$stockRequested && $out_of_stock === null) {
            return [
                'success' => false,
                'message' => 'Nothing to update: provide at least one field to change.',
            ];
        }

        $productSaved = true;
        if ($changes !== []) {
            $productSaved = (bool) $product->save();
        }

        if ($stockRequested && $productSaved) {
            // add_movement=false: no logged-in employee exists in an API request,
            // and StockMvt logging requires one. We set the absolute quantity
            // without writing a stock-movement audit row.
            \StockAvailable::setQuantity((int) $id_product, 0, (int) $quantity, null, false);
            $changes['quantity'] = (int) $quantity;
        }

        if ($out_of_stock !== null && $productSaved) {
            // Stored in stock_available too, like the quantity.
            \StockAvailable::setProductOutOfStock((int) $id_product, $out_of_stock);
            $changes['out_of_stock'] = $out_of_stock;
        }

        return [
            'success' => $productSaved,
            'id_product' => (int) $id_product,
            'changed' => $changes,
            'message' => $productSaved
                ? sprintf('Product %d updated.', $id_product)
                : sprintf('Could not save product %d.', $id_product),
        ];
    }
}
