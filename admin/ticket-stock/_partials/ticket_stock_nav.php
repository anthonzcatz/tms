<?php
$activeTicketStockModule = $activeTicketStockModule ?? '';
$links = [
    'balances' => ['label' => 'Balances', 'icon' => 'fa-warehouse', 'url' => '/admin/ticket-stock/balances'],
    'requests' => ['label' => 'Requests', 'icon' => 'fa-file-alt', 'url' => '/admin/ticket-stock/requests'],
    'movements' => ['label' => 'Movements', 'icon' => 'fa-exchange-alt', 'url' => '/admin/ticket-stock/movements'],
    'discrepancies' => ['label' => 'Discrepancies', 'icon' => 'fa-exclamation-triangle', 'url' => '/admin/ticket-stock/discrepancies'],
    'variants' => ['label' => 'Variants', 'icon' => 'fa-palette', 'url' => '/admin/ticket-stock/variants'],
];
if (Auth::can('MANAGE_TICKET_STOCK_ACCESS')) {
    $links['access'] = ['label' => 'Access', 'icon' => 'fa-user-shield', 'url' => '/admin/ticket-stock/access'];
}
?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small fw-bold me-1">Ticket Stock:</span>
      <?php foreach ($links as $key => $link): ?>
        <a href="<?php echo BASE_URL . $link['url']; ?>"
           class="btn btn-sm btn-<?php echo $activeTicketStockModule === $key ? 'primary' : 'falcon-default'; ?>">
          <span class="fas <?php echo $link['icon']; ?> me-1"></span><?php echo htmlspecialchars($link['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
