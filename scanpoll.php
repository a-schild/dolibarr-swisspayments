<?php

/*
 * Desktop poll endpoint for the mobile-scan pairing (see mobilescan.php).
 *
 * The logged-in desktop polls this with its pairing token; once the phone has posted a
 * scanned payload, it is returned here and createinvoice.php continues automatically.
 */

require '../../main.inc.php';
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
