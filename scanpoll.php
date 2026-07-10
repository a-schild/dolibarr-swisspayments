<?php

/*
 * Desktop poll endpoint for the mobile-scan pairing (see mobilescan.php).
 *
 * The logged-in desktop polls this with its pairing token; once the phone has posted a
 * scanned payload, it is returned here and createinvoice.php continues automatically.
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
require_once 'lib/swisspayments.lib.php';

global $user;

header('Content-Type: application/json');

// Only the logged-in internal user who created the pairing may read its payload.
if ($user->societe_id > 0 || empty($user->rights->swisspayments->invoices->create)) {
	echo json_encode(array('payload' => null, 'error' => 'forbidden'));
	exit;
}

$token = GETPOST('t', 'aZ09');
$data = swisspayments_scan_read($token);
if ($data === null) {
	echo json_encode(array('payload' => null, 'expired' => true));
	exit;
}
if ((int) ($data['fk_user'] ?? 0) !== (int) $user->id) {
	echo json_encode(array('payload' => null));
	exit;
}

echo json_encode(array('payload' => (isset($data['payload']) && $data['payload'] !== '') ? $data['payload'] : null));
