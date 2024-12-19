<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 John Botella <john.botella@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    propalautosend/admin/setup.php
 * \ingroup propalautosend
 * \brief   Propalautosend setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/propalautosend.lib.php';

// Translations
$langs->loadLangs(array("admin", "propalautosend@propalautosend"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('propalautosendsetup', 'globalsetup'));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');


if(file_exists(DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php')){
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
else{
	require_once __DIR__ . '/../retrocompatibility/core/class/html.formsetup.class.php';
}

$error = 0;
$setupnotempty = 0;

// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 0;
// Convert arrayofparameter into a formSetup object

$formSetup = new FormSetup($db);

// Minimal amount to do reminder
$item = $formSetup->newItem('PROPALAUTOSEND_MINIMAL_AMOUNT');
$item->setAsString();
$item->nameText = $langs->transnoentities('propalAutoAmountReminder');

// Calcule `relance_date` after propale validation
$item = $formSetup->newItem('PROPALAUTOSEND_CALCUL_DATE_ON_VALIDATION')->setAsYesNo();
$item->nameText = $langs->transnoentities('propalAutoSendCalculDateOnValidation');

// Calcule `relance_date` after propale sent by mail
$item = $formSetup->newItem('PROPALAUTOSEND_CALCUL_DATE_ON_EMAIL')->setAsYesNo();
$item->nameText = $langs->transnoentities('propalAutoSendCalculDateOnPropaleSentByMail');

// Join pdf sent by mail
$item = $formSetup->newItem('PROPALAUTOSEND_JOIN_PDF')->setAsYesNo();
$item->nameText = $langs->transnoentities('propalAutoSendUseAttachFile');

// Subject of mail
$item = $formSetup->newItem('PROPALAUTOSEND_MSG_SUBJECT');
$item->setAsString();
$item->helpText = $langs->transnoentities('propalAutoSendToolTipPropalValues');
$item->nameText = $langs->transnoentities('propalAutoSendSubject');

// Content to thirdparty
$item = $formSetup->newItem('PROPALAUTOSEND_MSG_THIRDPARTY');
$item->setAsHtml();
$item->helpText = $langs->transnoentities('propalAutoSendToolTipMsgThirdParty').$langs->transnoentitiesnoconv('propalAutoSendToolTipPropalValues');
$item->nameText = $langs->transnoentities('propalAutoSendMsgContact');


// Content to contact default
$item = $formSetup->newItem('PROPALAUTOSEND_MSG_CONTACT');
$item->setAsHtml();
$item->helpText = $langs->transnoentities('propalAutoSendToolTipMsgContact').$langs->transnoentitiesnoconv('propalAutoSendToolTipPropalValues');
$item->nameText = $langs->transnoentities('propalAutoSendMsgContact');

//Delay
$item = $formSetup->newItem('PROPALAUTOSEND_DEFAULT_NB_DAY');
$item->setAsString();
$item->helpText = $langs->transnoentities('propalAutoSendDefaultNbDayToolTips');
$item->nameText = $langs->transnoentities('propalAutoSendDefaultNbDay');

$setupnotempty = count($formSetup->items);



/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "propalAutoSendSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = propalautosendAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "propalautosend@propalautosend");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("propalAutoSendSetup").'</span><br><br>';

print $langs->transnoentitiesnoconv('propalAutoSendScriptPath', dol_buildpath('/propalautosend/script/propalAutoSend.php'));

if ($action == 'edit') {
	print $formSetup->generateOutput(true);

	?>
	<script>
		//script pour ajouter le logo + et la mention jours dans la conf PROPALAUTOSEND_DEFAULT_NB_DAY
		const inputField = document.getElementById('setup-PROPALAUTOSEND_DEFAULT_NB_DAY');

		if (inputField) {
			const span = document.createElement('span');
			span.className = 'fa fa-plus'; // Ajouter les classes

			const paragraph = document.createElement('p');
			paragraph.textContent = 'Jours';

			const tdParent = inputField.closest('td');

			if (tdParent) {
				tdParent.style.display = 'flex';
				tdParent.style.alignItems = 'center';

				tdParent.insertBefore(span, inputField);
				tdParent.appendChild(paragraph);
			}

		}
	</script>

	<?php

} else {
	if ($setupnotempty) {
		print $formSetup->generateOutput();

		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	} else {
		print '<br>'.$langs->trans("NothingToSetup");
	}

}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
