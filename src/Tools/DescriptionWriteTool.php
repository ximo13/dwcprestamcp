<?php
/**
 * DWC PrestaShop MCP - DescriptionWriteTool.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Write tools to apply improved content (descriptions and SEO meta).
 * Changes are applied to the store's default language only; other languages
 * are left untouched.
 */
class DescriptionWriteTool
{
    /**
     * Update a product's short and/or long description (default language).
     * Only provided fields change; HTML is allowed.
     *
     * This tool MODIFIES live store data, so confirm before running.
     *
     * @param int         $id_product        The product ID.
     * @param string|null $description_short  New short description. Null = unchanged.
     * @param string|null $description        New long description. Null = unchanged.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_product_description',
        title: 'Update product description',
        description: 'Updates a product\'s short and/or long description (default language). Only provided fields change. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update product description', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateProductDescription(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'string', maxLength: 4000)]
        ?string $description_short = null,
        #[Schema(type: 'string', maxLength: 65535)]
        ?string $description = null
    ): array {
        return $this->applyFields($id_product, [
            'description_short' => $description_short,
            'description' => $description,
        ]);
    }

    /**
     * Update a product's SEO meta title and/or meta description (default
     * language). Only provided fields change.
     *
     * This tool MODIFIES live store data, so confirm before running.
     *
     * @param int         $id_product       The product ID.
     * @param string|null $meta_title        New meta title. Null = unchanged.
     * @param string|null $meta_description  New meta description. Null = unchanged.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_product_meta',
        title: 'Update product SEO meta',
        description: 'Updates a product\'s SEO meta title and/or meta description (default language). Only provided fields change. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update product SEO meta', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateProductMeta(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'string', maxLength: 255)]
        ?string $meta_title = null,
        #[Schema(type: 'string', maxLength: 512)]
        ?string $meta_description = null
    ): array {
        return $this->applyFields($id_product, [
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
        ]);
    }

    /**
     * @param array<string, string|null> $fields column => value|null
     *
     * @return array<string, mixed>
     */
    private function applyFields(int $idProduct, array $fields): array
    {
        $product = new \Product((int) $idProduct);
        if (!\Validate::isLoadedObject($product)) {
            return ['success' => false, 'message' => sprintf('Product %d not found.', $idProduct)];
        }

        $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $context = \Context::getContext();
        if ($context !== null && $context->language !== null) {
            $idLang = (int) $context->language->id;
        }

        $changed = [];
        foreach ($fields as $column => $value) {
            if ($value === null) {
                continue;
            }
            /** @var array<int, string> $current */
            $current = is_array($product->{$column}) ? $product->{$column} : [];
            $current[$idLang] = $value;
            $product->{$column} = $current;
            $changed[$column] = mb_strlen(trim(strip_tags($value)));
        }

        if ($changed === []) {
            return ['success' => false, 'message' => 'Nothing to update: provide at least one field.'];
        }

        $saved = (bool) $product->save();

        return [
            'success' => $saved,
            'id_product' => (int) $idProduct,
            'id_lang' => $idLang,
            'changed_fields' => array_keys($changed),
            'message' => $saved
                ? sprintf('Product %d content updated.', $idProduct)
                : sprintf('Could not save product %d.', $idProduct),
        ];
    }
}
