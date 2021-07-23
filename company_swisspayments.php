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
$socid = $_GET["id"];
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe', $socid, '&societe');

$soc = new Societe($db);
if ($socid > 0) $soc->fetch($socid);
$form = new Form($db);

/*
 *	ACTIONS
 */
// if ($action == 'confirm_delete' && $confirm != 'yes') { $action=''; }
if ($_GET['action'] == 'delete' ) {
	$deleteid= $_GET["deleteid"];
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
        print '<h1>ESR Daten</h1>';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '  <td >Postkonto</td>';
	print '  <td >ESR ID</td>';
	print '  <td >Start Rech.Nr.</td>';
	print '  <td >Ende Rech.Nr</td>';
	print '  <td >Aktion</td></tr>';

        $resql=$db->query("select * from llx_swisspayments_soc where fk_societe=" . $socid);
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
                                        print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?action=delete&amp;deleteid='.$obj->rowid.'&amp;id='.$socid.'">'.$langs->trans("Delete").'</a>';
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
