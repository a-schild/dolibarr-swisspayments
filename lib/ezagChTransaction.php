<?php

/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 * 
 * Basierend auf der Version von Coreweb
 * 
 * Klasse zum Erzeugen von DTA Transaktionen im schweizer Six Interbank Clearing
 * Format.
 *
 * @author Christoph Vieth <cvieth@coreweb.de>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public License (LGPL)
 */

class ezagChTransaction {

    // Header
    // 03616072900000130666907530666907598000000000000000
    // 036= Head
    //    160729 = YYMMDD
    //          00000 = Immer 00000
    //               1 = Fixwert
    //                306669075 = Lastkonto
    //                         306669075 = Gebührenkonto
    //                                  98 = Auftragsnummer 01-99 (Muss unique pro Tag+Währung sein)
    //                                    00 = Kopfrecord, 22 = PC Inland (ES), 24 = Zahlungsanweisung Inland, 28=ESR Inland, 97=Totalrecord
    //                                      000000 = Transaktionnummer (0=Header, rest immer +1, Totalrecord=Nummer der höchten Transaktion)
    //                                            0000000 = Reserve 0
    //                                                   -> 650 Leerzeichen zum auffüllen der Zeile


    const EZAG_HEAD = 0;  // Headrecord for EZAG Postfinance
    const EZAG_ESR = 28;  // ESR Zahlung
    //const TA827 = 827;  // CHF Zahlung Inland
    const EZAG_IBAN = 27;  // IBAN Zahlung Inland/Ausland
    const EZAG_TOTAL = 97;  // Totalrecord

    /**
     * Füllzeichen
     * @var char 
     */

    private $fillChar = ' ';

    /**
     * Typ des Recordss
     * @var string
     */
    private $type = NULL;

    /**
     * DTA-ID
     * @var string
     */
    private $dtaId = NULL;

    /**
     * Zu belastendes Konto
     * @var string
     */
    private $debitAccount = NULL;

    /**
     * Vergütungsbetrag
     * @var string
     */
    private $paymentAmount = NULL;

    /**
     * Vergütungsbetrag in Numerischer Darstellung
     * @var float
     */
    private $paymentAmountNumeric = NULL;

    /**
     * Eingabe-Sequenznummer
     * @var int
     */
    private $inputSequenceNr = NULL;

    /**
     * Bankenclearing-Nr. der bank des Auftraggebers
     * @var int
     */
    private $clientClearingNr = NULL;

    /**
     * Erstellungsdatum
     * @var string
     */
    private $creationDate = NULL;

    /**
     * Gewünschter Verarbeitungstag
     * @var string
     */
    private $processingDay = NULL;

    /**
     * Auftraggeber
     * @var array 
     */
    private $client = NULL;

    /**
     * Begünstigter
     * @var array
     */
    private $recipient = NULL;
    private $recipientZIP = null;

    /**
     * Zahlungsgrund
     * @var array
     */
    private $paymentReason = NULL;

    /**
     * Totalbetrag
     * @var string
     */
    private $totalAmount;

    /**
     * Bankenclearing-Nr der Bank des Begüntigten
     * für TA 827 Transaktionen
     * @var int
     */
    private $recipientClearingNr;

    /**
     * ESR Teilnehmernummer
     */
    private $recipientESRPartyNr;

    /**
     * ESR Referenznummer
     * 
     */
    private $recipientESRNr;

    /**
     *
     * @var type IBAN of recipient
     */
    private $recipientIBANNr;

    /**
     *
     * @var type BIC of recipient
     */
    private $recipientBIC;
    private $currency;

    /**
     * 
     * @param type $transactionType
     * @throws Exception
     */
    public function __construct($transactionType) {
        $avaliableTypes = array(self::EZAG_HEAD, self::EZAG_IBAN, self::EZAG_ESR, self::EZAG_TOTAL);
        if (!in_array($transactionType, $avaliableTypes)) {
            throw new Exception("Transaktionstyp nicht bekannt oder nicht implementiert!");
        } else {
            $this->type = $transactionType;
        }
    }

    public function toString() {
        switch ($this->type) {
            case self::EZAG_HEAD:
                $record = $this->genHeadLine();
                break;
            case self::EZAG_ESR:
                $record = $this->genESR();
                break;
            case self::EZAG_IBAN:
                $record = $this->genIBAN();
                break;
            case self::EZAG_TOTAL:
                $record = $this->genTotalRow();
                break;
            default:
                throw new Exception("Transaktionstyp nicht nicht implementiert!");
        }
        $string = '';
        while ($segment = array_pop($record)) {
            $string = $segment . "\n" . $string;
        }
        return $string;
    }

    private function isIsoCurrencyCode($currencyCode) {
        /**
         * @todo Weitere ISO-Währungscodes einpflegen
         */
        $validCodes = array('CHF', 'EUR');
        if (in_array($currencyCode, $validCodes)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Erzeugt eine TA826 Transaktion
     * 
     * @return array
     */
    private function genESR() {
        $record = array();

        // 03616072900000130666907530666907598000000000000000
        // 036= Head
        //    160729 = YYMMDD
        //          00000 = Immer 00000
        //               1 = Fixwert
        //                306669075 = Lastkonto
        //                         306669075 = Gebührenkonto
        //                                  98 = Auftragsnummer 01-99 (Muss unique pro Tag+Währung sein)
        //                                    00 = Kopfrecord, 22 = PC Inland (ES), 24 = Zahlungsanweisung Inland, 28=ESR Inland, 97=Totalrecord
        //                                      000000 = Transaktionnummer (0=Header, rest immer +1, Totalrecord=Nummer der höchten Transaktion)
        //                                            0000000 = Reserve 0
        //                                                   -> 650 Leerzeichen zum auffüllen der Zeile

        $segment01 = '036'
                . $this->getCreationDate()
                . '00000'
                . '1'
                . $this->getDebitAccount()
                . $this->getDebitAccount()
                . $this->getDtaId()
                . '28'
                . $this->getInputSequenceNr()
                . '0000000'
                . $this->getCurrency()  // Zahlungswährung
                . $this->getPaymentAmount() // Zahlungsbetrag in Rappen
                . ' '
                . $this->getCurrency()
                . 'CH'
                . $this->getReserve(2) // TODO ev. Prüfziffer bei <
                . $this->getRecipientESRPartyNr()
                . $this->getRecipientESRNr() // ESR Referenz Nummer
                . $this->getReserve(35)     // Kein absender
                . $this->getReserve(555)
        ;

        array_push($record, $segment01);

        /*
        // Segment 01
        $segment01 = '01'
                . $this->getHeader()
                . $this->getReferenceNr()
                . $this->getDebitAccount()
                . $this->getPaymentAmount()
                . $this->getReserve(14);
        array_push($record, $segment01);

        // Segment 02
        $segment02 = '02'
                . $this->getClient()
                . $this->getReserve(30);
        array_push($record, $segment02);

        // segment 03
        $segment03 = '03'
                . $this->getRecipient()
                . $this->getRecipientESRNr()
                . $this->getReserve(7);
        array_push($record, $segment03);
         */
        return $record;
    }

    /**
     * Erzeugt eine TA827 Transaktion
     * 
     * @return array
     */
    private function genHeadLine() {
        $record = array();

        // 03616072900000130666907530666907598000000000000000
        // 036= Head
        //    160729 = YYMMDD
        //          00000 = Immer 00000
        //               1 = Fixwert
        //                306669075 = Lastkonto
        //                         306669075 = Gebührenkonto
        //                                  98 = Auftragsnummer 01-99 (Muss unique pro Tag+Währung sein)
        //                                    00 = Kopfrecord, 22 = PC Inland (ES), 24 = Zahlungsanweisung Inland, 28=ESR Inland, 97=Totalrecord
        //                                      000000 = Transaktionnummer (0=Header, rest immer +1, Totalrecord=Nummer der höchten Transaktion)
        //                                            0000000 = Reserve 0
        //                                                   -> 650 Leerzeichen zum auffüllen der Zeile

        $segment01 = '036'
                . $this->getCreationDate()
                . '00000'
                . '1'
                . $this->getDebitAccount()
                . $this->getDebitAccount()
                . $this->getDtaId()
                . '00'
                . $this->getInputSequenceNr()
                . '0000000'
                . $this->getReserve(650);
        array_push($record, $segment01);
        return $record;
    }

    /**
     * Erzeugt eine TA836 Transaktion
     * IBAN Payment
     * Fixed record format
     * @return array
     */
    private function genIBAN() {
        $record = array();

        // 03616072900000130666907530666907598000000000000000
        // 036= Head
        //    160729 = YYMMDD
        //          00000 = Immer 00000
        //               1 = Fixwert
        //                306669075 = Lastkonto
        //                         306669075 = Gebührenkonto
        //                                  98 = Auftragsnummer 01-99 (Muss unique pro Tag+Währung sein)
        //                                    00 = Kopfrecord, 22 = PC Inland (ES), 24 = Zahlungsanweisung Inland, 28=ESR Inland, 97=Totalrecord
        //                                      000000 = Transaktionnummer (0=Header, rest immer +1, Totalrecord=Nummer der höchten Transaktion)
        //                                            0000000 = Reserve 0
        //                                                   -> 650 Leerzeichen zum auffüllen der Zeile

        $segment01 = '036'
                . $this->getCreationDate()
                . '00000'
                . '1'
                . $this->getDebitAccount()
                . $this->getDebitAccount()
                . $this->getDtaId()
                . '27'
                . $this->getInputSequenceNr()
                . '0000000'
                . $this->getCurrency()  // Zahlungswährung
                . $this->getPaymentAmount() // Zahlungsbetrag in Rappen
                . ' '
                . $this->getCurrency()
                . 'CH'
                . $this->getReserve(15) //NOT needed for CH . $this->getRecipientClearingNr()
                . $this->getRecipientIBANNr()
                . $this->getReserve(35) // Name Empfängerbank/Finanzinstitut
                . $this->getReserve(35)
                . $this->getReserve(35)
                . $this->getReserve(10) // PLZ Empfängerbank
                . $this->getReserve(25)
                . $this->getRecipientLine1() // Endbegünstigter Name
                . $this->getRecipientLine2() // Endbegünstigter Bez.
                . $this->getRecipientLine3() // Endbegünstigter Strasse
                . $this->getRecipientZIP() // PLZ Endbegünstigter
                . $this->getRecipientTown() // Ort Endbegünstigter
                . $this->getPaymentReason() // Mitteilung Zeile1 - 4 4x35 Zeichen
                . $this->getReserve(3)
                . $this->getReserve(1)
                . $this->getReserve(35) // Auftraggeber
                . $this->getReserve(35)
                . $this->getReserve(35)
                . $this->getReserve(10)
                . $this->getReserve(25) // Ort Auftraggeber
                . $this->getReserve(14)
        ;

        array_push($record, $segment01);
        return $record;
    }

    /**
     * Erzeugt eine Transaktion vom Typ TA890 (Totalrecord)
     * 
     * @return array
     */
    private function genTotalRow() {
        $record = array();

        // 03616072900000130666907530666907598000000000000000
        // 036= Head
        //    160729 = YYMMDD
        //          00000 = Immer 00000
        //               1 = Fixwert
        //                306669075 = Lastkonto
        //                         306669075 = Gebührenkonto
        //                                  98 = Auftragsnummer 01-99 (Muss unique pro Tag+Währung sein)
        //                                    00 = Kopfrecord, 22 = PC Inland (ES), 24 = Zahlungsanweisung Inland, 28=ESR Inland, 97=Totalrecord
        //                                      000000 = Transaktionnummer (0=Header, rest immer +1, Totalrecord=Nummer der höchten Transaktion)
        //                                            0000000 = Reserve 0
        //                                                   -> 650 Leerzeichen zum auffüllen der Zeile

        $segment01 = '036'
                . $this->getCreationDate()
                . '00000'
                . '1'
                . $this->getDebitAccount()
                . $this->getDebitAccount()
                . $this->getDtaId()
                . '97'
                . $this->getInputSequenceNr()
                . '0000000'
                . 'CHF' //. $this->getCurrency()
                . str_pad($this->inputSequenceNr-1, 6, '0', STR_PAD_LEFT)
                . $this->getTotalAmount()
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . '0000000000000000000000'
                . $this->getReserve(320);
        array_push($record, $segment01);
        return $record;
    }

    /**
     * Setzt den Totalbetrag
     * 
     * @param float|int $amount
     * @throws Exception
     */
    public function setTotalAmount($amount) {
        // Überprüfen des Betrages
        if (!((is_float($amount)) || (is_integer($amount)))) {
            throw new Exception("Der übergebene Betrag muss Eine Zahl sein!");
        } else {
            $this->totalAmount = str_pad(number_format($amount, 2, '', ''), 13, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Fragt den Totalbetrag ab
     * 
     * @return string
     * @throws Exception
     */
    private function getTotalAmount() {
        if ($this->totalAmount == NULL) {
            throw new Exception("Vergütungsbetrag nicht gesetzt!");
        } elseif (strlen($this->totalAmount) != 13) {
            throw new Exception("Gesetzter Vergütungsbetrag hat ungültige Länge!");
        } else {
            return $this->totalAmount;
        }
    }

    private function getReserve($length) {
        $reserve = '';
        for ($i = 1; $i <= $length; $i++) {
            $reserve .= $this->fillChar;
        }
        return $reserve;
    }

    private function getHeader() {
        $header = $this->getProcessingDay()
                . $this->getRecipientClearingNr()
                . $this->getOutputSequenceNr()
                . $this->getCreationDate()
                . $this->getClientClearingNr()
                . $this->getDtaId()
                . $this->getInputSequenceNr()
                . $this->getTransactionType()
                . $this->getPaymentType()
                . $this->getProcessingFlag();
        return $header;
    }

    public function setProcessingDay($processingDay) {
        if (($this->type != self::EZAG_ESR) && ($this->type != self::EZAG_IBAN)) {
            throw new Exception("Gewünschter Verarbeitungstag ist nur bei TA826 und TA827 zu setzen");
        } elseif ((!is_numeric($processingDay)) && (!(strlen($processingDay) == 6))) {
            throw new Exception("Gewünschter Verarbeitungstag muss ein Datum im Format JJMMTT sein!");
        } else {
            $this->processingDay = $processingDay;
        }
    }

    private function getProcessingDay() {
        if (($this->type == self::EZAG_ESR) || ($this->type == self::EZAG_IBAN)) {
            if ($this->processingDay == NULL) {
                throw new Exception("Gewünschter Verarbeitungstag nicht gesetzt!");
            } else {
                return $this->processingDay;
            }
        } else {
            return '000000';
        }
    }

    /**
     * Setze Bankencleraing-Nr. der Bank des Begünstigten
     * 
     * @param int $clearingNr
     * @throws Exception
     */
    public function setRecipientClearingNr($clearingNr) {
        if (!is_integer($clearingNr)) {
            throw new Exception("Übergebene Clearing-Nr der Bank des Empfängers ist ungültig!");
        } else {
            $this->recipientClearingNr = $clearingNr;
        }
    }

    public function setRecipientESRPartyNr($esrPartyNr) {
        if ($this->type != self::EZAG_ESR) {
            throw new Exception("RecipientESRPartyNr only for TA826 allowed!");
        } else {
            $this->recipientESRPartyNr = $esrPartyNr;
        }
    }

    public function getRecipientESRPartyNr() {
        return $this->recipientESRPartyNr;
    }

    public function setRecipientESRNr($esrNr) {
        if ($this->type != self::EZAG_ESR) {
            throw new Exception("RecipientESRNr only for TA826 allowed!");
        } else {
            $this->recipientESRNr = $esrNr;
        }
    }

    public function getRecipientESRNr() {
        return $this->recipientESRNr;
    }

    public function setRecipientIBANNr($iban) {
        if ($this->type != self::EZAG_IBAN) {
            throw new Exception("RecipientIBANNr only for EZAG_IBAN allowed!");
        } else {
            // Strip out all blanks
            $this->recipientIBANNr = str_replace(' ', '', $iban);
        }
    }

    public function getRecipientIBANNr() {
        if ($this->type == self::EZAG_IBAN) {
            return str_pad($this->recipientIBANNr, 35, $this->fillChar);
        } else {
            return $this->recipientIBANNr;
        }
    }

    public function setRecipientBIC($bic) {
        if ($this->type != self::EZAG_IBAN) {
            throw new Exception("Recipient BICr only for IBAN allowed!");
        } else {
            $this->recipientBIC = $bic;
        }
    }

    public function getRecipientBIC($padLength) {
        if ($padLength) {
            return $this->recipientBIC . $this->getReserve($padLength - strlen($this->recipientBIC));
        } else {
            return $this->recipientBIC;
        }
    }

    private function getRecipientClearingNr() {
        if ($this->recipientClearingNr != NULL) {
            return str_pad($this->recipientClearingNr, 12, $this->fillChar);
        } else {
            return $this->getReserve(12);
        }
    }

    private function getOutputSequenceNr() {
        return '00000';
    }

    public function setCreationDate($creationDate) {
        if ((!is_numeric($creationDate)) && (!(strlen($creationDate) == 6))) {
            throw new Exception("Valuta muss ein Datum im Format JJMMTT sein!");
        } else {
            $this->creationDate = $creationDate;
        }
    }

    private function getCreationDate() {
        if ($this->creationDate == NULL) {
            throw new Exception("Erstellungsdatum nicht gesetzt!");
        } else {
            return $this->creationDate;
        }
    }

    public function setClientClearingNr($clearingNr) {
        if (!is_integer($clearingNr)) {
            throw new Exception("Übergebene Clearing-Nr der Bank des Auftraggebers ist ungültig!");
        } else {
            $this->clientClearingNr = $clearingNr;
        }
    }

    private function getClientClearingNr() {
        if ($this->clientClearingNr == NULL) {
            throw new Exception("Clearing-Nr der Bank des Auftraggebers nicht gesetzt!");
        } else {
            return str_pad($this->clientClearingNr, 7, $this->fillChar);
        }
    }

    public function setInputSequenceNr($sequenceNr) {
        if (!is_integer($sequenceNr)) {
            throw new Exception("Übergebene Eingabe-Sequenznummer ist ungültig!");
        } else {
            $this->inputSequenceNr = $sequenceNr;
        }
    }

    private function getInputSequenceNr() {
        return str_pad($this->inputSequenceNr, 6, '0', STR_PAD_LEFT);
    }

    private function getTransactionType() {
        return $this->type;
    }

    private function getPaymentType() {
        return '0';
    }

    private function getProcessingFlag() {
        return '0';
    }

// Ende der Header Funktionen    

    public function setDtaId($dtaId) {
        if (!(strlen($dtaId) == 5)) {
            throw new Exception("Übergebene DTA-ID hat nicht 5 stellen!");
        } else {
            $this->dtaId = substr($dtaId, 3);
        }
    }

    private function getDtaId() {
        if ($this->dtaId == NULL) {
            throw new Exception("DTA-ID nicht gesetzt!");
        } else {
            return $this->dtaId;
        }
    }

    private function getTransactionId() {
        /*
          $hash = strtoupper(hash('md5', $this->dtaId));
          for ($i=0; $i<strlen($hash); $i++) {
          $hash[$i] = ord($hash[$i]);
          }
          list($hash) = str_split(strtoupper(hash('md5', $this->dtaId)), 11);
         */

        //$seqNr = getInputSequenceNr();
        return mt_rand(100000, 999999) . $this->getInputSequenceNr();
    }

    private function getReferenceNr() {
        return $this->getDtaId() . $this->getTransactionId();
    }

    public function setDebitAccount($debitAccount) {
        $debitAccountClean = str_replace(' ', '', $debitAccount);
        $debitAccountClean = str_replace('-', '', $debitAccountClean);
        if (strlen($debitAccountClean) != 9) {
            throw new Exception("Übergebenes zu belastendes Konto muss 9 zeichen lang sein!");
        } else {
            $this->debitAccount = $debitAccountClean;
        }
    }

    public function setDebitAccountIBAN($debitAccountIBAN) {
        $this->debitAccountIBAN = $debitAccountIBAN;
    }
    
    private function getDebitAccount() {
        if ($this->debitAccount == NULL) {
            throw new Exception("Zu belastendes Konto nicht gesetzt!");
        } else {
            if (strlen($this->debitAccount) != 9) {
                throw new Exception("Gesetztes zu belastendes Konto hat ungültige Länge!");
            } else {
                return $this->debitAccount;
            }
        }
    }

    public function setPaymentAmount($amount, $currencyCode, $valuta = NULL) {
        $paymentAmount = '';

        // Überprüfen des Valuta
        if ($valuta == NULL) {
            $valuta = '      ';
        } else {
            if (!is_numeric($valuta) || (strlen($valuta) != 6)) {
                throw new Exception("Valuta muss ein Datum im Format JJMMTT sein!");
            }
        }

        // Überprüfen des Betrages
        if (!((is_float($amount)) || (is_integer($amount)))) {
            throw new Exception("Der übergebene Betrag muss Eine Zahl sein!");
        } else {
            $this->paymentAmountNumeric = $amount;
            $amount = str_pad(number_format($amount, 2, '', ''), 13, '0', STR_PAD_LEFT);
        }


        // Überprüfen des Währungscodes
        if (!$this->isIsoCurrencyCode($currencyCode)) {
            throw new Exception("Übergebener ISO-Währungscode nicht bekannt!");
        }

        $this->setCurrency($currencyCode);
        
        $paymentAmount = $amount;
        if (strlen($paymentAmount) != 13)
        {
            throw new Exception("Zu setzender Vergütungsbetrag hat ungültige Länge!");
        } else {
            $this->paymentAmount = $paymentAmount;
        }
    }

    /**
     * Gibt den gesetzen Vergütungsbetrag der Transaktion als numerischen Wert 
     * wieder.
     * 
     * @return float        Vergütungsbetrag als numerischer Wert
     * @throws Exception    Vergütungsbetrag nicht gesetzt
     */
    public function getPaymentAmountNumeric() {
        if ($this->type == self::EZAG_HEAD) {
            return 0;
        } else {
            if ($this->paymentAmountNumeric == NULL) {
                throw new Exception("Vergütungsbetrag nicht gesetzt!");
            } else {
                return $this->paymentAmountNumeric;
            }
        }
    }

    private function getPaymentAmount() {
        if ($this->paymentAmount == NULL) {
            throw new Exception("Vergütungsbetrag nicht gesetzt!");
        } else {
            if (($this->type == self::EZAG_IBAN && strlen($this->paymentAmount) != 13 ) ||
                    ($this->type != self::EZAG_IBAN && strlen($this->paymentAmount) != (13))) {
                throw new Exception("Gesetzter Vergütungsbetrag hat ungültige Länge!");
            } else {
                return $this->paymentAmount;
            }
        }
    }

    public function setClient($line1, $line2, $line3, $line4) {
        $lineLength = ($this->type == self::EZAG_IBAN ? 35 : 24);
        $client = array();
        if ($this->type != self::EZAG_IBAN) {
            array_push($client, $this->makeSafeString($line4, $lineLength));
        }
        array_push($client, $this->makeSafeString($line3, $lineLength));
        array_push($client, $this->makeSafeString($line2, $lineLength));
        array_push($client, $this->makeSafeString($line1, $lineLength));
        $this->client = $client;
    }

    private function getClient() {
        if ($this->client == NULL) {
            throw new Exception("Auftraggeber nicht gesetzt!");
        } else {
            $clients = $this->client;
            $client = '';
            while ($line = array_pop($clients)) {
                $client .= $line;
            }
            return $client;
        }
    }

    public function setRecipient($account, $line1, $line2, $line3, $zip, $town) {
        $this->setRecipientLine1($line1);
        $this->setRecipientLine2($line2);
        $this->setRecipientLine3($line3);
        $this->setRecipientZIP($zip);
        $this->setRecipientTown($town);
    }

    private function getRecipient() {
        if ($this->recipient == NULL) {
            throw new Exception("Begünstigter nicht gesetzt!");
        } else {
            $recipients = $this->recipient;
            $recipient = '';
            while ($line = array_pop($recipients)) {
                $recipient .= $line;
            }
            return $recipient;
        }
    }

    public function setPaymentReason($line1, $line2 = '', $line3 = '', $line4 = '') {
        $lineLength = 35;
        $reason = array();
        array_push($reason, $this->makeSafeString($line4, $lineLength));
        array_push($reason, $this->makeSafeString($line3, $lineLength));
        array_push($reason, $this->makeSafeString($line2, $lineLength));
        array_push($reason, $this->makeSafeString($line1, $lineLength));
        $this->paymentReason = $reason;
    }

    private function getPaymentReason() {
        $lineLength = 35;
        if ($this->paymentReason == NULL) {
            return $this->getReserve($lineLength)
                    . $this->getReserve($lineLength)
                    . $this->getReserve($lineLength)
                    . $this->getReserve($lineLength);
        } else {
            $reasons = $this->paymentReason;
            $reason = '';
            while ($line = array_pop($reasons)) {
                $reason .= $line;
            }
            return $reason;
        }
    }

    private function getEndRecipient() {
        return $this->getReserve(30)
                . $this->getReserve(24)
                . $this->getReserve(24)
                . $this->getReserve(24)
                . $this->getReserve(24);
    }

    private function replaceUmlauts($string) {
        $transMatrix = array(
            "  " => " ",
            "  " => " ",
            " " => " ",
            "À" => "A",
            "à" => "a",
            "Á" => "A",
            "á" => "a",
            "Â" => "A",
            "â" => "a",
            "Ã" => "A",
            "ã" => "a",
            "Ä" => "A",
            "ä" => "a",
            "Å" => "A",
            "å" => "a",
            "Æ" => "A",
            "æ" => "a",
            "Ç" => "C",
            "ç" => "c",
            "È" => "E",
            "è" => "e",
            "É" => "E",
            "é" => "e",
            "Ê" => "E",
            "ê" => "e",
            "Ë" => "E",
            "ë" => "e",
            "Ì" => "I",
            "ì" => "i",
            "Í" => "I",
            "í" => "i",
            "Î" => "I",
            "î" => "i",
            "Ï" => "I",
            "ï" => "i",
            "Ñ" => "N",
            "ñ" => "n",
            "Ò" => "O",
            "ò" => "o",
            "Ó" => "O",
            "ó" => "o",
            "Ô" => "O",
            "ô" => "o",
            "Õ" => "O",
            "õ" => "o",
            "Ö" => "O",
            "ö" => "o",
            "Ø" => "O",
            "ø" => "o",
            "Ù" => "U",
            "ù" => "u",
            "Ú" => "U",
            "ú" => "u",
            "Û" => "U",
            "û" => "u",
            "Ü" => "U",
            "ü" => "u",
            "Y´" => "Y",
            "y´" => "y",
            "ß" => "s"
        );
        return strtr($string, $transMatrix);
    }

    public function getCurrency() {
        return $this->currency;
    }

    public function setCurrency($currency) {
        $this->currency= $currency;
    }

    public function getRecipientZIP() {
        return $this->recipientZIP;
    }

    
    public function setRecipientZIP($zip)
    {
        $this->recipientZIP= $this->makeSafeString($zip, 10);
    }
    
    public function getRecipientTown() {
        return $this->recipientTown;
    }

    public function setRecipientTown($town)
    {
        $this->recipientTown= $this->makeSafeString($town, 25);
    }
    
    public function getRecipientLine1() {
        return $this->recipientLine1;
    }

    public function setRecipientLine1($line1)
    {
        $this->recipientLine1= $this->makeSafeString($line1, 35);
    }
    
    public function getRecipientLine2() {
        return $this->recipientLine2;
    }

    public function setRecipientLine2($line2)
    {
        $this->recipientLine2= $this->makeSafeString($line2, 35);
    }
    
    public function getRecipientLine3() {
        return $this->recipientLine3;
    }

    public function setRecipientLine3($line3)
    {
        $this->recipientLine3= $this->makeSafeString($line3, 35);
    }
    
    /**
     * Truncate String at max characters
     * 
     * @param type $string
     * @param type $length
     * @return type
     */
    protected function truncate($string, $length)
    {
        return (strlen($string) > $length) ? substr($string, 0, $length ) : $string;     
    }

    /**
     * Make a payment safe string
     * 
     * @param type $string
     * @param type $lineLength
     * @return type
     */
    protected function makeSafeString($string, $lineLength)
    {
        return $this->truncate(str_pad(strtoupper($this->replaceUmlauts($string)), $lineLength, $this->fillChar), $lineLength);
    }
}
