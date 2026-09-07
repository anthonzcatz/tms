<?php
$activeWalletModule = $activeWalletModule ?? '';
$walletGroup = ['ticket-providers', 'provider-wallets', 'wallet-transactions', 'provider-service-fees'];
$paymentGroup = ['charges', 'cashier-charges', 'bank-confirmations', 'refund-confirmations', 'refund-history'];
$settingsGroup = ['service-types', 'payment-methods', 'bank-accounts', 'discount-types', 'accommodation-types'];
$activeGroup = in_array($activeWalletModule, $walletGroup) ? 'wallet' : (in_array($activeWalletModule, $paymentGroup) ? 'payments' : (in_array($activeWalletModule, $settingsGroup) ? 'settings' : 'wallet'));
?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="text-muted small fw-bold me-1">POS Setup:</span>
      
      <?php if ($activeGroup === 'wallet'): ?>
      <!-- Wallet Management Group -->
      <a href="<?php echo BASE_URL; ?>/admin/wallet/ticket-providers" class="btn btn-sm btn-<?php echo $activeWalletModule === 'ticket-providers' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-plane me-1"></span>Providers</a>
      <a href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets" class="btn btn-sm btn-<?php echo $activeWalletModule === 'provider-wallets' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-wallet me-1"></span>Wallets</a>
      <a href="<?php echo BASE_URL; ?>/admin/wallet/wallet-transactions" class="btn btn-sm btn-<?php echo $activeWalletModule === 'wallet-transactions' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-exchange-alt me-1"></span>Transactions</a>
      <a href="<?php echo BASE_URL; ?>/admin/wallet/provider-service-fees" class="btn btn-sm btn-<?php echo $activeWalletModule === 'provider-service-fees' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-percentage me-1"></span>Fees</a>
      <?php elseif ($activeGroup === 'payments'): ?>
      <!-- Payment Operations Group -->
      <a href="<?php echo BASE_URL; ?>/admin/charges" class="btn btn-sm btn-<?php echo $activeWalletModule === 'charges' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-file-invoice-dollar me-1"></span>Charges</a>
      <a href="<?php echo BASE_URL; ?>/admin/charges/cashiers" class="btn btn-sm btn-<?php echo $activeWalletModule === 'cashier-charges' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-user-tag me-1"></span>Cashier Charges</a>
      <a href="<?php echo BASE_URL; ?>/admin/bank-confirmations" class="btn btn-sm btn-<?php echo $activeWalletModule === 'bank-confirmations' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-university me-1"></span>Bank</a>
      <a href="<?php echo BASE_URL; ?>/admin/refund-confirmations" class="btn btn-sm btn-<?php echo $activeWalletModule === 'refund-confirmations' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-undo me-1"></span>Refunds</a>
      <a href="<?php echo BASE_URL; ?>/admin/refund-confirmations/history" class="btn btn-sm btn-<?php echo $activeWalletModule === 'refund-history' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-history me-1"></span>Refund History</a>
      <?php elseif ($activeGroup === 'settings'): ?>
      <!-- POS Settings Group -->
      <a href="<?php echo BASE_URL; ?>/admin/settings/service-types" class="btn btn-sm btn-<?php echo $activeWalletModule === 'service-types' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-concierge-bell me-1"></span>Services</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/payment-methods" class="btn btn-sm btn-<?php echo $activeWalletModule === 'payment-methods' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-credit-card me-1"></span>Methods</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/bank-accounts" class="btn btn-sm btn-<?php echo $activeWalletModule === 'bank-accounts' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-piggy-bank me-1"></span>Accounts</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/discount-types" class="btn btn-sm btn-<?php echo $activeWalletModule === 'discount-types' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-tags me-1"></span>Discounts</a>
      <a href="<?php echo BASE_URL; ?>/admin/settings/accommodation-types" class="btn btn-sm btn-<?php echo $activeWalletModule === 'accommodation-types' ? 'primary' : 'falcon-default'; ?>"><span class="fas fa-bed me-1"></span>Accommodation</a>
      <?php endif; ?>
      
      <!-- Group Switcher -->
      <div class="dropdown ms-auto">
        <button class="btn btn-sm btn-falcon-default dropdown-toggle" type="button" id="posGroupSwitcher" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="fas fa-th-large me-1"></span>Other Groups
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="posGroupSwitcher">
          <li><h6 class="dropdown-header">Wallet</h6></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/wallet/ticket-providers">Providers</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/wallet/provider-wallets">Wallets</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/wallet/wallet-transactions">Transactions</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/wallet/provider-service-fees">Fees</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><h6 class="dropdown-header">Payments</h6></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/charges">Charges</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/charges/cashiers">Cashier Charges</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/bank-confirmations">Bank</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/refund-confirmations">Refunds</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/refund-confirmations/history">Refund History</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><h6 class="dropdown-header">Settings</h6></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/service-types">Services</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/payment-methods">Methods</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/bank-accounts">Accounts</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/discount-types">Discounts</a></li>
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings/accommodation-types">Accommodation</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>
