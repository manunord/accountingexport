<?php
/* Export to accounting module for Dolibarr
 * Copyright (C) 2012-2016  Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
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

/**
 * \file    index.php
 * \ingroup accountingexport
 * \brief   Export to accounting main page
 *
 * Allows to export accounting data from selected journals
 * for a specified period and to list and manage existing exports
 */

// Load Dolibarr environment
if (false === (@include '../main.inc.php')) {  // From htdocs directory
    require '../../main.inc.php'; // From "custom" directory
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once dirname(__FILE__) . '/class/accountingexport.class.php';
require_once dirname(__FILE__) . '/lib/accountingexport.lib.php';

global $conf, $db, $langs, $user;

$form = new Form($db);
$formfile = new FormFile($db);

// Load translation files required by the page
$langs->load('accountingexport@accountingexport');

// Get parameters
$accounts = aeListActiveAccounts($db);

$action = GETPOST('action', 'alpha');
$purchases = GETPOST('purchases', 'alpha');
$sales = GETPOST('sales', 'alpha');

$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
if (empty($sortfield)) {
    $sortfield = 'date';
}
if (empty($sortorder)) {
    $sortorder = 'desc';
}

$treasury = array();
if ($accounts) {
    foreach ($accounts as $id => $account) {
        $treasury[$id] = GETPOST($account['ref']);
    }
}

$start_date = GETPOST('start_date');
$end_date = GETPOST('end_date');

$start_time = dol_mktime(
    0,
    0,
    0,
    $_REQUEST['start_datemonth'],
    $_REQUEST['start_dateday'],
    $_REQUEST['start_dateyear']
);
$end_time = dol_mktime(
    23,
    59,
    59,
    $_REQUEST['end_datemonth'],
    $_REQUEST['end_dateday'],
    $_REQUEST['end_dateyear']
);

$confirm = GETPOST('confirm', 'alpha');
$urlfile = GETPOST('urlfile', 'alpha');

// Access control
if (0 < $user->socid || !$user->rights->accountingexport->read) {
    accessforbidden();
}

/*
 * ACTIONS
 */
$confirm_mesg = ''; // Confirmation message
$mesg = ''; // Dropdown message

// Check accounting mode
$accounting_mode = getAccountingModeConstant();
if ('RECETTES-DEPENSES' === $conf->global->$accounting_mode) {
    $mesg = '<div class="warning">';
    $mesg .= $langs->trans("UnsupportedAccountingMode");
    $mesg .= '</div>';
}

// Look for reverse period selection
if ($end_time < $start_time) {
    $mesg = '<div class="warning">' . $langs->trans("ForwardPeriod") . '</div>';
    // Don't do anything
    $action = '';
}

if ('export' === $action) {
    $journals = array();
    if ($purchases) {
        array_push($journals, $purchases);
    }
    if ($sales) {
        array_push($journals, $sales);
    }
    if ($treasury) {
        foreach ($accounts as $account) {
            if (in_array($account['ref'], $treasury)) {
                array_push($journals, $account['ref']);
            }
        }
    }

    if ($journals) {
        $export = new GpcSolutions\AccountingExport\AccountingExport(
            $db,
            $journals,
            $start_date,
            $start_time,
            $end_date,
            $end_time
        );

        if ($export->isError()) {
            // Creation KO
            $mesg = '<div class="error">';
            $mesg .= $langs->trans($export->getError());
            $mesg .= '</div>';
            if ('FileAlreadyExists' !== $export->getError()) {
                // Delete the bad file
                $export->rmFile();
            }
        } else {
            // Creation OK
            $mesg = $export->getFileName();
            $mesg .= ' ' . $langs->trans("SuccessfullyCreated");
        }
    } else {
        $mesg = '<div class="warning">'
            . $langs->trans("NothingToExport") . '</div>';
    }
}

if ('delete' === $action || 'deletefile' === $action) {
    $confirm_mesg = $form->formconfirm(
        '?urlfile=' . $urlfile,
        $langs->trans("DeleteFile"),
        $langs->trans("ConfirmDeleteFile") . ' ' . $urlfile . ' ?',
        'delete_ok',
        '',
        1,
        (int) $conf->use_javascript_ajax
    );
}

if ('delete_ok' === $action && 'yes' === $confirm) {
    if (dol_delete_file(
        $conf->accountingexport->dir_output . '/' . GETPOST('urlfile'),
        1
    )) {
        $mesg = '<div class="ok">' . $urlfile . ' '
            . $langs->trans("Deleted") . '</div>';
    } else {
        $mesg = '<div class="error">'
            . $langs->trans("UnableToDeleteFile") . '</div>';
    }
}

// Unset
$action = '';
$confirm = '';
$urlfile = '';

/*
 * VIEW
 */

$page_name = $langs->trans("AccountingExport");
$help_url = '';

llxHeader('', $page_name, $help_url);

// Information message
dol_htmloutput_mesg($mesg);

// Confirmation dialog
echo $confirm_mesg;

// Tab(s)
dol_fiche_head('', 0, $page_name, 0, 'accountingexport@accountingexport');
if (aeIsConfigured($db, $conf)) {
    // Period management
    if ('' === $start_date && '' === $end_date) {
        // Defaul period is the previous month
        $current_year = strftime('%Y', dol_now());
        $past_month = strftime('%m', dol_now()) - 1;
        $past_month_year = $current_year;
        if (0 === $past_month) {
            $past_month = 12;
            $past_month_year--;
        }

        $start_date = dol_get_first_day($past_month_year, $past_month, false);
        $end_date = dol_get_last_day($past_month_year, $past_month, false);
    } else {
        // We get the POST dates
        $start_date = $start_time;
        $end_date = $end_time;
    }

    echo '<form action="?action=export" method="POST" id="export">';
    echo '	<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
    echo '	<input type="hidden" name="action" value="update">';
    if ($conf->facture->enabled || $conf->fournisseur->enabled || $conf->banque->enabled) {
        echo '	<fieldset>';
        echo '		<legend>' . $langs->trans("Journals") . '</legend>';
        // Select/deselect all
        if ($conf->use_javascript_ajax) {
            echo '<script type="text/javascript" src="js/selectall.js"></script>';
            echo '<a href="#" onclick="toggleCheckboxes(true)">', $langs->trans("All"), '</a>';
            echo " / ";
            echo '<a href="#" onclick="toggleCheckboxes(false)">', $langs->trans("None"), '</a>';
            echo '<br>';
        }

        if ($conf->fournisseur->enabled) {
            echo '		<input type="checkbox" checked name="purchases" id="purchases" value="';
            echo $conf->global->ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE . '">';
            echo '		<label for="purchases">' . $langs->trans("PurchasesJournal") . '</label><br>';
        }
        if ($conf->facture->enabled) {
            echo '		<input type="checkbox" checked name="sales" id="sales" value="';
            echo $conf->global->ACCOUNTING_EXPORT_SALES_JOURNAL_CODE . '">';
            echo '		<label for="sales">' . $langs->trans("SalesJournal") . '</label><br>';
        }
        if ($conf->banque->enabled) {
            echo '		<fieldset>';
            echo '			<legend>' . $langs->trans("TreasuryJournals") . '</legend>';
            if ($accounts) {
                foreach ($accounts as $account) {
                    echo '			<input type="checkbox" checked name="';
                    echo $account['ref'] . '" id="' . $account['ref'] . '" value="' . $account['ref'] . '">';
                    echo '			<label for="' . $account['ref'] . '">' . $account['label'] . '</label><br>';
                }
            } else {
                echo '<em>';
                echo $langs->trans("NoAccountAvailable");
                echo '</em>';
            }
            echo '		</fieldset>';
        }
        echo '	</fieldset>';
    }
    echo '	<fieldset>';
    echo '		<legend>' . $langs->trans("Period") . '</legend>';

    echo $form->select_date($start_date, 'start_date', 0, 0, 0, '', 1, 0, 1);
    echo ' - ';
    echo $form->select_date($end_date, 'end_date', 0, 0, 0, '', 1, 0, 1);

    echo '	</fieldset>';
    echo '</div>'; // End tabBar
    echo '<div class="tabsAction">';
    echo '	<input class="butAction" type="submit" value="' . $langs->trans("Export") . '">';
    echo '</div>';
    echo '</form>';

    // Export files management
    $filearray = dol_dir_list(
        $conf->accountingexport->dir_output,
        'files',
        0,
        '',
        '',
        $sortfield,
        (strtolower($sortorder) === 'asc' ? SORT_ASC : SORT_DESC),
        1
    );
    $formfile->list_of_documents(
        $filearray,
        null,
        'accountingexport',
        '',
        1,
        '',
        1,
        0,
        $langs->trans("NoExportFileAvailable"),
        0,
        $langs->trans("PreviousExports")
    );
} else {
    // Module not configured
    echo '<div class="error">';
    echo $langs->trans('ModuleNotConfigured');
    echo '</div>';
    echo '<br>';
    if ($user->admin) {
        echo $langs->trans("ToConfigurePleaseGoTo");
        echo '&nbsp;';
        echo '<a href="' . dol_buildpath('/accountingexport/admin/accountingexport.php', 1) . '">';
        echo $langs->trans("ModuleConfigPage");
        echo '</a>';
        echo '<br>';
    } else {
        echo $langs->trans("PleaseAskYourAdmin");
    }
    if (!aeAreBankAccountsRefsUnique($db)) {
        echo '<div class="error">';
        echo $langs->trans("AccountRefsShouldBeUnique");
        echo '</div>';
        echo '<br>';
        if ($user->rights->banque->configurer) {
            $list = aeListActiveAccounts($db);
            echo $langs->trans("ToConfigurePleaseGoTo");
            echo ' ';
            echo $langs->trans("AccountConfigurationPages");
            echo '<br>';
            echo '<ul>';
            foreach ($list as $id => $account) {
                echo '<li style="margin-left: 2em;">';
                printBankLink($id);
                echo $account['label'];
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo $langs->trans("PleaseAskYourAdmin");
        }
    }
}

dol_fiche_end();

// Page end
llxFooter();
$db->close();
