<?php
/**
 * DWC PrestaShop MCP - CustomerTools.
 *
 * @author  DWC
 * @license MIT
 */

namespace DWC\PrestaMcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Read-only customer queries.
 */
class CustomerTools
{
    /**
     * List customers. With an email (or fragment), searches by email; without
     * it, returns the most recently registered customers.
     *
     * @param string|null $email Email or fragment to search for. Null = most recent.
     * @param int         $limit Max customers (1-100). Defaults to 20.
     *
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'dwc_get_customers',
        title: 'Get customers',
        description: 'Search customers by email, or list the most recently registered ones; returns id, name, email, registration date and active status.',
        annotations: new ToolAnnotations(title: 'Get customers', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false)
    )]
    public function getCustomers(
        #[Schema(type: 'string', maxLength: 128)]
        ?string $email = null,
        #[Schema(type: 'integer', minimum: 1, maximum: 100)]
        int $limit = 20
    ): array {
        $limit = min(100, max(1, $limit));

        $where = 'c.deleted = 0';
        if ($email !== null && trim($email) !== '') {
            $where .= ' AND c.email LIKE \'%' . pSQL(trim($email), true) . '%\'';
        }

        $sql = 'SELECT c.id_customer, c.firstname, c.lastname, c.email, c.date_add, c.active, c.newsletter
                FROM `' . _DB_PREFIX_ . 'customer` c
                WHERE ' . $where . '
                ORDER BY c.date_add DESC
                LIMIT ' . $limit;

        $rows = \Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id_customer' => (int) $r['id_customer'],
                'name' => trim(((string) $r['firstname']) . ' ' . ((string) $r['lastname'])),
                'email' => (string) $r['email'],
                'registered' => (string) $r['date_add'],
                'active' => (bool) $r['active'],
                'newsletter' => (bool) $r['newsletter'],
            ];
        }

        return $out;
    }
}
