<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$qrcode= $_REQUEST["qrcode"];
echo "<pre>";
echo $qrcode;
echo "</pre>";

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
