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
	// A Swiss QR-bill payload is well under 1 KB; cap it to avoid storing arbitrary large blobs.
	$payload = isset($_POST['payload']) ? (string) $_POST['payload'] : '';
	if (strlen($payload) > 2000) {
		echo json_encode(array('ok' => false, 'error' => 'toolarge'));
		exit;
	}
	$data['payload'] = $payload;
	$data['date_scan'] = time();
	file_put_contents(swisspayments_scan_file($token), json_encode($data));
	echo json_encode(array('ok' => true));
	exit;
}

header('Content-Type: text/html; charset=UTF-8');

// Brand logo: use the Dolibarr company logo when configured (served publicly through
// viewimage.php), otherwise fall back to a "Swisspayments" wordmark.
$logoHtml = '<span class="brandmark">Swiss<span class="accent">payments</span></span>';
$societeLogo = swisspayments_conf_string('MAIN_INFO_SOCIETE_LOGO');
if ($societeLogo !== '') {
	$logoUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&entity=' . ((int) $conf->entity) . '&file=' . urlencode('logos/' . $societeLogo);
	$logoHtml = '<img src="' . dol_escape_htmltag($logoUrl) . '" alt="Logo">';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title>Swisspayments Scan</title>
  <script type="text/javascript" src="js/html5-qrcode.min.js"></script>
  <style>
    :root {
      --bg: #f1f5f9; --card: #ffffff; --text: #0f172a; --muted: #64748b;
      --accent: #e30613; --ok: #16a34a; --err: #dc2626; --line: #e2e8f0; --radius: 18px;
    }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #0b1120; --card: #131a2a; --text: #e5e9f0; --muted: #94a3b8; --line: #263148; }
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; background: var(--bg); color: var(--text);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    .topbar {
      display: flex; align-items: center; justify-content: center; padding: 14px;
      background: #ffffff; border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 10;
    }
    .topbar img { max-height: 42px; max-width: 70%; object-fit: contain; }
    .brandmark { font-size: 1.35em; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
    .brandmark .accent { color: var(--accent); }
    .wrap { max-width: 460px; margin: 0 auto; padding: 20px 16px 32px; }
    h1 { font-size: 1.1em; font-weight: 600; text-align: center; color: var(--muted); margin: 4px 0 18px; }
    .card {
      background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
      box-shadow: 0 10px 30px rgba(2, 6, 23, .12); overflow: hidden;
    }
    #reader { width: 100%; background: #000; }
    #reader video { display: block; width: 100% !important; height: auto !important; }
    .card-body { padding: 18px; }
    #status { text-align: center; font-size: 1.05em; min-height: 1.3em; margin: 0; color: var(--muted); }
    #status.ok { color: var(--ok); font-weight: 700; }
    #status.err { color: var(--err); font-weight: 600; }
    .divider { display: flex; align-items: center; gap: 10px; color: var(--muted); font-size: .85em; margin: 16px 0 12px; }
    .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--line); }
    .filelabel {
      display: block; text-align: center; padding: 13px; border: 1px dashed var(--line);
      border-radius: 12px; color: var(--muted); font-size: .95em; cursor: pointer;
    }
    .filelabel input { display: none; }
    .badge { text-align: center; color: var(--muted); font-size: .8em; margin-top: 22px; }
  </style>
</head>
<body>
  <div class="topbar"><?php echo $logoHtml; ?></div>
<?php if (!$valid) { ?>
  <div class="wrap">
    <div class="card"><div class="card-body">
      <p id="status" class="err">Der Scan-Link ist ung&uuml;ltig oder abgelaufen.<br>Bitte am Computer einen neuen QR-Code anzeigen.</p>
    </div></div>
  </div>
<?php } else { ?>
  <div class="wrap">
    <h1>QR-Rechnung scannen</h1>
    <div class="card">
      <div id="reader"></div>
      <div class="card-body">
        <p id="status">Kamera wird gestartet &hellip;</p>
        <div class="divider">oder</div>
        <label class="filelabel">QR-Bild vom Ger&auml;t hochladen
          <input type="file" id="qr-input-file" accept="image/*" capture>
        </label>
      </div>
    </div>
    <div class="badge">Swisspayments &middot; sichere QR-Erfassung</div>
  </div>
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

    // Start the rear camera directly (uses the native BarcodeDetector when available).
    // The browser only shows the permission prompt if access isn't granted yet; if it is
    // denied or no camera is present, we fall back to the file upload below.
    var html5Qrcode = new Html5Qrcode("reader", { verbose: false });
    var config = {
      fps: 10,
      qrbox: function (vw, vh) { var m = Math.floor(Math.min(vw, vh) * 0.8); return { width: m, height: m }; },
      experimentalFeatures: { useBarCodeDetectorIfSupported: true }
    };
    function onCameraScan(decodedText) {
      html5Qrcode.stop().catch(function () {});
      send(decodedText);
    }
    html5Qrcode.start({ facingMode: "environment" }, config, onCameraScan, function () {})
      .then(function () {
        setStatus("QR-Rechnung vor die Kamera halten");
      })
      .catch(function () {
        setStatus("Kamerazugriff nicht m&ouml;glich (verweigert oder keine Kamera).<br>Bitte den Datei-Upload unten verwenden.", "err");
      });

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
