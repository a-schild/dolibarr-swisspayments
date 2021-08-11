<?php
function startsWith($haystack, $needle) {
    return !strncmp($haystack, $needle, strlen($needle));
}

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';

global $db, $langs, $user;

// Load translation files required by the page
$langs->load("swisspayments@swisspayments");


// Access control
if ($user->societe_id > 0) {
	// External user
	accessforbidden();
}

if (! $user->rights->swisspayments->invoices->create) accessforbidden();


$qrcode= $_REQUEST["qrcode"];

echo "<!--\n";
echo "<pre>";
echo $qrcode;
echo "</pre>";
echo "-->\n";

/*
SPC       <- Swiss Payment Code identifier, fixed
0200      <- Version 2.00 fixed
1         <- Encoding, fix 1 = UTF-8, nur Latin Zeichensatz
CH5930808005930315512 <- IBAN Nummer, nur CH und LI prefix erlaubt, 21 Zeichen total
S                     <- Adressentyp S= Strukturierte Adresse, K= Kombinierte Adresse (2 Adressfelder)
KMU Nidau - Ipsach - Umgebung <- Name, max 70 Zeichen
Postfach                      <- Strasse oder Adr.Zeile 1, max 70 Zeichen
0                             <- Strukturierte Adresse= Hausnummer, Kombinierte Adresse Adresszeile 2
2560                          <- PLZ, ohne Ländercode, Max 16 Zeichen
Nidau                         <- Ort, max 35 Zeichen (Bei Kombinierter Adresse in Adresszeile 2 enthalten)
CH                            <- Land 2 Zeichen ISO Code
                              <- Endgültiger Zahlungsempfänger (Strktur wie bei oberer Adresse, fängt auch mit S oder K an






150.00                        <- Zahlbetrag
CHF                           <- Währung CHF oder EUR
S                             <- Adresse Zahlungspflichtiger (S oder K für Adresstyp)
Aarboard AG
Egliweg
10
2560
Nidau
CH
QRR                           <- QRR = QR-Referenz, SCO Creditor Reference (ISO-11649), NON ohne Referenz
502493000000000059000210012   <- Referenznummer
                              <- Zusätzliche Infos, max 140 Zeichen
EPD                           <- Trailer, Schlusssegment, Fix EPD
//S1/10/21001/11/210706/32/0/40/0:30    <--- Alternatives Zahlungsvefahren
*/


$qr_lines = explode(PHP_EOL, $qrcode);

if (count($qr_lines) == 34)
{
  // Correct number of lines
  if ($qr_lines[0] == "SPC" && $qr_lines[1] == "0200" && $qr_lines[2] == "1" && $qr_lines[30] == "EPD")
  {
    // Header fields correct
    if (startsWith($qr_lines[3], "CH") || startsWith($qr_lines[3], "LI"))
    {
       $iban= $qr_lines[3];
       // Now find finance account and supplier address
       // with the replace method we ignore spaces inside the IBAN number
       $sql= "select * from ".MAIN_DB_PREFIX."societe_rib where REPLACE(iban_prefix, ' ', '')='".$db->escape($iban)."'";
        $resql=$db->query($sql);
        if ($resql)
        {
            if ($db->num_rows($resql) == 1)
            {
                $obj = $db->fetch_object($resql);

                $id    = $obj->rowid;
                $iban_prefix= $obj->iban_prefix;
                $fk_societe = $obj->fk_soc;
                echo "<div>Found supplier (".$fk_societe.")</div>";
                if ($iban != $iban_prefix)
                {
                  echo "<div>Updating IBAN number to clean format (old $iban_prefix new $iban)</div>";
                  $sql_upd= "update ".MAIN_DB_PREFIX."societe_rib set iban_prefix ='".$db->escape($iban)."' where rowid=".$id;
                  $db->query($sql_upd);
                }
                $resql=$db->query("select * from llx_facture_fourn where fk_soc=" . $fk_societe . " and ref_supplier='".$db->escape($qr_lines[28]) ."'");
                if ($resql)
                {
                    $num = $db->num_rows($resql);
                    $i = 0;
                    if ($num > 0)
                    {
                        $warn++;
                        $mesg= "Rechnung Nr. " . $qr_lines[28]. " existiert f&uumlr diesen Lieferanten schon, bitte Rechnungsnummer &auml;ndern";
                    }
                }
                $myobject = new SwisspaymentsClass($db);
                
            }
            else if ($db->num_rows($resql) > 1)
            {
              echo "<div>Multiple accounts found with the same iban number (".$db->num_rows($resql).")</div>";
            }
            else
            {
              echo "<div>No account found with this iban number (".$iban.")</div>";
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
      echo "<div class='error'>IBAN number must CH or LI account, got ".$qr_line[3]."</div>";
    }
  }
  else
  {
    echo "<div class='error'>Invalid header and/or footer lines in QR code, probably not a swiss QR invoice</div>";
    var_dump($qr_lines);
  }
}
else
{
  echo "<div class='error'>Invalid number of data lines in QR code</div>";
  echo "<div class='error'>Expecting 34, got ".count($qr_lines)."</div>";
  var_dump($qr_lines);
  var_dump($qrcode);
}