<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 * 	\file		class/swisspayments.class.php
 * 	\ingroup	swisspayments
 * 	\brief		CRUD swisspayments
 */
dol_include_once('/swisspayments/lib/swisspayments.lib.php');
/**
 * Put your class' description here
 */
class SwisspaymentsClass // extends CommonObject 
{
    private $db; //!< To store db handler
    public $error; //!< To return error code (or message)
    public $errors = array(); //!< To return several error codes (or messages)
    //public $element='skeleton';	//!< Id that identify managed objects
    //public $table_element='skeleton';	//!< Name of table without prefix where object is stored
    public $id;
    public $codeline;  // Complete esr line
    public $hasAmount;  // Is this ESR has a amount in it
	public $isIBAN;		// Is this code a IBAN number?
	public $isESR;		// Is this code a ESR code line?
    public $amountStr;  // The amount as string
    public $amount;     // The amount as numeric value
    public $pcAccount;  // The PC account to pay to
    public $fullRefline;    // The remaining esr line (PC Code and amount removed)
    public $refLine;    // The refcode, removed esrid
    public $billnr;     // The bill Nr.
    public $esrID;      // The EDR ID part (000000 for PC account)

    /**
     * Constructor
     *
     * 	@param	DoliDb		$db		Database handler
     */
    public function __construct($db) {
        $this->db = $db;

        return 1;
    }

    /**
     * Create object into database
     *
     * 	@param		User	$user		User that create
     * 	@param		int		$notrigger	0=launch triggers after, 1=disable triggers
     * 	@return		int					<0 if KO, Id of created object if OK
     */
    public function validateCode($user) {
        global $conf, $langs;
        $error = 0;

        // Clean parameters
        if (isset($this->codeline)) {
            $this->codeline = trim($this->codeline);
        } else {
            $errmsg = "Missing IBAN or (V)ESR code ";
            $error++;
            dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
            $this->error = $errmsg;
            return -1 * $error;
        }
        $this->hasAmount = false;
		$this->isIBAN= is_valid_iban($this->codeline);
		$this->isESR= is_valid_esr($this->codeline);
		if ($this->isESR)
		{
			if (startsWith($this->codeline, "01")) {
				// ESR mit Betrag
				$this->hasAmount = true;
				$this->amountStr= substr($this->codeline, 2, strpos($this->codeline, ">")-2);
				$this->fullRefline=  substr($this->codeline, strpos($this->codeline, ">")+1, strpos($this->codeline, "+")-strpos($this->codeline, ">")-1);
				$this->pcAccount=  substr($this->codeline, strpos($this->codeline, "+")+2, strlen($this->codeline)-strpos($this->codeline, "+")-3);
			} else if (startsWith($this->codeline, "042")) {
				// ESR ohne Betrag
				$this->hasAmount = false;
				$this->fullRefline=  substr($this->codeline, strpos($this->codeline, ">")+1, strpos($this->codeline, "+")-strpos($this->codeline, ">")-1);
				$this->pcAccount=  substr($this->codeline, strpos($this->codeline, "+")+2, strlen($this->codeline)-strpos($this->codeline, "+")-3);
			} else {
				$errmsg = "Invalid (V)ESR start code ";
				$error++;
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
				$this->error = $errmsg;
				return -1 * $error;
			}
			dol_syslog(__METHOD__ . " Amount: " . $this->amountStr, LOG_DEBUG);
			dol_syslog(__METHOD__ . " FullRefLine: " . $this->fullRefline, LOG_DEBUG);
			dol_syslog(__METHOD__ . " PCAccount: " . $this->pcAccount, LOG_DEBUG);

			if ($this->hasAmount)
			{
				if (!isValidCheckDigit("01".$this->amountStr))
				{
					//
					$errmsg = "Invalid (V)ESR amount check digit";
					$error++;
					dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
					$this->error = $errmsg;
					return -1 * $error;
				}
				else
				{
					$this->amount= (float) substr($this->amountStr, 1, strlen($this->amountStr)-2);
					$this->amount= $this->amount / 100;
					dol_syslog(__METHOD__ . " Amount in CHF: " . $this->amount, LOG_DEBUG);
				}
			}
			if (!isValidCheckDigit($this->fullRefline))
			{
				//
				$errmsg = "Invalid (V)ESR referenceNr check digit";
				$error++;
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
				$this->error = $errmsg;
				return -1 * $error;
			}
			else
			{
				$this->esrID= substr($this->fullRefline, 1, 6);
				$this->refLine= substr($this->fullRefline, 7,  strlen($this->fullRefline)-8);
				$this->billnr= substr($this->fullRefline, 7, strlen($this->fullRefline)-8);
				// $this->billnr= ltrim( $this->billnr, '0');
			}
			if (!isValidCheckDigit($this->pcAccount))
			{
				//
				$errmsg = "Invalid (V)ESR pcAccount check digit";
				$error++;
				dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
				$this->error = $errmsg;
				return -1 * $error;
			}
			// Codeline OK so far
		}
        return 1;
    }

    /**
     * Load object in memory from database
     *
     * 	@param		int		$id	Id object
     * 	@return		int			<0 if KO, >0 if OK
     */
    public function fetch($id) {
        global $langs;
        $sql = "SELECT";
        $sql.= " t.rowid,";
        $sql.= " t.field1,";
        $sql.= " t.field2";
        //...
        $sql.= " FROM " . MAIN_DB_PREFIX . "mytable as t";
        $sql.= " WHERE t.rowid = " . $id;

        dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->prop1 = $obj->field1;
                $this->prop2 = $obj->field2;
                //...
            }
            $this->db->free($resql);

            return 1;
        } else {
            $this->error = "Error " . $this->db->lasterror();
            dol_syslog(__METHOD__ . " " . $this->error, LOG_ERR);

            return -1;
        }
    }

    /**
     * Update object into database
     *
     * 	@param		User	$user		User that modify
     * 	@param		int		$notrigger	0=launch triggers after, 1=disable triggers
     * 	@return		int					<0 if KO, >0 if OK
     */
    public function update($user = 0, $notrigger = 0) {
        global $conf, $langs;
        $error = 0;

        // Clean parameters
        if (isset($this->prop1)) {
            $this->prop1 = trim($this->prop1);
        }
        if (isset($this->prop2)) {
            $this->prop2 = trim($this->prop2);
        }

        // Check parameters
        // Put here code to add control on parameters values
        // Update request
        $sql = "UPDATE " . MAIN_DB_PREFIX . "mytable SET";
        $sql.= " field1=" . (isset($this->field1) ? "'" . $this->db->escape($this->field1) . "'" : "null") . ",";
        $sql.= " field2=" . (isset($this->field2) ? "'" . $this->db->escape($this->field2) . "'" : "null") . "";

        $sql.= " WHERE rowid=" . $this->id;

        $this->db->begin();

        dol_syslog(__METHOD__ . " sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $error ++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            if (!$notrigger) {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action call a trigger.
                //// Call triggers
                //include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
                //$interface=new Interfaces($this->db);
                //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
                //if ($result < 0) { $error++; $this->errors=$interface->errors; }
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
     * Delete object in database
     *
     * 	@param		User	$user		User that delete
     * 	@param		int		$notrigger	0=launch triggers after, 1=disable triggers
     * 	@return		int					<0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = 0) {
        global $conf, $langs;
        $error = 0;

        $this->db->begin();

        if (!$error) {
            if (!$notrigger) {
                // Uncomment this and change MYOBJECT to your own tag if you
                // want this action call a trigger.
                //// Call triggers
                //include_once DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php";
                //$interface=new Interfaces($this->db);
                //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
                //if ($result < 0) { $error++; $this->errors=$interface->errors; }
                //// End call triggers
            }
        }

        if (!$error) {
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "mytable";
            $sql.= " WHERE rowid=" . $this->id;

            dol_syslog(__METHOD__ . " sql=" . $sql);
            $resql = $this->db->query($sql);
            if (!$resql) {
                $error ++;
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
     * Load an object from its id and create a new one in database
     *
     * 	@param		int		$fromid		Id of object to clone
     * 	@return		int					New id of clone
     */
    public function createFromClone($fromid) {
        global $user, $langs;

        $error = 0;

        $object = new SkeletonClass($this->db);

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
            $error ++;
        }

        if (!$error) {
            // Do something
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
     * Initialise object with example values
     * Id must be 0 if object instance is a specimen
     *
     * 	@return		void
     */
    public function initAsSpecimen() {
        $this->id = 0;
        $this->prop1 = 'prop1';
        $this->prop2 = 'prop2';
    }

    public function setCodeline($newCodeline) {
        $this->codeline = $newCodeline;
    }


    public function setBillno($billno)
    {
        $this->billnr= $billno;
    }
    
    public function findBillno($startPos, $endPos)
    {
        if ($startPos >= 0 && $endPos >= 0)
        {
            $this->billnr= substr($this->refLine, $startPos, $endPos-$startPos);
            $this->billnr= ltrim($this->billnr, '0');
            return 1;
        }
        else
        {
            return -1;
        }
    }

    public static function formatPCAccount($pcAccount)
    {
        $prefix= substr($pcAccount, 0, 2);
        $middle= ltrim(substr($pcAccount, 2, strlen($pcAccount)-3), '0');
        $suffix= substr($pcAccount, strlen($pcAccount)-1);
        return $prefix . '-' . $middle . '-' . $suffix;
    }
}
