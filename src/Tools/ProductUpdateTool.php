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
     * @param string|null $name       New product name (applied to all languages). Null = unchanged.
     * @param string|null $reference  New reference / SKU. Null = unchanged.
     * @param float|null  $weight     New weight (store's unit). Null = unchanged.
     * @param bool|null   $on_sale    Mark the product as on sale / discounted. Null = unchanged.
     * @param int|null    $quantity   New available stock quantity for the base product. Null = unchanged.
     *
     * @return array<string, mixed> Result with success flag, the applied changes, and a message.
     */
    #[McpTool(
        name: 'dwc_update_product',
        title: 'Update product',
        description: 'Updates an existing product. Only provided fields change: price, active status, name, reference, weight, on-sale flag and stock quantity. Modifies live store data.',
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
        ?int $quantity = null
    ): array {
        $product = new \Product((int) $id_product);
        if (!\Validate::isLoadedObject($product)) {
            return [
                'success' => false,
                'message' => sprintf('Product %d not found.', $id_product),
            ];
        }

        $changes = [];

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
            $languages = \Language::getLanguages(false);
            foreach ($languages as $lang) {
                $product->name[(int) $lang['id_lang']] = $trimmed;
            }
            $changes['name'] = $trimmed;
        }

        // Stock lives in a separate table (StockAvailable), not on the Product.
        $stockRequested = ($quantity !== null);
        if ($stockRequested && $quantity < 0) {
            return ['success' => false, 'message' => 'Quantity cannot be negative.'];
        }

        if ($changes === [] && !$stockRequested) {
            return [
                'success' => false,
                'message' => 'Nothing to update: provide at least one of price, active, name, reference, weight, on_sale or quantity.',
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
