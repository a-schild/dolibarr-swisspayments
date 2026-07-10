<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *  \file       class/swisspaymentspayh.class.php
 *  \ingroup    swisspayments
 *  \brief      Payment header lines
 */
// Put here all includes required by your class file
require_once(DOL_DOCUMENT_ROOT . "/core/class/commonobject.class.php");

require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/paiementfourn.class.php';
dol_include_once('/custom/swisspayments/lib/dtaChFile.php');
dol_include_once('/custom/swisspayments/lib/ezagChFile.php');

dol_include_once('/custom/swisspayments/class/swisspayments.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentssoc.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentspayl.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentsfactf.class.php');

dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PaymentInformation/PaymentInformation.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/FinancialInstitutionInterface.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/AccountInterface.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Message/MessageInterface.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Message/AbstractMessage.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/BIC.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/FinancialInstitutionAddress.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/GeneralAccount.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/IBAN.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/IID.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/ISRParticipant.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Message/CustomerCreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Money/Money.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Money/MixedMoney.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Money/CHF.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PaymentInformation/CategoryPurposeCode.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PaymentInformation/PaymentInformation.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PaymentInformation/SEPAPaymentInformation.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PostalAccount.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/PostalAddressInterface.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/StructuredPostalAddress.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/CreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/BankCreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/BankCreditTransferWithQRR.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/BankCreditTransferWithCreditorReference.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/ForeignCreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/IS1CreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/IS2CreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/ISRCreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/PurposeCode.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/TransactionInformation/SEPACreditTransfer.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/UnstructuredPostalAddress.php');
dol_include_once('/custom/swisspayments/lib/Z38/SwissPayment/Text.php');

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
use Z38\SwissPayment\TransactionInformation\BankCreditTransferWithQRR;
use Z38\SwissPayment\TransactionInformation\BankCreditTransferWithCreditorReference;
use Z38\SwissPayment\TransactionInformation\ForeignCreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS1CreditTransfer;
use Z38\SwissPayment\TransactionInformation\IS2CreditTransfer;
use Z38\SwissPayment\TransactionInformation\ISRCreditTransfer;
use Z38\SwissPayment\TransactionInformation\PurposeCode;
use Z38\SwissPayment\TransactionInformation\SEPACreditTransfer;
use Z38\SwissPayment\UnstructuredPostalAddress;

/**
 * 	Put here description of your class
 */
class Swisspaymentspayh extends CommonObject {

    var $db;       //!< To store db handler
    var $error;       //!< To return error code (or message)
    var $errors = array();    //!< To return several error codes (or messages)
    var $element = 'swisspaymentspayh';   //!< Id that identify managed objects
    var $table_element = 'swisspayments_payh';  //!< Name of table without prefix where object is stored
    var $id;
    var $entity;    // Dolibarr entity (multi-company isolation)
    var $fk_user_author; // User who created the batch (access scoping)
    var $payident;  // Payment identifier
    var $datec;     // Date created
    var $dtafile;   // DTA file with payment "commands"
    var $lines;     // Holds payl lines (if loaded via fetch_lines)
    var $isISO20022= true; // Defaults to ISO20022 format
    var $isDTA= false;   // Defaults to DTA payments


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */

    function __construct($db) {
        $this->db = $db;
        return 1;
    }

    /**
     *  Create object into database
     *
     *  @param	User	$user        User that creates
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger = 0) {
        global $conf, $langs;
        $error = 0;
        $now = dol_now();

        // Clean parameters

        if (isset($this->payident)) {
            $this->payident = trim($this->payident);
        }
        if (isset($this->dtafile)) {
            $this->dtafile = trim($this->dtafile);
        } else {
            if ($this->isISO20022)
            {
                $this->dtafile = trim("pay-" . date('Y-m-d-H-i-s', $now) . ".xml");
            }
            else
            {
                $this->dtafile = trim("pay-" . date('Y-m-d-H-i-s', $now) . ".dta");
            }
        }


        // Check parameters
        // Put here code to add control on parameters values
        // Insert request
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "(";

        $sql.= "entity,";
        $sql.= "fk_user_author,";
        $sql.= "payident,";
        $sql.= "datec,";
        $sql.= "dtafile";


        $sql.= ") VALUES (";

        $sql.= " " . ((int) $conf->entity) . ",";
        $sql.= " " . ((isset($user) && $user->id > 0) ? ((int) $user->id) : 'NULL') . ",";
        $sql.= " " . (!isset($this->payident) ? 'NULL' : "'" . $this->db->escape($this->payident) . "'") . ",";
        $sql.= " '" . $this->db->idate($now) . "',";
        $sql.= " " . (!isset($this->dtafile) ? 'NULL' : "'" . $this->db->escape($this->dtafile) . "'");


        $sql.= ")";

        $this->db->begin();

        dol_syslog(__METHOD__, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);

            if (!$notrigger) {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action calls a trigger.
                //// Call triggers
                //$result=$this->call_trigger('MYOBJECT_CREATE',$user);
                //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return $this->id;
        }
    }

    /**
     *  Load object in memory from the database
     *
     *  @param	int		$id    	Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    function fetch($id, $payident = null) {
        global $langs;
        $sql = "SELECT";
        $sql.= " t.rowid,";
        $sql.= " t.entity,";
        $sql.= " t.fk_user_author,";
        $sql.= " t.payident,";
        $sql.= " t.datec,";
        $sql.= " t.dtafile";


        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as t";
        if ($payident)
            $sql.= " WHERE t.payident = '" . $this->db->escape($payident) . "'";
        else
            $sql.= " WHERE t.rowid = " . ((int) $id);
        // Restrict to the current entity (multi-company isolation).
        $sql.= " AND t.entity IN (" . getEntity($this->element) . ")";

        dol_syslog(get_class($this) . "::fetch");
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->entity = $obj->entity;
                $this->fk_user_author = $obj->fk_user_author;
                $this->payident = $obj->payident;
                $this->datec = $this->db->jdate($obj->datec);
                $this->dtafile = $obj->dtafile;
            }
            $this->db->free($resql);

            return 1;
        } else {
            $this->error = "Error " . $this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modifies
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user, $notrigger = 0) {
        global $conf, $langs;
        $error = 0;

        // Clean parameters

        if (isset($this->payident))
        {
            $this->payident = trim($this->payident);
        }
        if (isset($this->dtafile))
        {
            $this->dtafile = trim($this->dtafile);
        }

        // Check parameters
        // Put here code to add a control on parameters values
        // Update request
        $sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET";

        $sql.= " payident=" . (isset($this->payident) ? "'" . $this->db->escape($this->payident) . "'" : "null") . ",";
        $sql.= " dtafile=" . (isset($this->dtafile) ? "'" . $this->db->escape($this->dtafile) . "'" : "null");

        $sql.= " WHERE rowid=" . $this->id;

        $this->db->begin();

        dol_syslog(__METHOD__);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            if (!$notrigger) {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action calls a trigger.
                //// Call triggers
                //$result=$this->call_trigger('MYOBJECT_MODIFY',$user);
                //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
                //// End call triggers
            }
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     *  Delete object in database
     *
     * 	@param  User	$user        User that deletes
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return	int					 <0 if KO, >0 if OK
     */
    function delete($user, $notrigger = 0) {
        global $conf, $langs;
        $error = 0;

        $this->db->begin();

        if (!$error) {
            if (!$notrigger) {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action calls a trigger.
                //// Call triggers
                //$result=$this->call_trigger('MYOBJECT_DELETE',$user);
                //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
                //// End call triggers
            }
        }

        if (!$error) {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
            $sql.= " WHERE rowid=" . $this->id;

            dol_syslog(__METHOD__);
            $resql = $this->db->query($sql);
            if (!$resql) {
                $error++;
                $this->errors[] = "Error " . $this->db->lasterror();
            }
        }

        // Commit or rollback
        if ($error) {
            foreach ($this->errors as $errmsg) {
                dol_syslog(__METHOD__ . " " . $errmsg, LOG_ERR);
                $this->error.=($this->error ? ', ' . $errmsg : $errmsg);
            }
            $this->db->rollback();
            return -1 * $error;
        } else {
            $this->db->commit();
            return 1;
        }
    }

    /**
     * 	Load an object from its id and create a new one in database
     *
     * 	@param	int		$fromid     Id of object to clone
     * 	@return	int					New id of clone
     */
    function createFromClone($fromid) {
        global $user, $langs;

        $error = 0;

        $object = new Swisspaymentssoc($this->db);

        $this->db->begin();

        // Load source object
        $object->fetch($fromid);
        $object->id = 0;
        $object->statut = 0;

        // Clear fields
        // ...
        // Create clone
        $result = $object->create($user);

        // Other options
        if ($result < 0) {
            $this->error = $object->error;
            $error++;
        }

        if (!$error) {
            
        }

        // End
        if (!$error) {
            $this->db->commit();
            return $object->id;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * 	Initialise object with example values
     * 	Id must be 0 if object instance is a specimen
     *
     * 	@return	void
     */
    function initAsSpecimen() {
        $this->id = 0;
        $this->payident = '';
        $this->dtafile = '';
    }

    /**
     * 	Load this->lines
     *
     * 	@return     int         1 si ok, < 0 si erreur
     */
    function fetch_lines() {
        $sql = 'SELECT f.rowid, f.fk_payh as fk_payh, f.fk_payementfourn as fk_payementfourn';
        $sql.= ' FROM ' . MAIN_DB_PREFIX . 'swisspayments_payl as f';
        $sql.= ' WHERE fk_payh=' . $this->id;

        dol_syslog(get_class($this) . "::fetch_lines", LOG_DEBUG);
        $resql_rows = $this->db->query($sql);
        if ($resql_rows) {
            $num_rows = $this->db->num_rows($resql_rows);
            if ($num_rows) {
                $i = 0;
                while ($i < $num_rows) {
                    $obj = $this->db->fetch_object($resql_rows);


                    $this->lines[$i] = new Swisspaymentspayl($this->db);

                    $this->lines[$i]->id = $obj->rowid;

                    $this->lines[$i]->fk_payh = $obj->fk_payh;
                    $this->lines[$i]->fk_payementfourn = $obj->fk_payementfourn;
                    $this->lines[$i]->datec = $this->db->jdate($obj->datec);

                    $i++;
                }
            }
            $this->db->free($resql_rows);
            return 1;
        } else {
            $this->error = $this->db->error();
            return -3;
        }
    }

    /**
     * Split a street line into street name and house number for the structured
     * address (pain.001 BldgNb). Recognises a trailing house number such as
     * "3", "4b", "10", "12-14" or "3/5". When no trailing number is found the
     * whole line is returned as the street with a null building number.
     *
     * @param   string  $line   Street line (e.g. "Hintermättlistr. 3")
     * @return  array           array(streetName, buildingNo|null)
     */
    private static function splitBuildingNo($line) {
        $line = trim($line);
        if (preg_match('/^(.+)\s+(\d+[a-zA-Z]?(?:[-\/]\d+[a-zA-Z]?)?)$/u', $line, $m)) {
            return array(trim($m[1]), $m[2]);
        }
        return array($line, null);
    }

    function createDTA() {
        if ($this->fetch_lines() >= 0) {
            $currentRow= 0;

            // Always emit the current Swiss Payment Standards format
            // pain.001.001.09.ch.03 (SPS 2022). The library still supports SPS_2021
            // (pain.001.001.03.ch.02) if an older format is ever needed again.
            $spsVersion = CustomerCreditTransfer::SPS_2022;

            // Software version reported in the message header (CtctDtls); single source of
            // truth is the module descriptor's VERSION constant.
            dol_include_once('/custom/swisspayments/core/modules/modSwisspayments.class.php');
            $moduleVersion = class_exists('modswisspayments') ? modswisspayments::VERSION : '';

            foreach ($this->lines as $payl) {
                $currentRow++;
                $paiement = new PaiementFourn($this->db);
                $paiement->fetch($payl->fk_payementfourn);
                $bank = new Account($this->db);
                $bank->fetch($paiement->bank_account);
                if ($currentRow == 1)
                {
                    //$isDTA= $bank->code_banque != '9000';
                }
                if ($this->isISO20022)
                {
                    if (!isset($message)) 
                    {
                        // SPS_2022 => pain.001.001.09.ch.03
                        $message = new CustomerCreditTransfer(
                                    $this->payident,
                                    $bank->proprio,
                                    $spsVersion,
                                    'Dolibarr Swisspayments',
                                    $moduleVersion,
                                    'Aarboard AG'
                                );
                    }
                    $paymentOut = new PaymentInformation($paiement->ref, $bank->proprio, new BIC($bank->bic), new IBAN($bank->iban));
                    //new z38ChFile(sprintf('%05d', $this->id), $bank->code_banque);
                }
                else if (!isset($paymentOut)) 
                {
                    if ($this->isDTA)
                    {
                        $paymentOut = new dtaChFile(sprintf('%05d', $this->id), $bank->code_banque);
                    }
                    else
                    {
                        $paymentOut = new ezagChFile(sprintf('%05d', $this->id), $bank->number);
                    }
                }
                $bills = $paiement->getBillsArray();
                dol_syslog("Factures to pay: " . var_export($paiement, true));
                dol_syslog("Factures to pay bills: " . var_export($bills, true));
                foreach ($bills as $bill) {
                    $fact = new FactureFournisseur($this->db);
                    $result= $fact->fetch($bill);
                    if ($result >= 0)
                    {
                        $isQRBILL= false;
                        $isESR= false;
                        dol_syslog("Facture: " . var_export($fact, true));
                        $soc = new Societe($this->db);
                        $result= $soc->fetch($fact->socid);
                        if ($result >= 0)
                        {
                            $ribs= $soc->get_all_rib();
                            $defaultRIB= null;
                            foreach($ribs as $rib)
                            {
                                    if ($rib->default_rib)
                                    {
                                            $defaultRIB= $rib;
                                    }
                            }
                            $factf= new Swisspaymentsfactf($this->db);
                            $result= $factf->fetch(null, $bill, null);
                            if ($result > 0 && isset($factf->id))
                            {
                              if ($factf->esrpartynr=="QRBILL")
                              {
                                 $isQRBILL= true;
                                 if (!$this->isISO20022)
                                 {
                                    $seqNr = $paymentOut->addTransactionIBAN();
                                    $trans = $paymentOut->loadTransaction($seqNr);
                                    $trans->setProcessingDay(dtaChFile::dateToDATString($paiement->date));
                                 }
                              }
                              else
                              {
                                 $isESR= true;
                                 if (!$this->isISO20022)
                                 {
                                    $seqNr = $paymentOut->addTransactionESR();
                                    $trans = $paymentOut->loadTransaction($seqNr);
                                    $trans->setProcessingDay(dtaChFile::dateToDATString($paiement->date));
                                 }
                              }
                            }
                            else
                            {
                                if (!$this->isISO20022)
                                {
                                    $seqNr = $paymentOut->addTransactionIBAN();
                                    $trans = $paymentOut->loadTransaction($seqNr);
                                }
                            }
                            $adrRecipient= str_replace(array("\r\n", "\r"), "\n", $soc->address);
                            $arrRec = explode("\n", $adrRecipient);
                            $rLine1= "";
                            $rLine2= "";
                            if (count($arrRec) > 0)
                            {
                                $rLine1= $arrRec[0];
                            }
                            if (count($arrRec) > 1)
                            {
                                $rLine2= $arrRec[1];
                            }
                            if (count($arrRec) > 2)
                            {
                                $rLine2= $rLine2 . ',' . $arrRec[2];
                            }
                            if (count($arrRec) > 3)
                            {
                                $rLine2= $rLine2 . ',' . $arrRec[3];
                            }

                            if ($this->isISO20022)
                            {
                                // Both formats carry a STRUCTURED creditor address (StrtNm/BldgNb/PstCd/
                                // TwnNm/Ctry). The .03.ch.02 schema accepts it too, and PostFinance
                                // recommends structured over the old AdrLine form (structured becomes
                                // mandatory from Nov 2026). Split the trailing house number out of the
                                // whole street (all address lines combined - the number is often on the
                                // last line) into BldgNb, which the standard recommends. Town + country
                                // are mandatory; fall back to CH when the third party has no country set.
                                list($strtNm, $bldgNb) = self::splitBuildingNo(trim($rLine1 . ' ' . $rLine2));
                                $creditorAddress = StructuredPostalAddress::sanitize(
                                            $strtNm,
                                            $bldgNb,
                                            $soc->zip,
                                            $soc->town,
                                            !empty($soc->country_code) ? $soc->country_code : 'CH'
                                        );
                                if ($isESR)
                                {
                                    // ESR / red inpayment slip (payment type 1) was removed from the
                                    // Swiss Payment Standards with pain.001.001.09. Such bills must be
                                    // paid as QR-bill or IBAN transfer instead.
                                    $error++;
                                    $this->errors[] = "ESR/red-slip payment (bill " . $fact->ref . ") is not supported in pain.001.001.09; use a QR-bill or IBAN transfer.";
                                    continue;
                                }
                                else if ($isQRBILL)
                                {
                                    // A Swiss QR-bill comes in three variants, distinguished by the
                                    // creditor account and reference:
                                    //  - QR-IBAN + QR reference (QRR, numeric)      -> BankCreditTransferWithQRR
                                    //  - normal IBAN + Creditor Reference (SCOR/RF) -> BankCreditTransferWithCreditorReference
                                    //  - normal IBAN + no reference (NON)           -> plain BankCreditTransfer
                                    // The creditor agent (IID) is derived from the CH/LI IBAN in all cases.
                                    // Use the IBAN stored with THIS bill (from the QR at import time); this
                                    // is the correct creditor account even when the supplier has several
                                    // bank accounts. Fall back to the default RIB for legacy bills imported
                                    // before the per-bill IBAN was stored.
                                    $billIban= (isset($factf->iban) && trim((string) $factf->iban) !== '')
                                            ? $factf->iban
                                            : (isset($defaultRIB->iban) ? $defaultRIB->iban : '');
                                    $iban= new IBAN($billIban);
                                    $qrRef= trim((string) $factf->esrline);
                                    $amountCHF= new Money\CHF(round(floatval($paiement->montant)*100.0));
                                    $isQrIban= (bool) preg_match('/^(CH|LI)[0-9]{2}3/', $iban->normalize());
                                    if ($isQrIban)
                                    {
                                        $transaction = new BankCreditTransferWithQRR(
                                                $paiement->id, $paiement->ref, $amountCHF,
                                                $soc->name, $creditorAddress, $iban,
                                                IID::fromIBAN($iban), $qrRef);
                                    }
                                    else if (preg_match('/^RF/i', str_replace(' ', '', $qrRef)))
                                    {
                                        $transaction = new BankCreditTransferWithCreditorReference(
                                                $paiement->id, $paiement->ref, $amountCHF,
                                                $soc->name, $creditorAddress, $iban,
                                                IID::fromIBAN($iban), $qrRef);
                                    }
                                    else
                                    {
                                        // Normal IBAN, no reference (NON): plain IBAN transfer, use the
                                        // supplier invoice number as unstructured remittance information.
                                        $transaction = new BankCreditTransfer(
                                                $paiement->id, $paiement->ref, $amountCHF,
                                                $soc->name, $creditorAddress, $iban,
                                                IID::fromIBAN($iban));
                                        $transaction->setRemittanceInformation($fact->ref_supplier);
                                    }
                                }
                                else
                                {
                                    if (isset($defaultRIB->iban) && !empty($defaultRIB->iban)
                                            && isset($defaultRIB->bic) && !empty($defaultRIB->bic))
                                    {
                                        $transaction = new BankCreditTransfer(
                                                    $paiement->id,
                                                    $paiement->ref,
                                                    new Money\CHF(round(floatval($paiement->montant)*100.0)),
                                                    $soc->name,
                                                    $creditorAddress,
                                                    new IBAN($defaultRIB->iban),
                                                    new BIC($defaultRIB->bic)
                                                );
                                        $transaction->setRemittanceInformation($fact->ref_supplier);
                                    }
                                    else
                                    {
                                        // Postal-account (IS) payments were also removed with
                                        // pain.001.001.09. Without a QR reference or IBAN+BIC we cannot
                                        // build a valid transaction.
                                        $error++;
                                        $this->errors[] = "Bill " . $fact->ref . " has no QR reference and no IBAN+BIC; cannot be paid via pain.001.001.09.";
                                        continue;
                                    }
                                }
                                $paymentOut->addTransaction($transaction);
                                $executionDate= new DateTime();
                                $executionDate->setTimestamp($paiement->date);
                                $paymentOut->setExecutionDate($executionDate);
                                $message->addPayment($paymentOut);                
                            }
                            else
                            {
                                $trans->setPaymentAmount(floatval($paiement->montant), "CHF", dtaChFile::dateToDATString($paiement->date));

                                // Von
                                $trans->setClientClearingNr((int)$bank->code_banque);
                                $adrSender= $bank->proprio . "\n" . $bank->owner_address;
                                $adrSender= str_replace(array("\r\n", "\r"), "\n", $adrSender);
                                $arr = explode("\n", $adrSender);
                                $line1= "";
                                $line2= "";
                                $line3= "";
                                $line4= "";
                                if (count($arr) > 0)
                                {
                                    $line1= $arr[0];
                                }
                                if (count($arr) > 1)
                                {
                                    $line2= $arr[1];
                                }
                                if (count($arr) > 2)
                                {
                                    $line3= $arr[2];
                                }
                                if (count($arr) > 3)
                                {
                                    $line4= $arr[3];
                                }
                                $trans->setClient($line1, $line2, $line3, $line4);
                                $trans->setDebitAccountIBAN($bank->iban);
                                // Nach
                                if ($isESR)
                                {
                                    $trans->setRecipientESRPartyNr($factf->esrpartynr);
                                    $trans->setRecipientESRNr($factf->esrrefnr);
                                    $trans->setRecipient($factf->esrpartynr, $soc->name, $rLine1, $rLine2, $soc->zip . ' ' . $soc->town);
                                }
                                else
                                {
                                    $trans->setRecipientBIC($defaultRIB->bic);
                                    $trans->setRecipientIBANNr($defaultRIB->iban);
                                    $trans->setRecipient($bank->number, $soc->name,  $rLine1, $rLine2, $soc->zip, $soc->town);
                                    $trans->setPaymentReason($fact->ref_supplier, $fact->ref);
                                }
                                //$trans->setTotalAmount(floatval($paiement->montant));
                                $paymentOut->saveTransaction($seqNr, $trans);
                            }
                        }
                        else
                        {
                            $error++;
                            $this->errors[] = $soc->errors();
                        }
                    }
                    else
                    {
                        $error++;
                        $this->errors[] = $fact->errors();
                    }
                }
            }

            dol_syslog("DOC path: " . DOL_DATA_ROOT);
            $dtaPath= DOL_DATA_ROOT . "/swisspayments/dtafiles/";
            if (!file_exists($dtaPath))
            {
                mkdir($dtaPath, 0777 , true);
            }
            $outFileName= $dtaPath . $this->dtafile;

            if ($this->isISO20022)
            {
                file_put_contents($outFileName, $message->asXml());
            }
            else
            {
                $paymentOut->toFile($outFileName);
            }
            return 1; // OK, done
        }
        else
        {
            $this->errors[] = "Error No payments found";
            return -1;
        }
    }

}
