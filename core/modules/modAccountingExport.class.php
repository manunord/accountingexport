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

/**
 * \defgroup    accountingexport    Export to accounting module
 * \file        core/modules/modAccountingExport.class.php
 * \ingroup     accountingexport
 * \brief       Descriptor
 *
 * Declares all the module's properties and handles integration with Dolibarr
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module AccountingExport
 */
// @codingStandardsIgnoreStart Dolibarr modules classes need to start with a lower case.
class modAccountingExport extends DolibarrModules
// @codingStandardsIgnoreEnd
{

    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        // DolibarrModules is abstract in Dolibarr < 3.8
        if (is_callable('parent::__construct')) {
            parent::__construct($db);
        } else {
            $this->db = $db;
        }

        $this->numero = 491003;
        $this->rights_class = 'accountingexport';
        $this->family = 'NORD ERP CRM';
        $this->module_position = -1;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Export to accounting";
        $this->descriptionlong = "Export Dolibarr data to an external accounting software.";
        $this->editor_name = 'NORD ERP CRM';
        $this->editor_url = 'https://www.nord-erp-crm.fr';
        $this->version = '2.0.8';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 2;
        $this->picto = 'img/Logo@accountingexport';
        $this->module_parts = array();
        $this->dirs = array('/accountingexport');
        $this->config_page_url = array('accountingexport.php@accountingexport');
        $this->depends = array('modComptabilite');
        $this->requiredby = array();
        $this->phpmin = array(5, 3);
        $this->need_dolibarr_version = array(3, 2);
        $this->langfiles = array('accountingexport@accountingexport'); // langfiles@accountingexport
        // Default constant values
        $this->const = array();
        $r = 0;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'HA'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_SALES_JOURNAL_CODE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'VT'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_SUSPENSE_ACCOUNT_CODE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '471000'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_OUTPUT_TAX_ACCOUNT_CODE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '445700'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_INPUT_TAX_ACCOUNT_CODE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '445600'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_FILE_ENCODING'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'iso-8859-1'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_FILE_LINE_ENDING'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'dos'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_FILE_HEADER'; // Key
        //$this->const[$r][1] = 'bool'; // Type
        $this->const[$r][2] = 'yes'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_DATE_FORMAT'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '%d/%m/%Y'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_FILE_EXTENSION'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '.csv'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = ','; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = '"'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_RESERVED_CHARACTERS'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = ''; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_USE_SUPPLIER_REF'; // Key
        //$this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'yes'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_FORMAT'; // Key
        $this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'native'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive
        $r++;
        $this->const[$r] = array();
        $this->const[$r][0] = 'ACCOUNTING_EXPORT_TYPE'; // Key
        $this->const[$r][1] = 'string'; // Type
        $this->const[$r][2] = 'native'; // Value
        //$this->const[$r][3] = ''; // Description
        $this->const[$r][4] = 0; // Not visible
        $this->const[$r][5] = 0; // Current entity
        $this->const[$r][6] = 1; // Delete on unactive

        // Permissions
        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 6487006;
        $this->rights[$r][1] = 'Export accounting';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'read';
        //// In php code, permission will be checked by test
        //// if ($user->rights->accountingexport->read)

        // Left menu entry
        $this->menus = array();
        $r = 0;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=accountancy',
            'type' => 'left',
            'titre' => 'AccountingExport',
            'mainmenu' => 'accountancy',
            'leftmenu' => 'accountingexport',
            'url' => '/accountingexport/index.php',
            'langs' => 'accountingexport@accountingexport',
            'position' => '100',
            'enabled' => '$conf->accountingexport->enabled',
            'perms' => '$user->rights->accountingexport->read',
            'target' => '',
            'user' => '0'
        );
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus
     * (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $sql = array();

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }
}
