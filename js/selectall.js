/* Export to accounting module for Dolibarr
 * Copyright (C) 2013-2015  Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
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
 * \file    selectall.js
 * \ingroup accountingexport
 * \brief   Helper to select/deselect all checkboxes in a div
 */

/**
 * Select/unselect checkboxes
 *
 * @param {boolean} flag
 */
function toggleCheckboxes(flag) {
    'use strict';
    var form = document.getElementById('export');
    var inputs = form.elements;
    var i = 0;
    if (!inputs) {
        //console.log("no inputs found");
        return;
    }
    if (!inputs.length) {
        //console.log("only one elements, forcing into an array");
        inputs = [inputs];
    }

    for (i; i < inputs.length; i += 1) {
        //console.log("checking input");
        if ('checkbox' === inputs[i].type) {
            inputs[i].checked = flag;
        }
    }
}
