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
      $mesg = "Found entry, assign";
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
      $mesg = "Not found, create?";
      if ($societe->id != 0) {
        $newESRSoc = new Swisspaymentssoc($db);
        $newESRSoc->fk_societe = $societe->id;
        $newESRSoc->esrid = $_POST["esrid"];
        $newESRSoc->clientno = $_POST["clientno"];
        $newESRSoc->pcaccount = $_POST["pcAccount"];
        $newESRSoc->startorderno = strpos($myobject->refLine, $_POST["billnr"]);
        $newESRSoc->endorderno = $newESRSoc->startorderno + strlen($_POST["billnr"]);
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
        $mesg = "Found entry, assign";
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
        $newESRSoc->startorderno = strpos($myobject->refLine, $_POST["billnr"]);
        $newESRSoc->endorderno = $newESRSoc->startorderno + strlen($_POST["billnr"]);
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

                $mesg = "Found entry, assign";
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
              echo "<div>Multiple accounts found with the same iban number (".$db->num_rows($resql).")</div>";
            }
            else
            {
              echo "<div>No account found with this iban number (".$myobject->iban.")</div>";
            }
            $db->free($resql);
        }
        else
        {
            echo "<div class='error'>Database error ".$db->lasterror()."</div>";
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
                  echo "<div>Updating IBAN number to clean format (old $iban_prefix new $myobject->iban)</div>";
                  $sql_upd= "update ".MAIN_DB_PREFIX."societe_rib set iban_prefix ='".$db->escape($myobject->iban)."' where rowid=".$id;
                  $db->query($sql_upd);
                }

                $mesg = "Found entry, assign";
                $societe = new Societe($db);
                $result = $societe->fetch($fk_societe);
                
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
              echo "<div>Multiple accounts found with the same iban number (".$db->num_rows($resql).")</div>";
            }
            else
            {
              echo "<div>No account found with this iban number (".$myobject->iban.")</div>";
            }
            $db->free($resql);
        }
        else
        {
            echo "<div class='error'>Database error ".$db->lasterror()."</div>";
        }
    }
  }
} else {
  // Creation NOT OK
  if ($_POST["codeline"]) {
    $mesg = $myobject->error;
    $error++;
  } else {
    // No codeline, 1. call?
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
          $factESR->esrline = $myobject->refLine;
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

if ($facture && $facture->id > 0 && $facture->statut == 0) {
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

if (!($facture && $facture->id > 0) && ($action == 'createesrid' || $action == 'analyzecode')) {
  if ($societe->id == 0) {
    if ($myobject->isESR) {
      echo "<h2>Unbekannter ESR Teilnehmer</h2>";
    } else {
      echo "<h2>Unbekannter IBAN Teilnehmer</h2>";
    }
  } else {
    if ($myobject->isESR) {
      echo "<h2>Bekannter ESR Teilnehmer</h2>";
    } else {
      echo "<h2>Bekannter IBAN Teilnehmer</h2>";
    }
  }
  echo "<form method='post' name='myform'>";
  echo "<table>";
  if ($myobject->iban)
  {
    echo "<tr><td>IBAN:</td><td>" . $myobject->iban . "</td></tr>";
  }
  else
  {
    echo "<tr><td>PC Konto:</td><td>" . $myobject->pcAccount . "</td></tr>";
    echo "<tr><td>ESR ID:</td><td>" . $myobject->esrID . "</td></tr>";
  }
  if ($myobject->hasAmount) {
    echo "<tr><td>Betrag:</td><td>" . price($myobject->amount) . "</td></tr>";
  } else {
    echo "<tr><td>Betrag:</td><td><input type='text' name='amount' ></td></tr>";
  }
  // Third party
  print '<tr><td class="fieldrequired">' . $langs->trans('Supplier') . '</td>';
  print '<td>';

  if ($societe->id != 0) {
    print $societe->getNomUrl(1);
    print '<input type="hidden" name="socid" value="' . $societe->id . '">';
  } else {
    print $form->select_company(GETPOST('socid', 'int'), 'socid', 's.fournisseur = 1', 1);
  }
  print '</td></tr>';
  if ($societe->id != 0) {
    print '<tr><td class="fieldrequired">Rechnung Nr.</td><td>';
    print '<input type="text" name="billnr" id="billnr" value="' . $myobject->billnr . '">';
    print '</td></tr>';
  } else {
    print '<tr><td>Codezeile</td><td>Rechnungnummer markieren, inkl. f&uuml;hrende Nullen<br><pre>' . $myobject->refLine . '</pre></td></tr>';
    print '<tr><td class="fieldrequired">Rechnung Nr.</td><td>';
    print '<input type="text" name="billnr" id="billnr"  value="' . $myobject->billnr . '">';
    print '</td></tr>';
  }
  print '<tr><td class="fieldrequired">Rechungsdatum</td><td>';
  $form->select_date('', 'facturedate', 0, 0, 0, "myform");
  print '</td></tr>';
  print '<tr><td class="fieldrequired">Zahlbar bis</td><td>';
  $nDays = 30;
  $condID = $societe->cond_reglement_supplier_id;
  if ($condID) {
    $payTerm = new PaymentTerm($db);
    $payTerm->fetch($condID);
    $nDays = $payTerm->nbjour;
    if ($nDays == null) {
      $nDays = 30;
    }
  }
  $dueDate = new DateTime();
  $dueDate->add(new DateInterval('P' . $nDays . 'D'));
  $form->select_date($dueDate->format('Y-m-d'), 'duedate', 0, 0, 0, "myform");
  print '</td></tr>';
  print '<tr><td class="fieldrequired">Rechnung freigeben</td><td>';
  print '<input type="checkbox" name="validate" value="1">';
  print '</td></tr><tr><td>';
  echo "<input type='hidden' name='codeline' id='codeline' value='" . $myobject->codeline . "'>";
  echo "<input type='hidden' name='pcAccount' id='pcAccount' value='" . $myobject->pcAccount . "'>";
  echo "<input type='hidden' name='esrid' id='esrid' value='" . $myobject->esrID . "'>";
  echo "<input type='hidden' name='codeline' id='codeline' value='" . $myobject->codeline . "'>";
  if ($myobject->hasAmount) {
    echo "<input type='hidden' name='amount' id='amount' value='" . $myobject->amount . "'>";
  }
  if ($societe->id == 0) {
    echo "<input type='submit' value='Lieferant zuweisen' >";
  } else {
    echo "<input type='submit' value='Rechnung erstellen' >";
  }
  if ($action == 'createesrid') {
    echo "<input type='hidden' name='action' value='createfacture' >";
  } else {
    echo "<input type='hidden' name='action' value='createesrid' >";
  }
  echo "</td></tr>";
  echo "</form>";
  if ($societe->id == 0) {
    echo '<script type="text/javascript" language="javascript">
                $(function(){
                    $(document.body).bind("mouseup", function(e){
                        var selection;

                        if (window.getSelection) {
                          selection = window.getSelection();
                        } else if (document.selection) {
                          selection = document.selection.createRange();
                        }

                        var sStr= selection.toString();
                        if (sStr && sStr.length >0)
                        {
                            document.getElementById("billnr").value= sStr;
                        }
                    });
                });
        </script>';
  }
  if ($warn > 0) {
    echo '<strong>';
    echo dol_htmloutput_mesg($mesg, null, 'warning');
    echo '</strong>';
  }
} else {
  if ($error > 0) {
    echo '<strong>';
    echo dol_htmloutput_errors($mesg);
    echo '</strong>';
  } else if ($facture && $facture->id > 0) {
    echo '<strong>Rechnung ' . $facture->getNomUrl() . ' wurde erfasst<br></strong><br>';
  }

  echo "<form method='post'>";
  echo "ESR Codierzeile:";
  echo "<input type='text' width='30' name='codeline' id='codeline'>";
  echo "<input type='submit' value='Einlesen' >";
  echo "<input type='hidden' name='action' value='analyzecode' >";
  echo "</form>";

  $actual_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
  //$actual_host= "http://192.168.200.140";

  echo "<a href='" . $actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php' target='_blank'>";
  echo "<img src='mobileqr.php'><br/>";
  echo "Scan this qr code to scan via mobile phone<br>";
  echo "</a>";

  echo '<script type="text/javascript" language="javascript">
            jQuery(document).ready(function() {

                            jQuery("#codeline").focus();
            });
    </script>';
}

// Example 2: Adding links to objects
// The class must extend CommonObject for this method to be available
// $somethingshown = $myobject->showLinkedObjectBlock();
// End of page
llxFooter();
