<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *	\file       htdocs/swisspayments/company_swisspayments.php
 *	\ingroup    swisspayments
 *	\brief      Company details for swiss payments */

$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require 'class/swisspayments.class.php';
require 'class/swisspaymentssoc.class.php';

$langs->load("companies");
$langs->load("commercial");
$langs->load("admin");
$langs->load("products");
$langs->load("swisspayments@swisspayments");

// Security check
$socid = GETPOST('id', 'int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid, '&societe');

// Apply any pending DB schema migration after a plain file/zip update (no module
// disable/re-enable needed). No-op once the schema-version constant is up to date.
swisspayments_check_db_version($db, $conf);

$soc = new Societe($db);
if ($socid > 0) $soc->fetch($socid);
$form = new Form($db);

/*
 *	ACTIONS
 */
// if ($action == 'confirm_delete' && $confirm != 'yes') { $action=''; }
// Delete is a POST action with a CSRF token (checked by main.inc.php).
if (GETPOST('action', 'aZ09') == 'delete') {
	$deleteid= GETPOST('deleteid', 'int');
        $swp= new Swisspaymentssoc($db);
        if ($swp->fetch($deleteid))
        {
            $result= $swp->delete($user);
            if ($result < 0) {$error++; dol_print_error($db,$camm->error);}
            else {$msg = $langs->trans("MAJOk");}
        }
}

/*
 *	VIEW
 */
 
$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('',"Swisspayments",$help_url);

$head = societe_prepare_head($soc);
dol_fiche_head($head, 'Swisspayments', 'Swisspayments',0,'company');

dol_htmloutput_mesg($msg, null, 'valid');
	
    print '<table class="border" width="100%">';
    print '<tr><td width="20%">'.$langs->trans('ThirdPartyName').'</td>';
    print '<td colspan="3">';
    print $form->showrefnav($soc,'socid','',($user->societe_id?0:1),'rowid','nom');
    print '</td></tr>';
    if ($soc->client) {
        print '<tr><td>';
        print $langs->trans('CustomerCode').'</td><td colspan="3">';
        print $soc->code_client;
        if ($soc->check_codeclient() <> 0) print ' <font class="error">('.$langs->trans("WrongCustomerCode").')</font>';
        print '</td></tr>';
    }
    if ($soc->fournisseur) {
        print '<tr><td>';
        print $langs->trans('SupplierCode').'</td><td colspan="3">';
        print $soc->code_fournisseur;
        if ($soc->check_codefournisseur() <> 0) print ' <font class="error">('.$langs->trans("WrongSupplierCode").')</font>';
        print '</td></tr>';
    }
    print "</table>";
	
	print "<br/> <br/>";
	
	$var = false;
        print '<h1>'.$langs->trans('SwpEsrData').'</h1>';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '  <td >'.$langs->trans('SwpPostalAccount').'</td>';
	print '  <td >'.$langs->trans('SwpEsrId').'</td>';
	print '  <td >'.$langs->trans('SwpStartInvoiceNo').'</td>';
	print '  <td >'.$langs->trans('SwpEndInvoiceNo').'</td>';
	print '  <td >'.$langs->trans('SwpAction').'</td></tr>';

        $resql=$db->query("select * from ".MAIN_DB_PREFIX."swisspayments_soc where fk_societe=" . ((int) $socid) . " AND entity IN (".getEntity('swisspaymentssoc').")");
        if ($resql)
        {
                $num = $db->num_rows($resql);
                $i = 0;
                if ($num)
                {
                        while ($i < $num)
                        {
                                $obj = $db->fetch_object($resql);
                                if ($obj)
                                {
                                        // You can use here results
                                        print "<tr>";
                                        print "<td>" . SwisspaymentsClass::formatPCAccount($obj->pcaccount) . "</td>";
                                        print "<td>" . $obj->esrid . "</td>";
                                        print "<td>" . $obj->startorderno . "</td>";
                                        print "<td>" . $obj->endorderno . "</td>";
                                        print "<td>";
                                        // POST + CSRF token instead of a GET link (prevents CSRF deletes).
                                        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'?id='.((int) $socid).'" style="display:inline">';
                                        print '<input type="hidden" name="token" value="'.newToken().'">';
                                        print '<input type="hidden" name="action" value="delete">';
                                        print '<input type="hidden" name="deleteid" value="'.((int) $obj->rowid).'">';
                                        print '<input type="submit" class="butActionDelete" value="'.dol_escape_htmltag($langs->trans("Delete")).'">';
                                        print '</form>';
                                        print "</td>";
                                        print "</td></tr>";
                                }
                                $i++;
                        }
                }
        }        
	print "</table>";
	
// End of page
llxFooter();
$db->close();
	
?>
