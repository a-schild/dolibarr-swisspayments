<?php
/* 
 * Render the QR code for the scanning page
 */
require '../../main.inc.php';
require_once 'lib/phpqrcode/qrlib.php';

global $db, $langs, $user;

// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (! $user->rights->swisspayments->invoices->create) accessforbidden();

$actual_host= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// $actual_host= "http://192.168.200.140";
// Encode the login-free mobile scan URL for this pairing token (see mobilescan.php).
$token= GETPOST('t', 'aZ09');
$url= $actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php";
if ($token != '') $url.= "?t=" . urlencode($token);
QRcode::png($url, false, 8, 8);

