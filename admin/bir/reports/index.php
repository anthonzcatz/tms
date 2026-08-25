<?php
/**
 * BIR Reports Controller
 * Generate DSR, Monthly Sales, SLS, Alphalist, and 2550M reports
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(__DIR__) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = PosAccess::allowedBranchIds($user);

function birBranchFilter(?int $branchId, array &$params, string $column = 'po.branch_id'): string
{
    global $allowedBranchIds;
    if ($branchId !== null) {
        $params[] = $branchId;
        return ' AND ' . $column . ' = ?';
    }
    if ($allowedBranchIds === null) {
        return '';
    }
    if (!$allowedBranchIds) {
        return ' AND 1 = 0';
    }

    $params = array_merge($params, $allowedBranchIds);
    return ' AND ' . $column . ' IN (' . implode(',', array_fill(0, count($allowedBranchIds), '?')) . ')';
}

function birReportAccessible(array $report): bool
{
    global $user, $allowedBranchIds;
    if ($allowedBranchIds === null) {
        return true;
    }

    $branchId = (int) ($report['branch_id'] ?? 0);
    return ($branchId > 0 && in_array($branchId, $allowedBranchIds, true))
        || ($branchId <= 0 && (int) ($report['generated_by'] ?? 0) === (int) ($user['user_id'] ?? 0));
}

function fetchAccessibleBirReport(int $reportId): ?array
{
    $report = Database::fetch(
        "SELECT r.*, b.branch_name
         FROM bir_reports r
         LEFT JOIN business_branches b ON r.branch_id = b.branch_id
         WHERE r.report_id = ?",
        [$reportId]
    );
    return $report && birReportAccessible($report) ? $report : null;
}

$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

$message = '';
$error = '';
$reportData = null;

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];

        if ($action === 'delete') {
            $rawReportId = $_POST['report_id'] ?? null;
            if ($rawReportId) {
                $reportId = is_numeric($rawReportId) ? (int)$rawReportId : IdEncoder::decode($rawReportId);
                if ($reportId) {
                    if (!fetchAccessibleBirReport($reportId)) {
                        throw new RuntimeException('Report not found or access denied.');
                    }
                    Database::execute("DELETE FROM bir_reports WHERE report_id = ?", [$reportId]);
                    header('Location: ' . BASE_URL . '/admin/bir/reports/?success=' . urlencode('Report deleted successfully!'));
                    exit;
                }
            }
        } else {
            $reportType = $_POST['report_type'] ?? '';
            $branchId = !empty($_POST['branch_id']) ? (int) $_POST['branch_id'] : null;
            if ($branchId !== null) {
                PosAccess::assertBranchAccess($user, $branchId);
            }
            $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
            $dateTo = $_POST['date_to'] ?? date('Y-m-d');

            switch ($reportType) {
                case 'DSR':
                    $reportData = generateDSR($branchId, $dateFrom);
                    break;
                case 'Monthly':
                    $reportData = generateMonthlyReport($branchId, $dateFrom, $dateTo);
                    break;
                case 'SLS':
                    $reportData = generateSLS($branchId, $dateFrom, $dateTo);
                    break;
                case 'Alphalist':
                    $reportData = generateAlphalist($branchId, $dateFrom, $dateTo);
                    break;
                case '2550M':
                    $reportData = generate2550M($branchId, $dateFrom, $dateTo);
                    break;
            }

            if ($reportData) {
                // Save report to database
                Database::execute(
                    "INSERT INTO bir_reports (report_type, report_date, report_period_start, report_period_end, branch_id, generated_by, data_json, status)
                     VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'generated')",
                    [$reportType, $dateFrom, $dateTo, $branchId, $user['user_id'], json_encode($reportData)]
                );
                // Redirect to prevent form resubmission on refresh
                header('Location: ' . BASE_URL . '/admin/bir/reports/?success=' . urlencode($reportType . ' report generated successfully!'));
                exit;
            }
        }
    } catch (Exception $e) {
        // Redirect with error message
        header('Location: ' . BASE_URL . '/admin/bir/reports/?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// Check for success/error messages from redirect
$message = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle view report request
$viewReportId = $_GET['view'] ?? null;
if ($viewReportId) {
    $isAjax = !empty($_GET['ajax']);
    $decodedReportId = is_numeric($viewReportId) ? (int)$viewReportId : IdEncoder::decode($viewReportId);
    if ($decodedReportId) {
        $viewReport = fetchAccessibleBirReport($decodedReportId);
        if ($viewReport) {
            $decoded = json_decode($viewReport['data_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Error decoding report data.']);
                    exit;
                }
                $error = 'Error decoding report data: ' . json_last_error_msg();
            } else {
                if ($isAjax) {
                    // Build period string for subtitle
                    $periodStr = '';
                    if (!empty($decoded['date'])) {
                        $periodStr = date('F d, Y', strtotime($decoded['date']));
                    } elseif (!empty($decoded['period_start'])) {
                        $periodStr = date('M d, Y', strtotime($decoded['period_start'])) . ' – ' . date('M d, Y', strtotime($decoded['period_end']));
                    }
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data'    => $decoded,
                        'meta'    => [
                            'report_type'  => $viewReport['report_type'],
                            'branch_name'  => $viewReport['branch_name'] ?? 'All Branches',
                            'generated_by' => $viewReport['generated_by'] ?? null,
                            'period'       => $periodStr,
                            'status'       => $viewReport['status'],
                        ]
                    ]);
                    exit;
                }
                $reportData = $decoded;
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Report not found.']);
                exit;
            }
            $error = 'Report not found.';
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid report ID.']);
            exit;
        }
        $error = 'Invalid report ID.';
    }
}

// ─── Download helpers ─────────────────────────────────────────────────────────

function birExcelXML(string $title, array $headers, array $rows): string {
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<?mso-application progid=\"Excel.Sheet\"?>\n";
    $xml .= "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
    $xml .= " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
    $xml .= "<Styles>\n";
    $xml .= "  <Style ss:ID=\"H\"><Font ss:Bold=\"1\" ss:Size=\"10\"/><Interior ss:Color=\"#DCE6F1\" ss:Pattern=\"Solid\"/></Style>\n";
    $xml .= "  <Style ss:ID=\"T\"><Font ss:Bold=\"1\" ss:Size=\"12\"/></Style>\n";
    $xml .= "  <Style ss:ID=\"N\"><NumberFormat ss:Format=\"#,##0.00\"/></Style>\n";
    $xml .= "</Styles>\n";
    $xml .= "<Worksheet ss:Name=\"Report\">\n<Table>\n";
    $xml .= "<Row><Cell ss:MergeAcross=\"" . (count($headers)-1) . "\" ss:StyleID=\"T\"><Data ss:Type=\"String\">" . $esc($title) . "</Data></Cell></Row>\n";
    $xml .= "<Row>" . implode('', array_map(fn($h) => "<Cell ss:StyleID=\"H\"><Data ss:Type=\"String\">" . $esc($h) . "</Data></Cell>", $headers)) . "</Row>\n";
    foreach ($rows as $row) {
        $xml .= '<Row>';
        foreach ($row as $cell) {
            $isNum = is_numeric($cell) && !preg_match('/^0\d/', (string)$cell);
            $type  = $isNum ? 'Number' : 'String';
            $sid   = $isNum ? ' ss:StyleID="N"' : '';
            $xml  .= "<Cell{$sid}><Data ss:Type=\"{$type}\">" . $esc($cell) . "</Data></Cell>";
        }
        $xml .= "</Row>\n";
    }
    $xml .= "</Table>\n</Worksheet>\n</Workbook>";
    return $xml;
}

function birPrintHTML(string $reportType, string $reportDate, array $reportData, array $systemSettings = []): string {
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_HTML5, 'UTF-8');
    $fmt = fn($n) => 'PHP ' . number_format((float)$n, 2);

    $systemName    = $systemSettings['company_name']    ?? 'TMS';
    $companyAddress= $systemSettings['company_address'] ?? '';
    $tin           = $systemSettings['company_tin']     ?? '';

    // ── Logo: read from disk and embed as base64 so dompdf always renders it ──
    $logoTag  = '';
    $logoDir  = dirname(dirname(dirname(__DIR__))) . '/api/images/logo/';
    $logoFile = '';
    // Try the known logo first, then fallback to any PNG in the folder
    $knownLogo = $logoDir . 'logo_1779670787_4364a51c.png';
    if (file_exists($knownLogo)) {
        $logoFile = $knownLogo;
    } else {
        $candidates = glob($logoDir . '*.{png,jpg,jpeg,gif}', GLOB_BRACE);
        if (!empty($candidates)) $logoFile = $candidates[0];
    }
    if ($logoFile && file_exists($logoFile)) {
        $ext      = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
        $mime     = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
        $b64      = base64_encode(file_get_contents($logoFile));
        $logoTag  = '<img src="data:' . $mime . ';base64,' . $b64 . '" style="max-height:56px; max-width:120px;">';
    }

    // ── Period line ──────────────────────────────────────────────────────────
    $periodLine = '';
    if (!empty($reportData['period_start']) && !empty($reportData['period_end'])) {
        $periodLine = date('F d, Y', strtotime($reportData['period_start']))
                    . ' &ndash; '
                    . date('F d, Y', strtotime($reportData['period_end']));
    } elseif (!empty($reportData['date'])) {
        $periodLine = date('F d, Y', strtotime($reportData['date']));
    }

    // ── Report title map ─────────────────────────────────────────────────────
    $titles = [
        'DSR'       => 'Daily Sales Report',
        'Monthly'   => 'Monthly Sales Report',
        'SLS'       => 'Summary List of Sales',
        'Alphalist' => 'Alphalist of Purchases',
        '2550M'     => 'VAT Return (BIR Form 2550M)',
    ];
    $fullTitle = $titles[$reportType] ?? $reportType . ' Report';

    // ── Summary cards ────────────────────────────────────────────────────────
    $summaryHtml = '';
    if ($reportType === 'DSR' && !empty($reportData['summary'])) {
        $s = $reportData['summary'];
        $summaryHtml = buildSumCards([
            ['label'=>'Total Transactions', 'value'=>number_format($s['total_transactions']??0)],
            ['label'=>'Total Sales',        'value'=>$fmt($s['total_sales']??0)],
            ['label'=>'Total VAT',          'value'=>$fmt($s['total_vat']??0)],
            ['label'=>'Cancelled/Refunded', 'value'=>number_format(($s['cancelled_count']??0)+($s['refunded_count']??0))],
        ]);
    } elseif ($reportType === 'Monthly' && !empty($reportData['summary'])) {
        $s = $reportData['summary'];
        $summaryHtml = buildSumCards([
            ['label'=>'Total Transactions', 'value'=>number_format($s['total_transactions']??0)],
            ['label'=>'Total Sales',        'value'=>$fmt($s['total_sales']??0)],
            ['label'=>'Total VAT',          'value'=>$fmt($s['total_vat']??0)],
            ['label'=>'Taxable Sales',      'value'=>$fmt($s['taxable_sales']??0)],
        ]);
    } elseif (in_array($reportType, ['SLS','Alphalist','2550M'])) {
        $s     = $reportData['summary'] ?? [];
        $cards = [];
        if (!empty($s['total_transactions'])) $cards[] = ['label'=>'Transactions',     'value'=>number_format($s['total_transactions'])];
        if (!empty($s['total_sales']))        $cards[] = ['label'=>'Total Sales',       'value'=>$fmt($s['total_sales'])];
        if (!empty($s['total_vat']))          $cards[] = ['label'=>'Total VAT',         'value'=>$fmt($s['total_vat'])];
        if (!empty($s['total_purchases']))    $cards[] = ['label'=>'Total Purchases',   'value'=>$fmt($s['total_purchases'])];
        if (!empty($s['total_vat_input']))    $cards[] = ['label'=>'VAT Input',         'value'=>$fmt($s['total_vat_input'])];
        if (!empty($reportData['output_vat']['output_vat'])) $cards[] = ['label'=>'Output VAT', 'value'=>$fmt($reportData['output_vat']['output_vat'])];
        if ($cards) $summaryHtml = buildSumCards($cards);
    }

    // ── Detail tables ────────────────────────────────────────────────────────
    $tableHtml = '';
    if ($reportType === 'DSR') {
        if (!empty($reportData['hourly_sales'])) {
            $tableHtml .= buildHTMLTable('Hourly Sales Breakdown',
                ['Hour', 'Transactions', 'Total Sales'],
                array_map(fn($r) => [
                    str_pad((string)($r['hour']??0),2,'0',STR_PAD_LEFT).':00',
                    number_format($r['transaction_count']??0),
                    $fmt($r['total_sales']??0),
                ], $reportData['hourly_sales']));
        }
        if (!empty($reportData['payment_breakdown'])) {
            $tableHtml .= buildHTMLTable('Payment Method Breakdown',
                ['Payment Method', 'Transactions', 'Total Amount'],
                array_map(fn($r) => [
                    $r['payment_method']??'',
                    number_format($r['transaction_count']??0),
                    $fmt($r['total_amount']??0),
                ], $reportData['payment_breakdown']));
        }
    } elseif ($reportType === 'Monthly' && !empty($reportData['daily_breakdown'])) {
        $tableHtml .= buildHTMLTable('Daily Breakdown',
            ['Date', 'Transactions', 'Total Sales', 'VAT Amount'],
            array_map(fn($r) => [
                date('M d, Y', strtotime($r['date']??'')),
                number_format($r['transaction_count']??0),
                $fmt($r['total_sales']??0),
                $fmt($r['vat_amount']??0),
            ], $reportData['daily_breakdown']));
    } elseif ($reportType === 'SLS' && !empty($reportData['transactions'])) {
        $tableHtml .= buildHTMLTable('List of Sales Transactions',
            ['Date', 'Order Code', 'Branch', 'Total', 'VAT', 'VAT Type', 'Status', 'OR Number'],
            array_map(fn($r) => [
                date('M d, Y H:i', strtotime($r['created_at']??'')),
                $r['order_code']??'',
                $r['branch_name']??'',
                $fmt($r['grand_total']??0),
                $fmt($r['vat_amount']??0),
                $r['vat_type']??'',
                ucfirst($r['status']??''),
                $r['or_full_number']??'-',
            ], $reportData['transactions']));
    } elseif ($reportType === 'Alphalist' && !empty($reportData['purchases'])) {
        $tableHtml .= buildHTMLTable('Alphalist of Disbursements / Purchases',
            ['Date', 'Code', 'Type', 'Bank / Account', 'Amount', 'VAT Input', 'Remarks'],
            array_map(fn($r) => [
                date('M d, Y', strtotime($r['created_at']??'')),
                $r['txn_code']??'',
                $r['txn_type']??'',
                trim(($r['bank_name']??'').' '.($r['account_name']??'')),
                $fmt($r['amount']??0),
                $fmt((float)($r['amount']??0)/1.12*0.12),
                $r['remarks']??'-',
            ], $reportData['purchases']));
    } elseif ($reportType === '2550M' && !empty($reportData['vat_breakdown'])) {
        $tableHtml .= buildHTMLTable('VAT Breakdown',
            ['VAT Type', 'Transactions', 'Total Sales', 'VAT Amount', 'Taxable Amount'],
            array_map(fn($r) => [
                $r['vat_type']??'',
                number_format($r['transaction_count']??0),
                $fmt($r['total_sales']??0),
                $fmt($r['vat_amount']??0),
                $fmt($r['taxable_amount']??0),
            ], $reportData['vat_breakdown']));
    }

    $now     = date('F d, Y \a\t h:i A');
    $birAccr = $systemSettings['bir_accreditation_number'] ?? '';
    $birPerm = $systemSettings['bir_permit_number'] ?? '';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$esc($fullTitle)}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #1a1a1a; background: #fff; }

  /* ── Header band ───────────────────────────── */
  .hdr-band { background-color: #1a3a6c; width: 100%; padding: 0; margin-bottom: 0; }
  .hdr-band-inner { padding: 10px 16px; }
  .hdr-main { width: 100%; border-collapse: collapse; }
  .hdr-main td { vertical-align: middle; padding: 0; }
  .co-name { font-size: 14pt; font-weight: 700; color: #ffffff; line-height: 1.2; }
  .co-sub  { font-size: 7.5pt; color: #c8d8f0; margin-top: 2px; }
  .rpt-badge { background: #ffffff; color: #1a3a6c; font-size: 11pt; font-weight: 700;
               text-transform: uppercase; padding: 5px 12px; border-radius: 3px;
               text-align: center; display: inline-block; }
  .rpt-period-band { font-size: 8pt; color: #c8d8f0; text-align: right; margin-top: 4px; }

  /* ── Sub-header (BIR info bar) ─────────────── */
  .info-bar { width: 100%; border-collapse: collapse; background: #f0f4fa;
              border-bottom: 2px solid #1a3a6c; margin-bottom: 12px; }
  .info-bar td { font-size: 7.5pt; color: #333; padding: 5px 12px; border-right: 1px solid #d0daea; }
  .info-bar td:last-child { border-right: none; }
  .info-bar .lbl { font-weight: 700; color: #1a3a6c; display: block; }

  /* ── Summary cards ─────────────────────────── */
  .sum-row { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .sum-card { border: 1px solid #c5d5e8; padding: 8px 10px; text-align: center;
              background: #f7f9fc; border-top: 3px solid #1a3a6c; }
  .sum-card .lbl { font-size: 6.5pt; color: #6c757d; text-transform: uppercase; letter-spacing: 0.3px; }
  .sum-card .val { font-size: 10.5pt; font-weight: 700; color: #1a3a6c; margin-top: 3px; }

  /* ── Section heading ───────────────────────── */
  .sec-title { font-size: 8.5pt; font-weight: 700; text-transform: uppercase;
               letter-spacing: 0.5px; color: #fff; background: #2c5282;
               padding: 4px 8px; margin: 14px 0 0; }

  /* ── Data table ────────────────────────────── */
  table.data-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  table.data-tbl th { background: #dce6f1; font-size: 7pt; font-weight: 700;
                      padding: 4px 6px; text-align: left; border: 1px solid #a0b4cc;
                      color: #1a3a6c; }
  table.data-tbl td { font-size: 8pt; padding: 4px 6px; border: 1px solid #d0d9e4; vertical-align: top; }
  table.data-tbl tr.even td { background: #f2f6fb; }
  table.data-tbl td.num { text-align: right; }

  /* ── Footer ────────────────────────────────── */
  .ftr { margin-top: 28px; border-top: 1px solid #ccc; padding-top: 10px; }
  .sig-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .sig-table td { width: 33%; text-align: center; padding: 0 14px; }
  .sig-line { border-bottom: 1px solid #555; height: 24px; margin-bottom: 4px; }
  .sig-lbl  { font-size: 7.5pt; color: #444; }
  .gen-info { font-size: 6.5pt; color: #888; text-align: center; margin-top: 8px; }
</style>
</head>
<body>

<!-- ── Header band ── -->
<table class="hdr-main" style="width:100%; background:#1a3a6c; padding:10px 16px;">
  <tr>
    <td width="15%" style="padding:8px 12px 8px 12px; vertical-align:middle;">
      {$logoTag}
    </td>
    <td width="55%" style="padding:8px 4px; vertical-align:middle;">
      <div class="co-name">{$esc($systemName)}</div>
      <div class="co-sub">{$esc($companyAddress)}</div>
      <div class="co-sub">TIN: {$esc($tin)}</div>
    </td>
    <td width="30%" style="padding:8px 12px; vertical-align:middle; text-align:right;">
      <div class="rpt-badge">{$esc($fullTitle)}</div>
      <div class="rpt-period-band">{$periodLine}</div>
    </td>
  </tr>
</table>

<!-- ── Info bar ── -->
<table class="info-bar">
  <tr>
    <td width="30%"><span class="lbl">Report Type</span>{$esc($reportType)}</td>
    <td width="40%"><span class="lbl">Period</span>{$periodLine}</td>
    <td width="30%"><span class="lbl">Generated</span>{$now}</td>
  </tr>
  <tr>
    <td><span class="lbl">BIR Accreditation No.</span>{$esc($birAccr ?: 'N/A')}</td>
    <td><span class="lbl">BIR Permit No.</span>{$esc($birPerm ?: 'N/A')}</td>
    <td><span class="lbl">Company TIN</span>{$esc($tin ?: 'N/A')}</td>
  </tr>
</table>

{$summaryHtml}
{$tableHtml}

<!-- ── Footer / signatures ── -->
<div class="ftr">
  <table class="sig-table">
    <tr>
      <td><div class="sig-line"></div><div class="sig-lbl">Prepared by</div></td>
      <td><div class="sig-line"></div><div class="sig-lbl">Verified by</div></td>
      <td><div class="sig-line"></div><div class="sig-lbl">Approved by</div></td>
    </tr>
  </table>
  <div class="gen-info">
    {$esc($systemName)} &bull; BIR Accredited Computerized Accounting System &bull; {$now}
  </div>
</div>

</body></html>
HTML;
}

function buildSumCards(array $cards): string {
    $cols = count($cards);
    $width = $cols > 0 ? round(100 / $cols) . '%' : '25%';
    $html = '<table class="sum-row"><tr>';
    foreach ($cards as $c) {
        $html .= '<td width="' . $width . '" class="sum-card">';
        $html .= '<div class="lbl">' . htmlspecialchars($c['label']) . '</div>';
        $html .= '<div class="val">' . htmlspecialchars($c['value']) . '</div>';
        $html .= '</td>';
    }
    return $html . '</tr></table>';
}

function buildHTMLTable(string $heading, array $headers, array $rows): string {
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_HTML5, 'UTF-8');
    $html = '<div class="sec-title">' . $esc($heading) . '</div>';
    $html .= '<table class="data-tbl"><thead><tr>';
    foreach ($headers as $h) $html .= '<th>' . $esc($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $i => $row) {
        $cls = ($i % 2 === 1) ? ' class="even"' : '';
        $html .= '<tr' . $cls . '>';
        foreach ($row as $cell) {
            $isNum = is_string($cell) && preg_match('/^PHP\s[\d,]+\.\d{2}$/', $cell);
            $tdCls = $isNum ? ' class="num"' : '';
            $html .= '<td' . $tdCls . '>' . $esc($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

// ─── Handle download report request ───────────────────────────────────────────
$downloadReportId = $_GET['download'] ?? null;
$downloadFormat = $_GET['format'] ?? 'json';
if ($downloadReportId) {
    $decodedReportId = is_numeric($downloadReportId) ? (int)$downloadReportId : IdEncoder::decode($downloadReportId);
    if ($decodedReportId) {
        $downloadReport = fetchAccessibleBirReport($decodedReportId);
        if ($downloadReport) {
            $reportData = json_decode($downloadReport['data_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Error decoding report data: ' . json_last_error_msg();
            } else {
                $reportDate = $reportData['date'] ?? $downloadReport['report_date'];
                $reportType = $downloadReport['report_type'];
                // Fetch system settings for company info
                $sysSettings = [];
                try {
                    $sysRow = Database::fetch("SELECT company_name, company_address, company_tin, bir_accreditation_number, bir_permit_number FROM system_settings WHERE setting_id = 1");
                    if ($sysRow) $sysSettings = $sysRow;
                } catch (Exception $e) {}

                $dateSlug = date('Y-m-d', strtotime($reportDate));
                switch ($downloadFormat) {

                    // ── Excel (SpreadsheetML XML — opens natively in Excel) ──────
                    case 'excel':
                        $filename = $reportType . '_Report_' . $dateSlug . '.xls';
                        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');

                        $titles = [
                            'DSR'=>'Daily Sales Report (DSR)',
                            'Monthly'=>'Monthly Sales Report',
                            'SLS'=>'Summary List of Sales',
                            'Alphalist'=>'Alphalist of Purchases',
                            '2550M'=>'VAT Return (BIR Form 2550M)',
                        ];
                        $sheetTitle = ($titles[$reportType] ?? $reportType . ' Report') . ' — ' . $dateSlug;

                        if ($reportType === 'DSR') {
                            $headers = ['Hour','Transactions','Total Sales (PHP)'];
                            $rows = array_map(fn($r) => [
                                ($r['hour']??'0') . ':00',
                                $r['transaction_count']??0,
                                $r['total_sales']??0
                            ], $reportData['hourly_sales'] ?? []);
                            // Prepend summary rows
                            $s = $reportData['summary'] ?? [];
                            array_unshift($rows,
                                ['Total Transactions', $s['total_transactions']??0, ''],
                                ['Total Sales',        '',                           $s['total_sales']??0],
                                ['Total VAT',          '',                           $s['total_vat']??0],
                                ['Taxable Sales',      '',                           $s['taxable_sales']??0],
                                ['Cancelled+Refunded', ($s['cancelled_count']??0)+($s['refunded_count']??0), ''],
                                []
                            );
                        } elseif ($reportType === 'Monthly') {
                            $headers = ['Date','Transactions','Total Sales (PHP)','VAT Amount (PHP)'];
                            $rows = array_map(fn($r) => [
                                $r['date']??'',
                                $r['transaction_count']??0,
                                $r['total_sales']??0,
                                $r['vat_amount']??0
                            ], $reportData['daily_breakdown'] ?? []);
                        } elseif ($reportType === 'SLS') {
                            $headers = ['Date','Order Code','Branch','Total (PHP)','VAT (PHP)','VAT Type','Status','OR Number'];
                            $rows = array_map(fn($r) => [
                                $r['created_at']??'',
                                $r['order_code']??'',
                                $r['branch_name']??'',
                                $r['grand_total']??0,
                                $r['vat_amount']??0,
                                $r['vat_type']??'',
                                $r['status']??'',
                                $r['or_full_number']??'-'
                            ], $reportData['transactions'] ?? []);
                        } elseif ($reportType === 'Alphalist') {
                            $headers = ['Date','Transaction Code','Type','Bank/Account','Amount (PHP)','VAT Input (PHP)','Remarks'];
                            $rows = array_map(fn($r) => [
                                $r['created_at']??'',
                                $r['txn_code']??'',
                                $r['txn_type']??'',
                                ($r['bank_name']??'') . ' - ' . ($r['account_name']??''),
                                $r['amount']??0,
                                round((float)($r['amount']??0)/1.12*0.12, 2),
                                $r['remarks']??'-'
                            ], $reportData['purchases'] ?? []);
                        } elseif ($reportType === '2550M') {
                            $headers = ['VAT Type','Transactions','Total Sales (PHP)','VAT Amount (PHP)','Taxable Amount (PHP)'];
                            $rows = array_map(fn($r) => [
                                $r['vat_type']??'',
                                $r['transaction_count']??0,
                                $r['total_sales']??0,
                                $r['vat_amount']??0,
                                $r['taxable_amount']??0
                            ], $reportData['vat_breakdown'] ?? []);
                        } else {
                            $headers = ['Key','Value'];
                            $rows = array_map(fn($k,$v) => [$k, is_array($v) ? json_encode($v) : $v],
                                array_keys($reportData), array_values($reportData));
                        }

                        echo birExcelXML($sheetTitle, $headers, $rows);
                        exit;

                    // ── CSV (plain comma-separated, Excel-compatible) ────────────
                    case 'csv':
                        $filename = $reportType . '_Report_' . $dateSlug . '.csv';
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');
                        $out = fopen('php://output', 'w');
                        // BOM for Excel UTF-8 compatibility
                        fwrite($out, "\xEF\xBB\xBF");
                        if ($reportType === 'DSR') {
                            $s = $reportData['summary'] ?? [];
                            fputcsv($out, ['DSR Report — ' . $reportDate]);
                            fputcsv($out, ['Total Transactions', $s['total_transactions']??0]);
                            fputcsv($out, ['Total Sales', $s['total_sales']??0]);
                            fputcsv($out, ['Total VAT', $s['total_vat']??0]);
                            fputcsv($out, ['Taxable Sales', $s['taxable_sales']??0]);
                            fputcsv($out, ['Cancelled', $s['cancelled_count']??0]);
                            fputcsv($out, ['Refunded', $s['refunded_count']??0]);
                            fputcsv($out, []);
                            fputcsv($out, ['Hour','Transactions','Total Sales']);
                            foreach ($reportData['hourly_sales'] ?? [] as $r)
                                fputcsv($out, [$r['hour'].':00', $r['transaction_count']??0, $r['total_sales']??0]);
                        } elseif ($reportType === 'Monthly') {
                            fputcsv($out, ['Monthly Report — '.$reportData['period_start'].' to '.$reportData['period_end']]);
                            fputcsv($out, ['Date','Transactions','Total Sales','VAT Amount']);
                            foreach ($reportData['daily_breakdown'] ?? [] as $r)
                                fputcsv($out, [$r['date']??'',$r['transaction_count']??0,$r['total_sales']??0,$r['vat_amount']??0]);
                        } elseif ($reportType === 'SLS') {
                            fputcsv($out, ['Date','Order Code','Branch','Total','VAT','VAT Type','Status','OR Number']);
                            foreach ($reportData['transactions'] ?? [] as $r)
                                fputcsv($out, [$r['created_at']??'',$r['order_code']??'',$r['branch_name']??'',$r['grand_total']??0,$r['vat_amount']??0,$r['vat_type']??'',$r['status']??'',$r['or_full_number']??'-']);
                        } elseif ($reportType === 'Alphalist') {
                            fputcsv($out, ['Date','Transaction Code','Type','Bank/Account','Amount','VAT Input','Remarks']);
                            foreach ($reportData['purchases'] ?? [] as $r)
                                fputcsv($out, [$r['created_at']??'',$r['txn_code']??'',$r['txn_type']??'',($r['bank_name']??'').'-'.($r['account_name']??''),$r['amount']??0,round((float)($r['amount']??0)/1.12*0.12,2),$r['remarks']??'-']);
                        } elseif ($reportType === '2550M') {
                            fputcsv($out, ['VAT Type','Transactions','Total Sales','VAT Amount','Taxable Amount']);
                            foreach ($reportData['vat_breakdown'] ?? [] as $r)
                                fputcsv($out, [$r['vat_type']??'',$r['transaction_count']??0,$r['total_sales']??0,$r['vat_amount']??0,$r['taxable_amount']??0]);
                        }
                        fclose($out);
                        exit;

                    // ── PDF — generate real PDF using dompdf ─────────────────
                    case 'pdf':
                        $filename = $reportType . '_Report_' . $dateSlug . '.pdf';
                        $html = birPrintHTML($reportType, $reportDate, $reportData, $sysSettings);
                        $options = new \Dompdf\Options();
                        $options->set('isHtml5ParserEnabled', true);
                        $options->set('isRemoteEnabled', true);
                        $options->set('defaultFont', 'helvetica');
                        $options->set('chroot', realpath(dirname(dirname(dirname(__DIR__)))));
                        $dompdf = new \Dompdf\Dompdf($options);
                        $dompdf->loadHtml($html, 'UTF-8');
                        $dompdf->setPaper('letter', 'portrait');
                        $dompdf->render();
                        $dompdf->stream($filename, ['Attachment' => true]);
                        exit;

                    // ── JSON raw data ────────────────────────────────────────────
                    case 'json':
                    default:
                        $filename = $reportType . '_Report_' . $dateSlug . '.json';
                        header('Content-Type: application/json; charset=utf-8');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Cache-Control: no-cache, must-revalidate');
                        header('Pragma: no-cache');
                        echo json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        exit;
                }
            }
        } else {
            $error = 'Report not found.';
        }
    } else {
        $error = 'Invalid report ID.';
    }
}

// Fetch generated reports
$reportWhere = [];
$reportParams = [];
if ($allowedBranchIds !== null) {
    if (!$allowedBranchIds) {
        $reportWhere[] = '1 = 0';
    } else {
        $reportPlaceholders = [];
        foreach (array_values($allowedBranchIds) as $index => $allowedBranchId) {
            $key = 'report_branch_' . $index;
            $reportPlaceholders[] = ':' . $key;
            $reportParams[$key] = $allowedBranchId;
        }
        $reportParams['report_user_id'] = (int) $user['user_id'];
        $reportWhere[] = '(r.branch_id IN (' . implode(',', $reportPlaceholders) . ') OR (r.branch_id IS NULL AND r.generated_by = :report_user_id))';
    }
}
$reportFilter = $reportWhere ? 'WHERE ' . implode(' AND ', $reportWhere) : '';
$reports = Database::fetchAll(
    "SELECT r.*, b.branch_name, u.username as generated_by_name
     FROM bir_reports r
     LEFT JOIN business_branches b ON r.branch_id = b.branch_id
     LEFT JOIN user_accounts u ON r.generated_by = u.user_id
     {$reportFilter}
     ORDER BY r.created_at DESC
     LIMIT 50",
    $reportParams
);

// Encode report IDs for URL security
foreach ($reports as &$report) {
    $encoded = IdEncoder::encode($report['report_id']);
    $report['encoded_id'] = $encoded;
}
unset($report);

// Fetch branches
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'bir_report_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

// Helper functions for report generation
function generateDSR($branchId, $date) {
    $params = [$date];
    $branchFilter = birBranchFilter($branchId, $params);

    $summary = Database::fetch(
        "SELECT
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(vt.non_taxable_amount), 0) as non_taxable_sales,
            COALESCE(SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_count,
            COALESCE(SUM(CASE WHEN po.status = 'refunded' THEN 1 ELSE 0 END), 0) as refunded_count
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $paymentBreakdown = Database::fetchAll(
        "SELECT
            'Cash' as payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_amount
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $hourlySales = Database::fetchAll(
        "SELECT
            HOUR(po.created_at) as hour,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales
         FROM pos_orders po
         WHERE DATE(po.created_at) = ? AND po.status = 'completed' $branchFilter
         GROUP BY HOUR(po.created_at)
         ORDER BY hour",
        $params
    );

    return [
        'report_type' => 'DSR',
        'date' => $date,
        'summary' => $summary,
        'payment_breakdown' => $paymentBreakdown,
        'hourly_sales' => $hourlySales
    ];
}

function generateMonthlyReport($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = birBranchFilter($branchId, $params);

    $summary = Database::fetch(
        "SELECT
            COUNT(DISTINCT po.order_id) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_sales,
            COALESCE(SUM(vt.non_taxable_amount), 0) as non_taxable_sales
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $dailyBreakdown = Database::fetchAll(
        "SELECT
            DATE(po.created_at) as date,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as vat_amount
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY DATE(po.created_at)
         ORDER BY date",
        $params
    );

    return [
        'report_type' => 'Monthly',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'daily_breakdown' => $dailyBreakdown
    ];
}

function generateSLS($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = birBranchFilter($branchId, $params);

    $transactions = Database::fetchAll(
        "SELECT
            po.order_code,
            po.created_at,
            bb.branch_name,
            po.grand_total,
            vt.vat_amount,
            vt.vat_type,
            po.status,
            orn.or_full_number
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
         LEFT JOIN bir_or_numbers orn ON po.or_number_id = orn.or_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter
         ORDER BY po.created_at DESC",
        $params
    );

    $summary = Database::fetch(
        "SELECT
            COUNT(*) as total_transactions,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as total_vat
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? $branchFilter",
        $params
    );

    return [
        'report_type' => 'SLS',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => $summary,
        'transactions' => $transactions
    ];
}

function generate2550M($branchId, $dateFrom, $dateTo) {
    $params = [$dateFrom, $dateTo];
    $branchFilter = birBranchFilter($branchId, $params);

    $outputVat = Database::fetch(
        "SELECT COALESCE(SUM(vt.vat_amount), 0) as output_vat
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter",
        $params
    );

    $vatBreakdown = Database::fetchAll(
        "SELECT
            vt.vat_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(po.grand_total), 0) as total_sales,
            COALESCE(SUM(vt.vat_amount), 0) as vat_amount,
            COALESCE(SUM(vt.taxable_amount), 0) as taxable_amount
         FROM pos_orders po
         LEFT JOIN bir_vat_transactions vt ON po.order_id = vt.order_id
         WHERE DATE(po.created_at) BETWEEN ? AND ? AND po.status = 'completed' $branchFilter
         GROUP BY vt.vat_type",
        $params
    );

    return [
        'report_type' => '2550M',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'output_vat' => $outputVat,
        'vat_breakdown' => $vatBreakdown
    ];
}

function generateAlphalist($branchId, $dateFrom, $dateTo) {
    // Alphalist of Purchases - based on bank transactions (disbursements/expenses)
    $params = [$dateFrom, $dateTo];
    $branchFilter = birBranchFilter($branchId, $params, 'ba.branch_id');

    // Get all disbursement transactions (purchases/expenses)
    $purchases = Database::fetchAll(
        "SELECT
            bt.bank_txn_id,
            bt.txn_code,
            bt.txn_type,
            bt.amount,
            bt.remarks,
            bt.created_at,
            ba.bank_name,
            ba.account_name,
            ba.account_number,
            u.username as created_by_name
         FROM bank_transactions bt
         LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.bank_account_id
         LEFT JOIN user_accounts u ON bt.created_by = u.user_id
         WHERE bt.txn_type IN ('DISBURSEMENT', 'ADJUSTMENT')
           AND bt.direction = 'OUT'
           AND DATE(bt.created_at) BETWEEN ? AND ?
           $branchFilter
         ORDER BY bt.created_at ASC",
        $params
    );

    // Calculate totals
    $totalPurchases = 0;
    $totalVatInput = 0;
    $totalNonVat = 0;

    foreach ($purchases as $purchase) {
        $totalPurchases += floatval($purchase['amount']);
        // Assume 12% VAT input for disbursements (can be refined with VAT tracking)
        $vatInput = floatval($purchase['amount']) / 1.12 * 0.12;
        $totalVatInput += $vatInput;
        $totalNonVat += floatval($purchase['amount']) - $vatInput;
    }

    return [
        'report_type' => 'Alphalist',
        'period_start' => $dateFrom,
        'period_end' => $dateTo,
        'summary' => [
            'total_purchases' => $totalPurchases,
            'total_vat_input' => $totalVatInput,
            'total_non_vat' => $totalNonVat,
            'transaction_count' => count($purchases)
        ],
        'purchases' => $purchases
    ];
}

$viewData = [
    'reports' => $reports,
    'branches' => $branches,
    'message' => $message,
    'error' => $error,
    'reportData' => $reportData,
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
