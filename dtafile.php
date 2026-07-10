<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *	\file		dtafile.php
 *	\ingroup	swisspayments
 *	\brief		Create DTA payment file, from data previously stored by dtapayments.php
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
dol_include_once('/custom/swisspayments/lib/dtaChFile.php');

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

// Apply any pending DB schema migration after a plain file/zip update (no module
// disable/re-enable needed). No-op once the schema-version constant is up to date.
swisspayments_check_db_version($db, $conf);

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
    // Access scoping: fetch() already restricts to the current entity. A batch may only
    // be (re)generated/downloaded by the user who created it, or by an admin. This closes
    // the IDOR where any paydta user could grab another user's bank file by changing the id.
    if ($result >= 0 && (empty($payh->id)
            || ($payh->fk_user_author && $payh->fk_user_author != $user->id && empty($user->admin))))
    {
        accessforbidden();
    }
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
        setEventMessage($langs->trans('SwpMissingPaymentId'), 'errors');
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
    $downloadUrl = DOL_URL_ROOT.'/document.php?modulepart=swisspayments&file='.urlencode('dtafiles/'.$dtaFile);

    // Prominent download button with a download icon for the generated bank payment file.
    echo '<div class="center" style="margin:18px 0;">';
    echo '<a class="butAction" href="'.$downloadUrl.'">';
    echo img_picto($langs->trans('SwpDownloadPaymentFile'), 'download', 'class="paddingright"');
    echo dol_escape_htmltag($langs->trans('SwpDownloadPaymentFile')).' ('.dol_escape_htmltag($dtaFile).')';
    echo '</a>';
    echo '</div>';

    echo "<div class='center'><a href='".DOL_URL_ROOT."/fourn/paiement/list.php?leftmenu=suppliers_bills_payment'>".$langs->trans('SwpBackToPaymentList')."</a></div>";
}

// End of page
$db->close();
llxFooter();
