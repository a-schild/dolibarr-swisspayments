<?php

/* 
 * Page to scan or upload qr code
 * 
 * https://blog.minhazav.dev/research/html5-qrcode
 * https://github.com/mebjas/html5-qrcode
 * 
 */
require '../../main.inc.php';

global $db, $langs, $user;

// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (! $user->rights->swisspayments->invoices->create) accessforbidden();

?>
<!DOCTYPE html>
<html>
  <head>
    <title>Swisspayments mobilescan</title>
    <script type="text/javascript" src="js/html5-qrcode.min.js"></script>
  </head>
  <body>
      <form action="createinvoice.php" id="qrform" method="post">
		  <input type="hidden" name="token" value="<?= newToken() ?>">
          <textarea id="qrcode" name="qrcode" style="display:none"></textarea>
          <input type="hidden" name="action" value="analyzecode">
      </form>
  <div id="reader" width="100%"></div>
  <script>
    function onScanSuccess(decodedText, decodedResult) {
  // handle the scanned code as you like, for example:
  console.log(`Code matched = ${decodedText}`, decodedResult);
  //alert(decodedText);
  //alert(decodedResult);
  var q= document.getElementById("qrcode");
  q.value= decodedText;
  var f= document.getElementById("qrform");
  f.submit();
}

function onScanFailure(error) {
  // handle scan failure, usually better to ignore and keep scanning.
  // for example:
  console.warn(`Code scan error = ${error}`);
}

let html5QrcodeScanner = new Html5QrcodeScanner(
	"reader", { 
      fps: 10, 
        qrbox: 550,
        experimentalFeatures: {
          useBarCodeDetectorIfSupported: true
      } }, /* verbose= */ false);
html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
<input type="file" id="qr-input-file" accept="image/*" capture>
<script>
const html5QrCode = new Html5Qrcode(/* element id */ "reader");
// File based scanning
const fileinput = document.getElementById('qr-input-file');
fileinput.addEventListener('change', e => {
  if (e.target.files.length == 0) {
    // No file selected, ignore 
    return;
  }

  const imageFile = e.target.files[0];
  // Scan QR Code
  html5QrCode.scanFile(imageFile, true)
  .then(decodedText => {
    // success, use decodedText
    console.log(decodedText);
  //alert(decodedText);
  //alert(decodedResult);
  var q= document.getElementById("qrcode");
  q.value= decodedText;
  var f= document.getElementById("qrform");
  f.submit();
  })
  .catch(err => {
    // failure, handle it.
    console.log(`Error scanning file. Reason: ${err}`)
  });
});
  </script>
  </body>
</html>
