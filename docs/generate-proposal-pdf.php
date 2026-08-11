<?php
/**
 * PDF Generator for Wallet Hierarchy Proposal
 * Creates docs/wallet-hierarchy-proposal.pdf from the HTML proposal.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$htmlFile = __DIR__ . '/wallet-hierarchy-proposal-print.html';
$pdfFile = __DIR__ . '/wallet-hierarchy-proposal.pdf';

if (!is_file($htmlFile)) {
    echo "HTML source file not found: {$htmlFile}";
    exit(1);
}

$html = file_get_contents($htmlFile);

// Force DejaVu Sans for PDF to support the peso sign and other Unicode characters
$html = str_replace('</head>', '<style>* { font-family: "DejaVu Sans", sans-serif !important; }</style></head>', $html);

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultPaperSize', 'A4');
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

file_put_contents($pdfFile, $dompdf->output());

echo "PDF generated successfully: {$pdfFile}\n";
echo "URL: http://localhost/TMS/docs/wallet-hierarchy-proposal.pdf\n";
