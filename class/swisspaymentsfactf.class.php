<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 * 
 *  \file       class/swisspaymentsfactf.class.php
 *  \ingroup    swisspayments
 * 
 * 
 */

// Put here all includes required by your class file
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");


/**
 *	Stores the swiss payment specific informations, like ESR or QR esr nr
 */
class Swisspaymentsfactf extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	var $element='swisspaymentsfactf';			//!< Id that identify managed objects
	var $table_element='swisspayments_factf';		//!< Name of table without prefix where object is stored

        var $id;
    
	var $fk_factid;
	var $esrline;
        var $esrpartynr;
        var $esrrefnr;

    


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
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
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->fk_factid)) $this->fk_factid=trim($this->fk_factid);
		if (isset($this->esrline)) $this->esrline=trim($this->esrline);
		if (isset($this->esrpartynr)) $this->esrpartynr=trim($this->esrpartynr);
		if (isset($this->esrrefnr)) $this->esrrefnr=trim($this->esrrefnr);
        

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element."(";
		
		$sql.= "fk_factid,";
		$sql.= "esrline,";
		$sql.= "esrpartynr,";
		$sql.= "esrrefnr";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($this->fk_factid)?'NULL':"'".$this->fk_factid."'").",";
		$sql.= " ".(! isset($this->esrline)?'NULL':"'".$this->db->escape(str_replace("\n", "\\n", str_replace("\r", "",$this->esrline)))."'").",";
		$sql.= " ".(! isset($this->esrpartynr)?"'QRBILL'":"'". $this->db->escape($this->esrpartynr)."'").",";
		$sql.= " ".(! isset($this->esrrefnr)?"'QRBILL'":"'".$this->db->escape($this->esrrefnr)."'");
        
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(__METHOD__, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action calls a trigger.

	            //// Call triggers
	            //$result=$this->call_trigger('MYOBJECT_CREATE',$user);
	            //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
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
    function fetch($id, $factID, $esrLINE)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		
		$sql.= " t.fk_factid,";
		$sql.= " t.esrline,";
                $sql.= " t.esrpartynr,";
                $sql.= " t.esrrefnr";

		
        $sql.= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
        if ($factID)
        {
            $sql.= " WHERE t.fk_factid = ".$this->db->escape($factID);
        }
        else if ($esrLINE)
        {
            $sql.= " WHERE t.esrline = ".$this->db->escape($esrLINE);
        }
        else
        {
            $sql.= " WHERE t.rowid = ".$id;
        }

    	dol_syslog(get_class($this)."::fetch");
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->fk_factid = $obj->fk_factid;
				$this->esrline = $obj->esrline;
				$this->esrpartynr = $obj->esrpartynr;
				$this->esrrefnr = $obj->esrrefnr;
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
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
    function update($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->fk_factid)) $this->fk_factid=trim($this->fk_factid);
		if (isset($this->esrline)) $this->esrline=trim($this->esrline);
		if (isset($this->esrpartynr)) $this->esrpartynr=trim($this->esrpartynr);
		if (isset($this->esrrefnr)) $this->esrrefnr=trim($this->esrrefnr);

		// Check parameters
		// Put here code to add a control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        
		$sql.= " fk_factid=".(isset($this->fk_factid)?$this->fk_factid:"null").",";
		$sql.= " esrline=".(isset($this->esrline)?$this->esrline:"null").",";
		$sql.= " esrpartynr=".(isset($this->esrpartynr)?$this->esrpartynr:"null").",";
		$sql.= " esrrefnr=".(isset($this->esrrefnr)?$this->esrrefnr:"null");
        
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(__METHOD__);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action calls a trigger.

	            //// Call triggers
	            //$result=$this->call_trigger('MYOBJECT_MODIFY',$user);
	            //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
	            //// End call triggers
			 }
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param  User	$user        User that deletes
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action calls a trigger.

	            //// Call triggers
	            //$result=$this->call_trigger('MYOBJECT_DELETE',$user);
	            //if ($result < 0) { $error++; //Do also what you must do to rollback action if trigger fail}
	            //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
    		$sql.= " WHERE rowid=".$this->id;

    		dol_syslog(__METHOD__);
    		$resql = $this->db->query($sql);
        	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(__METHOD__." ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}



	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Swisspaymentssoc($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{


		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function initAsSpecimen()
	{
		$this->id=0;
		
		$this->fk_factid='';
		$this->esrline='';
		$this->esrpartynr='';
		$this->esrrefnr='';
	}

}
