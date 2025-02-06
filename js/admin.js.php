<?php
/* Export to accounting module for Dolibarr
 * Copyright (C) 2016  Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
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
 * \file    admin.js
 * \ingroup accountingexport
 * \brief   Helper to set and disable parameters from presets
 */

use GPCsolutions\AccountingExport\AccountingExport;

// Load Dolibarr environment
if (false === (@include '../../main.inc.php')) {  // From htdocs directory
    require '../../../main.inc.php'; // From "custom" directory
}

require_once '../lib/constants.lib.php';
require_once '../class/accountingexport.class.php';

header('Content-Type: application/javascript');

$format_constants = getAccountingExportFormatConstants();

$format_properties = getAccountingExportFormatProperties();

$reflect = new ReflectionClass('GPCsolutions\AccountingExport\AccountingExport');
$default_format_settings = $reflect->getDefaultProperties();
// Only keep format properties
$default_format_settings = array_intersect_key(
    $default_format_settings,
    array_flip($format_properties)
);
// Restore constants names
$default_format_settings = propertyArrayToConstantArray($default_format_settings);

$forced_format_settings = AccountingExport::$FORCED_FORMAT_SETTINGS;
// Restore constant names
arrayOfPropertyArraysToArrayOfConstantsArray($forced_format_settings);

$type_specific_settings = array();
// Get constant names
foreach (AccountingExport::$TYPE_SPECIFIC_PROPERTIES as $type => $type_specific_setting) {
    $type_specific_settings[$type] = array_flip(propertyArrayToConstantArray(array_flip($type_specific_setting)));
}
$type_unused_settings = array();
foreach (AccountingExport::$SUPPORTED_TYPES as $name => $type) {
    $other_types_specific_settings = $type_specific_settings;
    unset($other_types_specific_settings[$type]);
    $type_unused_settings[$type] = array();
    foreach ($other_types_specific_settings as $other_types_specific_setting) {
        $type_unused_settings[$type] = array_merge($type_unused_settings[$type], $other_types_specific_setting);
    }
}
?>
$(document).ready(init);

function init() {
    'use strict';
//    console.info("init triggered");
    displayAll();
    enableAll();
    attachTypeSelectEvent();
    attachPresetSelectEvent();
}

function attachPresetSelectEvent() {
    'use strict';
//    console.info("attachPresetSelectEvent triggered");
    $('[name="ACCOUNTING_EXPORT_FORMAT"]').on('change', setPreset);
    // Loads current preset
    $('[name="ACCOUNTING_EXPORT_FORMAT"]').trigger('change');
}

function setPreset() {
    'use strict';
//    console.info("Set Preset triggered");

    var selected_value = this.value;

    enableAll();
    switch (selected_value) {
        default:
            setPresetSettings(selected_value);
            break;
        case 'native':
            setDefaultSettings();
            break;
        case 'none':
            break;
    }
}

function setPresetSettings(name) {
    'use strict';
//    console.info("getPresetSettings triggered");
    var preset_format_settings = <?php echo json_encode($forced_format_settings); ?>[name];
    $.each(
        preset_format_settings,
        setForcedSetting
    )
}

function setDefaultSettings() {
    'use strict';
//    console.info("getDefaultSettings triggered");
    var default_format_settings = <?php echo json_encode($default_format_settings); ?>;
    $.each(
        default_format_settings,
        setForcedSetting
    )
}

function setForcedSetting(key, value) {
    'use strict';
//    console.info("setSetting triggered");
    $('[name="' + key + '"]')
        .prop('disabled', true)
        .val(bool2string(value))
        .trigger('change')
        .attr('name', 'forced-' + key); // Since disabled field don't get a value on form POST, we rename it …
    // … and store the actual value in a hidden field
    $('<input type="hidden" name="' + key + '">')
        .val(bool2string(value)).appendTo('form');
}

// Dolibarr HTML select uses yes and no instead of true and false
function bool2string(value) {
    if (true === value) {
        return 'yes';
    } else if (false === value) {
        return 'no';
    } else {
        return value;
    }
}

function enableAll() {
    'use strict';
//    console.info("enableAll triggered");
    var fields = <?php echo json_encode($format_constants) ?>;
    $.each(fields, enableSetting);
}

function enableSetting(key, value) {
    'use strict';
//    console.info("enableSetting triggered with key: " + key + " and value: " + value);
    if ($('[name="forced-' + value + '"]').length) {
//        console.info("enabling " + value);
        $('[name="' + value + '"]').remove(); // Hidden field holding the data
        $('[name="forced-' + value + '"]')
            .attr('name', value); // Renamed to not forced
    }
    $('[name="' + value + '"]')
        .prop('disabled', false);
}

function displayAll() {
    'use strict';
//    console.info("displayAll triggered");
    var fields = <?php echo json_encode($format_constants) ?>;
    $.each(fields, displaySetting);
}

function displaySetting(key, value) {
    'use strict';
//    console.info("displaySetting triggered");
    $('[name="' + value + '"]')
        .closest('tr')
        .show();
}

function attachTypeSelectEvent() {
    'use strict';
//    console.info("attachTypeSelectEvent triggered");
    $('[name="ACCOUNTING_EXPORT_TYPE"]').on('change', renderType);
    // Load current type
    $('[name="ACCOUNTING_EXPORT_TYPE"]').trigger('change');
}

function renderType() {
    'use strict';
//    console.info("renderType triggered");

    var selected_value = this.value;
    var fields_to_hide = <?php echo json_encode($type_unused_settings) ?>[selected_value];

    displayAll();

    $.each(fields_to_hide, hideSetting)
}

function hideSetting(key, value) {
    'use strict';
//    console.info("hideSetting triggered");

    $('[name="' + value + '"]')
        .closest('tr')
        .hide();
}
