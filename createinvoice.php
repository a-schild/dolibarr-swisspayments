<?php

/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 * 	\file		createinvoice.php
 * 	\ingroup	swisspayments
 * 	\brief		Create a supplier bill from a PVR line
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

global $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/paymentterm.class.php';
require_once(DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php');
require_once(DOL_DOCUMENT_ROOT . '/core/class/discount.class.php');
require_once(DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT . "/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT . '/core/lib/invoice.lib.php');
require_once(DOL_DOCUMENT_ROOT . '/societe/class/companybankaccount.class.php');

dol_include_once('/swisspayments/class/swisspayments.class.php');
dol_include_once('/swisspayments/class/swisspaymentssoc.class.php');
dol_include_once('/swisspayments/class/swisspaymentsfactf.class.php');

// Load translation files required by the page
$langs->load("swisspayments@swisspayments");

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Access control
if (swisspayments_user_socid($user) > 0) {
  // External user
  accessforbidden();
}

// Apply any pending DB schema migration after a plain file/zip update (no module
// disable/re-enable needed). No-op once the schema-version constant is up to date.
swisspayments_check_db_version($db, $conf);

// Default action
if (empty($action) && empty($id) && empty($ref)) {
  $action = 'showcodefield';
}

// Load object if id or ref is provided as parameter
$object = new SwisspaymentsClass($db);
if (($id > 0 || !empty($ref)) && $action != 'add') {
  $result = $object->fetch($id, $ref);
  if ($result < 0) {
    dol_print_error($db);
  }
}

// Always have a (possibly empty) supplier object so the view can test $societe->id.
$societe = new Societe($db);
$error = 0;
$warn = 0;
$mesg = '';
$facture = null; // set once the supplier invoice has been created

/*
 * ACTIONS
 *
 * Put here all code to do according to value of "action" parameter
 */

// Invoice / due date as posted by the date selectors (<prefix>day/month/year). They are
// read field by field: GETPOST(..., 'date') is not a valid check type and returns an
// empty value on Dolibarr 20+.
$factureDate = dol_mktime(12, 0, 0, GETPOST('facturedatemonth', 'int'), GETPOST('facturedateday', 'int'), GETPOST('facturedateyear', 'int'));
$dueDate = dol_mktime(12, 0, 0, GETPOST('duedatemonth', 'int'), GETPOST('duedateday', 'int'), GETPOST('duedateyear', 'int'));

if ($action == "createesrid") {
  if (GETPOST('socid', 'int') < 1) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('Supplier')), 'errors');
    $error++;
  } else {
    $societe = new Societe($db);
    $societe->fetch(GETPOST('socid', 'int'));
  }
  if (empty($factureDate)) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('SwpInvoiceDate')), 'errors');
    $error++;
  }
  if (empty($dueDate)) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('SwpDueDate')), 'errors');
    $error++;
  }
}

$myobject = new SwisspaymentsClass($db);
$myobject->setCodeline(isset($_POST["codeline"]) ? $_POST["codeline"] : '', isset($_POST["qrcode"]) ? $_POST["qrcode"] : '');

$result = $myobject->validateCode($user);
if ($result > 0) {
  // Validation passed
  $newESRSoc = new Swisspaymentssoc($db);
  if ($myobject->isESR) {
    // ESR stuff
    if ($newESRSoc->fetch(null, null, $myobject->pcAccount, $myobject->esrID) > 0 && $newESRSoc->id) {
      dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
      $societe = new Societe($db);
      $result = $societe->fetch($newESRSoc->fk_societe);
      if ($result < 0) {
        $mesg = $newESRSoc->error;
        $error++;
      } else {
        if ($_POST["billnr"]) {
          $myobject->setBillno($_POST["billnr"]);
        } else {
          $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
        }
        if (isset($_POST["amount"])) {
          $myobject->amount = price2num(GETPOST("amount", 'alpha'));
        }
        // Now check billNr for duplicates
        $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
        if ($resql) {
          $num = $db->num_rows($resql);
          $i = 0;
          if ($num > 0) {
            $warn++;
            $mesg = $langs->trans('SwpInvoiceExistsChange', $myobject->billnr);
          }
        }
      }
    }
    else {
      dol_syslog(__METHOD__ . " no matching supplier entry found", LOG_DEBUG);
      if ($societe->id != 0) {
        $newESRSoc = new Swisspaymentssoc($db);
        $newESRSoc->fk_societe = $societe->id;
        $newESRSoc->esrid = $_POST["esrid"];
        $newESRSoc->clientno = $_POST["clientno"];
        $newESRSoc->pcaccount = $_POST["pcAccount"];
        // startorderno/endorderno mark where the bill number sits inside the reference
        // (legacy ESR logic). For QR bills the bill number is not part of the reference,
        // so strpos() often returns false - coerce to 0 to avoid an invalid INT insert.
        $startPos = strpos((string) $myobject->refLine, (string) $_POST["billnr"]);
        $newESRSoc->startorderno = ($startPos === false) ? 0 : $startPos;
        $newESRSoc->endorderno = $newESRSoc->startorderno + strlen((string) $_POST["billnr"]);
        $result = $newESRSoc->create($user);
        if ($result < 0) {
          $mesg = $newESRSoc->error;
          $error++;
        } else {
          $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
        }
      }
    }
  }
  else if ($myobject->isQRCode) {
    // Is a QR bill. Supplier bank accounts are matched in PHP through
    // swisspayments_find_ribs_by_iban(): Dolibarr 18+ stores societe_rib.iban_prefix
    // encrypted, so an SQL comparison on that column cannot find the supplier.
    $postedSocid = (int) GETPOST('socid', 'int');
    // For QR bills the invoice number ("Rechnung Nr.") is exactly what the user typed;
    // it is never derived from the QR reference line (that was ESR logic).
    $postedBillnr = trim((string) GETPOST('billnr', 'alphanohtml'));
    $myobject->setBillno($postedBillnr);

    if ($action == "createesrid" && $postedSocid > 0 && $societe->id > 0) {
      // The user assigned the bill to a supplier: make sure that supplier has a bank
      // account with the scanned IBAN.
      $ribs = swisspayments_find_ribs_by_iban($db, $myobject->iban, $postedSocid);
      if (count($ribs) == 0) {
        $db->begin(); // Begin transaction
        $cba = new CompanyBankAccount($db);
        $cba->type = "ban";
        $cba->socid = $postedSocid;
        $cba->label = "QR Bill";
        $cba->bank = "QR Bill";
        $cba->iban = $myobject->iban;
        $cba->proprio = $myobject->payToName; // Lieferant Name (Dolibarr < 20)
        $cba->owner_name = $myobject->payToName; // renamed property, Dolibarr 20+
        $cba->owner_address = $myobject->payToAddress;
        $cba->default_rib = 1;
        $result = $cba->create($user, 0);
        if ($result < 0) {
          $mesg = $cba->error;
          $error++;
          $db->rollback(); // Rollback transaction
        } else {
          $cba->update($user);
          $db->commit(); // End transaction
          $ribs = swisspayments_find_ribs_by_iban($db, $myobject->iban, $postedSocid);
        }
      }

      // The payment file falls back to the supplier's DEFAULT bank account for bills
      // without a stored IBAN. When the user opted in, make the scanned IBAN the default.
      if (GETPOST('set_default_iban', 'int') && count($ribs) > 0) {
        $db->query("UPDATE " . MAIN_DB_PREFIX . "societe_rib SET default_rib=0 WHERE fk_soc=" . $postedSocid);
        $db->query("UPDATE " . MAIN_DB_PREFIX . "societe_rib SET default_rib=1 WHERE rowid=" . ((int) $ribs[0]->rowid));
      }

      $newESRSoc = new Swisspaymentssoc($db);
      if ($newESRSoc->fetch(null, null, null, null, $postedSocid) > 0 && $newESRSoc->id) {
        dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
      } else if ($postedBillnr !== '') {
        $newESRSoc = new Swisspaymentssoc($db);
        $newESRSoc->fk_societe = $societe->id;
        $newESRSoc->esrid = "QRBILL";
        // startorderno/endorderno mark where the bill number sits inside the reference
        // (legacy ESR logic). For QR bills the bill number is not part of the reference,
        // so strpos() often returns false - coerce to 0 to avoid an invalid INT insert.
        $startPos = strpos((string) $myobject->refLine, $postedBillnr);
        $newESRSoc->startorderno = ($startPos === false) ? 0 : $startPos;
        $newESRSoc->endorderno = $newESRSoc->startorderno + strlen($postedBillnr);
        $result = $newESRSoc->create($user);
        if ($result < 0) {
          $mesg = $newESRSoc->error;
          $error++;
        }
      }
    }

    if (!($societe->id > 0)) {
      // Identify the supplier by the scanned IBAN (restricted to the supplier carried
      // by the form, if any, so a second supplier sharing the IBAN does not get in the way).
      $ribs = swisspayments_find_ribs_by_iban($db, $myobject->iban, $postedSocid);
      $socids = array();
      foreach ($ribs as $rib) {
        $socids[$rib->fk_soc] = $rib->fk_soc;
      }
      if (count($socids) == 1) {
        dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
        $societe = new Societe($db);
        $result = $societe->fetch(reset($socids));
      } else if (count($socids) > 1) {
        setEventMessage($langs->trans('SwpMultipleAccountsSameIban', count($socids)), 'warnings');
      } else {
        dol_syslog(__METHOD__ . " No supplier account found for IBAN " . $myobject->iban, LOG_INFO);
      }
    }

    // Check the typed invoice number for duplicates at this supplier.
    if ($societe->id > 0 && $myobject->billnr !== '') {
      $resql = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE fk_soc=" . ((int) $societe->id) . " AND entity=" . ((int) $conf->entity) . " AND ref_supplier='" . $db->escape($myobject->billnr) . "'");
      if ($resql) {
        if ($db->num_rows($resql) > 0) {
          $error++;
          $mesg = $langs->trans('SwpInvoiceExistsChange', $myobject->billnr);
        }
        $db->free($resql);
      } else {
        setEventMessage($langs->trans('SwpDbError', $db->lasterror()), 'errors');
      }
    }
  }
} else {
  // Parse failed. Show the error when something was actually submitted (QR textarea
  // or the carried-over codeline); stay silent on the first, empty page load.
  if (!empty($_POST["codeline"]) || !empty($_POST["qrcode"])) {
    $mesg = $myobject->error;
    $error++;
  }
}

// Create a brand new supplier from the reviewed QR-bill data (name + structured
// address + IBAN). Only for QR bills; the form pre-fills these fields from the QR.
if ($action == "createsupplier" && $result > 0 && $myobject->isQRCode) {
  $newName = trim(GETPOST('new_name', 'alphanohtml'));
  $newZip = trim(GETPOST('new_zip', 'alphanohtml'));
  $newTown = trim(GETPOST('new_town', 'alphanohtml'));
  if ($newName == '') {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('Name')), 'errors');
    $error++;
  } else if ($newZip == '' || $newTown == '') {
    // Post code + town are mandatory for the structured pain.001 address.
    setEventMessage($langs->trans('SwpZipTownRequired'), 'errors');
    $error++;
  } else {
    $db->begin();
    $newSoc = new Societe($db);
    $newSoc->name = $newName;
    $newSoc->address = trim(GETPOST('new_street', 'alphanohtml'));
    $newSoc->zip = $newZip;
    $newSoc->town = $newTown;
    $ccode = trim(GETPOST('new_country', 'alpha'));
    if ($ccode == '') $ccode = 'CH';
    $newSoc->country_code = $ccode;
    $rqc = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "c_country WHERE code='" . $db->escape($ccode) . "'");
    if ($rqc && $db->num_rows($rqc) > 0) {
      $oc = $db->fetch_object($rqc);
      $newSoc->country_id = $oc->rowid;
    }
    $newSoc->fournisseur = 1;
    $newSoc->client = 0;
    // Let the configured numbering module assign the supplier code. Without this,
    // setups where the supplier code is mandatory reject the new third party.
    $newSoc->code_fournisseur = 'auto';
    if ($newSoc->create($user) < 0) {
      $error++;
      setEventMessage($newSoc->error ? $newSoc->error : implode(', ', $newSoc->errors), 'errors');
      $db->rollback();
    } else {
      // Attach the QR IBAN as the supplier's default bank account.
      $cba = new CompanyBankAccount($db);
      $cba->socid = $newSoc->id;
      $cba->type = "ban";
      $cba->label = "QR Bill";
      $cba->bank = "QR Bill";
      $cba->iban = $myobject->iban;
      $cba->proprio = $myobject->payToName; // Dolibarr < 20
      $cba->owner_name = $myobject->payToName; // renamed property, Dolibarr 20+
      $cba->owner_address = $myobject->payToAddress;
      $cba->default_rib = 1;
      if ($cba->create($user, 0) < 0) {
        $error++;
        setEventMessage($cba->error, 'errors');
        $db->rollback();
      } else {
        $cba->update($user);
        $db->commit();
        $societe = $newSoc;
        // Continue in the "known supplier" flow so the invoice fields are shown.
        $action = 'analyzecode';
        setEventMessage($langs->trans('SwpSupplierCreated', $newSoc->name), 'mesgs');
      }
    }
  }
}

// Validate/normalize the invoice amount before creating the invoice. Bills with a fixed
// amount from the QR/ESR code keep it; open-amount bills require a positive numeric value
// entered by the user (reject empty, zero, negative or non-numeric input). price2num()
// accepts localized input like "1'234.50" or "1234,50".
if ($error == 0 && $societe->id != 0 && ($action == "createfacture" || $action == "createesrid") && !$myobject->hasAmount) {
  $amount = price2num(GETPOST('amount', 'alpha'));
  if (!is_numeric($amount) || $amount <= 0) {
    setEventMessage($langs->trans('SwpAmountRequired'), 'errors');
    $error++;
  } else {
    $myobject->amount = $amount;
  }
}

// The invoice number ("Rechnung Nr.") is required (it is the supplier's invoice number
// and the duplicate-check key). For QR bills it now defaults to empty, so enforce it here.
if ($error == 0 && $societe->id != 0 && ($action == "createfacture" || $action == "createesrid") && trim((string) $myobject->billnr) === "") {
  setEventMessage($langs->trans('SwpInvoiceNumberRequired'), 'errors');
  $error++;
}

if ($error == 0 && $societe->id != 0 && ($action == "createfacture" || $action == "createesrid")) {
  $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
  if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;
    if ($num > 0) {
      $error++;
      $mesg = $langs->trans('SwpInvoiceExists', $myobject->billnr);
    } else {
      $db->begin(); // Begin transaction
      // Create Facture
      $facture = new FactureFournisseur($db);
      $facture->socid = $societe->id;
      $facture->datec = getdate();
      //$facture->date= getdate();
      $facture->datef = $factureDate;
      $facture->date_echeance = $dueDate;
      $facture->type = 0;
      $facture->cond_reglement_id = $societe->cond_reglement_supplier_id;
      $facture->mode_reglement_id = $societe->mode_reglement_supplier_id;
      // $facture->fk_account= $societe->
      $facture->amount = $myobject->amount;
      $facture->total = $myobject->amount;
      // $facture->note_private= $myobject->codeline;
      $facture->ref_supplier = $myobject->billnr;
      $facture->libelle = $myobject->billnr;

      if ($facture->create($user) > 0) {
        $facture->addline("Lieferantenrechnung", $myobject->amount, NULL, NULL, NULL, 1);
        //$facture->update($user);
        if (GETPOST('validate', 'int') == '1') {
          $facture->validate($user);
        }

        $factESR = new Swisspaymentsfactf($db);
        $factESR->fk_factid = $facture->id;
        if ($myobject->isQRCode)
        {
          // Use the (possibly corrected) QR reference from the review form; fall back
          // to the scanned value. This is stored as esrline and paid as the QRR.
          $qrref = str_replace(' ', '', trim(GETPOST('qrref', 'alphanohtml')));
          $factESR->esrline = ($qrref != '') ? $qrref : $myobject->refLine;
        }
        else
        {
          $factESR->esrline = $myobject->codeline;
        }
        $factESR->esrpartynr = $myobject->pcAccount;
        $factESR->esrrefnr = $myobject->fullRefline;
        // Store the creditor IBAN of THIS bill so the payment file targets the correct
        // account even when the supplier has several bank accounts (independent of which
        // societe_rib happens to be the default). Empty for legacy ESR (non-QR) bills.
        $factESR->iban = $myobject->iban;
        $result = $factESR->create($user, 0);
        if ($result < 0) {
          $mesg = $newESRSoc->error;
          $error++;
          $db->rollback(); // Rollback transaction
        } else {
          $db->commit(); // End transaction
        }
      } else {
        $error++;
        $mesg = $facture->error;
        $db->rollback(); // Rollback transaction
      }
    }
  } else {
    $error++;
    $mesg = $langs->trans('SwpDuplicateCheckError');
  }
}

if ($error == 0 && $facture && $facture->id > 0 && $facture->statut == 0) {
  $loc = DOL_URL_ROOT . '/fourn/facture/card.php?facid=' . $facture->id;
  header("Location: " . $loc);
  exit;
}

/*
 * VIEW
 *
 * Put here all code to build page
 */

llxHeader('', $langs->trans('ReadESR'), '');

echo "<h1>" . $langs->trans('SwpEnterSupplierInvoice') . "</h1>";

$form = new Form($db);

// Only show the review step when we actually parsed a usable payment (IBAN or PC account);
// a failed parse falls back to the entry step with the error message.
$parsedOk = (!empty($myobject->iban) || !empty($myobject->pcAccount));
$inReview = !($facture && $facture->id > 0) && $parsedOk && ($action == 'createesrid' || $action == 'createfacture' || $action == 'analyzecode' || $action == 'createsupplier');

// Wizard step indicator
$step = $inReview ? 2 : 1;
$stepLabel1 = $langs->trans('SwpStepReadQr');
$stepLabel2 = $langs->trans('SwpStepSupplierInvoice');
echo '<div style="margin:0 0 14px 0;font-size:1.05em;">';
echo ($step == 1 ? '<strong>&#10148; 1. ' . $stepLabel1 . '</strong>' : '<span style="opacity:.6">1. ' . $stepLabel1 . '</span>');
echo ' &nbsp;&rarr;&nbsp; ';
echo ($step == 2 ? '<strong>&#10148; 2. ' . $stepLabel2 . '</strong>' : '<span style="opacity:.6">2. ' . $stepLabel2 . '</span>');
echo '</div>';

// Central message display (setEventMessage entries are shown by the framework itself).
if ($error > 0 && !empty($mesg)) {
  echo dol_htmloutput_errors($mesg);
} else if ($warn > 0 && !empty($mesg)) {
  echo dol_htmloutput_mesg($mesg, null, 'warning');
}

// Renders the shared invoice detail fields (QR ref, bill nr, dates, release) inside a table.
$renderInvoiceFields = function () use ($form, $societe, $myobject, $db, $langs) {
  if ($myobject->isQRCode) {
    // Editable QR reference (stored as esrline; the QRR sent to the bank). Pre-filled
    // from the scan so a bad scan can be corrected here. Validated live in JS below.
    // A QRR is only mandatory with a QR-IBAN (CH/LI, '3' at position 5); a QR-bill on a
    // normal IBAN legitimately has no QRR (reference type SCOR or NON), so don't require it.
    $isQrIban = preg_match('/^(CH|LI)[0-9]{2}3/', strtoupper(str_replace(' ', '', (string) $myobject->iban)));
    print '<tr><td width="30%" class="' . ($isQrIban ? 'fieldrequired' : '') . '">' . $langs->trans('SwpQrReference') . '</td><td>';
    print '<input type="text" name="qrref" id="qrref" size="35" value="' . dol_escape_htmltag($myobject->refLine) . '" onkeyup="swpCheckQrref()" onchange="swpCheckQrref()"> ';
    print '<span id="qrref_msg"></span>';
    if (!$isQrIban) print '<br><span class="opacitymedium">' . $langs->trans('SwpNoQrIbanRefOptional') . '</span>';
    print '</td></tr>';
  }
  // For QR bills the invoice number is the supplier's own number, not the QR reference.
  // Keep a valid value the user typed; otherwise (empty, or already used for this
  // supplier) propose a bill number in yyyymmdd form, made unique with a -N suffix so it
  // is never itself a duplicate.
  $billnrDefault = $myobject->billnr;
  if ($myobject->isQRCode) {
    $sockid = (int) $societe->id;
    $billnrEntity = (int) $conf->entity;
    $billnrTaken = function ($val) use ($db, $sockid, $billnrEntity) {
      $val = trim((string) $val);
      if ($sockid <= 0 || $val === '') return false;
      $r = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "facture_fourn WHERE fk_soc=" . $sockid . " AND entity=" . $billnrEntity . " AND ref_supplier='" . $db->escape($val) . "'");
      return ($r && $db->num_rows($r) > 0);
    };
    $typed = trim((string) GETPOST('billnr', 'alphanohtml'));
    if ($typed !== '' && !$billnrTaken($typed)) {
      $billnrDefault = $typed;
    } else {
      $billnrDefault = date('Ymd');
      $suffix = 1;
      while ($billnrTaken($billnrDefault)) {
        $suffix++;
        $billnrDefault = date('Ymd') . '-' . $suffix;
      }
    }
  }
  print '<tr><td width="30%" class="fieldrequired">' . $langs->trans('SwpInvoiceNumber') . '</td><td>';
  print '<input type="text" name="billnr" id="billnr" value="' . dol_escape_htmltag($billnrDefault) . '"></td></tr>';
  print '<tr><td class="fieldrequired">' . $langs->trans('SwpInvoiceDate') . '</td><td>';
  $form->select_date('', 'facturedate', 0, 0, 0, "myform");
  print '</td></tr>';
  $nDays = 30;
  $condID = $societe->cond_reglement_supplier_id;
  if ($condID) {
    $payTerm = new PaymentTerm($db);
    $payTerm->fetch($condID);
    if ($payTerm->nbjour) $nDays = $payTerm->nbjour;
  }
  $dueDate = new DateTime();
  $dueDate->add(new DateInterval('P' . $nDays . 'D'));
  print '<tr><td class="fieldrequired">' . $langs->trans('SwpDueDate') . '</td><td>';
  $form->select_date($dueDate->format('Y-m-d'), 'duedate', 0, 0, 0, "myform");
  print '</td></tr>';
  print '<tr><td class="fieldrequired">' . $langs->trans('SwpValidateInvoice') . '</td><td><input type="checkbox" name="validate" value="1"></td></tr>';
};

if ($inReview) {
  // ===== STEP 2: review parsed QR / assign supplier / create invoice =====

  // Scanned payment info
  print '<table class="border" width="100%">';
  print '<tr class="liste_titre"><td colspan="2">' . ($myobject->isQRCode ? $langs->trans('SwpQrBill') : $langs->trans('SwpPayment')) . '</td></tr>';
  if ($myobject->iban) {
    print '<tr><td width="30%">IBAN</td><td>' . dol_escape_htmltag($myobject->iban) . '</td></tr>';
  } else {
    print '<tr><td width="30%">' . $langs->trans('SwpPostalAccount') . '</td><td>' . dol_escape_htmltag($myobject->pcAccount) . '</td></tr>';
    print '<tr><td>' . $langs->trans('SwpEsrId') . '</td><td>' . dol_escape_htmltag($myobject->esrID) . '</td></tr>';
  }
  if ($myobject->payToName) print '<tr><td>' . $langs->trans('SwpRecipient') . '</td><td>' . dol_escape_htmltag($myobject->payToName) . '</td></tr>';
  // Build the address from the clean structured parts (avoids the raw newlines that
  // sit in the combined payToAddress string); fall back to the combined string.
  $addrParts = array();
  $s = trim($myobject->payToStreet . ' ' . $myobject->payToBuildingNo);
  if ($s !== '') $addrParts[] = $s;
  $ct = trim($myobject->payToPostcode . ' ' . $myobject->payToTown);
  if ($ct !== '') $addrParts[] = $ct;
  if (!empty($myobject->payToCountry)) $addrParts[] = $myobject->payToCountry;
  if (empty($addrParts) && $myobject->payToAddress) {
    $addrParts[] = trim(str_replace(array("\r\n", "\r", "\n"), ', ', $myobject->payToAddress));
  }
  if (!empty($addrParts)) print '<tr><td>' . $langs->trans('SwpAddress') . '</td><td>' . dol_escape_htmltag(implode(', ', $addrParts)) . '</td></tr>';
  if ($myobject->hasAmount) print '<tr><td>' . $langs->trans('Amount') . '</td><td>' . price($myobject->amount) . ' CHF</td></tr>';
  print '</table><br>';

  if ($societe->id != 0) {
    // ----- Known supplier: create invoice -----
    print '<div class="info">' . $langs->trans('SwpKnownSupplier') . ': ' . $societe->getNomUrl(1) . '</div><br>';
    print '<form method="post" name="myform">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
    print '<input type="hidden" name="socid" value="' . $societe->id . '">';
    if ($myobject->hasAmount) print '<input type="hidden" name="amount" value="' . dol_escape_htmltag($myobject->amount) . '">';
    print '<table class="border" width="100%">';
    // Warn when this bill's IBAN is not the supplier's default bank account: the payment
    // file uses the default account, so paying this bill would go to the wrong IBAN.
    // Offer to make the scanned IBAN the default (checked by default).
    $scannedIban = strtoupper(str_replace(' ', '', (string) $myobject->iban));
    if ($scannedIban !== '') {
      $defaultRibIban = '';
      $rqDef = $db->query("SELECT iban_prefix FROM " . MAIN_DB_PREFIX . "societe_rib WHERE fk_soc=" . ((int) $societe->id) . " AND default_rib=1");
      if ($rqDef && $db->num_rows($rqDef) > 0) { $oDef = $db->fetch_object($rqDef); $defaultRibIban = swisspayments_norm_iban($oDef->iban_prefix); }
      if ($defaultRibIban !== '' && $defaultRibIban !== $scannedIban) {
        print '<tr><td width="30%">' . $langs->trans('SwpDefaultAccount') . '</td><td>';
        print img_warning() . ' ' . $langs->trans('SwpIbanNotDefaultWarn', $myobject->iban, $defaultRibIban) . '<br>';
        print '<label><input type="checkbox" name="set_default_iban" value="1" checked> ' . $langs->trans('SwpSetScannedIbanDefault') . '</label>';
        print '</td></tr>';
      }
    }
    if (!$myobject->hasAmount) print '<tr><td width="30%" class="fieldrequired">' . $langs->trans('Amount') . '</td><td><input type="text" name="amount" value="' . dol_escape_htmltag(GETPOST('amount', 'alpha')) . '"></td></tr>';
    $renderInvoiceFields();
    print '</table><br>';
    print '<div class="center"><input type="submit" class="button" value="' . dol_escape_htmltag($langs->transnoentities('SwpCreateInvoice')) . '"></div>';
    print '<input type="hidden" name="action" value="' . ($action == 'createesrid' ? 'createfacture' : 'createesrid') . '">';
    print '</form>';
  } else {
    // ----- Unknown supplier: assign existing OR create new -----
    print '<form method="post" name="myform">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
    if ($myobject->hasAmount) print '<input type="hidden" name="amount" value="' . dol_escape_htmltag($myobject->amount) . '">';
    print '<table class="border" width="100%">';
    print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('SwpAssignExistingSupplier') . '</td></tr>';
    print '<tr><td width="30%" class="fieldrequired">' . $langs->trans('Supplier') . '</td><td>';
    print $form->select_company(GETPOST('socid', 'int'), 'socid', swisspayments_supplier_filter(), 1);
    print '</td></tr>';
    if (!$myobject->hasAmount) print '<tr><td class="fieldrequired">' . $langs->trans('Amount') . '</td><td><input type="text" name="amount" value="' . dol_escape_htmltag(GETPOST('amount', 'alpha')) . '"></td></tr>';
    $renderInvoiceFields();
    print '</table><br>';
    print '<div class="center"><input type="submit" class="button" value="' . dol_escape_htmltag($langs->transnoentities('SwpAssignAndCreateInvoice')) . '"></div>';
    print '<input type="hidden" name="action" value="' . ($action == 'createesrid' ? 'createfacture' : 'createesrid') . '">';
    print '</form>';

    // Create a new supplier from the QR data (QR bills only)
    if ($myobject->isQRCode && $myobject->iban) {
      $prefStreet = trim($myobject->payToStreet . ' ' . $myobject->payToBuildingNo);
      print '<br><form method="post">';
      print '<input type="hidden" name="token" value="' . newToken() . '">';
      echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
      print '<table class="border" width="100%">';
      print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('SwpCreateSupplierFromQr') . '</td></tr>';
      print '<tr><td width="30%" class="fieldrequired">' . $langs->trans('SwpName') . '</td><td><input type="text" name="new_name" size="50" value="' . dol_escape_htmltag($myobject->payToName) . '"></td></tr>';
      print '<tr><td>' . $langs->trans('SwpStreetNo') . '</td><td><input type="text" name="new_street" size="50" value="' . dol_escape_htmltag($prefStreet) . '"></td></tr>';
      print '<tr><td class="fieldrequired">' . $langs->trans('SwpZipTown') . '</td><td>';
      print '<input type="text" name="new_zip" size="8" value="' . dol_escape_htmltag($myobject->payToPostcode) . '"> ';
      print '<input type="text" name="new_town" size="30" value="' . dol_escape_htmltag($myobject->payToTown) . '"></td></tr>';
      print '<tr><td class="fieldrequired">' . $langs->trans('SwpCountry') . '</td><td><input type="text" name="new_country" size="4" value="' . dol_escape_htmltag($myobject->payToCountry ? $myobject->payToCountry : 'CH') . '"></td></tr>';
      print '<tr><td>IBAN</td><td>' . dol_escape_htmltag($myobject->iban) . '</td></tr>';
      print '</table><br>';
      print '<div class="center"><input type="submit" class="button" value="' . dol_escape_htmltag($langs->transnoentities('SwpCreateSupplier')) . '"></div>';
      print '<input type="hidden" name="action" value="createsupplier">';
      print '</form>';
    }
  }

  // Live validation of the editable QR reference: a QRR (numeric, max 27 digits +
  // modulo-10 recursive check digit) or a SCOR / ISO 11649 creditor reference (RF + two
  // mod-97 check digits + reference). Mirrors what the payment side accepts.
  if ($myobject->isQRCode) {
    echo '<script type="text/javascript">
      function swpMod10(n){var t=[0,9,4,6,8,2,7,1,3,5],x=0;for(var i=0;i<n.length;i++){x=t[(x+parseInt(n.charAt(i),10))%10];}return (10-x)%10;}
      function swpValidScor(v){var r=v.toUpperCase();if(!/^RF[0-9]{2}[A-Z0-9]{1,21}$/.test(r))return false;var a=r.substring(4)+r.substring(0,4),n="",i,c;for(i=0;i<a.length;i++){c=a.charAt(i);n+=/[0-9]/.test(c)?c:(c.charCodeAt(0)-55).toString();}var rem=0;for(i=0;i<n.length;i++){rem=(rem*10+parseInt(n.charAt(i),10))%97;}return rem===1;}
      function swpCheckQrref(){var e=document.getElementById("qrref");if(!e)return;var v=e.value.replace(/\s/g,"");var m=document.getElementById("qrref_msg");
        if(v===""){m.innerHTML="";e.style.backgroundColor="";return;}
        var ok,label;
        if(/^RF/i.test(v)){ok=swpValidScor(v);label=ok?"✓ gültige SCOR-Referenz":"✗ ungültige SCOR-Referenz (RF...)";}
        else{ok=/^[0-9]{1,27}$/.test(v)&&swpMod10(v.substring(0,v.length-1))===parseInt(v.charAt(v.length-1),10);label=ok?"✓ gültige QR-Referenz ("+v.length+" Ziffern)":"✗ ungültige QR-Referenz ("+v.length+" Ziffern, erwartet 27)";}
        if(ok){m.innerHTML="<span style=\'color:green\'>"+label+"</span>";e.style.backgroundColor="#e6ffe6";}
        else{m.innerHTML="<span style=\'color:#cc0000\'>"+label+"</span>";e.style.backgroundColor="#ffe6e6";}
      }
      jQuery(document).ready(function(){swpCheckQrref();});
    </script>';
  }
} else {
  // ===== STEP 1: read a QR code =====
  if ($facture && $facture->id > 0) {
    // Use transnoentities(): the %s parameter is an HTML <a> link (getNomUrl), and
    // trans() would HTML-escape it, printing the raw tag as text.
    echo '<div class="ok">' . $langs->transnoentities('SwpInvoiceRecorded', $facture->getNomUrl()) . '</div><br>';
  }

  print '<form method="post">';
  print '<input type="hidden" name="token" value="' . newToken() . '">';
  print '<table class="border" width="100%">';
  print '<tr><td width="30%">QR-Code</td><td><textarea name="qrcode" id="qrcode" rows="6" cols="60"></textarea></td></tr>';
  print '</table><br>';
  print '<div class="center"><input type="submit" class="button" value="' . dol_escape_htmltag($langs->transnoentities('SwpRead')) . '"></div>';
  print '<input type="hidden" name="action" value="analyzecode">';
  print '</form><br>';

  // Pair a phone as a login-free scanner: show a QR code that opens the mobile scan page
  // for a one-time token, then poll for the scanned payload and continue automatically.
  $scanToken = swisspayments_scan_create_token($user->id, $conf->entity);
  $actual_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  $mobileUrl = $actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php?t=" . $scanToken;
  echo '<div style="margin-top:10px">';
  echo "<a href='" . $mobileUrl . "' target='_blank'>";
  echo "<img src='mobileqr.php?t=" . $scanToken . "'><br/>";
  echo dol_escape_htmltag($langs->trans('SwpScanWithPhone'));
  echo "</a>";
  echo '<div id="swp_scan_status" style="margin-top:6px"></div>';
  echo '</div>';

  echo '<script type="text/javascript">
    jQuery(document).ready(function() { jQuery("#qrcode").focus(); });
    (function(){
      var token = "' . $scanToken . '";
      var timer = setInterval(function(){
        fetch("scanpoll.php?t=" + token, {cache:"no-store"}).then(function(r){ return r.json(); }).then(function(d){
          if (d && d.payload) {
            clearInterval(timer);
            var q = document.getElementById("qrcode");
            var st = document.getElementById("swp_scan_status");
            if (st) st.innerHTML = ' . json_encode("✓ " . $langs->trans('SwpReceivedFromPhone')) . ';
            if (q) { q.value = d.payload; if (q.form) { q.form.submit(); } }
          }
        }).catch(function(){});
      }, 2000);
    })();
  </script>';
}

// Example 2: Adding links to objects
// The class must extend CommonObject for this method to be available
// $somethingshown = $myobject->showLinkedObjectBlock();
// End of page
llxFooter();
