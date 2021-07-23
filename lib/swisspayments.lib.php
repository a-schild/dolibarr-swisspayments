<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 *	\file		lib/swisspayments.lib.php
 *	\ingroup	swisspayments
 *	\brief		About library
 */

function swisspaymentsAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("mymodule@mymodule");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/mymodule/admin/admin_mymodule.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/mymodule/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'mymodule');

	return $head;
}


function is_valid_iban($str) {
  static $charmap = array (
    'A' => 10, 'C' => 12, 'D' => 13, 'E' => 14, 'F' => 15, 'G' => 16, 'H' => 17, 'I' => 18, 'J' => 19, 'K' => 20, 'L' => 21, 'M' => 22, 'N' => 23, 'O' => 24, 'P' => 25, 'Q' => 26, 'R' => 27, 'S' => 28, 'T' => 29, 'U' => 30, 'V' => 31, 'W' => 32, 'X' => 33, 'Y' => 34, 'Z' => 35,
);
 if (!preg_match("/\A[A-Z]{2}\d{2} ?[A-Z\d]{4}( ?\d{4}){1,} ?\d{1,4}\z/", $str)) {
    return false;
  }
  $iban = str_replace(' ', '', $str);
  $iban = substr($iban, 4) . substr($iban, 0, 4);
  echo $iban, "\n";
  $iban = strtr($iban, $charmap);
  echo $iban, "\n";
  return $iban % 97 == 1;
}

function is_valid_esr($str)
{
	$hasMount= false;
	$amountStr= "";
	$fullRefline= "";
	
	if (startsWith($str, "01")) {
		// ESR mit Betrag
		$hasAmount = true;
		$amountStr= substr($str, 2, strpos($str, ">")-2);
		$fullRefline=  substr($str, strpos($str, ">")+1, strpos($str, "+")-strpos($str, ">")-1);
		$pcAccount=  substr($str, strpos($str, "+")+2, strlen($str)-strpos($str, "+")-3);
	} else if (startsWith($str, "042")) {
		// ESR ohne Betrag
		$hasAmount = false;
		$fullRefline=  substr($str, strpos($str, ">")+1, strpos($str, "+")-strpos($str, ">")-1);
		$pcAccount=  substr($str, strpos($str, "+")+2, strlen($str)-strpos($str, "+")-3);
	} else {
		return false;
	}
	if ($hasAmount)
	{
		if (!isValidCheckDigit("01".$amountStr))
		{
			return false;
        }
	}
	if (!isValidCheckDigit($fullRefline))
	{
		return false;
	}
	if (!isValidCheckDigit($pcAccount))
	{
		return false;
	}
	return true;
}

/**
 * Creates Modulo10 recursive check digit
 * found on http://www.developers-guide.net/forums/5431,modulo10-rekursiv, THANK YOU!
 *
 * @param string $number
 * @return int
 */
function modulo10($number) {
	$table = array(0, 9, 4, 6, 8, 2, 7, 1, 3, 5);
	$next = 0;
	for ($i = 0; $i < strlen($number); $i++) {
		$next = $table[($next + substr($number, $i, 1)) % 10];
	}
	return (10 - $next) % 10;
}

function isValidCheckDigit($fullString)
{
	$checkDigit= substr($fullString, strlen($fullString)-1);
	$code= substr($fullString, 0, strlen($fullString)-1);
	$calcDigit= modulo10($code);
	$retVal= $checkDigit == $calcDigit;
	if (!$retVal)
	{
		dol_syslog(__METHOD__ . " checkdigit should be: " . $calcDigit . " received " . $checkDigit . " in full code " . $fullString . " code " .$code, LOG_INFO);
	}
	return $retVal;
}

function startsWith($fullstring, $startString) {
	return !strncmp($fullstring, $startString, strlen($startString));
}
	