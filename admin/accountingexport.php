<?php
/* Export to accounting module for Dolibarr
 * Copyright (C) 2012-2016  Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2016       Marina Dias         <mdias@gpcsolutions.fr>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
use GPCsolutions\AccountingExport\AccountingExport;

/**
 * \file    admin/accountingexport.php
 * \ingroup accountingexport
 * \brief    Setup page
 *
 * Helps configure booking journal and account codes
 */

// Load Dolibarr environment
if (false === (@include '../../main.inc.php')) {  // From htdocs directory
    require '../../../main.inc.php'; // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once '../lib/accountingexport.lib.php';
require_once '../lib/constants.lib.php';
require_once '../core/modules/modAccountingExport.class.php';
require_once '../class/accountingexport.class.php';

global $bc;
global $conf;
global $db;
global $langs;
global $user;

$error = 0; //< Errors counter
$dolibarr_version = versiondolibarrarray();

// Translations
$langs->load('admin');
$langs->load('compta');
if ((3 == $dolibarr_version[0] && 7 <= $dolibarr_version[1]) || 3 < $dolibarr_version[0]) { // DOL_VERSION >= 3.7
    $langs->load('accountancy');
}
$langs->load('accountingexport@accountingexport');

// Access control
// if (!$user->admin || !$conf->global->MAIN_MODULE_ACCOUNTINGEXPORT) {
    // accessforbidden();
// }

// Parameters
$confirm = GETPOST('confirm', 'alpha');
$action = GETPOST('action', 'alpha');
$reset = GETPOST('reset', 'alpha');

$form = new Form($db);
$module = new modAccountingExport($db);

/*
 * Actions
 */

// Confirmation dialog
$confirm_mesg = ''; // Confirmation message
$mesg = ''; // Dropdown message

if ($reset) {
    $confirm_mesg = $form->formconfirm(
        '',
        $langs->trans("Reset"),
        $langs->trans("ConfirmReset"),
        'reset_ok',
        '',
        1,
        (int) $conf->use_javascript_ajax
    );
    // We don't want any action to be executed
    $action = '';
}

if ('reset_ok' === $action && 'yes' === $confirm) {
    foreach ($module->const as $index) {
        // We replace each constant POST with the module's default value
        $_POST[$index[0]] = $index[2];
    }
    // We set action to update for the update code to run
    $action = 'update';
}

if ('update' === $action) {
    $constlist = getAccountingExportConstants();

    foreach ($constlist as $const) {
        $value = GETPOST($const, null, 2);
        // We prevent setting empty constants expect for those allowed
        $allowed_empty = getAllowedEmptyConstants();
        if (!empty($value) || in_array($const, $allowed_empty)) {
            $res = dolibarr_set_const(
                $db,
                $const,
                $value,
                '',
                0,
                '',
                $conf->entity
            );
            if (! 0 < $res) {
                $error++;
            }
        } else {
            $error++;
            $mesg = '<div class="error">' . $langs->trans("EmptyValuesNotAllowed") . '</div>';
        }
    }

    if (!$error) {
        $db->commit();
        $mesg = '<div class="ok">' . $langs->trans("Saved") . '</div>';
    } elseif (!$mesg) {
        $db->rollback();
        $mesg = '<div class="error">' . $langs->trans("UnexpectedError") . '</div>';
    }
}

if (!aeAreBankAccountsRefsUnique($db)) {
    $mesg = '<div class="error">' . $langs->trans('AccountRefsShouldBeUnique') . '</div>';
}

/*
 * View
 */
$page_name = $langs->trans("AccountingExportSetup");
$help_url = 'http://modules.gpcsolutions.fr/export-vers-comptabilite/manuel/configuration';

llxHeader('', $page_name, $help_url);

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($page_name, $linkback);

// Information message
dol_htmloutput_mesg($mesg);

// Confirmation dialog
echo $confirm_mesg;

// Configuration header
$head = aePrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module105000Name"),
    0,
    'accountingexport@accountingexport'
);

// Setup page

echo '<script type="text/javascript" src="../js/admin.js.php"></script>';

$even = false;

echo '<form action="accountingexport.php" method="POST">';
echo '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
echo '<input type="hidden" name="action" value="update">';

echo '<table class="noborder" width="100%">';

// Accounting mode
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo '<a href="' . DOL_URL_ROOT . '/admin/compta.php">';
echo $form->textwithpicto($langs->trans("OptionMode"), $langs->trans("FollowLinkToConfigure"), 3);
echo '</a>';
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(
    array('OptionModeTrue' => 'RECETTES-DEPENSES', 'OptionModeVirtual' => 'CREANCES-DETTES'),
    getAccountingModeConstant(),
    true
);
echo '</td>';
echo '</tr>';

echo '<tr class="liste_titre">';
echo '<td colspan="2">' . $langs->trans("BookingJournalCodes") . '</td>';
echo '</tr>';

// Journal codes
$list = array(
    'ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE',
    'ACCOUNTING_EXPORT_SALES_JOURNAL_CODE'
);

$even = !aeAdminConstLines($list, $bc, $even);

// Note about liquid asset accounts taking cash account reference
echo '<tr ' . $bc[$even] . '>';
echo '<td colspan="2">';
echo $form->textwithpicto($langs->trans("TreasuryJournals"), $langs->trans("LiquidAssetsCodesAreBankAccountsRefs"));
echo '</td>';
echo '</tr>';

// List bank account refs
$list = aeListActiveAccounts($db);
$even = !aeAdminAccountLines($list, $bc, $even, $form);

// Link to dolibarr accounting parameters
echo '<tr class="liste_titre">';
echo '<td colspan="2">';
echo '<a href="' . DOL_URL_ROOT . '/admin/compta.php">';
echo $form->textwithpicto($langs->trans("StandardAccountNumbers"), $langs->trans("FollowLinkToConfigure"), 3);
echo '</a>';
echo '</td>';
echo '</tr>';

// List standard account numbers for information
$list = getNativeAccountsConstants();

$even = !aeAdminConstLines($list, $bc, $even, true);

// Native VAT account numbers
echo '<tr class="liste_titre">';
echo '<td colspan ="2">';
echo '<a href="' . DOL_URL_ROOT . '/admin/dict.php?id=10">';
echo $form->textwithpicto($langs->trans("NativeVATAccountsNumbers"), $langs->trans("FollowLinkToConfigure"), 3);
echo '</a>';
echo '</td>';
echo '</tr>';

// TODO: Maybe list all accounts set?

// Extra account numbers
echo '<tr class="liste_titre">';
echo '<td colspan="2">';
$list = array('ACCOUNTING_EXPORT_SUSPENSE_ACCOUNT_CODE');
if (3 == $dolibarr_version[0] && 5 > $dolibarr_version[1]) { // DOL_VERSION < 3.5
    echo $form->textwithpicto($langs->trans("ExtraAccountNumbers"), $langs->trans("StandardVATNumbersAreNotUsed"));
    array_push(
        $list,
        'ACCOUNTING_EXPORT_OUTPUT_TAX_ACCOUNT_CODE',
        'ACCOUNTING_EXPORT_INPUT_TAX_ACCOUNT_CODE'
    );
} else {
    echo $langs->trans("ExtraAccountNumbers");
}
echo '</td>';
echo '</tr>';

$even = aeAdminConstLines($list, $bc, $even);

// Bank account numbers
if ($conf->global->MAIN_MODULE_BANQUE) {
    echo '<tr class="liste_titre">';
    echo '<td colspan="2">';
    echo $langs->trans("BankAccountNumbers");
    echo '</td>';
    echo '</tr>';

    $list = aeListActiveAccounts($db);
    $even = !aeAdminCodeLines($list, $bc, $even, $form);
}

// Options
echo '<tr class="liste_titre">';
echo '<td colspan="2">';
echo $langs->trans("Options");
echo '</td>';
echo '</tr>';

echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("UseSupplierRefInsteadOfDolibarrRef");
echo '</td>';
echo '<td align="right">';
echo $form->selectyesno('ACCOUNTING_EXPORT_USE_SUPPLIER_REF', $conf->global->ACCOUNTING_EXPORT_USE_SUPPLIER_REF);
echo '</td>';
echo '</tr>';

// File type
echo '<tr class="liste_titre">';
echo '<td colspan="2">';
echo $langs->trans("FileFormat");
echo '</td>';
echo '</tr>';

// Presets
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("Preset");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_FORMATS, 'ACCOUNTING_EXPORT_FORMAT', false);
echo '</td>';
echo '</tr>';

// Type
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("ContentFormat");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_TYPES, 'ACCOUNTING_EXPORT_TYPE');
echo '</td>';
echo '</tr>';

// Row header
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("RowsHeader");
echo '</td>';
echo '<td align="right">';
echo $form->selectyesno('ACCOUNTING_EXPORT_FILE_HEADER', $conf->global->ACCOUNTING_EXPORT_FILE_HEADER);
echo '</td>';
echo '</tr>';

// Date format
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("DateFormat");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_DATE_FORMATS, 'ACCOUNTING_EXPORT_DATE_FORMAT');
echo '</td>';
echo '</tr>';

// Line field delimiter
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("LineFieldDelimiter");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_DELIMITERS, 'ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER');
// TODO: Re-implement custom delimiter
//echo '<input type="text" size="1" maxlength="1" name="ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER"';
//echo ' value="' . $conf->global->ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER . '">';
echo '</td>';
echo '</tr>';

// Line field enclosure
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("LineFieldEnclosure");
echo '</td>';
echo '<td align="right">';
echo '<input type="text" size="1" maxlength="1" name="ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE"';
echo ' value="' . htmlentities($conf->global->ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE) . '">';
echo '</td>';
echo '</tr>';

// Line ending
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("Newline");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_LINE_ENDINGS, 'ACCOUNTING_EXPORT_FILE_LINE_ENDING');
echo '</td>';
echo '</tr>';

// Reserved characters
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $form->textwithpicto($langs->trans("ReservedCharacters"), $langs->trans("StrippedList"));
echo '</td>';
echo '<td align="right">';
echo '<input type="text" size="25" maxlength="25" name="ACCOUNTING_EXPORT_RESERVED_CHARACTERS"';
echo ' value="' . htmlentities($conf->global->ACCOUNTING_EXPORT_RESERVED_CHARACTERS) . '">';
echo '</td>';
echo '</tr>';


// Encoding
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("Encoding");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_ENCODINGS, 'ACCOUNTING_EXPORT_FILE_ENCODING');
echo '</td>';
echo '</tr>';

// Extensions
echo '<tr ' . $bc[$even] . '>';
echo '<td>';
echo $langs->trans("FileExtension");
echo '</td>';
echo '<td align="right">';
echo aeSelectConst(AccountingExport::$SUPPORTED_FILE_EXTENSIONS, 'ACCOUNTING_EXPORT_FILE_EXTENSION');
echo '</td>';
echo '</tr>';

echo '</table>';

echo '<input type="submit" class="button" value="' . $langs->trans("ResetValues") . '" name="reset">';

echo '<input type="submit" class="button" value="' . $langs->trans("Modify") . '" name="modify">';

echo '</form>';

dol_fiche_end();

llxFooter();

$db->close();
