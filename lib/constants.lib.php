<?php
/* Export to accounting module for Dolibarr
 * Copyright (C) 2014-2016  Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
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
 * Gives the modules constant names
 *
 * @return string[] Constants
 */
function getAccountingExportConstants()
{
    require_once dirname(__FILE__) . '/../core/modules/modAccountingExport.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
    global $db;

    $module = new modAccountingExport($db);
    $dolibarr_version = versiondolibarrarray();

    // Extract constants names from module declarations
    $constants = array_map(function ($const) {
        return $const[0];
    }, $module->const);

    if ($dolibarr_version[0] == 3 && $dolibarr_version[1] >= 5 || $dolibarr_version[0] > 3) { // DOL_VERSION >= 3.5
        // Remove unused constants
        $unused_constants = array(
            'ACCOUNTING_EXPORT_INPUT_TAX_ACCOUNT_CODE',
            'ACCOUNTING_EXPORT_OUTPUT_TAX_ACCOUNT_CODE'
        );
        $constants = array_diff($constants, $unused_constants);
    }

    return $constants;
}

/**
 * Gives the native accounting accounts constants names
 *
 * @return string[] Constants
 */
function getNativeAccountsConstants()
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

    $dolibarr_version = versiondolibarrarray();
    if (3 == $dolibarr_version[0] && 7 > $dolibarr_version[1]) { // DOL_VERSION < 3.7
        $native_accounts = array(
            'COMPTA_PRODUCT_BUY_ACCOUNT',
            'COMPTA_PRODUCT_SOLD_ACCOUNT',
            'COMPTA_SERVICE_BUY_ACCOUNT',
            'COMPTA_SERVICE_SOLD_ACCOUNT',
            'COMPTA_ACCOUNT_CUSTOMER',
            'COMPTA_ACCOUNT_SUPPLIER'
        );
        // Use native VAT accounts if they exist
        if (5 <= $dolibarr_version[1]) { // 3.7 > DOL_VERSION > 3.5
            array_push(
                $native_accounts,
                'COMPTA_VAT_ACCOUNT',
                'COMPTA_VAT_BUY_ACCOUNT'
            );
        }
    } elseif (3 == $dolibarr_version[0] && 7 == $dolibarr_version[1]) { // DOL_VERSION == 3.7
        $native_accounts = array(
            'ACCOUNTING_PRODUCT_BUY_ACCOUNT',
            'ACCOUNTING_PRODUCT_SOLD_ACCOUNT',
            'ACCOUNTING_SERVICE_BUY_ACCOUNT',
            'ACCOUNTING_SERVICE_SOLD_ACCOUNT',
            'ACCOUNTING_VAT_ACCOUNT',
            'ACCOUNTING_VAT_BUY_ACCOUNT',
            'ACCOUNTING_ACCOUNT_CUSTOMER',
            'ACCOUNTING_ACCOUNT_SUPPLIER'
        );
    } else { // DOL_VERSION >= 3.8
        $native_accounts = array(
            'ACCOUNTING_PRODUCT_BUY_ACCOUNT',
            'ACCOUNTING_PRODUCT_SOLD_ACCOUNT',
            'ACCOUNTING_SERVICE_BUY_ACCOUNT',
            'ACCOUNTING_SERVICE_SOLD_ACCOUNT',
            'ACCOUNTING_VAT_SOLD_ACCOUNT',
            'ACCOUNTING_VAT_BUY_ACCOUNT',
            'ACCOUNTING_ACCOUNT_CUSTOMER',
            'ACCOUNTING_ACCOUNT_SUPPLIER'
        );
    }

    return $native_accounts;
}

/**
 * Gives constant names allowed to remain empty
 *
 * @return string[] Constants
 */
function getAllowedEmptyConstants()
{
    return array(
        'ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE',
        'ACCOUNTING_EXPORT_RESERVED_CHARACTERS'
    );
}

/**
 * Gives accounting mode constant name
 *
 * @return string Constant
 */
function getAccountingModeConstant()
{
    $dolibarr_version = versiondolibarrarray();

    // DOL_VERSION >= 3.7
    $accounting_mode = 'ACCOUNTING_MODE';

    // DOL_VERSION < 3.7
    if (3 == $dolibarr_version[0] && 7 > $dolibarr_version[1]) {
        $accounting_mode = 'COMPTA_MODE';
    }

    return $accounting_mode;
}

/**
 * Gives the format affected constant names
 *
 * @return string[] Constants
*/
function getAccountingExportFormatConstants()
{
    return array(
        'ACCOUNTING_EXPORT_TYPE',
        'ACCOUNTING_EXPORT_FILE_HEADER',
        'ACCOUNTING_EXPORT_DATE_FORMAT',
        'ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER',
        'ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE',
        'ACCOUNTING_EXPORT_FILE_LINE_ENDING',
        'ACCOUNTING_EXPORT_FILE_ENCODING',
        'ACCOUNTING_EXPORT_FILE_EXTENSION',
    );
}

/**
 * Gives the format affected class properties names from the format constant names
 *
 * @return string
 */
function getAccountingExportFormatProperties()
{
    $format_constants = getAccountingExportFormatConstants();
    array_walk(
        $format_constants,
        function (&$value) {
            $value = constantToProperty($value);
        }
    );
    return $format_constants;
}

function constantToProperty($value)
{
    return strtolower(str_replace('ACCOUNTING_EXPORT_', '', $value));
}

function propertyToConstant($value)
{
    return 'ACCOUNTING_EXPORT_' . strtoupper($value);
}

function propertyArrayToConstantArray($array)
{
    return array_combine(
        array_map(
            function ($key) {
                return propertyToConstant($key);
            },
            array_keys($array)
        ),
        $array
    );
}

function arrayOfPropertyArraysToArrayOfConstantsArray(&$array)
{
    array_walk(
        $array,
        function (&$value) {
            $value = propertyArrayToConstantArray($value);
        }
    );
}
