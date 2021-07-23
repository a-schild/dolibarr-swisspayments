<?php
/* Swiss payments from ESR to EZAG for Postfinance
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 * Basierend auf der Version von Coreweb
 * 
 * Klasse zum Erzeugen von EZAG Dateien im schweizer Postfinance
 * Format.
 * 
 * @author Christoph Vieth <cvieth@coreweb.de>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public License (LGPL)
 */
require_once(dirname(__FILE__) . '/ezagChTransaction.php');

class ezagChFile {

    private $transactions = array();
    private $transactionCounter = 0;
    private $creationDate;
    private $ident;
    private $debitAccountNr;
    private $feesAccountNr;
    public $currentTransaction = NULL;

    public function __construct($ident, $debitAccountNr) {
        $this->creationDate = date('ymd');
        $this->ident = $ident;
        $this->debitAccountNr = $debitAccountNr;
        $this->feesAccountNr = $debitAccountNr;
        $this->addTransactionHeader(); // Headerrecord
    }

    public function addTransaction($type) {
        $seqNr = $this->transactionCounter;
        $this->transactionCounter++;
        $this->transactions[$seqNr] = new ezagChTransaction($type);
        $this->transactions[$seqNr]->setInputSequenceNr($seqNr);
        $this->transactions[$seqNr]->setCreationDate($this->creationDate);
        $this->transactions[$seqNr]->setDtaId($this->ident);
        $this->transactions[$seqNr]->setDebitAccount($this->debitAccountNr);
        return $seqNr;
    }

    public function addTransactionHEADER() {
        $seqNr= $this->addTransaction(ezagChTransaction::EZAG_HEAD);
        return $seqNr;
    }
    
    public function addTransactionESR() {
        return $this->addTransaction(ezagChTransaction::EZAG_ESR);
    }

    public function addTransactionIBAN() {
        return $this->addTransaction(ezagChTransaction::EZAG_IBAN);
    }

    
    public function loadTransaction($seqNr) {
        return $this->transactions[$seqNr];
    }

    public function saveTransaction($seqNr, $transaction) {
        return $this->transactions[$seqNr] = $transaction;
    }

    private function createTotalRecord() {
        $sum = 0;
        foreach ($this->transactions as $transaction) {
            $sum += $transaction->getPaymentAmountNumeric();
        }
        dol_syslog("Sum Amount: " . $sum);
        $id = $this->addTransaction(ezagChTransaction::EZAG_TOTAL);
        $totalRecord = $this->loadTransaction($id);
        dol_syslog("Sum Records: " . $id);
        $totalRecord->setTotalAmount($sum);
        $totalRecord->setDebitAccount($this->debitAccountNr);
        $this->saveTransaction($id, $totalRecord);
    }

    public function toFile($filename) {
        $this->createTotalRecord();
        $fptr = fopen($filename, 'w+');
        if (!$fptr)
            throw new Exception('Kann Datei "' . $filename . '"nicht öffnen!');
        foreach ($this->transactions as $transaction) {
            //ceho "Writing Transaction: " . $transaction->getSeqNr() . "\n";
            fwrite($fptr, $transaction->toString());
        }
        fclose($fptr);
    }

    public function toString() {
        $this->createTotalRecord();
        $output = '';
        foreach ($this->transactions as $transaction) {
            //echo "Writing Transaction: " . $transaction->getSeqNr() . "\n";
            $output .= $transaction->toString();
        }
        return $output;
    }

    /**
     * Convert a date into YYMMDD timestamp as used by DTA files
     * 
     * @param type $date
     */
    public static function dateToDATString($date)
    {
        return date('ymd', $date);
    }
}

?>
