<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild
 *
 */

/**
 *	\file		dtapayments.php
 *	\ingroup	swisspayments
 *	\brief		Create DTA payment items
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

// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (! $user->rights->swisspayments->paydta) accessforbidden();

$socid=GETPOST('socid','int');
$option = GETPOST('option');

// Security check
if ($user->societe_id > 0)
{
	$action = '';
	$socid = $user->societe_id;
}

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');

$search_ref = GETPOST('search_ref','alpha');
$search_ref_supplier = GETPOST('search_ref_supplier','alpha');
$search_company = GETPOST('search_company','alpha');
$search_amount_no_tax = GETPOST('search_amount_no_tax','alpha');
$search_amount_all_tax = GETPOST('search_amount_all_tax','alpha');

$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield="f.date_lim_reglement";
if (! $sortorder) $sortorder="ASC";

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) // Both test are required to be compatible with all browsers
{
	$search_ref="";
	$search_ref_supplier="";
	$search_company="";
	$search_amount_no_tax="";
	$search_amount_all_tax="";
}


if (isset($_REQUEST["createDTAPay"]))
{
    $factIDS= explode(";", $_REQUEST["factures"]);
    //dol_syslog("Factures to pay: " . var_export($factIDS, true));
    $accountid= GETPOST('accountid');
    
    $dtaPayments;
    
    $datepaye = dol_mktime(12, 0, 0, GETPOST('remonth'), GETPOST('reday'), GETPOST('reyear'));
    $db->begin();
    
    $payh= new Swisspaymentspayh($db);
    $payh->payident= $_POST['num_paiement'];
    $res= $payh->create($user);
    if ($res < 0)
    {
        setEventMessage($payh->error, 'errors');
        $error++;
    }
    else
    {
        foreach ($factIDS as $factid )
        {
            $amount= $_POST['amount_' . $factid];
            if ($amount > 0)
            {
                // Creation de la ligne paiement
                $paiement = new PaiementFourn($db);
                if (is_int($datepaye) )
                {
                    $paiement->datepaye     = $datepaye;
                }
                else
                {
                    $paiement->datepaye     = $_POST['datelimite_' . $factid];;
                }
                $paiement->paiementid   = $_POST['paiementid']; // Type of payment
                $paiement->num_paiement = $_POST['num_paiement'];
                $paiement->amounts      = [$factid => $amount];   // Array of amounts
                $paiement->note         = $_POST['comment'] . "-" . $factid;
                //$paiement->facid        = $factid;

                //dol_syslog("Factures to pay: " . var_export($paiement, true));
                if (! $error)
                {
                    $paiement_id = $paiement->create($user, 1); // Close facture if completly payed
                    if ($paiement_id < 0)
                    {
                        setEventMessage($paiement->error, 'errors');
                        $error++;
                    }
                    else
                    {
                        $dtaPayments[$factid]= $paiement_id;
                    }
                }

                if (! $error)
                {
                    $result=$paiement->addPaymentToBank($user,'payment_supplier','(SupplierInvoicePayment)',$accountid,'','');
                    if ($result < 0)
                    {
                        setEventMessage($paiement->error, 'errors');
                        $error++;
                    }
                    else
                    {
                        $payl= new Swisspaymentspayl($db);
                        $payl->fk_payh= $payh->id;
                        $payl->fk_payementfourn= $paiement_id;
                        $result= $payl->create($user);
                        if ($result < 0)
                        {
                            setEventMessage($payl->error, 'errors');
                            $error++;
                        }
                    }
                }
            }
        }
    }

    if (! $error)
    {
        $db->commit();

        dol_syslog("Payments to include in DTA file: " . var_export($dtaPayments, true));
        $qParams= "payh=" . $payh->id;
        $loc = DOL_URL_ROOT.'/custom/swisspayments/dtafile.php?'.$qParams;
        header('Location: '.$loc);
        exit;
    }
    else
    {
        $db->rollback();
    }
}

/*
 * View
 */


$now=dol_now();

llxHeader('',$langs->trans("BillsSuppliersUnpaid"));

$title=$langs->trans("BillsSuppliersUnpaid");

?>
<script language="javascript" type="text/javascript">
	function checkAll() {
		jQuery(".checkElement").attr('checked', true);
		var valeur = parseFloat(jQuery("#totalAmount")[0].innerHTML);
		jQuery("#selectedAmount")[0].innerHTML = valeur;
		jQuery("#selectedAmountHuman")[0].innerHTML = Math.round((valeur) * 100) / 100;
	}

	function checkNone() {
		jQuery(".checkElement").attr('checked', false);
		jQuery("#selectedAmount")[0].innerHTML = "0";
		jQuery("#selectedAmountHuman")[0].innerHTML = "0";
	}
	
	function recalculeMontant(cb, montant) {
	 var valeur = parseFloat(jQuery("#selectedAmount")[0].innerHTML);
		if (cb.checked) {
			jQuery("#selectedAmount")[0].innerHTML = valeur + montant;
			jQuery("#selectedAmountHuman")[0].innerHTML = Math.round((valeur + montant) * 100) / 100;
		}
		else if (!cb.checked) {
			jQuery("#selectedAmount")[0].innerHTML = valeur - montant;
			jQuery("#selectedAmountHuman")[0].innerHTML = Math.round((valeur - montant) * 100)/100;
		}
	}
</script>
<?php

$facturestatic=new FactureFournisseur($db);
$companystatic=new Societe($db);

if ($user->rights->fournisseur->facture->lire)
{
	$sql = "SELECT s.rowid as socid, s.nom as name,";
	$sql.= " f.rowid, f.ref, f.ref_supplier, f.total_ht, f.total_ttc,";
	$sql.= " f.datef as df, f.date_lim_reglement as datelimite, ";
	$sql.= " f.paye as paye, f.rowid as facid, f.fk_statut";
	$sql.= " ,sum(pf.amount) as am";
	$sql.= " ,f.total_ht-IFNULL(sum(pf.amount),0) as stilltopay";
	$sql.= " ,sff.esrpartynr, sr.rowid as ribid, sr.iban_prefix, sr.bic ";
	if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", sc.fk_soc, sc.fk_user ";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as f on  f.fk_soc = s.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf ON f.rowid=pf.fk_facturefourn ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."swisspayments_factf as sff ON f.rowid=sff.fk_factid ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_rib sr ON s.rowid=sr.fk_soc and default_rib=1 ";
	$sql.= " WHERE f.entity = ".$conf->entity;
	$sql.= " AND f.fk_soc = s.rowid";
	$sql.= " AND f.paye = 0 AND f.fk_statut = 1";
	if ($option == 'late') $sql.=" AND f.date_lim_reglement < '".$db->idate(dol_now() - $conf->facture->fournisseur->warning_delay)."'";
	if (! $user->rights->societe->client->voir && ! $socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	if ($socid) $sql .= " AND s.rowid = ".$socid;

	if (GETPOST('filtre'))
	{
		$filtrearr = explode(",", GETPOST('filtre'));
		foreach ($filtrearr as $fil)
		{
			$filt = explode(":", $fil);
			$sql .= " AND " . $filt[0] . " = " . $filt[1];
		}
	}

	if ($search_ref)
	{
		$sql .= " AND f.ref LIKE '%".$search_ref."%'";
	}
	if ($search_ref_supplier)
	{
		$sql .= " AND f.ref_supplier LIKE '%".$search_ref_supplier."%'";
	}

	if ($search_company)
	{
		$sql .= " AND s.nom LIKE '%".$search_company."%'";
	}

	if ($search_amount_no_tax)
	{
		$sql .= " AND f.total_ht = '".$search_amount_no_tax."'";
	}

	if ($search_amount_all_tax)
	{
		$sql .= " AND f.total_ttc = '".$search_amount_all_tax."'";
	}

	if (dol_strlen(GETPOST('sf_re')) > 0)
	{
		$sql .= " AND f.ref_supplier LIKE '%".GETPOST('sf_re')."%'";
	}

	$sql.= " GROUP BY s.rowid, s.nom, f.rowid, f.ref, f.ref_supplier, f.total_ht, f.total_ttc, f.datef, f.date_lim_reglement, f.paye, f.fk_statut";
	if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", sc.fk_soc, sc.fk_user ";
        // $sql .= " having esrpartynr is not null or (iban_prefix is not null and bic is not null) ";
	$sql.=$db->order($sortfield,$sortorder);
	if (! in_array("f.ref_supplier",explode(',',$sortfield))) $sql.= ", f.ref_supplier DESC";
        
	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);

		if ($socid)
		{
			$soc = new Societe($db);
			$soc->fetch($socid);
		}

		$param ='';
		if ($socid) $param.="&socid=".$socid;

		if ($search_ref)         	$param.='&amp;search_ref='.urlencode($search_ref);
		if ($search_ref_supplier)	$param.='&amp;search_ref_supplier='.urlencode($search_ref_supplier);
		if ($search_company)     	$param.='&amp;search_company='.urlencode($search_company);
		if ($search_amount_no_tax)	$param.='&amp;search_amount_no_tax='.urlencode($search_amount_no_tax);
		if ($search_amount_all_tax) $param.='&amp;search_amount_all_tax='.urlencode($search_amount_all_tax);

		$param.=($option?"&option=".$option:"");
		if (! empty($late)) $param.='&late='.urlencode($late);
		$urlsource=str_replace('&amp;','&',$param);

		$titre=($socid?$langs->trans("BillsSuppliersUnpaidForCompany",$soc->name):$langs->trans("BillsSuppliersUnpaid"));

		if ($option == 'late')
                {
                    $titre.=' ('.$langs->trans("Late").')';
                }
                else
                {
                    $titre.=' ('.$langs->trans("All").')';
                }

		$link='';
		if (empty($option)) $link='<a href="'.$_SERVER["PHP_SELF"].'?option=late'.($socid?'&socid='.$socid:'').'">'.$langs->trans("ShowUnpaidLateOnly").'</a>';
		elseif ($option == 'late') $link='<a href="'.$_SERVER["PHP_SELF"].'?'.($socid?'&socid='.$socid:'').'">'.$langs->trans("ShowUnpaidAll").'</a>';
		print_fiche_titre($titre,$link);

		print_barre_liste('','',$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',0);	// We don't want pagination on this page
		$i = 0;
                $form=new Form($db);
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" name="createDTA">';

		print '<table class="liste" width="100%">';
		print '<tr class="liste_titre">';
		print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"f.rowid","",$param,"",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("RefSupplier"),$_SERVER["PHP_SELF"],"f.ref_supplier","",$param,"",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"],"f.datef","",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("DateDue"),$_SERVER["PHP_SELF"],"f.date_lim_reglement","",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Company"),$_SERVER["PHP_SELF"],"s.nom","",$param,"",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("AmountHT"),$_SERVER["PHP_SELF"],"f.total_ht","",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("AmountTTC"),$_SERVER["PHP_SELF"],"f.total_ttc","",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("AlreadyPaid"),$_SERVER["PHP_SELF"],"am","",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Noch offen"),$_SERVER["PHP_SELF"],"am","",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Bezahlen"),$_SERVER["PHP_SELF"],"am","",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"],"fk_statut,paye,am","",$param,'align="right"',$sortfield,$sortorder);
		//print_liste_field_titre("Bezahlen");
		print "</tr>\n";

		// Lines with filter fields
		print '<tr class="liste_titre">';
		print '<td class="liste_titre">';
		print '<input class="flat" size="8" type="text" name="search_ref" value="'.$search_ref.'"></td>';
		print '<td class="liste_titre">';
		print '<input class="flat" size="8" type="text" name="search_ref_supplier" value="'.$search_ref_supplier.'"></td>';
		print '<td class="liste_titre">&nbsp;</td>';
		print '<td class="liste_titre">&nbsp;</td>';
		print '<td class="liste_titre" align="left">';
		print '<input class="flat" type="text" size="6" name="search_company" value="'.$search_company.'">';
		print '</td><td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="8" name="search_amount_no_tax" value="'.$search_amount_no_tax.'">';
		print '</td><td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="8" name="search_amount_all_tax" value="'.$search_amount_all_tax.'">';
		print '</td><td class="liste_titre">&nbsp;';
		print '</td><td class="liste_titre">&nbsp;';
		print '</td><td class="liste_titre" colspan="2" align="right">';
		print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
		print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
		print "</td>";
                /*
                if ($conf->use_javascript_ajax) {print '<td align="left" width="80"><a onClick="checkAll()">'.$langs->trans("All").'</a> / <a onClick="checkNone()">'.$langs->trans("None").'</a></td>';}
                else {print '	<td align="left" width="80">'.$langs->trans('Sel.').'</td>';}
                 * 
                 */
                print "</tr>\n";

		if ($num > 0)
		{
			$var=True;
			$total_ht=0;
			$total_ttc=0;
			$total_paid=0;
                        $total_topay= 0;
                        $fact_ids= "";

			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);
                                // dol_syslog(var_export($objp, true));

                                $canPay= !empty($objp->esrpartynr) || (!empty($objp->iban_prefix) && !empty($objp->bic));
                                if (strlen($fact_ids) > 0)
                                {
                                    $fact_ids.= ";";
                                }
                                $fact_ids.= $objp->facid;
                                
				$var=!$var;

				print "<tr ".$bc[$var].">";
				$classname = "impayee";

				print '<td class="nowrap">';
				$facturestatic->id=$objp->facid;
				$facturestatic->ref=$objp->ref;
				print $facturestatic->getNomUrl(1);
				print "</td>\n";

				print '<td class="nowrap">'.dol_trunc($objp->ref_supplier,12).'</td>';

				print '<td class="nowrap" align="center">'.dol_print_date($db->jdate($objp->df),'day')."</td>\n";
				print '<td class="nowrap" align="center">'.dol_print_date($db->jdate($objp->datelimite),'day');
				if ($objp->datelimite && $db->jdate($objp->datelimite) < ($now - $conf->facture->fournisseur->warning_delay) && ! $objp->paye && $objp->fk_statut == 1) print img_warning($langs->trans("Late"));
                                print "<input type=\"hidden\" name='datelimite_" . $objp->facid . "' value='" .$db->jdate($objp->datelimite). "' >";
				print "</td>\n";

				print '<td>';
				$companystatic->id=$objp->socid;
				$companystatic->name=$objp->name;
				print $companystatic->getNomUrl(1,'supplier',32);
				print '</td>';

				print "<td align=\"right\">".price($objp->total_ht)."</td>";
				print "<td align=\"right\">".price($objp->total_ttc)."</td>";
				print "<td align=\"right\">".price($objp->am)."</td>";
				print "<td align=\"right\">".price($objp->stilltopay)."</td>";
				print "<td align=\"right\">";
                                if ($canPay)
                                {
                                    print "<input type='text' value=\"".price($objp->stilltopay)."\" name='amount_" . $objp->facid . "' size='8'>";
                                    print "<input type='hidden' name='socid_" . $objp->facid ."' value='". $objp->socid."' >";
                                }
                                else
                                {
                                    print "Zahlungsinformationen fehlen";
                                    print img_warning($langs->trans("ESR Zeile oder IBAN + BLZ"));
                                }
                                print "</td>";

				// Show invoice status
				print '<td align="right" class="nowrap">';
				print $facturestatic->LibStatut($objp->paye,$objp->fk_statut,5,$objp->am);
				print '</td>';
                                /*
                                print '<td>';
                                if ($objp->stilltopay > 0)
                                {
                                    print '<input name="factures[]" class="checkElement" value='.$objp->facid.' type="checkbox" onclick="recalculeMontant(this, '.$objp->stilltopay.')"/>';
                                }
                                print '</td>';
                                 * 
                                 */

				print "</tr>\n";
				$total_ht+=$objp->total_ht;
				$total_ttc+=$objp->total_ttc;
				$total_paid+=$objp->am;
                                $total_topay+= $objp->stilltopay;

				$i++;
			}

			print '<tr class="liste_total">';
			print "<td colspan=\"5\" align=\"left\">".$langs->trans("Total").": </td>";
			print "<td align=\"right\"><b>".price($total_ht)."</b></td>";
			print "<td align=\"right\"><b>".price($total_ttc)."</b></td>";
			print "<td align=\"right\"><b>".price($total_paid)."</b></td>";
			print "<td align=\"right\"><b>".price($total_topay)."</b></td>";
			print '<td align="center">&nbsp;</td>';
			print '<td align="center">&nbsp;</td>';
			print "</tr>\n";
		}

		print "</table>";

                print '<br/>';
                print '<div style="text-align:right;">';
                
                //print '<span id="selectedAmount" style="display:none">0.00</span><br/>';
                //print '<span id="selectedAmountHuman">0.00</span>';
                //print ' / <b><span id="totalAmount">'.$total_topay.'</span> HT</b>';
                print '</div>';

                if ($num > 0 ) {

                    print '<table class="border" width="100%">';

                    print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('Payment').'</td>';
                    print '<tr><td >'.$langs->trans('Zahlungsdatum').'</td><td>';
                    $form->select_date('','re',0, 0,1,"",1,1);
                    print 'Leer = Fälligkeitsdatum</td>';
                    print '<td>'.$langs->trans('Comments').'</td></tr>';
                    print '<tr><td class="fieldrequired">'.$langs->trans('PaymentMode').'</td><td>';
                    $form->select_types_paiements(empty($_POST['paiementid'])?'2':$_POST['paiementid'],'paiementid');
                    print '</td>';
                    print '<td rowspan="3" valign="top">';
                    print '<textarea name="comment" wrap="soft" cols="60" rows="'.ROWS_3.'">'.(empty($_POST['comment'])?'':$_POST['comment']).'</textarea></td></tr>';
                    print '<tr><td>'.$langs->trans('Numero').'</td><td><input name="num_paiement" type="text" value="'.(empty($_POST['num_paiement'])?date('Y-m-d-H:i'):$_POST['num_paiement']).'"></td></tr>';
                    if (! empty($conf->banque->enabled))
                    {
                        print '<tr><td class="fieldrequired">'.$langs->trans('Account').'</td><td>';
                        $form->select_comptes(empty($accountid)?'1':$accountid,'accountid',0,'',2);
                        print '</td></tr>';
                    }
                    else
                    {
                        print '<tr><td colspan="2">&nbsp;</td></tr>';
                    }
                    print '</table>';

                    print '	<div class="tabsAction">';
                    // print $langs->trans('DateInvoice'). ' : ';
                    print ' <input style="margin-left:20px" type="submit" class="butAction" name="createDTAPay" value="Zahlungsdatei erstellen">';
                    print ' <input type="hidden" value="' . $fact_ids . '" name="factures" >';
                    print '	</div>';
                }

		print '</form>';

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}

}

// End of page
$db->close();
llxFooter();
