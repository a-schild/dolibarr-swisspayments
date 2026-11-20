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

/**
 * Validate a SCOR / ISO 11649 creditor reference: "RF" + two check digits + up to 21
 * alphanumeric characters, with a valid mod-97 check digit (letters mapped A=10..Z=35,
 * the "RF" + check digits moved to the end). Spaces are ignored.
 *
 * @param string $ref
 * @return bool
 */
function isValidScor($ref) {
	$ref = strtoupper(str_replace(' ', '', $ref));
	if (!preg_match('/^RF[0-9]{2}[A-Z0-9]{1,21}$/', $ref)) {
		return false;
	}
	$rearranged = substr($ref, 4) . substr($ref, 0, 4);
	$numeric = '';
	for ($i = 0; $i < strlen($rearranged); $i++) {
		$c = $rearranged[$i];
		$numeric .= ctype_digit($c) ? $c : (string) (ord($c) - 55);
	}
	// Compute mod 97 piecewise to avoid overflowing on the large number.
	$remainder = 0;
	for ($i = 0; $i < strlen($numeric); $i++) {
		$remainder = ($remainder * 10 + (int) $numeric[$i]) % 97;
	}
	return $remainder === 1;
}

/**
 * Entity-aware data directory of the module. On a multicompany install core maps the
 * module's documents to DOL_DATA_ROOT/<entity>/swisspayments for every entity other than
 * 1, and document.php resolves downloads through the same mapping - so files must be
 * written there, never to a hard-coded DOL_DATA_ROOT/swisspayments. For entity 1 (and on
 * single-company installs) this is still DOL_DATA_ROOT/swisspayments.
 *
 * @param string $subdir  Optional sub directory (e.g. 'dtafiles')
 * @return string         Path without trailing slash
 */
function swisspayments_data_dir($subdir = '') {
	global $conf;
	$dir = '';
	if (isset($conf->swisspayments) && !empty($conf->swisspayments->multidir_output[$conf->entity])) {
		$dir = $conf->swisspayments->multidir_output[$conf->entity];
	} elseif (isset($conf->swisspayments) && !empty($conf->swisspayments->dir_output)) {
		$dir = $conf->swisspayments->dir_output;
	} else {
		// Module not (yet) known to $conf, e.g. during the very first activation.
		$dir = DOL_DATA_ROOT . ((int) $conf->entity > 1 ? '/' . ((int) $conf->entity) : '') . '/swisspayments';
	}
	$dir = rtrim($dir, '/');
	return ($subdir !== '') ? $dir . '/' . trim($subdir, '/') : $dir;
}

/**
 * Directory (under the Dolibarr data root) that holds the mobile-scan pairing files.
 * Deliberately NOT per entity: the public phone page (mobilescan.php) runs without a
 * login and therefore without knowing the entity; the pairing file itself records it.
 *
 * @return string
 */
function swisspayments_scan_dir() {
	return DOL_DATA_ROOT . '/swisspayments/scan';
}

/**
 * Absolute path of the pairing file for a token, or null when the token is malformed.
 * A token is 16-64 lowercase hex characters, so it can never contain path separators.
 *
 * @param string $token
 * @return string|null
 */
function swisspayments_scan_file($token) {
	if (!preg_match('/^[a-f0-9]{16,64}$/', (string) $token)) {
		return null;
	}
	return swisspayments_scan_dir() . '/' . $token . '.json';
}

/**
 * Create and persist a new mobile-scan pairing token for a user. Also prunes pairing
 * files older than 10 minutes.
 *
 * @param int $fk_user
 * @param int $entity
 * @return string The new token
 */
function swisspayments_scan_create_token($fk_user, $entity) {
	$dir = swisspayments_scan_dir();
	if (!is_dir($dir)) {
		dol_mkdir($dir);
	}
	foreach ((array) glob($dir . '/*.json') as $f) {
		if (@filemtime($f) < (time() - 600)) {
			@unlink($f);
		}
	}
	$token = bin2hex(random_bytes(16));
	$data = array('fk_user' => (int) $fk_user, 'entity' => (int) $entity, 'date_create' => time(), 'payload' => null);
	file_put_contents(swisspayments_scan_file($token), json_encode($data));
	return $token;
}

/**
 * Read a pairing file. Returns the decoded array, or null if missing/expired (10 min).
 *
 * @param string $token
 * @return array|null
 */
function swisspayments_scan_read($token) {
	$file = swisspayments_scan_file($token);
	if (!$file || !is_file($file)) {
		return null;
	}
	$data = json_decode(file_get_contents($file), true);
	if (!is_array($data) || (time() - (int) ($data['date_create'] ?? 0)) > 600) {
		return null;
	}
	return $data;
}

/**
 * Columns added to the module tables after the initial release. Single source of truth
 * shared by the module descriptor's init() and the runtime auto-migration guard.
 * Format: table (without MAIN_DB_PREFIX) => list of "columnname coldefinition".
 *
 * @return array
 */
function swisspayments_db_columns() {
	return array(
		'swisspayments_soc'   => array("entity INTEGER DEFAULT 1 NOT NULL"),
		'swisspayments_factf' => array("entity INTEGER DEFAULT 1 NOT NULL", "iban VARCHAR(34)"),
		'swisspayments_payl'  => array("entity INTEGER DEFAULT 1 NOT NULL"),
		'swisspayments_payh'  => array("entity INTEGER DEFAULT 1 NOT NULL", "fk_user_author INT"),
	);
}

/**
 * Add any missing module columns to already-existing tables. Each ALTER is guarded by a
 * DB-portable column probe ("SELECT <col> ... WHERE 1=0", which fails only when the column
 * is missing), so the function is idempotent and safe to run repeatedly on both
 * MySQL/MariaDB and PostgreSQL.
 *
 * @param DoliDB $db
 * @param string $fromVersion  Schema version stored before this run ('' = unknown/none);
 *                             selects the one-time data migrations to apply
 * @return int  1
 */
function swisspayments_migrate_tables($db, $fromVersion = '') {
	foreach (swisspayments_db_columns() as $table => $defs) {
		foreach ($defs as $def) {
			$col = trim(substr($def, 0, strpos($def, ' ')));
			// A missing column makes the probe SELECT fail; then add it.
			$probe = $db->query("SELECT ".$col." FROM ".MAIN_DB_PREFIX.$table." WHERE 1 = 0");
			if (!$probe) {
				$db->query("ALTER TABLE ".MAIN_DB_PREFIX.$table." ADD COLUMN ".$def);
			}
		}
	}
	swisspayments_migrate_indexes($db);
	swisspayments_backfill_entities($db, $fromVersion);
	return 1;
}

/**
 * Index migration (2026.11.3): the batch number `swisspayments_payh.payident` used to be
 * unique across ALL entities (inline "UNIQUE (payident)" in the CREATE TABLE). It is now
 * unique per entity: drop the old single-column unique key and create
 * uk_swisspayments_payh_payident (payident, entity). Idempotent - the existing indexes
 * are looked up first, so nothing is executed once the schema is current. Must run after
 * the `entity` column has been added.
 *
 * @param DoliDB $db
 * @return int  1 if OK, -1 if an index statement failed
 */
function swisspayments_migrate_indexes($db) {
	$table = MAIN_DB_PREFIX.'swisspayments_payh';
	$newIdx = 'uk_swisspayments_payh_payident';
	$isPg = (isset($db->type) && $db->type == 'pgsql');
	$ok = 1;

	// name => true of the existing indexes on the table
	$existing = array();
	if ($isPg) {
		$resql = $db->query("SELECT indexname AS idx FROM pg_indexes WHERE tablename = '".$db->escape($table)."'");
	} else {
		$resql = $db->query("SHOW INDEX FROM ".$table);
	}
	if (!$resql) {
		return -1;
	}
	while ($obj = $db->fetch_object($resql)) {
		$name = $isPg ? $obj->idx : $obj->Key_name;
		$existing[strtolower($name)] = true;
	}

	// Old global unique key: MySQL/MariaDB names it after the column, PostgreSQL
	// "<table>_payident_key" (a constraint).
	if ($isPg && isset($existing[strtolower($table).'_payident_key'])) {
		if (!$db->query("ALTER TABLE ".$table." DROP CONSTRAINT ".$table."_payident_key")) {
			$ok = -1;
		}
	} elseif (!$isPg && isset($existing['payident'])) {
		if (!$db->query("ALTER TABLE ".$table." DROP INDEX payident")) {
			$ok = -1;
		}
	}
	if (!isset($existing[$newIdx])) {
		if (!$db->query("CREATE UNIQUE INDEX ".$newIdx." ON ".$table." (payident, entity)")) {
			$ok = -1;
		}
	}
	if ($ok < 0) {
		dol_syslog(__FUNCTION__." failed: ".$db->lasterror(), LOG_WARNING);
	}
	return $ok;
}

/**
 * One-time data migration (2026.11.3): rows that existed before the `entity` column was
 * added all got the column default 1. On an install that already ran several entities
 * they are invisible in the entity they belong to. Re-derive the entity from the parent
 * record (supplier invoice / third party / supplier payment). Only rows still carrying
 * the default 1 are touched, and it runs once (gated by the stored schema version),
 * because a swisspayments_soc row is per entity by design and may later legitimately
 * differ from the entity owning a shared third party.
 *
 * @param DoliDB $db
 * @param string $fromVersion  Schema version stored before this run
 * @return int  1 if the backfill ran, 0 if it was not needed
 */
function swisspayments_backfill_entities($db, $fromVersion = '') {
	if ($fromVersion !== '' && version_compare($fromVersion, '2026.11.3', '>=')) {
		return 0;
	}
	$p = MAIN_DB_PREFIX;
	// child table, FK column, parent table
	$map = array(
		array('swisspayments_factf', 'fk_factid', 'facture_fourn'),
		array('swisspayments_soc', 'fk_societe', 'societe'),
		array('swisspayments_payl', 'fk_payementfourn', 'paiementfourn'),
	);
	foreach ($map as $m) {
		$sql = "UPDATE ".$p.$m[0]." SET entity = (SELECT par.entity FROM ".$p.$m[2]." par WHERE par.rowid = ".$p.$m[0].".".$m[1].")";
		$sql .= " WHERE entity = 1 AND ".$m[1]." IN (SELECT par2.rowid FROM ".$p.$m[2]." par2 WHERE par2.entity > 1)";
		if (!$db->query($sql)) {
			dol_syslog(__FUNCTION__." backfill of ".$m[0]." failed: ".$db->lasterror(), LOG_WARNING);
		}
	}
	// A batch header follows its (already corrected) lines.
	$sql = "UPDATE ".$p."swisspayments_payh SET entity = (SELECT MAX(l.entity) FROM ".$p."swisspayments_payl l WHERE l.fk_payh = ".$p."swisspayments_payh.rowid)";
	$sql .= " WHERE entity = 1 AND rowid IN (SELECT l2.fk_payh FROM ".$p."swisspayments_payl l2 WHERE l2.entity > 1)";
	if (!$db->query($sql)) {
		dol_syslog(__FUNCTION__." backfill of swisspayments_payh failed: ".$db->lasterror(), LOG_WARNING);
	}
	return 1;
}

/**
 * Persist the schema version. The schema is shared by all entities, so the constant is
 * stored once with entity 0 (visible everywhere); per-entity copies written by older
 * releases are removed first, since they would override the entity-0 value.
 *
 * @param DoliDB $db
 * @param string $version
 * @return void
 */
function swisspayments_store_db_version($db, $version) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	dolibarr_del_const($db, 'SWISSPAYMENTS_DB_VERSION', -1);
	dolibarr_set_const($db, 'SWISSPAYMENTS_DB_VERSION', $version, 'chaine', 0, '', 0);
}

/**
 * Runtime auto-migration guard. Compares the stored schema-version constant
 * (`SWISSPAYMENTS_DB_VERSION`) with the module VERSION and, when it is missing or older,
 * runs swisspayments_migrate_tables() once and bumps the constant. This lets a plain
 * file/zip update apply its schema changes on the first module page load, without the
 * disable/re-enable dance. When versions match it returns immediately (no DB writes),
 * so it is cheap to call on every module page.
 *
 * @param DoliDB $db
 * @param Conf   $conf
 * @return int  1 if OK / up to date, 0 if the module version could not be resolved
 */
function swisspayments_check_db_version($db, $conf) {
	dol_include_once('/swisspayments/core/modules/modSwisspayments.class.php');
	$current = class_exists('modswisspayments') ? modswisspayments::VERSION : '';
	if ($current === '') {
		return 0;
	}
	// Read the stored version directly from $conf->global (already loaded, no query) so we
	// don't depend on getDolGlobalString() being present on older Dolibarr releases.
	$stored = isset($conf->global->SWISSPAYMENTS_DB_VERSION) ? (string) $conf->global->SWISSPAYMENTS_DB_VERSION : '';
	if ($stored !== '' && version_compare($stored, $current, '>=')) {
		return 1; // schema already at or above the current version
	}
	swisspayments_migrate_tables($db, $stored);
	swisspayments_fix_menu_langs($db);
	swisspayments_store_db_version($db, $current);
	// Reflect it in memory so the guard is a no-op for the rest of this request.
	if (!isset($conf->global)) {
		$conf->global = new stdClass();
	}
	$conf->global->SWISSPAYMENTS_DB_VERSION = $current;
	return 1;
}

/*
 * Cross-version Dolibarr compatibility shims (supports 15.x through 24.x).
 *
 * The module must run on Dolibarr 15 as well as 24. Several modern APIs
 * (User::hasRight(), getDolGlobalString(), isModEnabled()) were introduced in
 * Dolibarr 16+, while the classic accessors ($user->rights->..., $conf->global->...,
 * $user->societe_id) are deprecated in recent releases and emit notices. These
 * helpers prefer the modern API when it is available at runtime and fall back to
 * the legacy accessor otherwise, so callers stay clean on every supported version.
 */

/**
 * Check a user permission across Dolibarr versions.
 * Uses User::hasRight() (Dolibarr 16+) when available, else walks $user->rights.
 *
 * @param User   $user    Current user
 * @param string $module  Module rights class (e.g. 'swisspayments')
 * @param string $l1      First permission level (e.g. 'invoices')
 * @param string $l2      Optional second permission level (e.g. 'create')
 * @return bool           True if the permission is granted
 */
function swisspayments_user_has_right($user, $module, $l1, $l2 = '')
{
	if (is_object($user) && method_exists($user, 'hasRight')) {
		return (bool) $user->hasRight($module, $l1, $l2);
	}
	// Legacy fallback: navigate the rights stdClass tree defensively.
	if (empty($user->rights->$module->$l1)) {
		return false;
	}
	if ($l2 === '') {
		return !empty($user->rights->$module->$l1);
	}
	return !empty($user->rights->$module->$l1->$l2);
}

/**
 * Return the third-party id an (external) user is bound to, across versions.
 * Prefers $user->socid; falls back to the deprecated $user->societe_id alias.
 *
 * @param User $user Current user
 * @return int       Third-party id (0 for internal users)
 */
function swisspayments_user_socid($user)
{
	if (isset($user->socid) && $user->socid > 0) {
		return (int) $user->socid;
	}
	if (isset($user->societe_id) && $user->societe_id > 0) {
		return (int) $user->societe_id;
	}
	return 0;
}

/**
 * Read a Dolibarr global constant as a string, across versions.
 * Uses getDolGlobalString() (Dolibarr 16+) when available, else $conf->global.
 *
 * @param string $key     Constant name
 * @param string $default Value returned when the constant is unset/empty
 * @return string
 */
function swisspayments_conf_string($key, $default = '')
{
	if (function_exists('getDolGlobalString')) {
		return getDolGlobalString($key, $default);
	}
	global $conf;
	return (isset($conf->global->$key) && $conf->global->$key !== '') ? (string) $conf->global->$key : $default;
}

/**
 * Test whether a Dolibarr module is enabled, across versions.
 * Uses isModEnabled() (Dolibarr 15+) when available, else the legacy
 * $conf->{module}->enabled flag.
 *
 * @param string $module Module code (e.g. 'banque', 'facture')
 * @return bool
 */
function swisspayments_mod_enabled($module)
{
	if (function_exists('isModEnabled')) {
		return (bool) isModEnabled($module);
	}
	global $conf;
	return !empty($conf->$module->enabled);
}

/**
 * Decrypt a value read straight from a Dolibarr table, across versions.
 * Dolibarr 18+ stores sensitive columns such as societe_rib.iban_prefix encrypted
 * ("dolcrypt:..."); dolDecrypt() returns plain values unchanged. Older releases have
 * no dolDecrypt() and store the value in clear text.
 *
 * @param string $value Raw column value
 * @return string       Clear-text value
 */
function swisspayments_decrypt($value)
{
	$value = (string) $value;
	if ($value !== '' && function_exists('dolDecrypt')) {
		return (string) dolDecrypt($value);
	}
	return $value;
}

/**
 * Normalize an IBAN read from the database or a QR code: decrypt, strip blanks, upper-case.
 *
 * @param string $value Raw IBAN (possibly encrypted, possibly with blanks)
 * @return string
 */
function swisspayments_norm_iban($value)
{
	return strtoupper(preg_replace('/\s+/', '', swisspayments_decrypt($value)));
}

/**
 * Find the third-party bank accounts (societe_rib) holding a given IBAN.
 *
 * The comparison is done in PHP on the decrypted value: since Dolibarr encrypts
 * societe_rib.iban_prefix (with a random IV), an SQL "WHERE iban_prefix = '...'" can no
 * longer match. Works unchanged on releases that store the IBAN in clear text.
 *
 * @param DoliDB $db
 * @param string $iban   IBAN to look for
 * @param int    $socid  Restrict to this third party (0 = all third parties of the entity)
 * @return array         List of objects {rowid, fk_soc, default_rib, iban}; empty when none
 */
function swisspayments_find_ribs_by_iban($db, $iban, $socid = 0)
{
	$found = array();
	$wanted = swisspayments_norm_iban($iban);
	if ($wanted === '') {
		return $found;
	}
	$sql = "SELECT sr.rowid, sr.fk_soc, sr.default_rib, sr.iban_prefix";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe_rib as sr";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = sr.fk_soc";
	$sql .= " WHERE s.entity IN (".getEntity('societe').")";
	$sql .= " AND sr.iban_prefix IS NOT NULL AND sr.iban_prefix <> ''";
	if ($socid > 0) {
		$sql .= " AND sr.fk_soc = ".((int) $socid);
	}
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			if (swisspayments_norm_iban($obj->iban_prefix) === $wanted) {
				$row = new stdClass();
				$row->rowid = (int) $obj->rowid;
				$row->fk_soc = (int) $obj->fk_soc;
				$row->default_rib = (int) $obj->default_rib;
				$row->iban = $wanted;
				$found[] = $row;
			}
		}
		$db->free($resql);
	}
	return $found;
}

/**
 * Filter for Form::select_company() restricting the list to suppliers, across versions.
 * Dolibarr 18+ expects the "Universal Search Filter" syntax (and 20+ rejects raw SQL);
 * older releases only understand a raw SQL condition.
 *
 * @return string
 */
function swisspayments_supplier_filter()
{
	if (defined('DOL_VERSION') && version_compare(DOL_VERSION, '18.0.0', '>=')) {
		return '(s.fournisseur:=:1)';
	}
	return 's.fournisseur = 1';
}

/**
 * Make sure the module's menu entries reference the module language file, so their
 * titles are translated. Entries created by releases up to 2026.11.1 had no language
 * file and showed the raw key (e.g. "SwpMenuReadInvoice"). Idempotent.
 *
 * @param DoliDB $db
 * @return void
 */
function swisspayments_fix_menu_langs($db)
{
	$db->query("UPDATE ".MAIN_DB_PREFIX."menu SET langs = 'swisspayments@swisspayments'"
		." WHERE module = 'swisspayments' AND (langs IS NULL OR langs = '' OR langs = 'mymodule@mymodule')");
}
