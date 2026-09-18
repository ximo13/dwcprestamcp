<?php
/**
 * DWC PrestaShop MCP - OrderTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only order, sales and cart queries.
 */
class OrderTools
{
    /**
     * List orders, optionally filtered by order status (state). Pass a status
     * name (e.g. "payment", "shipped", "pendiente") or a numeric state id.
     *
     * @param string|null $status         Status name to match (localized, substring). Null = any.
     * @param int|null    $id_order_state Exact order state id. Null = any.
     * @param int         $limit          Max orders (1-100). Defaults to 20.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_get_orders_by_status',
        title: 'Get orders by status',
        description: 'Lists recent orders, optionally filtered by status name or state id; returns reference, customer, total and status.',
        annotations: new ToolAnnotations(title: 'Get orders by status', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getOrdersByStatus(
        #[Schema(type: 'string', maxLength: 64)]
        ?string $status = null,
        #[Schema(type: 'integer', minimum: 1)]
        ?int $id_order_state = null,
        #[Schema(type: 'integer', minimum: 1, maximum: 100)]
        int $limit = 20
    ): array {
        $limit = min(100, max(1, $limit));
        [$idLang] = self::ctx();

        $where = '1';
        if ($id_order_state !== null) {
            $where .= ' AND o.current_state = ' . (int) $id_order_state;
        }
        if ($status !== null && trim($status) !== '') {
            $where .= ' AND osl.name LIKE \'%' . pSQL(trim($status), true) . '%\'';
        }

        $sql = 'SELECT o.id_order, o.reference, o.total_paid, o.date_add,
                       CONCAT(c.firstname, " ", c.lastname) AS customer,
                       osl.name AS status
                FROM `' . _DB_PREFIX_ . 'orders` o
                INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
                LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                    ON osl.id_order_state = o.current_state AND osl.id_lang = ' . $idLang . '
                WHERE ' . $where . '
                ORDER BY o.date_add DESC
                LIMIT ' . $limit;

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id_order' => (int) $r['id_order'],
                'reference' => (string) $r['reference'],
                'customer' => trim((string) $r['customer']),
                'total_paid' => round((float) $r['total_paid'], 2),
                'status' => (string) ($r['status'] ?? ''),
                'date' => (string) $r['date_add'],
            ];
        }

        return $out;
    }

    /**
     * Sales summary for a date range: number of orders, total revenue and
     * average order value.
     *
     * @param string $date_from  Start date, YYYY-MM-DD (inclusive).
     * @param string $date_to    End date, YYYY-MM-DD (inclusive).
     * @param bool   $valid_only Count only valid (paid) orders. Defaults to true.
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'dwc_get_sales_by_date_range',
        title: 'Get sales by date range',
        description: 'Returns total revenue, number of orders and average order value for a date range (YYYY-MM-DD).',
        annotations: new ToolAnnotations(title: 'Get sales by date range', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getSalesByDateRange(
        #[Schema(type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$')]
        string $date_from,
        #[Schema(type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$')]
        string $date_to,
        ?bool $valid_only = true
    ): array {
        if (!self::isDate($date_from) || !self::isDate($date_to)) {
            return ['success' => false, 'message' => 'Dates must be in YYYY-MM-DD format.'];
        }

        $where = 'o.date_add BETWEEN \'' . pSQL($date_from) . ' 00:00:00\' AND \'' . pSQL($date_to) . ' 23:59:59\'';
        if ($valid_only !== false) {
            $where .= ' AND o.valid = 1';
        }

        $sql = 'SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_paid), 0) AS revenue
                FROM `' . _DB_PREFIX_ . 'orders` o
                WHERE ' . $where;

        $rows = \Db::getInstance()->executeS($sql);
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : ['orders' => 0, 'revenue' => 0];

        $orders = (int) $row['orders'];
        $revenue = round((float) $row['revenue'], 2);

        return [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'valid_only' => ($valid_only !== false),
            'orders' => $orders,
            'revenue' => $revenue,
            'average_ticket' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
        ];
    }

    /**
     * Best-selling products in a period (by quantity sold), from valid orders.
     *
     * @param string|null $date_from Start date YYYY-MM-DD. Null = no lower bound.
     * @param string|null $date_to   End date YYYY-MM-DD. Null = no upper bound.
     * @param int         $limit     Max products (1-50). Defaults to 10.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_get_top_selling_products',
        title: 'Get top selling products',
        description: 'Best-selling products by quantity in a period (valid orders); returns quantity sold and revenue per product.',
        annotations: new ToolAnnotations(title: 'Get top selling products', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getTopSellingProducts(
        #[Schema(type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$')]
        ?string $date_from = null,
        #[Schema(type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$')]
        ?string $date_to = null,
        #[Schema(type: 'integer', minimum: 1, maximum: 50)]
        int $limit = 10
    ): array {
        $limit = min(50, max(1, $limit));

        $where = 'o.valid = 1';
        if ($date_from !== null && self::isDate($date_from)) {
            $where .= ' AND o.date_add >= \'' . pSQL($date_from) . ' 00:00:00\'';
        }
        if ($date_to !== null && self::isDate($date_to)) {
            $where .= ' AND o.date_add <= \'' . pSQL($date_to) . ' 23:59:59\'';
        }

        $sql = 'SELECT od.product_id AS id_product, od.product_name AS name, od.product_reference AS reference,
                       SUM(od.product_quantity) AS qty_sold,
                       SUM(od.total_price_tax_incl) AS revenue
                FROM `' . _DB_PREFIX_ . 'order_detail` od
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = od.id_order
                WHERE ' . $where . '
                GROUP BY od.product_id
                ORDER BY qty_sold DESC
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
                'quantity_sold' => (int) $r['qty_sold'],
                'revenue' => round((float) $r['revenue'], 2),
            ];
        }

        return $out;
    }

    /**
     * Abandoned carts: carts with products but no order, updated within the
     * last N days.
     *
     * @param int $days  Look back this many days (1-90). Defaults to 7.
     * @param int $limit Max carts (1-100). Defaults to 20.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_get_abandoned_carts',
        title: 'Get abandoned carts',
        description: 'Carts that contain products but never became an order, updated within the last N days.',
        annotations: new ToolAnnotations(title: 'Get abandoned carts', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getAbandonedCarts(
        #[Schema(type: 'integer', minimum: 1, maximum: 90)]
        int $days = 7,
        #[Schema(type: 'integer', minimum: 1, maximum: 100)]
        int $limit = 20
    ): array {
        $days = min(90, max(1, $days));
        $limit = min(100, max(1, $limit));

        $sql = 'SELECT ca.id_cart, ca.date_upd,
                       CONCAT(c.firstname, " ", c.lastname) AS customer, c.email,
                       (SELECT COALESCE(SUM(cp.quantity), 0) FROM `' . _DB_PREFIX_ . 'cart_product` cp WHERE cp.id_cart = ca.id_cart) AS items
                FROM `' . _DB_PREFIX_ . 'cart` ca
                LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart = ca.id_cart
                LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = ca.id_customer
                WHERE o.id_order IS NULL
                    AND ca.date_upd >= (NOW() - INTERVAL ' . $days . ' DAY)
                    AND EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_product` cp WHERE cp.id_cart = ca.id_cart)
                ORDER BY ca.date_upd DESC
                LIMIT ' . $limit;

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id_cart' => (int) $r['id_cart'],
                'customer' => trim((string) ($r['customer'] ?? '')),
                'email' => (string) ($r['email'] ?? ''),
                'items' => (int) $r['items'],
                'last_update' => (string) $r['date_upd'],
            ];
        }

        return $out;
    }

    private static function isDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
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
