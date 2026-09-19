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
 * Changes are applied to one language: the store's default language, or the
 * one given by its ISO code. Other languages are left untouched.
 */
class DescriptionWriteTool
{
    /**
     * Update a product's short and/or long description in one language.
     * Only provided fields change; HTML is allowed.
     *
     * This tool MODIFIES live store data, so confirm before running.
     *
     * @param int         $id_product        The product ID.
     * @param string|null $description_short  New short description. Null = unchanged.
     * @param string|null $description        New long description. Null = unchanged.
     * @param string|null $language           Language ISO code (e.g. "en"). Null = default language.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_product_description',
        title: 'Update product description',
        description: 'Updates a product\'s short and/or long description in the default language, or in the language given by its ISO code (e.g. to save a translation). Only provided fields change. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update product description', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateProductDescription(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'string', maxLength: 4000)]
        ?string $description_short = null,
        #[Schema(type: 'string', maxLength: 65535)]
        ?string $description = null,
        #[Schema(type: 'string', minLength: 2, maxLength: 5)]
        ?string $language = null
    ): array {
        return $this->applyFields($id_product, [
            'description_short' => $description_short,
            'description' => $description,
        ], $language);
    }

    /**
     * Update a product's SEO meta title and/or meta description in one
     * language. Only provided fields change.
     *
     * This tool MODIFIES live store data, so confirm before running.
     *
     * @param int         $id_product       The product ID.
     * @param string|null $meta_title        New meta title. Null = unchanged.
     * @param string|null $meta_description  New meta description. Null = unchanged.
     * @param string|null $language          Language ISO code (e.g. "en"). Null = default language.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_update_product_meta',
        title: 'Update product SEO meta',
        description: 'Updates a product\'s SEO meta title and/or meta description in the default language, or in the language given by its ISO code (e.g. to save a translation). Only provided fields change. Modifies live store data.',
        annotations: new ToolAnnotations(title: 'Update product SEO meta', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false)
    )]
    public function updateProductMeta(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product,
        #[Schema(type: 'string', maxLength: 255)]
        ?string $meta_title = null,
        #[Schema(type: 'string', maxLength: 512)]
        ?string $meta_description = null,
        #[Schema(type: 'string', minLength: 2, maxLength: 5)]
        ?string $language = null
    ): array {
        return $this->applyFields($id_product, [
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
        ], $language);
    }

    /**
     * @param array<string, string|null> $fields   column => value|null
     * @param string|null                $language Language ISO code. Null = default language.
     *
     * @return array<string, mixed>
     */
    private function applyFields(int $idProduct, array $fields, ?string $language = null): array
    {
        $product = new \Product((int) $idProduct);
        if (!\Validate::isLoadedObject($product)) {
            return ['success' => false, 'message' => sprintf('Product %d not found.', $idProduct)];
        }

        $idLang = ProductQueryTools::langId($language);
        if ($idLang === null) {
            return ['success' => false, 'message' => sprintf('Language "%s" not found or inactive.', (string) $language)];
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
