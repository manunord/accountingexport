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
 * \file    lib/accountingexport.lib.php
 * \ingroup accountingexport
 * \brief   Helper functions library
 */

require_once 'constants.lib.php';

/**
 * Generates the page header
 *
 * @global Translate $langs Translations
 * @global Conf $conf Configuration
 * @return string HTML
 */
function aePrepareHead()
{
    global $langs, $conf;

    $langs->load('accountingexport@accountingexport');

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/accountingexport/admin/accountingexport.php', 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath('/accountingexport/admin/Documentation.pdf', 1);
    $head[$h][1] = $langs->trans("Documentation");
    $head[$h][2] = 'documentation';
    $h++;

    // Unused
    $object = null;

    complete_head_from_modules(
        $conf,
        $langs,
        $object,
        $head,
        $h,
        'accountingexport'
    );

    return $head;
}

/**
 * Generates table lines for constants configuration
 *
 * @global Translate $langs Translations
 * @global Conf $conf Main configuration
 * @param array $list Constants list
 * @param array $bc Alternate lines html classes call
 * @param boolean $even Alternate lines status
 * @param boolean $disabled Should input be disabled ?
 * @return boolean Alternate line status
 */
function aeAdminConstLines($list, $bc, $even, $disabled = false)
{
    global $langs, $conf;

    foreach ($list as $const) {
        $even = !$even;

        echo '<tr ' . $bc[$even] . ' class="value">';

        echo '<td>';
        echo $langs->trans($const);
        echo '</td>';

        echo '<td align="right">';
        echo '<input ';
        if ($disabled) {
            echo 'disabled ';
        }
        echo 'type="text" size="10" name="' . $const . '" value="' . $conf->global->$const . '">';
        echo '</td>';

        echo '</tr>';
    }

    return $even;
}

/**
 * Generates table lines for bank accounts
 *
 * @global Translate $langs Translations
 * @global Conf $conf Configuration
 * @param array $list Accounts list
 * @param array $bc Alternate lines html classes call
 * @param boolean $even Alternate lines status
 * @param Form $form Form to apply picto
 * @return boolean Line type
 */
function aeAdminAccountLines($list, $bc, $even, $form)
{
    global $langs;

    foreach ($list as $id => $account) {
        $even = !$even;

        echo '<tr ' . $bc[$even] . ' class="value">';

        echo '<td>';
        // Account link on label
        printBankLink($id);
        echo $form->textwithpicto(
            "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $account['label'],
            $langs->trans("FollowLinkToConfigure")
        );
        echo '</a>';
        echo '</td>';

        echo '<td align="right">';
        echo '<input disabled type="text" size="10" name="' . $account['ref'] . '" value="' . $account['ref'] . '">';
        echo '</td>';

        echo '</tr>';
    }

    return $even;
}

/**
 * Generates table lines for bank codes
 *
 * @global Translate $langs Translations
 * @global Conf $conf Configuration
 * @param array $list Accounts list
 * @param array $bc Alternate lines html classes call
 * @param boolean $even Alternate lines status
 * @param Form $form Form to apply picto
 * @return boolean Line type
 */
function aeAdminCodeLines($list, $bc, $even, $form)
{
    global $langs;

    foreach ($list as $id => $account) {
        $even = !$even;

        echo '<tr ' . $bc[$even] . ' class="value">';

        echo '<td>';
        // Account link on label
        printBankLink($id);
        echo $form->textwithpicto($account['label'], $langs->trans("FollowLinkToConfigure"));
        echo '</a>';
        echo '</td>';

        echo '<td align="right">';
        echo '<input disabled type="text" size="10" name="' . $account['ref'] . '" value="' . $account['code'] . '">';
        echo '</td>';

        echo '</tr>';
    }

    return $even;
}

/**
 *
 * @global Conf $conf Configuration
 * @param DoliDB $db Database handler
 * @return array Active accounts
 */
function aeListActiveAccounts($db)
{
    global $conf;

    $accounts = array();

    $sql = 'SELECT rowid, label, ref, account_number';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . "bank_account";
    $sql .= ' WHERE entity = ' . $conf->entity;
    $sql .= ' AND clos = 0';
    $sql .= $db->order('label', 'ASC');

    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i = 0;
        while ($i < $num) {
            $objp = $db->fetch_object($resql);
            $accounts[$objp->rowid] = array(
                'label' => $objp->label,
                'ref' => $objp->ref,
                'code' => $objp->account_number
            );
            $i++;
        }
        $db->free($resql);

        return $accounts;
    }

    // Something went wrong
    return false;
}

/**
 * Checks if all constants are configured
 *
 * @param Conf $conf Configuration
 * @param string[] $list Configuration constants
 * @param string[] $allowed_empty Constants allowed to remain empty
 * @return boolean Configuration status
 */
function aeCheckConf($conf, $list, $allowed_empty = array())
{
    $configured = null;

    foreach ($list as $const) {
        $value = $conf->global->$const;
        if ((is_null($value) && !in_array($const, $allowed_empty)) // Allow some constants to be empty
            || in_array($value, $list)
        ) {
            $configured = false;
        } elseif (false !== $configured) {
            $configured = true;
        } else {
            $configured = false;
        }
    }

    return $configured;
}

/**
 * Checks if native accounting module is configured
 *
 * @param Conf $conf Configuration
 * @return boolean Accounting configuration status
 */
function aeIsAccountingConfigured($conf)
{
    $list = getNativeAccountsConstants();
    $accounting_mode = getAccountingModeConstant();
    array_push($list, $accounting_mode);

    return aeCheckConf($conf, $list);
}

/**
 * Checks if our module is configured
 *
 * @param Conf $conf Configuration
 * @return boolean Module configuration status
 */
function aeIsAccountingExportConfigured($conf)
{
    $list = getAccountingExportConstants();
    $allowed_empty = getAllowedEmptyConstants();

    return aeCheckConf($conf, $list, $allowed_empty);
}

/**
 * Checks if everything needed for our module is configured
 *
 * @param DoliDB $db Database handler
 * @param Conf $conf Configuration
 * @return boolean Configuration status
 */
function aeIsConfigured($db, $conf)
{
    $configured = false;

    if (aeAreBankAccountConfigured($db)
        && aeIsAccountingConfigured($conf)
        && aeIsAccountingExportConfigured($conf)
    ) {
        $configured = true;
    }

    return $configured;
}

/**
 * Checks bank accounts refs uniqueness
 *
 * @param DoliDB $db Database handler
 * @return boolean Uniqueness
 */
function aeAreBankAccountsRefsUnique($db)
{
    $accounts = aeListActiveAccounts($db);
    $refs = array();
    foreach ($accounts as $id => $account) {
        $refs[$id] = $account['ref'];
    }

    return !((count($refs) != count(array_unique($refs))));
}

/**
 * Build a select for the constants
 *
 * @global \Conf $conf Configuration
 * @global \Translate $langs Translations
 *
 * @param array $options Available options
 * @param string $name Configuration constant
 * @param bool $disabled Disabled HTML element
 *
 * @return string HTML select or corresponding hidden input
 */
function aeSelectConst($options, $name, $disabled = false)
{
    global $conf, $langs;

    $output = '<select class = "flat" name="' . $name . '"';
    if ($disabled) {
        $output .= ' disabled';
    }
    $output .= '>';

    foreach ($options as $label => $value) {
        // Hide customer specific values
        if (0 == $conf->global->MAIN_FEATURES_LEVEL && '_' === $value[0] && $conf->global->$name != $value) {
            continue;
        }

        $output .= '<';
        $output .= 'option';
        $output .= ' label="' . $langs->trans($label) . '"';
        $output .= ' value="' . $value . '"';
        if ($conf->global->$name === $value) {
            $output .= ' selected';
        }
        $output .= '>';
        $output .= $langs->trans($label);
        $output .= '</option>';
    }
    $output .= '</select>';

    return $output;
}

/**
 * Checks bank accounts are fully configured
 *
 * @param DoliDB $db Database handler
 * @return boolean Configuration status
 */
function aeAreBankAccountConfigured($db)
{
    $configured = true;

    $accounts = aeListActiveAccounts($db);
    foreach ($accounts as $account) {
        if ('' === $account['code']) {
            $configured = false;
        } elseif (false !== $configured) {
            $configured = true;
        } else {
            $configured = false;
        }
    }

    return $configured;
}

/**
 * Print a link to the bank configuration page
 *
 * @param int $id Bank ID
 */
function printBankLink($id)
{
    $dolibarr_version = versiondolibarrarray();
    if (3 == $dolibarr_version[0] && 7 > $dolibarr_version[1]) { // DOL_VERSION < 3.7
        echo '<a href="' . DOL_URL_ROOT . '/compta/bank/fiche.php?action=edit&id=' . $id . '">';
    } else {
        echo '<a href="' . DOL_URL_ROOT . '/compta/bank/card.php?action=edit&id=' . $id . '">';
    }
}
