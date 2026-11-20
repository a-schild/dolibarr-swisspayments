<?php
// TEST STUB - not the real multicompany module. Emulates only entity resolution with
// third parties and bank accounts shared between entities 1 and 2.
class ActionsMulticompany
{
	public $db; public $id = 0; public $label = ''; public $sharings = array(); public $entities = array(1 => 'Entity 1', 2 => 'Entity 2');
	public function __construct($db) { $this->db = $db; }
	public function setValues(&$conf) { return 1; }
	public function getEntity($element = false, $shared = 1, $currentobject = null)
	{
		global $conf;
		$sharedElements = array('societe', 'thirdparty', 'socpeople', 'contact', 'bank_account', 'bankaccount');
		if ($shared && in_array($element, $sharedElements) && in_array((int) $conf->entity, array(1, 2))) return '1,2';
		$addzero = array('user', 'usergroup', 'cronjob', 'c_email_templates', 'email_template', 'default_values', 'overwrite_trans');
		return (in_array($element, $addzero) ? '0,' : '').((int) $conf->entity);
	}
	public function getInfo($id) { $this->id = (int) $id; $this->label = 'Entity '.$id; return 1; }
	public function checkRight($uid, $entity) { return 1; }
	public function getEntitiesList($a = false, $b = false, $c = false, $d = false) { return $this->entities; }
	public function select_entities($a = '', $b = '', $c = '', $d = false, $e = false, $f = false) { return ''; }
	public function switchEntity($id, $userid = null) { return 1; }
	public function setEntity($o) { global $conf; return ((is_object($o) && $o->id > 0 && $o->entity > 0) ? $o->entity : $conf->entity); }
	public function __call($n, $a) { return 0; }
}
