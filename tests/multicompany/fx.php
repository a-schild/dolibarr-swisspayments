<?php
/**
 * CLI part of the multi-company test suite (see README.md, started by run.sh).
 * Runs INSIDE the Dolibarr container, as the web server user:
 *
 *   php fx.php <entity> <action> [args]
 *
 * The entity is forced through DOLENTITY, so no login is involved. Actions:
 *   init       activate the module in the entity
 *   fixture    supplier, bank accounts, one open QR supplier invoice
 *   invoice    one more open QR invoice for supplier <socid>
 *   pay        pay the newest open invoice of the entity and generate the payment file
 *   isolation  records of entity <other> must not be readable from this entity
 *   legacy     rewind the schema/data to the state before 2026.11.3
 *   migrate    run the runtime migration guard and verify the result
 */
if (php_sapi_name() !== 'cli') die('CLI only');
$entity = (int) $argv[1]; $action = $argv[2];
define('DOLENTITY', $entity);
if (!defined('NOSESSION')) define('NOSESSION', '1');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
$user = new User($db); $user->fetch(0, 'admin'); $user->getrights();
$P = MAIN_DB_PREFIX;
function out($k, $v) { echo str_pad($k, 46).' '.(is_scalar($v) ? $v : json_encode($v))."\n"; }
function chk($label, $cond) { echo ($cond ? 'PASS' : 'FAIL').'  '.$label."\n"; if (!$cond) $GLOBALS['fails'] = 1 + (isset($GLOBALS['fails']) ? $GLOBALS['fails'] : 0); }
function rows($db, $sql) { $o = array(); $r = $db->query($sql); if (!$r) { echo "SQLERR ".$db->lasterror()." :: $sql\n"; return $o; } while ($x = $db->fetch_array($r)) { $o[] = array_filter($x, 'is_string', ARRAY_FILTER_USE_KEY); } return $o; }
out('entity / dolibarr', $conf->entity.' / '.DOL_VERSION.' / php '.PHP_VERSION);

if ($action == 'init') {
	dol_include_once('/swisspayments/core/modules/modSwisspayments.class.php');
	$m = new modSwisspayments($db);
	$r = $m->init();
	chk('module init() returns 1', $r == 1);
	$c = rows($db, "SELECT value, entity FROM {$P}const WHERE name='SWISSPAYMENTS_DB_VERSION'");
	out('DB_VERSION const rows', $c);
	chk('exactly one DB_VERSION row, entity 0, = VERSION', count($c) == 1 && $c[0]['entity'] == 0 && $c[0]['value'] == modSwisspayments::VERSION);
	exit(empty($fails) ? 0 : 1);
}

dol_include_once('/swisspayments/lib/swisspayments.lib.php');
dol_include_once('/custom/swisspayments/class/swisspaymentsfactf.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentssoc.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentspayh.class.php');
dol_include_once('/custom/swisspayments/class/swisspaymentspayl.class.php');

if ($action == 'fixture') {
	$ch = rows($db, "SELECT rowid FROM {$P}c_country WHERE code='CH'"); $chid = $ch[0]['rowid'];
	$tag = 'E'.$conf->entity.'-'.substr(md5(uniqid()), 0, 5);
	$s = new Societe($db);
	$s->name = 'Lieferant '.$tag; $s->address = 'Musterstrasse 1'; $s->zip = '3000'; $s->town = 'Bern';
	$s->country_id = $chid; $s->country_code = 'CH'; $s->fournisseur = 1; $s->client = 0; $s->code_fournisseur = 'auto';
	$r = $s->create($user); if ($r < 0) { $s->code_fournisseur = 'SU'.$tag; $r = $s->create($user); }
	chk('supplier created', $r >= 0 && $s->id > 0); if ($r < 0) out('err', $s->error.json_encode($s->errors));
	$qriban = 'CH4431999123000889012'; $qrr = '210000000003139471430009017';
	if ($conf->entity == 1) {
		// Only the entity-1 supplier gets the QR-IBAN as bank account, so the scan in the UI
		// test matches exactly one (shared) supplier. create() only inserts a stub row.
		$cba = new CompanyBankAccount($db);
		$cba->type = 'ban'; $cba->socid = $s->id; $cba->label = 'QR Bill'; $cba->bank = 'QR Bill'; $cba->iban = $qriban;
		$cba->proprio = $s->name; $cba->owner_name = $s->name; $cba->default_rib = 1;
		$r = $cba->create($user, 0); if ($r > 0) $r = $cba->update($user);
		$db->query("UPDATE {$P}societe_rib SET default_rib = 1 WHERE rowid = ".((int) $cba->id));
		chk('supplier bank account (QR-IBAN) created', $r > 0);
	}
	$accs = array();
	foreach (array('open', 'closed') as $kind) {
		$a = new Account($db);
		$a->ref = strtoupper(substr($kind, 0, 2)).$tag; $a->label = 'Bank '.$kind.' '.$tag; $a->courant = 1; $a->type = 1;
		$a->currency_code = 'CHF'; $a->country_id = $chid; $a->iban = 'CH9300762011623852957'; $a->bic = 'POFICHBEXXX';
		$a->proprio = 'Meine Firma '.$conf->entity; $a->owner_name = $a->proprio; $a->owner_address = 'Hauptstrasse 2, 3000 Bern';
		$a->date_solde = dol_now(); $a->solde = 0; $a->balance = 0;
		$r = $a->create($user); chk("bank account ($kind) created", $r > 0); if ($r <= 0) out('err', $a->error.json_encode($a->errors));
		$db->query("UPDATE {$P}bank_account SET proprio='".$db->escape($a->proprio)."', iban_prefix='CH9300762011623852957', bic='POFICHBEXXX'".($kind == 'closed' ? ', clos=1' : '')." WHERE rowid=".((int) $a->id));
		$accs[$kind] = $a->id;
	}
	if ($conf->entity == 1) {
		// An open account in an entity that is NOT shared with 1/2 (must never be accepted).
		$db->query("INSERT INTO {$P}bank_account(ref, label, entity, clos, courant, currency_code, fk_pays, proprio, iban_prefix, bic, datec, rappro)"
			." SELECT CONCAT('E3-', rowid), 'Bank entity 3', 3, 0, courant, currency_code, fk_pays, proprio, iban_prefix, bic, datec, rappro FROM {$P}bank_account WHERE rowid = ".((int) $accs['open']));
	}
	$f = new FactureFournisseur($db);
	$f->socid = $s->id; $f->datef = dol_now(); $f->date = dol_now(); $f->date_echeance = dol_now() + 86400 * 30; $f->type = 0;
	$f->ref_supplier = 'RE-1001'; $f->libelle = 'RE-1001'; $f->label = 'RE-1001';
	$r = $f->create($user); chk('supplier invoice created', $r > 0); if ($r <= 0) out('err', $f->error.json_encode($f->errors));
	$f->addline('Lieferantenrechnung', 199.95, 0, 0, 0, 1);
	$f->fetch($f->id); $r = $f->validate($user); chk('supplier invoice validated', $r >= 0);
	$ff = new Swisspaymentsfactf($db);
	$ff->fk_factid = $f->id; $ff->esrline = $qrr; $ff->esrpartynr = 'QRBILL'; $ff->esrrefnr = $qrr; $ff->iban = $qriban;
	$r = $ff->create($user, 0); chk('swisspayments_factf created', $r > 0);
	$so = new Swisspaymentssoc($db); $so->fk_societe = $s->id; $so->esrid = 'QRBILL'; $so->startorderno = 0; $so->endorderno = 0;
	$r = $so->create($user); chk('swisspayments_soc created', $r > 0);
	$e = rows($db, "SELECT (SELECT entity FROM {$P}facture_fourn WHERE rowid={$f->id}) AS inv, (SELECT entity FROM {$P}swisspayments_factf WHERE rowid={$ff->id}) AS factf, (SELECT entity FROM {$P}swisspayments_soc WHERE rowid={$so->id}) AS soc, (SELECT entity FROM {$P}bank_account WHERE rowid={$accs['open']}) AS bank");
	chk('invoice/factf/soc/bank rows carry current entity', count(array_unique($e[0])) == 1 && reset($e[0]) == $conf->entity);
	exit(empty($fails) ? 0 : 1);
}

if ($action == 'invoice') { // extra open QR invoice for an existing supplier
	$f = new FactureFournisseur($db);
	$f->socid = (int) $argv[3]; $f->datef = dol_now(); $f->date = dol_now(); $f->date_echeance = dol_now() + 86400 * 30; $f->type = 0;
	$f->ref_supplier = 'RE-'.mt_rand(2000, 99999); $f->libelle = $f->ref_supplier; $f->label = $f->ref_supplier;
	$r = $f->create($user); $f->addline('Lieferantenrechnung', 199.95, 0, 0, 0, 1); $f->fetch($f->id); $f->validate($user);
	$ff = new Swisspaymentsfactf($db);
	$ff->fk_factid = $f->id; $ff->esrline = '210000000003139471430009017'; $ff->esrpartynr = 'QRBILL'; $ff->esrrefnr = $ff->esrline; $ff->iban = 'CH4431999123000889012';
	$ff->create($user, 0);
	echo 'INV '.$f->id."\n"; exit(0);
}

if ($action == 'pay') { // CLI equivalent of the dtapayments.php POST handler + dtafile.php
	$x = rows($db, "SELECT rowid FROM {$P}facture_fourn WHERE entity = ".((int) $conf->entity)." AND paye = 0 AND fk_statut = 1 ORDER BY rowid DESC"); $inv = (int) $x[0]['rowid'];
	$x = rows($db, "SELECT rowid FROM {$P}bank_account WHERE entity = ".((int) $conf->entity)." AND clos = 0 ORDER BY rowid"); $acc = (int) $x[0]['rowid'];
	$vir = rows($db, "SELECT id FROM {$P}c_paiement WHERE code='VIR' AND entity=".((int) $conf->entity));
	$payh = new Swisspaymentspayh($db); $payh->payident = 'CLI-'.dol_now();
	chk('payh created', $payh->create($user) > 0);
	$p = new PaiementFourn($db); $p->datepaye = dol_now(); $p->paiementid = $vir[0]['id']; $p->num_paiement = $payh->payident; $p->num_payment = $payh->payident;
	$p->amounts = array($inv => 199.95); $p->note = 'test';
	$pid = $p->create($user, 1); chk('supplier payment created', $pid > 0); if ($pid <= 0) out('err', $p->error.json_encode($p->errors));
	$rb = $p->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $acc, '', ''); chk('payment added to bank', $rb > 0); if ($rb <= 0) out('err', $p->error.json_encode($p->errors));
	$pl = new Swisspaymentspayl($db); $pl->fk_payh = $payh->id; $pl->fk_payementfourn = $pid; chk('payl created', $pl->create($user) > 0);
	$h = new Swisspaymentspayh($db); $h->fetch($payh->id);
	try { $r = $h->createDTA(); } catch (Exception $e) { $r = -9; out('exception', $e->getMessage()); }
	chk('createDTA() ok', $r > 0); if ($r < 0) out('err', $h->error.json_encode($h->errors));
	$expect = DOL_DATA_ROOT.($conf->entity > 1 ? '/'.$conf->entity : '').'/swisspayments/dtafiles/'.$h->dtafile;
	out('expected file', $expect);
	chk('file written to entity-aware dir', is_file($expect));
	if ($conf->entity > 1) chk('file NOT in legacy shared dir', !is_file(DOL_DATA_ROOT.'/swisspayments/dtafiles/'.$h->dtafile));
	$chk = dol_check_secure_access_document('swisspayments', 'dtafiles/'.$h->dtafile, $conf->entity, $user);
	out('document.php resolves to', $chk['original_file']);
	chk('document.php path == written file, access allowed', $chk['original_file'] == $expect && !empty($chk['accessallowed']));
	if (is_file($expect)) {
		$d = new DOMDocument(); $d->load($expect); libxml_use_internal_errors(true);
		$valid = @$d->schemaValidate(dol_buildpath('/swisspayments/tests/pain.001.001.09.ch.03.xsd'));
		$cannotCompile = false; foreach (libxml_get_errors() as $e) { if (strpos($e->message, 'compile') !== false) $cannotCompile = true; }
		// libxml < 2.9.11 (e.g. the Dolibarr 15 image) cannot compile a regex of this XSD.
		if (!$valid && $cannotCompile) echo 'SKIP  XSD validation: this libxml ('.LIBXML_DOTTED_VERSION.") cannot compile the schema\n";
		else chk('XML valid against pain.001.001.09.ch.03 XSD', $valid);
		chk('XML contains QRR + creditor QR-IBAN', strpos(file_get_contents($expect), '210000000003139471430009017') !== false && strpos(file_get_contents($expect), 'CH4431999123000889012') !== false);
	}
	$e = rows($db, "SELECT (SELECT entity FROM {$P}swisspayments_payh WHERE rowid={$payh->id}) AS payh, (SELECT entity FROM {$P}swisspayments_payl WHERE rowid={$pl->id}) AS payl");
	chk('payh/payl rows carry current entity', $e[0]['payh'] == $conf->entity && $e[0]['payl'] == $conf->entity);
	exit(empty($fails) ? 0 : 1);
}

if ($action == 'isolation') { // records of entity $argv[3] must be invisible from the current entity
	$o = (int) $argv[3];
	$x = rows($db, "SELECT (SELECT MAX(rowid) FROM {$P}swisspayments_payh WHERE entity=$o) AS payh, (SELECT MAX(rowid) FROM {$P}swisspayments_factf WHERE entity=$o) AS factf, (SELECT MAX(fk_societe) FROM {$P}swisspayments_soc WHERE entity=$o) AS soc");
	chk('precondition: other entity has payh/factf/soc rows', $x[0]['payh'] > 0 && $x[0]['factf'] > 0 && $x[0]['soc'] > 0);
	$h = new Swisspaymentspayh($db); $h->fetch((int) $x[0]['payh']); chk('foreign payh not fetchable', empty($h->id));
	$f = new Swisspaymentsfactf($db); $f->fetch((int) $x[0]['factf'], null, null); chk('foreign factf not fetchable', empty($f->id));
	$so = new Swisspaymentssoc($db); $so->fetch(null, null, null, null, (int) $x[0]['soc']); chk('foreign soc mapping not fetchable (per entity by design)', empty($so->id));
	exit(empty($fails) ? 0 : 1);
}

if ($action == 'legacy') { // simulate an install that ran several entities before the entity column existed
	foreach (array('factf', 'soc', 'payl', 'payh') as $t) $db->query("UPDATE {$P}swisspayments_$t SET entity = 1");
	$db->query("DELETE FROM {$P}const WHERE name='SWISSPAYMENTS_DB_VERSION'");
	foreach (array(1, 2) as $e) $db->query("INSERT INTO {$P}const(name, entity, value, type, visible) VALUES ('SWISSPAYMENTS_DB_VERSION', $e, '2026.11.2', 'chaine', 0)");
	// old index layout: batch number unique across all entities
	$db->query("ALTER TABLE {$P}swisspayments_payh DROP INDEX uk_swisspayments_payh_payident");
	$db->query("ALTER TABLE {$P}swisspayments_payh ADD UNIQUE payident (payident)");
	out('const rows now', rows($db, "SELECT value, entity FROM {$P}const WHERE name='SWISSPAYMENTS_DB_VERSION'"));
	exit(0);
}

if ($action == 'migrate') {
	$before = rows($db, "SELECT 'factf' t, entity, count(*) n FROM {$P}swisspayments_factf GROUP BY entity UNION ALL SELECT 'payh', entity, count(*) FROM {$P}swisspayments_payh GROUP BY entity");
	out('before', $before);
	swisspayments_check_db_version($db, $conf);
	$bad = rows($db, "SELECT 'factf' t, x.rowid FROM {$P}swisspayments_factf x JOIN {$P}facture_fourn p ON p.rowid=x.fk_factid WHERE x.entity<>p.entity"
		." UNION ALL SELECT 'soc', x.rowid FROM {$P}swisspayments_soc x JOIN {$P}societe p ON p.rowid=x.fk_societe WHERE x.entity<>p.entity"
		." UNION ALL SELECT 'payl', x.rowid FROM {$P}swisspayments_payl x JOIN {$P}paiementfourn p ON p.rowid=x.fk_payementfourn WHERE x.entity<>p.entity"
		." UNION ALL SELECT 'payh', h.rowid FROM {$P}swisspayments_payh h JOIN {$P}swisspayments_payl l ON l.fk_payh=h.rowid WHERE h.entity<>l.entity");
	chk('backfill: every module row has its parent\'s entity', count($bad) == 0); if ($bad) out('mismatches', $bad);
	$n2 = rows($db, "SELECT count(*) n FROM {$P}swisspayments_factf WHERE entity=2");
	chk('backfill: entity-2 rows exist again', $n2[0]['n'] > 0);
	$c = rows($db, "SELECT value, entity FROM {$P}const WHERE name='SWISSPAYMENTS_DB_VERSION'");
	out('DB_VERSION const rows', $c);
	chk('const collapsed to one entity-0 row with new version', count($c) == 1 && $c[0]['entity'] == 0 && $c[0]['value'] == modswisspayments::VERSION);
	$idx = array(); foreach (rows($db, "SHOW INDEX FROM {$P}swisspayments_payh") as $r) { if ($r['Non_unique'] == 0 && $r['Key_name'] != 'PRIMARY') $idx[$r['Key_name']][] = $r['Column_name']; }
	out('unique indexes on payh', $idx);
	chk('payident unique key is now (payident, entity) only', $idx == array('uk_swisspayments_payh_payident' => array('payident', 'entity')));
	chk('index migration is idempotent', swisspayments_migrate_indexes($db) == 1);
	// the backfill is one-time: a per-entity soc mapping moved on purpose must stay where it is
	$db->query("UPDATE {$P}swisspayments_soc SET entity = 1 WHERE entity = 2");
	swisspayments_migrate_tables($db, modswisspayments::VERSION);
	$n = rows($db, "SELECT count(*) n FROM {$P}swisspayments_soc x JOIN {$P}societe p ON p.rowid = x.fk_societe WHERE x.entity <> p.entity");
	chk('backfill does not run again once the schema version is current', $n[0]['n'] > 0);
	$db->query("UPDATE {$P}swisspayments_soc x JOIN {$P}societe p ON p.rowid = x.fk_societe SET x.entity = p.entity");
	exit(empty($fails) ? 0 : 1);
}
