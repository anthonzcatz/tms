<?php
/**
 * Real data feed for the navbar search dropdown.
 * Renders categories using the existing Falcon search list markup.
 */

try {
    $baseUrl = rtrim(BASE_URL ?? '', '/');

    function navbarSearchSql($sql, $params = []) {
        try {
            return Database::fetchAll($sql, $params) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    function escapeNav($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    function navItem($url, $title, $subtitle = '', $icon = 'fas fa-circle') {
        return '<a class="dropdown-item fs-10 px-x1 py-2 navbar-search-real" href="' . escapeNav($url) . '">' .
               '  <div class="d-flex align-items-center">' .
               '    <span class="' . escapeNav($icon) . ' me-2 text-500 fs-11"></span>' .
               '    <div class="flex-1">' .
               '      <div class="fw-normal title">' . $title . '</div>' .
               ($subtitle ? '<p class="fs-11 mb-0 text-600">' . escapeNav($subtitle) . '</p>' : '') .
               '    </div>' .
               '  </div>' .
               '</a>';
    }

    function sectionHeader($label) {
        return '<h6 class="dropdown-header fw-medium text-uppercase px-x1 fs-11 pt-0 pb-2">' . escapeNav($label) . '</h6>';
    }

    $sections = [];

    // 1. Recently Browsed — tracked from session, fallback to permissions
    $recent = $_SESSION['tms_recent_pages'] ?? [];
    if (empty($recent)) {
        $permissions = navbarSearchSql(
            "SELECT permission_name, menu_url, module_name
             FROM permissions
             WHERE is_menu_item = 1 AND menu_url IS NOT NULL AND menu_url != ''
             ORDER BY menu_order, permission_name
             LIMIT 5"
        );
        $recent = [];
        foreach ($permissions as $p) {
            $recent[] = [
                'url'   => $baseUrl . '/' . ltrim($p['menu_url'], '/'),
                'title' => $p['permission_name'],
                'icon'  => 'fas fa-file-alt',
            ];
        }
    }
    if (!empty($recent)) {
        $html = sectionHeader('Recently Browsed');
        foreach ($recent as $p) {
            $html .= navItem($p['url'], $p['title'], '', $p['icon'] ?? 'fas fa-file-alt');
        }
        $sections[] = $html;
    }

    // 2. Suggested Filters — useful quick links
    $suggested = [
        ['label' => 'accounts:', 'title' => 'Accounts Receivable', 'url' => $baseUrl . '/admin/charges', 'color' => 'badge-subtle-warning'],
        ['label' => 'wallet:',   'title' => 'Provider Wallets',    'url' => $baseUrl . '/admin/wallet/provider-wallets/', 'color' => 'badge-subtle-danger'],
        ['label' => 'tickets:',  'title' => 'Latest tickets',      'url' => $baseUrl . '/admin/pos/transactions',  'color' => 'badge-subtle-success'],
        ['label' => 'events:',   'title' => 'Events this month',   'url' => $baseUrl . '/admin/calendar', 'color' => 'badge-subtle-info'],
        ['label' => 'services:', 'title' => 'All service types',   'url' => $baseUrl . '/admin/settings/service-types', 'color' => 'badge-subtle-primary'],
    ];
    $html = sectionHeader('Suggested Filter');
    foreach ($suggested as $f) {
        $html .=
            '<a class="dropdown-item px-x1 py-1 fs-9 navbar-search-real" href="' . escapeNav($f['url']) . '">' .
            '  <div class="d-flex align-items-center">' .
            '    <span class="badge fw-medium text-decoration-none me-2 ' . escapeNav($f['color']) . '">' . escapeNav($f['label']) . '</span>' .
            '    <div class="flex-1 fs-10 title">' . escapeNav($f['title']) . '</div>' .
            '  </div>' .
            '</a>';
    }
    $sections[] = $html;

    // 3. Customers
    $customers = navbarSearchSql(
        "SELECT passenger_id, fullname, mobile_number, email
         FROM passenger_accounts
         ORDER BY fullname
         LIMIT 5"
    );
    if (!empty($customers)) {
        $html = sectionHeader('Customers');
        foreach ($customers as $c) {
            $subtitle = trim(($c['mobile_number'] ?? '') . ' ' . ($c['email'] ?? ''));
            $html .= navItem(
                $baseUrl . '/admin/charges',
                $c['fullname'] ?: 'Customer',
                $subtitle,
                'fas fa-user'
            );
        }
        $sections[] = $html;
    }

    // 4. Tickets / Orders
    $tickets = navbarSearchSql(
        "SELECT transaction_id, ticket_number, transaction_code
         FROM ticket_transactions
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $orders = navbarSearchSql(
        "SELECT order_id, order_code, grand_total
         FROM pos_orders
         ORDER BY created_at DESC
         LIMIT 5"
    );
    if (!empty($tickets) || !empty($orders)) {
        $html = sectionHeader('Transactions');
        foreach ($tickets as $t) {
            $html .= navItem(
                $baseUrl . '/admin/pos/transactions',
                $t['ticket_number'] ?: $t['transaction_code'],
                'Ticket',
                'fas fa-ticket-alt'
            );
        }
        foreach ($orders as $o) {
            $html .= navItem(
                $baseUrl . '/admin/pos/transactions',
                $o['order_code'],
                'Order ₱' . number_format((float) $o['grand_total'], 2),
                'fas fa-shopping-cart'
            );
        }
        $sections[] = $html;
    }

    // 5. Members — employees
    $navbarEmployees = navbarSearchSql(
        "SELECT emp_id, first_name, last_name
         FROM employees
         ORDER BY last_name, first_name
         LIMIT 5"
    );
    if (!empty($navbarEmployees)) {
        $html = sectionHeader('Members');
        foreach ($navbarEmployees as $e) {
            $name = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
            $html .=
                '<a class="dropdown-item px-x1 py-2 navbar-search-real" href="' . escapeNav($baseUrl . '/admin/settings/employees') . '">' .
                '  <div class="d-flex align-items-center">' .
                '    <div class="avatar avatar-l me-2">' .
                '      <div class="avatar-name rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center fs-10" style="width:2rem;height:2rem;">' .
                       escapeNav(strtoupper(substr($name, 0, 1))) .
                '      </div>' .
                '    </div>' .
                '    <div class="flex-1">' .
                '      <h6 class="mb-0 title">' . escapeNav($name) . '</h6>' .
                '      <p class="fs-11 mb-0 d-flex">Employee #' . escapeNav((string) $e['emp_id']) . '</p>' .
                '    </div>' .
                '  </div>' .
                '</a>';
        }
        $sections[] = $html;
    }

    echo implode('<hr class="text-200 dark__text-900" />', $sections);

} catch (Exception $e) {
    // Fail silently: the original fallback search markup will still render.
}
