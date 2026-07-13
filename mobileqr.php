<?php
/* 
 * Render the QR code for the scanning page
 */

// Load Dolibarr environment (works whether the module lives in htdocs/ or htdocs/custom/)
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once 'lib/phpqrcode/qrlib.php';
require_once 'lib/swisspayments.lib.php';

global $db, $langs, $user;

// Access control
if (swisspayments_user_socid($user) > 0) {
	// External user
	accessforbidden();
}

if (! swisspayments_user_has_right($user, 'swisspayments', 'invoices', 'create')) accessforbidden();

$actual_host= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// $actual_host= "http://192.168.200.140";
// Encode the login-free mobile scan URL for this pairing token (see mobilescan.php).
$token= GETPOST('t', 'aZ09');
$url= $actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php";
if ($token != '') $url.= "?t=" . urlencode($token);
QRcode::png($url, false, 8, 8);

