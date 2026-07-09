<?php

/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 * 	\file		createinvoice.php
 * 	\ingroup	swisspayments
 * 	\brief		Create a supplier bill from a PVR line
 */
require '../../main.inc.php';

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
$action = GETPOST('action', 'showcodefield');

// Access control
if ($user->societe_id > 0) {
  // External user
  accessforbidden();
}

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

/*
 * ACTIONS
 *
 * Put here all code to do according to value of "action" parameter
 */

if ($action == "createesrid") {
  if (GETPOST('socid', 'int') < 1) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('Supplier')), 'errors');
    $error++;
  } else {
    $societe = new Societe($db);
    $societe->fetch(GETPOST('socid', 'int'));
  }
  if (GETPOST('facturedate', 'date') < 1) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('facturedate')), 'errors');
    $error++;
  }
  if (GETPOST('duedate', 'date') < 1) {
    setEventMessage($langs->trans('ErrorFieldRequired', $langs->transnoentities('duedate')), 'errors');
    $error++;
  }
}

$myobject = new SwisspaymentsClass($db);
$myobject->setCodeline($_POST["codeline"], $_POST["qrcode"]);

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
          $myobject->amount = $_POST["amount"];
        }
        // Now check billNr for duplicates
        $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
        if ($resql) {
          $num = $db->num_rows($resql);
          $i = 0;
          if ($num > 0) {
            $warn++;
            $mesg = "Rechnung Nr. " . $myobject->billnr . " existiert f&uumlr diesen Lieferanten schon, bitte Rechnungsnummer &auml;ndern";
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
      // Is a QR bill
    if ($action == "createesrid")
    {
      $soc_id= $_POST["socid"];
      $sql= "select * from ".MAIN_DB_PREFIX."societe_rib where REPLACE(iban_prefix, ' ', '')='".$db->escape($myobject->iban)."' AND fk_soc=".$soc_id;
      $resql=$db->query($sql);
      if ($resql)
      {
          if ($db->num_rows($resql) >= 1)
          {
            // OK Exists
          }
          else if ($db->num_rows($resql) == 0)
          {
            $db->begin(); // Begin transaction
              $cba= new CompanyBankAccount($db);
              $cba->type="ban";
              $cba->socid= $soc_id;
              $cba->label= "QR Bill";
              $cba->bank= "QR Bill";
              $cba->iban= $myobject->iban;
              $cba->proprio= $myobject->payToName;// Lieferant Name
              $cba->owner_address= $myobject->payToAddress;
              $cba->default_rib= 1;
              $result = $cba->create($user, 0);
              if ($result < 0) {
                $mesg = $cba->error;
                $error++;
                $db->rollback(); // Rollback transaction
              } else {
                $cba->update($user);
                $db->commit(); // End transaction
              }
          }
      }
      $db->free($resql);
  
      $newESRSoc = new Swisspaymentssoc($db);
      if ($newESRSoc->fetch(null, null, null, null, $soc_id) > 0 && $newESRSoc->id) {
        dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
        if ($_POST["billnr"]) {
          $myobject->setBillno($_POST["billnr"]);
        } else {
          $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
        }
      }
      else if ($_POST["billnr"])
      {
        $newESRSoc = new Swisspaymentssoc($db);
        $newESRSoc->fk_societe = $societe->id;
        $newESRSoc->esrid = "QRBILL";
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
      
      $sql= "select * from ".MAIN_DB_PREFIX."societe_rib where REPLACE(iban_prefix, ' ', '')='".$db->escape($myobject->iban)."'";
        $resql=$db->query($sql);
        if ($resql)
        {
            if ($db->num_rows($resql) == 1)
            {
                $obj = $db->fetch_object($resql);

                $id    = $obj->rowid;
                $iban_prefix= $obj->iban_prefix;
                $fk_societe = $obj->fk_soc;
                
                //echo "<div>Found supplier (".$fk_societe.")</div>";
                if ($myobject->iban != $iban_prefix)
                {
                  //echo "<div>Updating IBAN number to clean format (old $iban_prefix new $myobject->iban)</div>";
                  $sql_upd= "update ".MAIN_DB_PREFIX."societe_rib set iban_prefix ='".$db->escape($myobject->iban)."' where rowid=".$id;
                  $db->query($sql_upd);
                }

                dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
                $societe = new Societe($db);
                $result = $societe->fetch($fk_societe);
                
                // Now check billNr for duplicates
                $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
                if ($resql) {
                  $num = $db->num_rows($resql);
                  $i = 0;
                  if ($num > 0) {
                    $warn++;
                    $mesg = "Rechnung Nr. " . $myobject->billnr . " existiert f&uumlr diesen Lieferanten schon, bitte Rechnungsnummer &auml;ndern";
                  }
                }
            }
            else if ($db->num_rows($resql) > 1)
            {
              setEventMessage("Mehrere Konten mit gleicher IBAN gefunden (".$db->num_rows($resql)."). Bitte Lieferant manuell zuweisen.", 'warnings');
            }
            else
            {
              dol_syslog(__METHOD__ . " No supplier account found for IBAN " . $myobject->iban, LOG_INFO);
            }
            $db->free($resql);
        }
        else
        {
            setEventMessage("Datenbankfehler: ".$db->lasterror(), 'errors');
        }
      
    }
    else
    {
       $sql= "select * from ".MAIN_DB_PREFIX."societe_rib where REPLACE(iban_prefix, ' ', '')='".$db->escape($myobject->iban)."'";
        $resql=$db->query($sql);
        if ($resql)
        {
            if ($db->num_rows($resql) == 1)
            {
                $obj = $db->fetch_object($resql);

                $id    = $obj->rowid;
                $iban_prefix= $obj->iban_prefix;
                $fk_societe = $obj->fk_soc;
                
                //echo "<div>Found supplier (".$fk_societe.")</div>";
                if ($myobject->iban != $iban_prefix)
                {
                  //echo "<div>Updating IBAN number to clean format (old $iban_prefix new $myobject->iban)</div>";
                  $sql_upd= "update ".MAIN_DB_PREFIX."societe_rib set iban_prefix ='".$db->escape($myobject->iban)."' where rowid=".$id;
                  $db->query($sql_upd);
                }

                dol_syslog(__METHOD__ . " matching supplier found, assigning", LOG_DEBUG);
                $societe = new Societe($db);
                $result = $societe->fetch($fk_societe);
                
                if (! $_POST["billnr"])
                {
                  $newESRSoc = new Swisspaymentssoc($db);
                  if ($newESRSoc->fetch(null, null, null, null, $fk_societe) > 0 && $newESRSoc->id) {
                    $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
                  }
                }

                // Now check billNr for duplicates
                $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
                if ($resql) {
                  $num = $db->num_rows($resql);
                  $i = 0;
                  if ($num > 0) {
                    $error++;
                    $mesg = "Rechnung Nr. " . $myobject->billnr . " existiert f&uumlr diesen Lieferanten schon, bitte Rechnungsnummer &auml;ndern";
                  }
                }

//                $resql=$db->query("select * from llx_facture_fourn where fk_soc=" . $fk_societe . " and ref_supplier='".$db->escape($qr_lines[28]) ."'");
//                if ($resql)
//                {
//                    $num = $db->num_rows($resql);
//                    $i = 0;
//                    if ($num > 0)
//                    {
//                        $warn++;
//                        $mesg= "Rechnung Nr. " . $qr_lines[28]. " existiert f&uumlr diesen Lieferanten schon, bitte Rechnungsnummer &auml;ndern";
//                    }
//                }
//                $myobject = new SwisspaymentsClass($db);
                
            }
            else if ($db->num_rows($resql) > 1)
            {
              setEventMessage("Mehrere Konten mit gleicher IBAN gefunden (".$db->num_rows($resql)."). Bitte Lieferant manuell zuweisen.", 'warnings');
            }
            else
            {
              dol_syslog(__METHOD__ . " No supplier account found for IBAN " . $myobject->iban, LOG_INFO);
            }
            $db->free($resql);
        }
        else
        {
            setEventMessage("Datenbankfehler: ".$db->lasterror(), 'errors');
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
    setEventMessage("PLZ und Ort sind erforderlich (strukturierte Adresse)", 'errors');
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
      $cba->proprio = $myobject->payToName;
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
        setEventMessage("Lieferant '" . $newSoc->name . "' wurde angelegt", 'mesgs');
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
    setEventMessage("Bitte einen g&uuml;ltigen Betrag (gr&ouml;sser als 0) erfassen", 'errors');
    $error++;
  } else {
    $myobject->amount = $amount;
  }
}

if ($error == 0 && $societe->id != 0 && ($action == "createfacture" || $action == "createesrid")) {
  $resql = $db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='" . $db->escape($myobject->billnr) . "'");
  if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;
    if ($num > 0) {
      $error++;
      $mesg = "Rechnung Nr. " . $myobject->billnr . " existiert f&uuml;r diesen Lieferanten schon";
    } else {
      $db->begin(); // Begin transaction
      // Create Facture
      $facture = new FactureFournisseur($db);
      $facture->socid = $societe->id;
      $facture->datec = getdate();
      //$facture->date= getdate();
      $facture->datef = dol_mktime(12, 0, 0, $_POST['facturedatemonth'], $_POST['facturedateday'], $_POST['facturedateyear']);
      $facture->date_echeance = dol_mktime(12, 0, 0, $_POST['duedatemonth'], $_POST['duedateday'], $_POST['duedateyear']);
      $facture->type = 0;
      $facture->cond_reglement_id = $societe->cond_reglement_supplier_id;
      $facture->mode_reglement_id = $societe->mode_reglement_supplier_id;
      // $facture->fk_account= $societe->
      $facture->amount = $myobject->amount;
      $facture->total = $myobject->amount;
      // $facture->note_private= $myobject->codeline;
      $facture->ref_supplier = $myobject->billnr;
      $facture->libelle = $myobject->billnr;
      $facture->entity = 0;
      // $facture->en

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
    $mesg = "Error duplicate check";
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

echo "<h1>Lieferantenrechnung erfassen</h1>";

$form = new Form($db);

// Only show the review step when we actually parsed a usable payment (IBAN or PC account);
// a failed parse falls back to the entry step with the error message.
$parsedOk = (!empty($myobject->iban) || !empty($myobject->pcAccount));
$inReview = !($facture && $facture->id > 0) && $parsedOk && ($action == 'createesrid' || $action == 'analyzecode' || $action == 'createsupplier');

// Wizard step indicator
$step = $inReview ? 2 : 1;
echo '<div style="margin:0 0 14px 0;font-size:1.05em;">';
echo ($step == 1 ? '<strong>&#10148; 1. QR-Code einlesen</strong>' : '<span style="opacity:.6">1. QR-Code einlesen</span>');
echo ' &nbsp;&rarr;&nbsp; ';
echo ($step == 2 ? '<strong>&#10148; 2. Lieferant &amp; Rechnung</strong>' : '<span style="opacity:.6">2. Lieferant &amp; Rechnung</span>');
echo '</div>';

// Central message display (setEventMessage entries are shown by the framework itself).
if ($error > 0 && !empty($mesg)) {
  echo dol_htmloutput_errors($mesg);
} else if ($warn > 0 && !empty($mesg)) {
  echo dol_htmloutput_mesg($mesg, null, 'warning');
}

// Renders the shared invoice detail fields (QR ref, bill nr, dates, release) inside a table.
$renderInvoiceFields = function () use ($form, $societe, $myobject, $db) {
  if ($myobject->isQRCode) {
    // Editable QR reference (stored as esrline; the QRR sent to the bank). Pre-filled
    // from the scan so a bad scan can be corrected here. Validated live in JS below.
    // A QRR is only mandatory with a QR-IBAN (CH/LI, '3' at position 5); a QR-bill on a
    // normal IBAN legitimately has no QRR (reference type SCOR or NON), so don't require it.
    $isQrIban = preg_match('/^(CH|LI)[0-9]{2}3/', strtoupper(str_replace(' ', '', (string) $myobject->iban)));
    print '<tr><td width="30%" class="' . ($isQrIban ? 'fieldrequired' : '') . '">QR-Referenz</td><td>';
    print '<input type="text" name="qrref" id="qrref" size="35" value="' . dol_escape_htmltag($myobject->refLine) . '" onkeyup="swpCheckQrref()" onchange="swpCheckQrref()"> ';
    print '<span id="qrref_msg"></span>';
    if (!$isQrIban) print '<br><span class="opacitymedium">Keine QR-IBAN &ndash; QR-Referenz optional (SCOR/ohne Referenz)</span>';
    print '</td></tr>';
  }
  print '<tr><td width="30%" class="fieldrequired">Rechnung Nr.</td><td>';
  print '<input type="text" name="billnr" id="billnr" value="' . dol_escape_htmltag($myobject->billnr) . '"></td></tr>';
  print '<tr><td class="fieldrequired">Rechnungsdatum</td><td>';
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
  print '<tr><td class="fieldrequired">Zahlbar bis</td><td>';
  $form->select_date($dueDate->format('Y-m-d'), 'duedate', 0, 0, 0, "myform");
  print '</td></tr>';
  print '<tr><td class="fieldrequired">Rechnung freigeben</td><td><input type="checkbox" name="validate" value="1"></td></tr>';
};

if ($inReview) {
  // ===== STEP 2: review parsed QR / assign supplier / create invoice =====

  // Scanned payment info
  print '<table class="border" width="100%">';
  print '<tr class="liste_titre"><td colspan="2">' . ($myobject->isQRCode ? 'QR-Rechnung' : 'Zahlung') . '</td></tr>';
  if ($myobject->iban) {
    print '<tr><td width="30%">IBAN</td><td>' . dol_escape_htmltag($myobject->iban) . '</td></tr>';
  } else {
    print '<tr><td width="30%">PC Konto</td><td>' . dol_escape_htmltag($myobject->pcAccount) . '</td></tr>';
    print '<tr><td>ESR ID</td><td>' . dol_escape_htmltag($myobject->esrID) . '</td></tr>';
  }
  if ($myobject->payToName) print '<tr><td>Empf&auml;nger</td><td>' . dol_escape_htmltag($myobject->payToName) . '</td></tr>';
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
  if (!empty($addrParts)) print '<tr><td>Adresse</td><td>' . dol_escape_htmltag(implode(', ', $addrParts)) . '</td></tr>';
  if ($myobject->hasAmount) print '<tr><td>Betrag</td><td>' . price($myobject->amount) . ' CHF</td></tr>';
  print '</table><br>';

  if ($societe->id != 0) {
    // ----- Known supplier: create invoice -----
    print '<div class="info">Bekannter Lieferant: ' . $societe->getNomUrl(1) . '</div><br>';
    print '<form method="post" name="myform">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
    print '<input type="hidden" name="socid" value="' . $societe->id . '">';
    if ($myobject->hasAmount) print '<input type="hidden" name="amount" value="' . dol_escape_htmltag($myobject->amount) . '">';
    print '<table class="border" width="100%">';
    if (!$myobject->hasAmount) print '<tr><td width="30%" class="fieldrequired">Betrag</td><td><input type="text" name="amount" value="' . dol_escape_htmltag(GETPOST('amount', 'alpha')) . '"></td></tr>';
    $renderInvoiceFields();
    print '</table><br>';
    print '<div class="center"><input type="submit" class="button" value="Rechnung erstellen"></div>';
    print '<input type="hidden" name="action" value="' . ($action == 'createesrid' ? 'createfacture' : 'createesrid') . '">';
    print '</form>';
  } else {
    // ----- Unknown supplier: assign existing OR create new -----
    print '<form method="post" name="myform">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
    if ($myobject->hasAmount) print '<input type="hidden" name="amount" value="' . dol_escape_htmltag($myobject->amount) . '">';
    print '<table class="border" width="100%">';
    print '<tr class="liste_titre"><td colspan="2">Bestehendem Lieferant zuweisen</td></tr>';
    print '<tr><td width="30%" class="fieldrequired">' . $langs->trans('Supplier') . '</td><td>';
    print $form->select_company(GETPOST('socid', 'int'), 'socid', 's.fournisseur = 1', 1);
    print '</td></tr>';
    if (!$myobject->hasAmount) print '<tr><td class="fieldrequired">Betrag</td><td><input type="text" name="amount" value="' . dol_escape_htmltag(GETPOST('amount', 'alpha')) . '"></td></tr>';
    $renderInvoiceFields();
    print '</table><br>';
    print '<div class="center"><input type="submit" class="button" value="Zuweisen &amp; Rechnung erstellen"></div>';
    print '<input type="hidden" name="action" value="' . ($action == 'createesrid' ? 'createfacture' : 'createesrid') . '">';
    print '</form>';

    // Create a new supplier from the QR data (QR bills only)
    if ($myobject->isQRCode && $myobject->iban) {
      $prefStreet = trim($myobject->payToStreet . ' ' . $myobject->payToBuildingNo);
      print '<br><form method="post">';
      print '<input type="hidden" name="token" value="' . newToken() . '">';
      echo "<input type='hidden' name='codeline' value='" . $myobject->codeline . "'>";
      print '<table class="border" width="100%">';
      print '<tr class="liste_titre"><td colspan="2">Neuen Lieferant aus QR-Daten anlegen</td></tr>';
      print '<tr><td width="30%" class="fieldrequired">Name</td><td><input type="text" name="new_name" size="50" value="' . dol_escape_htmltag($myobject->payToName) . '"></td></tr>';
      print '<tr><td>Strasse / Nr.</td><td><input type="text" name="new_street" size="50" value="' . dol_escape_htmltag($prefStreet) . '"></td></tr>';
      print '<tr><td class="fieldrequired">PLZ / Ort</td><td>';
      print '<input type="text" name="new_zip" size="8" value="' . dol_escape_htmltag($myobject->payToPostcode) . '"> ';
      print '<input type="text" name="new_town" size="30" value="' . dol_escape_htmltag($myobject->payToTown) . '"></td></tr>';
      print '<tr><td class="fieldrequired">Land</td><td><input type="text" name="new_country" size="4" value="' . dol_escape_htmltag($myobject->payToCountry ? $myobject->payToCountry : 'CH') . '"></td></tr>';
      print '<tr><td>IBAN</td><td>' . dol_escape_htmltag($myobject->iban) . '</td></tr>';
      print '</table><br>';
      print '<div class="center"><input type="submit" class="button" value="Lieferant anlegen"></div>';
      print '<input type="hidden" name="action" value="createsupplier">';
      print '</form>';
    }
  }

  // Live validation of the editable QR reference (QRR): max 27 digits + modulo-10
  // recursive check digit, mirroring isValidCheckDigit() / the payment-side gate.
  if ($myobject->isQRCode) {
    echo '<script type="text/javascript">
      function swpMod10(n){var t=[0,9,4,6,8,2,7,1,3,5],x=0;for(var i=0;i<n.length;i++){x=t[(x+parseInt(n.charAt(i),10))%10];}return (10-x)%10;}
      function swpCheckQrref(){var e=document.getElementById("qrref");if(!e)return;var v=e.value.replace(/\s/g,"");var m=document.getElementById("qrref_msg");
        if(v===""){m.innerHTML="";e.style.backgroundColor="";return;}
        var ok=/^[0-9]{1,27}$/.test(v)&&swpMod10(v.substring(0,v.length-1))===parseInt(v.charAt(v.length-1),10);
        if(ok){m.innerHTML="<span style=\'color:green\'>✓ gültig ("+v.length+" Ziffern)</span>";e.style.backgroundColor="#e6ffe6";}
        else{m.innerHTML="<span style=\'color:#cc0000\'>✗ ungültige QR-Referenz ("+v.length+" Ziffern, erwartet 27)</span>";e.style.backgroundColor="#ffe6e6";}
      }
      jQuery(document).ready(function(){swpCheckQrref();});
    </script>';
  }
} else {
  // ===== STEP 1: read a QR code =====
  if ($facture && $facture->id > 0) {
    echo '<div class="ok">Rechnung ' . $facture->getNomUrl() . ' wurde erfasst</div><br>';
  }

  print '<form method="post">';
  print '<input type="hidden" name="token" value="' . newToken() . '">';
  print '<table class="border" width="100%">';
  print '<tr><td width="30%">QR-Code</td><td><textarea name="qrcode" id="qrcode" rows="6" cols="60"></textarea></td></tr>';
  print '</table><br>';
  print '<div class="center"><input type="submit" class="button" value="Einlesen"></div>';
  print '<input type="hidden" name="action" value="analyzecode">';
  print '</form><br>';

  $actual_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  echo "<a href='" . $actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php' target='_blank'>";
  echo "<img src='mobileqr.php'><br/>";
  echo "QR-Code scannen, um per Mobiltelefon zu erfassen<br>";
  echo "</a>";

  echo '<script type="text/javascript" language="javascript">
            jQuery(document).ready(function() {
                            jQuery("#qrcode").focus();
            });
    </script>';
}

// Example 2: Adding links to objects
// The class must extend CommonObject for this method to be available
// $somethingshown = $myobject->showLinkedObjectBlock();
// End of page
llxFooter();
