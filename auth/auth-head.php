<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

// Fetch system settings for branding
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'Falcon', ENT_QUOTES, 'UTF-8');
$companyAbbreviation = htmlspecialchars($systemSettings['company_abbreviation'] ?? '', ENT_QUOTES, 'UTF-8');
$companyName = htmlspecialchars($systemSettings['company_name'] ?? '', ENT_QUOTES, 'UTF-8');
// Decode HTML entities recursively to handle multiple encoding layers
$companyTaglineRaw = $systemSettings['company_tagline'] ?? '';
$previous = '';
while ($companyTaglineRaw !== $previous) {
    $previous = $companyTaglineRaw;
    $companyTaglineRaw = html_entity_decode($companyTaglineRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
$companyTagline = $companyTaglineRaw;
$systemLogo = $systemSettings['system_logo'] ?? null;

// Validate logo URL to prevent XSS attacks
if ($systemLogo) {
    // Only allow relative URLs starting with / or absolute URLs with http/https
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null; // Invalid URL, use default
    }
}
?>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title><?php echo $systemName ?? 'Falcon'; ?> | Dashboard &amp; Web App Template</title>


    <!-- ===============================================-->
    <!--    Favicons-->
    <!-- ===============================================-->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/favicon.ico">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/resources/assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="<?php echo BASE_URL; ?>/resources/assets/img/favicons/mstile-150x32.png">
    <meta name="theme-color" content="#ffffff">
    <script src="<?php echo BASE_URL; ?>/resources/assets/js/config.js"></script>
    <script src="<?php echo BASE_URL; ?>/resources/vendors/simplebar/simplebar.min.js"></script>


    <!-- ===============================================-->
    <!--    Stylesheets-->
    <!-- ===============================================-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
    <link href="<?php echo BASE_URL; ?>/resources/assets/css/user.css" rel="stylesheet" id="user-style-default">
    <script>
      var isRTL = JSON.parse(localStorage.getItem('isRTL'));
      if (isRTL) {
        var linkDefault = document.getElementById('style-default');
        var userLinkDefault = document.getElementById('user-style-default');
        linkDefault.setAttribute('disabled', true);
        userLinkDefault.setAttribute('disabled', true);
        document.querySelector('html').setAttribute('dir', 'rtl');
      } else {
        var linkRTL = document.getElementById('style-rtl');
        var userLinkRTL = document.getElementById('user-style-rtl');
        linkRTL.setAttribute('disabled', true);
        userLinkRTL.setAttribute('disabled', true);
      }
    </script>
  </head>
