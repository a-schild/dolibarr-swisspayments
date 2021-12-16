<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *	\file		swisspayments.php
 *	\ingroup	swisspayments
 *	\brief		Form to enter new PVR bills
 */
require '../../main.inc.php';

global $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT .'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/paymentterm.class.php';
require_once(DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');
require_once(DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php');

dol_include_once('/swisspayments/class/swisspayments.class.php');
dol_include_once('/swisspayments/class/swisspaymentssoc.class.php');

// Load translation files required by the page
$langs->load("swisspayments@swisspayments");

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'showcodefield');
$myparam = GETPOST('myparam', 'alpha');

// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (! $user->rights->swisspayments->invoices->create) accessforbidden();

// Default action
if (empty($action) && empty($id) && empty($ref)) {
	$action='showcodefield';
}

// Load object if id or ref is provided as parameter
$object = new SwisspaymentsClass($db);
if (($id > 0 || ! empty($ref)) && $action != 'add') {
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

if ($action == "createesrid")
{
    if (GETPOST('socid','int')<1)
    {
	setEventMessage($langs->trans('ErrorFieldRequired',$langs->transnoentities('Supplier')), 'errors');
    	$error++;
    }
    else
    {
        $societe=new Societe($db);
        $societe->fetch(GETPOST('socid','int'));
    }
    if (GETPOST('facturedate','date')<1)
    {
	setEventMessage($langs->trans('ErrorFieldRequired',$langs->transnoentities('facturedate')), 'errors');
    	$error++;
    }
    if (GETPOST('duedate','date')<1)
    {
	setEventMessage($langs->trans('ErrorFieldRequired',$langs->transnoentities('duedate')), 'errors');
    	$error++;
    }
}

$myobject = new SwisspaymentsClass($db);
$myobject->setCodeline($_POST["codeline"]);
// dol_syslog(__METHOD__ . " " . $_POST["codeline"], LOG_INFO);

$result = $myobject->validateCode($user);
if ($result > 0) {
    // Validation passed
    if (isset($_POST["billnr"]))
    {
        $myobject->billnr= $_POST["billnr"];
    }
    if (isset($_POST["amount"]))
    {
        $myobject->amount= $_POST["amount"];
    }
    $factESR= new Swisspaymentsfactf($db);
    $factESR->fetch(null, null, $myobject->getCodeline());
    if (isset($factESR->id))
    {
	setEventMessage("Dieser ESR Beleg wurde schon erfasst", 'errors');
    	$error++;
    }
    else
    {
        $newESRSoc= new Swisspaymentssoc($db);
        if ($newESRSoc->fetch(null, null, $myobject->pcAccount, $myobject->esrID) > 0
                && $newESRSoc->id)
        {
            $mesg= "Found entry, assign";
            $societe=new Societe($db);
            $societe->fetch($newESRSoc->fk_societe);
            if (!isset($_POST["billnr"]))
            {
                $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
                //$myobject->billnr= $_POST["billnr"];
            }
        }
        else
        {
            $mesg= "Not found, create?";
            if ($societe->id != 0)
            {
                $newESRSoc= new Swisspaymentssoc($db);
                $newESRSoc->fk_societe= $societe->id;
                $newESRSoc->esrid= $_POST["esrid"];
                $newESRSoc->clientno= $_POST["clientno"];
                $newESRSoc->pcaccount= $_POST["pcAccount"];
                $newESRSoc->startorderno= strpos($myobject->refLine, $_POST["billnr"]);
                $newESRSoc->endorderno= $newESRSoc->startorderno+strlen($_POST["billnr"]);
                $newESRSoc->create($user);
                if (!isset($_POST["billnr"]))
                {
                    $myobject->findBillno($newESRSoc->startorderno, $newESRSoc->endorderno);
                    //$myobject->billnr= $_POST["billnr"];
                }
            }
        }
    }
}
else
{
        // Creation NOT OK
    if ($_POST["codeline"])
    {
        $mesg = $myobject->error;
        $error++;
    }
    else
    {
        // No codeline, 1. call?
    }
}

if ($error == 0 && $societe->id != 0 && ($action == "createfacture" || $action == "createesrid"))
{
    $resql=$db->query("select * from llx_facture_fourn where fk_soc=" . $societe->id . " and ref_supplier='".$db->escape($myobject->billnr) ."'");
    if ($resql)
    {
        $num = $db->num_rows($resql);
        $i = 0;
        if ($num > 0)
        {
            $error++;
            $mesg= "Fehler, Doppelte Rechnungnummer, Nr. " . $myobject->billnr. " schon vorhanden für diesen Lieferanten";
            //$action == 'analyzecode';
        }
        else
        {
            $db->begin(); // Begin transaction
            // Create Facture
            $facture = new FactureFournisseur($db);
            $facture->socid= $societe->id;
            $facture->datec= getdate();
            //$facture->date= getdate();
            $facture->datef= dol_mktime(12, 0 , 0, $_POST['facturedatemonth'], $_POST['facturedateday'], $_POST['facturedateyear']);
            $facture->date_echeance= dol_mktime(12, 0 , 0, $_POST['duedatemonth'], $_POST['duedateday'], $_POST['duedateyear']);
            $facture->type = 0;
            $facture->cond_reglement_id= $societe->cond_reglement_supplier_id;
            $facture->mode_reglement_id= $societe->mode_reglement_supplier_id;
            // $facture->fk_account= $societe->
            $facture->amount= $myobject->amount;
            $facture->total= $myobject->amount;
            // $facture->note_private= $myobject->codeline;
            $facture->ref_supplier= $myobject->billnr;
            $facture->libelle=  $myobject->billnr;
            $facture->entity= 0;
            // $facture->en

            if ($facture->create($user) > 0)
            {
                $facture->addline("Lieferantenrechnung", $myobject->amount, NULL, NULL, NULL, 1);
                //$facture->update($user);
                if (GETPOST('validate','int') == '1')
                {
                    $facture->validate($user);
                }
                $db->commit(); // End transaction
            }
            else
            {
                $error++;
                $mesg= $facture->error;
                $db->rollback(); // Rollback transaction

            }
        }
    }
    else
    {
        $error++;
        $mesg= "Error duplicate check";
    }
}


/*
 * VIEW
 *
 * Put here all code to build page
 */

llxHeader('', $langs->trans('ReadESR'), '');

echo "<h1>Lieferantenrechnung erfassen</h1>";

$form = new Form($db);

if (!($facture && $facture->id > 0) && ($action == 'createesrid' || $action == 'analyzecode')&& $error == 0)
{
    if ($societe->id == 0) {
        echo "<h2>Unbekannter ESR Teilnehmer</h2>";
    }
    else
    {
        echo "<h2>Bekannter ESR Teilnehmer</h2>";
    }
    echo "<form method='post' name='myform'>";
    echo "<table>";
    echo "<tr><td>PC Konto:</td><td>" . $myobject->pcAccount . "</td></tr>";
    echo "<tr><td>ESR ID:</td><td>" . $myobject->esrID. "</td></tr>";
    if ($myobject->hasAmount)
    {
        echo "<tr><td>Betrag:</td><td>" . price($myobject->amount);
        echo "<input type='hidden' name='amount' id='amount' value='". $myobject->amount."'>";
        echo "</td></tr>";
    }
    else
    {
        echo '<tr><td class="fieldrequired">Betrag:</td><td><input type="text" name="amount" id="amount"></td></tr>';
    }
    // Third party
    print '<tr><td class="fieldrequired">'.$langs->trans('Supplier').'</td>';
    print '<td>';

    if ($societe->id != 0)
    {
        print $societe->getNomUrl(1);
        print '<input type="hidden" name="socid" value="'.$societe->id.'">';
    }
    else
    {
        print $form->select_company(GETPOST('socid','int'),'socid','s.fournisseur = 1',1);
    }
    print '</td></tr>';
    if ($societe->id != 0)
    {
        print '<tr><td class="fieldrequired">Rechnung Nr.</td><td>';
        print '<input type="text" name="billnr" id="billnr" value="' . $myobject->billnr.'">';
        print '</td></tr>';
    }
    else
    {
        print '<tr><td>Codezeile</td><td>Rechnungnummer markieren, inkl. führende Nullen<br><pre>' . $myobject->refLine . '</pre></td></tr>';
        print '<tr><td class="fieldrequired">Rechnung Nr.</td><td>';
        print '<input type="text" name="billnr" id="billnr"  value="' . $myobject->billnr.'">';
        print '</td></tr>';
    }
    print '<tr><td class="fieldrequired">Rechungsdatum</td><td>';
    $form->select_date('','facturedate',0,0,0,"myform");
    print '</td></tr>';
    print '<tr><td class="fieldrequired">Zahlbar bis</td><td>';
    $nDays= 30;
    $condID= $societe->cond_reglement_supplier_id;
    if ($condID)
    {
        $payTerm= new PaymentTerm($db);
        $payTerm->fetch($condID);
        $nDays= $payTerm->nbjour;
    }
    $dueDate= new DateTime();
    $dueDate->add(new DateInterval('P'.$nDays.'D'));
    $form->select_date($dueDate->format('Y-m-d'),'duedate',0,0,0,"myform");
    print '</td></tr>';
    print '<tr><td class="fieldrequired">Rechnung freigeben</td><td>';
    print '<input type="checkbox" name="validate" value="1">';
    print '</td></tr><tr><td>';
    echo "<input type='hidden' name='codeline' id='codeline' value='". $myobject->codeline."'>";
    echo "<input type='hidden' name='pcAccount' id='pcAccount' value='". $myobject->pcAccount."'>";
    echo "<input type='hidden' name='esrid' id='esrid' value='". $myobject->esrID."'>";
    echo "<input type='hidden' name='codeline' id='codeline' value='". $myobject->codeline."'>";
    if ($societe->id == 0) {
        echo "<input type='submit' value='Lieferant zuweisen' >";
    }
    else
    {
        echo "<input type='submit' value='Rechnung erstellen' >";
    }
    if ($action == 'createesrid')
    {
        echo "<input type='hidden' name='action' value='createfacture' >";
    }
    else
    {
        echo "<input type='hidden' name='action' value='createesrid' >";
    }
    echo "</td></tr>";
    echo "</form>";
    if ($societe->id == 0)
    {
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
}
else
{
    if ($error > 0)
    {
        echo '<strong>';
        echo dol_htmloutput_errors($mesg);
        echo '</strong>';
    }
    else if ($facture && $facture->id > 0)
    {
        echo '<strong>Rechnung '.$facture->getNomUrl().' wurde erfasst<br></strong><br>';
    }

    echo "<form method='post'>";
    echo "Codierzeile:";
    echo "<input type='text' width='30' name='codeline' id='codeline'>";
    echo "<input type='submit' value='Einlesen' >";
    echo "<input type='hidden' name='action' value='analyzecode' >";
    echo "</form>";

    // Put here content of your page
    // Example 1: Adding jquery code
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
