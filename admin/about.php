<?php
/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

/**
 * 	\file		admin/about.php
 * 	\ingroup	swisspayments
 * 	\brief		Config swisspayments
 */
// Dolibarr environment
$res = @include "../../main.inc.php"; // From htdocs directory
if (! $res) {
	$res = @include "../../../main.inc.php"; // From "custom" directory
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/swisspayments.lib.php';

// Use the .inc variant because we don't have autoloading support
require_once '../lib/php-markdown/Michelf/Markdown.inc.php';

use \Michelf\Markdown;

//require_once "../class/myclass.class.php";
// Translations
$langs->load("swisspayments@swisspayments");

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

/*
 * View
 */
$page_name = "SwisspaymentsAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
	. $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = mymoduleAdminPrepareHead();
dol_fiche_head(
	$head,
	'about',
	$langs->trans("Module10000Name"),
	0,
	'swisspayments@swisspayments'
);

// About page goes here
echo $langs->trans("SwisspaymentsAboutPage");

echo '<br>';

$buffer = file_get_contents(dol_buildpath('/swisspayments/README.md', 0));
echo Markdown::defaultTransform($buffer);

echo '<br>',
'<a href="' . dol_buildpath('/swisspayments/COPYING', 1) . '">',
'<img src="' . dol_buildpath('/swisspayments/img/gplv3.png', 1) . '"/>',
'</a>';


// Page end
dol_fiche_end();
llxFooter();
