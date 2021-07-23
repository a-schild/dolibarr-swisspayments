<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 * Basierend auf der Version von Coreweb
 * 
 * Klasse zum Erzeugen von DTA Dateien im schweizer Six Interbank Clearing 
 * Format.
 * 
 * @author Christoph Vieth <cvieth@coreweb.de>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public License (LGPL)
 */

namespace Z38ChFile;

require_once(dirname(__FILE__) . '/z38ChTransaction.php');

use Z38\SwissPayment\BIC;
use Z38\SwissPayment\FinancialInstitutionAddress;
use Z38\SwissPayment\GeneralAccount;
use Z38\SwissPayment\IBAN;
use Z38\SwissPayment\IID;
use Z38\SwissPayment\ISRParticipant;
use Z38\SwissPayment\Message\CustomerCreditTransfer;
use Z38\SwissPayment\Money;
use Z38\SwissPayment\PaymentInformation\CategoryPurposeCode;
use Z38\SwissPayment\PaymentInformation\PaymentInformation;
use Z38\SwissPayment\PaymentInformation\SEPAPaymentInformation;
use Z38\SwissPayment\PostalAccount;
use Z38\SwissPayment\StructuredPostalAddress;
use Z38\SwissPayment\TransactionInformation\BankCreditTransfer;
use Z38\SwissPayment\TransactionInformation\ForeignCreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS1CreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS2CreditTransfer;
use Z38\SwissPayment\TransactionInformation\ISRCreditTransfer;
use Z38\SwissPayment\TransactionInformation\PurposeCode;
use Z38\SwissPayment\TransactionInformation\SEPACreditTransfer;
use Z38\SwissPayment\UnstructuredPostalAddress;

class z38ChFile {

    private $transactions = array();
    private $transactionCounter = 0;
    private $creationDate;
    private $ident;
    private $clearingNr;
    public $currentTransaction = NULL;

    public function __construct($ident, $clearingNr) {
        $this->creationDate = date('ymd');
        $this->ident = $ident;
        $this->clearingNr = $clearingNr;
    }

    public function addTransaction($type) {
        $this->transactionCounter++;
        $seqNr = $this->transactionCounter;
        $this->transactions[$seqNr] = new dtaChTransaction($type);
        $this->transactions[$seqNr]->setInputSequenceNr($seqNr);
        $this->transactions[$seqNr]->setCreationDate($this->creationDate);
        $this->transactions[$seqNr]->setDtaId($this->ident);
        return $seqNr;
    }

    public function addTransactionESR() {
        return $this->addTransaction(dtaChTransaction::TA826);
    }

    public function addTransactionIBAN() {
        return $this->addTransaction(dtaChTransaction::TA836);
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
        $id = $this->addTransaction(dtaChTransaction::TA890);
        $totalRecord = $this->loadTransaction($id);
        dol_syslog("Sum Records: " . $id);
        $totalRecord->setTotalAmount($sum);
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
