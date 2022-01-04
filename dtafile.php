<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *	\file		dtafile.php
 *	\ingroup	swisspayments
 *	\brief		Create DTA payment file, from data previously stored by dtapayments.php
 */
require '../../main.inc.php';


global $db, $langs, $user;

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/paymentterm.class.php';
require_once(DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');
require_once(DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php');
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
require_once(DOL_DOCUMENT_ROOT.'/custom/swisspayments/lib/dtaChFile.php');

$langs->load('companies');
$langs->load('bills');
$langs->load('banks');
$langs->load('compta');

dol_include_once('/custom/swisspayments/class/swisspayments.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentssoc.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentspayh.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentspayl.class.php');


// Load translation files required by the page
$langs->load("swisspayments@swisspayments");

// Get parameters
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'showcodefield');
$myparam = GETPOST('myparam', 'alpha');
$dtaFile= null;

// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (!$user->rights->swisspayments->paydta->dopay) {
	accessforbidden();
}

$socid=GETPOST('socid','int');
$option = GETPOST('option');

// Security check
if ($user->societe_id > 0)
{
	$action = '';
	$socid = $user->societe_id;
}

if (isset($_REQUEST["payh"]))
{
    
    $payh= new Swisspaymentspayh($db);
    $result= $payh->fetch($_REQUEST["payh"]);
    if ($result >= 0)
    {
        try
        {
          $result= $payh->createDTA();
          if ($result < 0)
          {
              $error++;
              setEventMessage($payh->error, 'errors');
          }
          else
          {
              $dtaFile= $payh->dtafile;
          }
        }
        catch (Exception $e)
        {
              $error++;
              setEventMessage($e, 'errors');
        }
    }
    else
    {
        $error++;
        setEventMessage($payh->error, 'errors');
    }

    if (! $error)
    {
        //$loc = DOL_URL_ROOT.'/fourn/facture/paiement.php';
        //header('Location: '.$loc);
        //exit;
    }
}
else
{
        $error++;
        setEventMessage("Missing payment ID", 'errors');
}


/*
 * VIEW
 *
 * Put here all code to build page
 */

llxHeader('', $langs->trans('ReadESR'), '');

if ($error > 0)
{

    echo '<strong>';
    echo dol_htmloutput_errors($mesg);
    echo '</strong>';
}
else
{
    echo "<div><a href='".DOL_URL_ROOT.'/fourn/facture/paiement.php' ."'>Zur Zahlungsliste</a></div>";
    echo "<div><a href='".DOL_URL_ROOT.'/document.php?modulepart=swisspayments&file=dtafiles/'. $dtaFile ."'>DTA File herunterladen</a></div>";
}

// End of page
$db->close();
llxFooter();
