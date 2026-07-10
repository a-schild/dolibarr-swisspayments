<?php

/*
 * Login-free mobile QR-bill scanner, paired to a desktop session by a one-time token.
 *
 * The desktop (createinvoice.php) shows a QR code that opens this page on the phone as
 * mobilescan.php?t=<token>. The phone scans the QR-bill and posts the decoded payload
 * back here; the desktop polls scanpoll.php and continues the process automatically -
 * so the phone needs no Dolibarr login and the desktop behaves like a USB scanner fed it.
 *
 * Scanner library: https://github.com/mebjas/html5-qrcode
 */

// Public page: no Dolibarr login / CSRF token (the pairing token authorises the scan).
if (!defined('NOLOGIN')) define('NOLOGIN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOIPCHECK')) define('NOIPCHECK', '1');
if (!defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

require '../../main.inc.php';
require_once 'lib/swisspayments.lib.php';

$token = GETPOST('t', 'aZ09');
$data = swisspayments_scan_read($token);
$valid = ($data !== null);

// Receiver: store the scanned payload for the desktop to pick up.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	if (!$valid) {
		echo json_encode(array('ok' => false, 'error' => 'expired'));
		exit;
	}
	// Raw payload (the QR-bill text, may contain newlines) - stored as-is, never executed.
	$data['payload'] = isset($_POST['payload']) ? (string) $_POST['payload'] : '';
	$data['date_scan'] = time();
	file_put_contents(swisspayments_scan_file($token), json_encode($data));
	echo json_encode(array('ok' => true));
	exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Swisspayments Scan</title>
  <script type="text/javascript" src="js/html5-qrcode.min.js"></script>
  <style>
    body { font-family: sans-serif; margin: 0; padding: 12px; color: #333; }
    h1 { font-size: 1.2em; }
    #reader { width: 100%; max-width: 480px; margin: 0 auto; }
    #status { font-size: 1.1em; text-align: center; padding: 16px; }
    .ok { color: #178017; }
    .err { color: #cc0000; }
    input[type=file] { margin-top: 14px; }
  </style>
</head>
<body>
<?php if (!$valid) { ?>
  <div id="status" class="err">Der Scan-Link ist ung&uuml;ltig oder abgelaufen.<br>Bitte am Computer einen neuen QR-Code anzeigen.</div>
<?php } else { ?>
  <h1>QR-Rechnung scannen</h1>
  <div id="reader"></div>
  <input type="file" id="qr-input-file" accept="image/*" capture>
  <div id="status"></div>
  <div id="reader-file" style="display:none"></div>
  <script>
    var SCAN_URL = "mobilescan.php?t=<?php echo $token; ?>";
    var sent = false;
    function setStatus(html, cls) {
      var s = document.getElementById("status");
      s.className = cls || "";
      s.innerHTML = html;
    }
    function send(text) {
      if (sent) return;
      sent = true;
      setStatus("&#8987; wird &uuml;bertragen ...");
      var fd = new FormData();
      fd.append("payload", text);
      fetch(SCAN_URL, { method: "POST", body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            setStatus("&#10003; Erfolgreich gescannt!<br>Bitte am Computer fortfahren.", "ok");
          } else {
            sent = false;
            setStatus("Der Scan-Link ist abgelaufen. Bitte am Computer einen neuen QR-Code anzeigen.", "err");
          }
        })
        .catch(function () {
          sent = false;
          setStatus("&#10007; &Uuml;bertragung fehlgeschlagen - bitte erneut versuchen.", "err");
        });
    }

    var scanner = new Html5QrcodeScanner("reader", {
      fps: 10,
      qrbox: 250,
      experimentalFeatures: { useBarCodeDetectorIfSupported: true }
    }, false);
    scanner.render(function (decodedText) {
      scanner.clear().catch(function () {});
      send(decodedText);
    }, function () {});

    document.getElementById("qr-input-file").addEventListener("change", function (e) {
      if (!e.target.files || !e.target.files.length) return;
      var fileScanner = new Html5Qrcode("reader-file", { verbose: false });
      fileScanner.scanFile(e.target.files[0], true)
        .then(function (decodedText) { send(decodedText); })
        .catch(function () { setStatus("Datei konnte nicht gelesen werden.", "err"); });
    });
  </script>
<?php } ?>
</body>
</html>
