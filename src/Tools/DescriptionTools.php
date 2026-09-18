<?php
/**
 * DWC PrestaShop MCP - DescriptionTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only content/SEO audit and content retrieval for products.
 */
class DescriptionTools
{
    /** @var array<string, string> Public field name => product_lang column. */
    private const FIELDS = [
        'short' => 'description_short',
        'long' => 'description',
        'meta_title' => 'meta_title',
        'meta_description' => 'meta_description',
    ];

    /**
     * List products whose given content field is missing or too short. Use it
     * to audit the catalog for content/SEO gaps.
     *
     * @param string $field      Which field to check: "short", "long", "meta_title" or "meta_description".
     * @param int    $max_length Consider it missing/poor when the text length is at or below this. 0 = only empty. Defaults to 0.
     * @param int    $limit      Max products to return (1-200). Defaults to 50.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_get_products_missing_content',
        title: 'Get products with missing/poor content',
        description: 'Lists products whose short/long description or meta title/description is empty or shorter than a length threshold. For content and SEO audits.',
        annotations: new ToolAnnotations(title: 'Get products with missing/poor content', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductsMissingContent(
        #[Schema(type: 'string', enum: ['short', 'long', 'meta_title', 'meta_description'])]
        string $field = 'short',
        #[Schema(type: 'integer', minimum: 0, maximum: 100000)]
        int $max_length = 0,
        #[Schema(type: 'integer', minimum: 1, maximum: 200)]
        int $limit = 50
    ): array {
        if (!isset(self::FIELDS[$field])) {
            return [];
        }
        $column = self::FIELDS[$field];
        $max_length = max(0, $max_length);
        $limit = min(200, max(1, $limit));
        [$idLang, $idShop] = self::ctx();

        // Raw length as a fast SQL pre-filter; exact stripped length computed in PHP.
        $sql = 'SELECT p.id_product, p.reference, pl.name, pl.`' . $column . '` AS field_value
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.id_product = p.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                WHERE CHAR_LENGTH(TRIM(pl.`' . $column . '`)) <= ' . ($max_length + 32) . '
                ORDER BY CHAR_LENGTH(TRIM(pl.`' . $column . '`)) ASC, p.id_product ASC
                LIMIT ' . ($limit * 2);

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $text = trim(strip_tags((string) $r['field_value']));
            $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
            if ($len > $max_length) {
                continue;
            }
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name' => (string) $r['name'],
                'reference' => (string) $r['reference'],
                'field' => $field,
                'text_length' => $len,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Get the full editable content of a product (short & long description,
     * meta title & meta description) so it can be reviewed and improved.
     *
     * @param int $id_product The product ID.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_product_content',
        title: 'Get product content',
        description: 'Returns a product\'s short description, long description, meta title and meta description (with their lengths) for review or improvement.',
        annotations: new ToolAnnotations(title: 'Get product content', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getProductContent(
        #[Schema(type: 'integer', minimum: 1)]
        int $id_product
    ): array {
        [$idLang, $idShop] = self::ctx();

        $rows = \Db::getInstance()->executeS(
            'SELECT pl.name, pl.description_short, pl.description, pl.meta_title, pl.meta_description
             FROM `' . _DB_PREFIX_ . 'product_lang` pl
             WHERE pl.id_product = ' . (int) $id_product . ' AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
             LIMIT 1'
        );
        if (!is_array($rows) || $rows === []) {
            return ['id_product' => (int) $id_product, 'found' => false, 'message' => 'Product not found.'];
        }

        $r = $rows[0];
        $len = static function (string $html): int {
            $t = trim(strip_tags($html));

            return function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
        };

        return [
            'id_product' => (int) $id_product,
            'found' => true,
            'name' => (string) $r['name'],
            'description_short' => (string) $r['description_short'],
            'description_short_length' => $len((string) $r['description_short']),
            'description' => (string) $r['description'],
            'description_length' => $len((string) $r['description']),
            'meta_title' => (string) $r['meta_title'],
            'meta_description' => (string) $r['meta_description'],
            'meta_description_length' => $len((string) $r['meta_description']),
        ];
    }

    /**
     * @return array{0:int,1:int} [id_lang, id_shop]
     */
    private static function ctx(): array
    {
        $context = \Context::getContext();
        $idLang = ($context !== null && $context->language !== null) ? (int) $context->language->id : (int) \Configuration::get('PS_LANG_DEFAULT');
        $idShop = ($context !== null && $context->shop !== null) ? (int) $context->shop->id : (int) \Configuration::get('PS_SHOP_DEFAULT');

        return [$idLang, $idShop];
    }
}
