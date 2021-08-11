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

  private $db; // To store db handler
  public $error; // To return error code (or message)
  public $errors = array(); //!< To return several error codes (or messages)
  //public $element='skeleton';	//!< Id that identify managed objects
  //public $table_element='skeleton';	//!< Name of table without prefix where object is stored
  public $id;
  public $codeline;   // Complete esr line
  public $hasAmount;  // Is this ESR has a amount in it
  public $isIBAN;     // Is this code a IBAN number?
  public $isESR;      // Is this code a ESR code line?
  public $isQRCode;   // Is this a QR code?
  public $amountStr;  // The amount as string
  public $amount;     // The amount as numeric value
  public $pcAccount;  // The PC account to pay to
  public $iban;       // The IBAN account to pay to
  public $fullRefline;    // The remaining esr line (PC Code and amount removed)
  public $refLine;    // The refcode, removed esrid
  public $billnr;     // The bill Nr.
  public $esrID;      // The ESR ID part (000000 for PC account)
  public $payToName;
  public $payToAddress;

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
      $errmsg = "Missing QR / IBAN / (V)ESR code ";
      $error++;
      dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
      $this->error = $errmsg;
      return -1 * $error;
    }
    $this->hasAmount = false;
    $this->isIBAN = is_valid_iban($this->codeline);
    $this->isESR = is_valid_esr($this->codeline);
    if ($this->isESR) {
      if (startsWith($this->codeline, "01")) {
        // ESR mit Betrag
        $this->hasAmount = true;
        $this->amountStr = substr($this->codeline, 2, strpos($this->codeline, ">") - 2);
        $this->fullRefline = substr($this->codeline, strpos($this->codeline, ">") + 1, strpos($this->codeline, "+") - strpos($this->codeline, ">") - 1);
        $this->pcAccount = substr($this->codeline, strpos($this->codeline, "+") + 2, strlen($this->codeline) - strpos($this->codeline, "+") - 3);
      } else if (startsWith($this->codeline, "042")) {
        // ESR ohne Betrag
        $this->hasAmount = false;
        $this->fullRefline = substr($this->codeline, strpos($this->codeline, ">") + 1, strpos($this->codeline, "+") - strpos($this->codeline, ">") - 1);
        $this->pcAccount = substr($this->codeline, strpos($this->codeline, "+") + 2, strlen($this->codeline) - strpos($this->codeline, "+") - 3);
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

      if ($this->hasAmount) {
        if (!isValidCheckDigit("01" . $this->amountStr)) {
          //
          $errmsg = "Invalid (V)ESR amount check digit";
          $error++;
          dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
          $this->error = $errmsg;
          return -1 * $error;
        } else {
          $this->amount = (float) substr($this->amountStr, 1, strlen($this->amountStr) - 2);
          $this->amount = $this->amount / 100;
          dol_syslog(__METHOD__ . " Amount in CHF: " . $this->amount, LOG_DEBUG);
        }
      }
      if (!isValidCheckDigit($this->fullRefline)) {
        //
        $errmsg = "Invalid (V)ESR referenceNr check digit";
        $error++;
        dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
        $this->error = $errmsg;
        return -1 * $error;
      } else {
        $this->esrID = substr($this->fullRefline, 1, 6);
        $this->refLine = substr($this->fullRefline, 7, strlen($this->fullRefline) - 8);
        $this->billnr = substr($this->fullRefline, 7, strlen($this->fullRefline) - 8);
        // $this->billnr= ltrim( $this->billnr, '0');
      }
      if (!isValidCheckDigit($this->pcAccount)) {
        //
        $errmsg = "Invalid (V)ESR pcAccount check digit";
        $error++;
        dol_syslog(__METHOD__ . " " . $errmsg, LOG_INFO);
        $this->error = $errmsg;
        return -1 * $error;
      }
      // Codeline OK so far
    }
    else if ($this->isQRCode)
    {
      // Strip CR from QR code (PHP_EOL won't work here)
      $qr_lines = explode("\n", str_replace("\r", "", $this->codeline));
      if (count($qr_lines) == 32)
      {
        // Correct number of lines
        if ($qr_lines[0] == "SPC" && $qr_lines[1] == "0200" && $qr_lines[2] == "1" && $qr_lines[30] == "EPD")
        {
          // Header fields correct
          if (startsWith($qr_lines[3], "CH") || startsWith($qr_lines[3], "LI"))
          {
            if ($qr_lines[19] == "CHF")
            {
              $this->refLine = $qr_lines[28];
              $this->billnr = $qr_lines[28];
              $this->iban = $qr_lines[3]; // Set IBAN in this field
              if (strlen($qr_lines[18]) > 0)
              {
                $this->hasAmount= true;
                $this->amountStr = $qr_lines[18];
                $this->amount = (float) $this->amountStr;
                dol_syslog(__METHOD__ . " Amount in CHF: " . $this->amount, LOG_DEBUG);
              }
              else
              {
                $this->hasAmount= false;
              }
              $this->payToName= $qr_lines["5"];
              $this->payToAddress= $qr_lines["6"];
              if ($qr_lines["4"] == "S")
              {
                // Strukturierte Adresse, Hausnummer separat
                if ($qr_lines["7"] <> "0")
                {
                  $this->payToAddress.= " ".$qr_lines["7"];
                }
                $this->payToAddress.= PHP_EOL.$qr_lines["8"]." ".$qr_lines["9"];
              }
              else
              {
                // Nicht strukturiert= Zeile 2
                $this->payToAddress.= PHP_EOL.$qr_lines["7"];
                $this->payToAddress.= PHP_EOL.$qr_lines["8"]." ".$qr_lines["9"];
              }
            }
            else
            {
              $errmsg = "Currency must be CHF, got ".$qr_line[18];
              $error++;
              dol_syslog(__METHOD__ . " " . $errmsg, LOG_WARNING);
              $this->error = $errmsg;
              return -1 * $error;
            }
          }
          else
          {
            $errmsg = "IBAN number must CH or LI account, got ".$qr_line[3];
            $error++;
            dol_syslog(__METHOD__ . " " . $errmsg, LOG_WARNING);
            $this->error = $errmsg;
            return -1 * $error;
          }
        }
        else
        {
            $errmsg = "Invalid header and/or footer lines in QR code, probably not a swiss QR invoice";
            $error++;
            dol_syslog(__METHOD__ . " " . $errmsg, LOG_WARNING);
            $this->error = $errmsg;
            return -1 * $error;
        }
      }
      else
      {
          $errmsg = "Invalid number of data lines in QR code. ".
                    "Expecting 32, got ".count($qr_lines);
          $error++;
          dol_syslog(__METHOD__ . " " . $errmsg, LOG_WARNING);
          $this->error = $errmsg;
          return -1 * $error;
      }
    }
    else {
          dol_syslog(__METHOD__ . "Neither ESR not QRBill <".$qr_line.">", LOG_INFO);
    }
    return 1;
  }

  public function setCodeline($newEsrCodeline, $newQRCode) {
    if ($newEsrCodeline) {
      $this->codeline = $newEsrCodeline;
      $this->isQRCode= startsWith($this->codeline, "SPC");
    } else {
      $this->codeline = $newQRCode;
      $this->isQRCode = true;
    }
  }

  public function setBillno($billno) {
    $this->billnr = $billno;
  }

  public function findBillno($startPos, $endPos) {
    if ($startPos >= 0 && $endPos >= 0) {
      $this->billnr = substr($this->refLine, $startPos, $endPos - $startPos);
      $this->billnr = ltrim($this->billnr, '0');
      return 1;
    } else {
      return -1;
    }
  }

  public static function formatPCAccount($pcAccount) {
    $prefix = substr($pcAccount, 0, 2);
    $middle = ltrim(substr($pcAccount, 2, strlen($pcAccount) - 3), '0');
    $suffix = substr($pcAccount, strlen($pcAccount) - 1);
    return $prefix . '-' . $middle . '-' . $suffix;
  }

}
