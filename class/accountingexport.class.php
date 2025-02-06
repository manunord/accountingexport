<?php
/* Export to accounting module for Dolibarr
 * Copyright (C) 2012-2017  Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
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
namespace GPCsolutions\AccountingExport;

/**
 * \file    class/accountingexport.class.php
 * \ingroup accountingexport
 * \brief   Main class
 *
 * Manages the export file generation
 */
require dirname(__FILE__) . '/../vendor/autoload.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
require_once PHPEXCELNEW_PATH.'Spreadsheet.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Settings;

/**
 * Manages the export file generation
 *
 * Class AccountingExport
 */
class AccountingExport
{
    /**
     * @var array Format templates
     *
     * The number of entries determines the number of columns.
     * Entries can be used as headers content.
     * @see file_header
     *
     * 'template name' => array(
     *     'column 1 header',
     *     'column 2 header',
     *     ...
     *     'column n header',
     * ),
     */
    // TODO: make template headers translatable
    private static $TEMPLATES = array(
        'native' => array(
            'Code journal',
            'Date',
            'Type opération',
            'Pièce',
            'Compte',
            'Libellé',
            'Débit',
            'Crédit'
        ),
        'coala' => array(
            'Date',
            'Code journal',
            'Compte',
            'Pièce',
            'Libellé',
            'Débit',
            'Crédit',
            'Devise'
        ),
        '_abysse' => array(
            'Code journal',
            'Date',
            'Type opération',
            'Pièce',
            'Compte',
            'Code tiers',
            'Libellé',
            'Échéance',
            'Débit',
            'Crédit'
        ),
        '_ggs' => array (
            'Code journal',
            'Date',
            'Type opération',
            'Pièce',
            'Compte',
            'Libellé',
            'Débit',
            'Crédit',
            'Date d\'échéance',
            'Numéro de facture',
            'Mode de paiement',
            'Libellé 2',
            'Date export',
            'Devise'
        )
    );
    /**
     * @var string Date format for customer specific abysse format
     * @see date_format
     */
    private static $ABYSSE_DATE_FORMAT = '%d%m%y';

    /**
     * @var array Supported delimiters
     * @see line_field_delimiter
     */
    public static $SUPPORTED_DELIMITERS = array(
        // TODO: implement
//        'Custom' => false,
        'Comma' => ',',
        'Colon' => ':',
        'Dot' => '.',
        'Semicolon' => ';',
        'Space' => ' ',
        'Tabulation' => "\t",
        'CarriageReturn' => "\r",
        'LineFeed' => "\n",
        'CarriageReturnLineFeed' => "\r\n",
    );

    /**
     * @var array Supported encodings
     * @see file_encoding
     */
    public static $SUPPORTED_ENCODINGS = array(
        'UTF-8' => 'utf-8',
        'ISO-8859-1' => 'iso-8859-1'
    );
    /**
     * @var array Supported line endings
     * @see file_line_ending
     */
    public static $SUPPORTED_LINE_ENDINGS = array(
        'UnixLinuxMacOSX' => 'nix',
        'DosWindows' => 'dos',
        'MacOSClassic' => 'mac'
    );
    /**
     * @var array Supported date formats
     * @see date_format
     */
    public static $SUPPORTED_DATE_FORMATS = array(
        // TODO: implement
//        'Custom' => false
        'ISO-8601' => '%Y-%m-%d',
        'DD/MM/YYYY' => '%d/%m/%Y'
    );
    /**
     * @var array Supported file extensions
     * @see file_extension
     */
    public static $SUPPORTED_FILE_EXTENSIONS = array(
        'CSV' => '.csv',
        'TSV' => '.tsv',
        'TXT' => '.txt',
        'XLS' => '.xls',
        'XLSX' => '.xlsx'
    );
    /**
     * @var array Supported contents formats
     * @see FORMATS_TEMPLATES
     *
     * 'format human readable name translation key' => 'format name'
     */
    public static $SUPPORTED_FORMATS = array(
        'None' => 'none',
        'Native' => 'native',
        'CielComptaEvolution' => 'cielevo',
        'Coala' => 'coala',
        'Quadra' => '_quadra',
        'Sage100' => 'sage100',
        'Abysse' => '_abysse',
        'GGS' => '_ggs'
    );
    /**
     * @var array Map templates used by formats
     * @see SUPPORTED_FORMATS
     * @see TEMPLATES
     *
     * 'format name' => 'template name'
     */
    private static $FORMATS_TEMPLATES = array(
        'cielevo' => 'native',
        'coala' => 'coala',
        'native' => 'native',
        'none' => 'native',
        'sage100' => 'native',
        '_abysse' => 'abysse',
        '_quadra' => 'native',
        '_ggs' => '_ggs' // FIXME: confirm with customer
    );
    /**
     * @var array Supported output types
     * @see type
     *
     * 'Human readable type name translation key' => 'type name'
     */
    public static $SUPPORTED_TYPES = array(
        'DelimiterSeparatedValues' => 'dsv',
        'Excel5' => 'excel5',
        'Excel2007' => 'excel2007',
        // TODO: Add PHPExcel CSV, HTML and PDF?
    );
    /**
     * @var array Forced per format settings
     * @see SUPPORTED_FORMATS
     * @see enforceFormatSettings()
     *
     * 'format name' => array(
     *      'class property name' => 'value',
     *       […]
     * )
     */
    public static $FORCED_FORMAT_SETTINGS = array(
        'cielevo' => array(
            'type' => 'dsv',
            'file_header' => true,
            'date_format' => '%d/%m/%Y',
            'line_field_delimiter' => ',',
            'line_field_enclosure' => '"',
            'file_line_ending' => 'dos',
            'file_encoding' => 'iso-8859-1',
            'file_extension' => '.csv',
        ),
        'coala' => array(
            'type' => 'excel2007',
            'file_extension' => '.xlsx',
            'single_worksheet' => false,
            'file_encoding' => 'utf-8',
            'date_format' => '%Y-%m-%d',
        ),
        'sage100' => array(
            'type' => 'dsv',
            'file_header' => true,
            'date_format' => '%d/%m/%Y',
            'line_field_delimiter' => ',',
            'line_field_enclosure' => '',
            'file_line_ending' => 'dos',
            'file_encoding' => 'iso-8859-1',
            'file_extension' => '.txt',
        ),
        // TODO: test me
        '_quadra' => array(
            'type' => 'excel2007',
            'file_header' => true,
            'file_extension' => '.xlsx',
            'single_worksheet' => false,
            'file_encoding' => 'utf-8',
            'date_format' => '%Y-%m-%d',
        ),
        '_ggs' => array(
            // Sage 100
            'type' => 'dsv',
            'file_header' => true,
            'date_format' => '%d/%m/%Y',
            'line_field_delimiter' => ',',
            'line_field_enclosure' => '',
            'file_line_ending' => 'dos',
            'file_encoding' => 'iso-8859-1',
            'file_extension' => '.txt',
        ),
    );
    /**
     * @var array List of properties that only apply to a type
     * @see SUPPORTED_TYPES
     *
     * 'type name' => array(
     *     'class property name',
     * )
     */
    public static $TYPE_SPECIFIC_PROPERTIES = array(
        'dsv' => array(
            'file_line_ending',
            'line_field_delimiter',
            'line_field_enclosure',
            'date_format'
        ),
        'excel2007' => array(
            'single_worksheet',
            'phpexcel'
        )
    );

    /** @var \DoliDb Database handler */
    private $db;
    /** @var bool Single worksheet or one worksheet per journal mode */
    private $single_worksheet = true;
    /** @var resource File handler */
    private $fh;
    /** @var string File path and name */
    private $name;
    /** @var array Journals included in export */
    private $journals = array();
    /** @var string Period start in human readable */
    private $start_date;
    /** @var string Period start in machine readable */
    private $start_time;
    /** @var string Period end in human readable */
    private $end_date;
    /** @var string Period end in machine readable */
    private $end_time;
    /**
     * @var string Export type
     * @see SUPPORTED_TYPES
     */
    private $type = 'dsv';
    /**
     * @var string Export format
     * @see SUPPORTED_FORMATS
     */
    private $format = 'native';
    /**
     * @var string File encoding
     * @see SUPPORTED_ENCODINGS
     */
    private $file_encoding = 'utf-8';
    /**
     * @var string Newline type
     * @see SUPPORTED_LINE_ENDINGS
     */
    private $file_line_ending = 'nix';
    /**
     * @var string Export date format using MySQL syntax
     * @see SUPPORTED_DATE_FORMATS
     */
    private $date_format = '%Y-%m-%d';
    /**
     * @var string File extension
     * @see SUPPORTED_FILE_EXTENSIONS
     */
    private $file_extension = '.csv';
    /**
     * @var string Line field delimiter for CSV format
     * @see SUPPORTED_DELIMITERS
     */
    private $line_field_delimiter = ',';
    /** @var string Line field enclosure for CSV format */
    private $line_field_enclosure = '"';
    /** @var bool Support having no enclosures */
    private $strip_enclosures = false;
    /**
     * @var bool Add row headers to the file
     * @see TEMPLATES
     */
    private $file_header = true;

    /**
     * @var \PHPExcel PHPExcel object
     * @see buildExport() for init
     */
    private $phpexcel;
	private $spreadsheet;

    /** @var string Error message */
    private $errormsg;
    /** @var int Header size for file checking */
    private $header_size;

    /** @var array VAT accounting accounts by VAT rates */
    private $vat_account_tvatx = array();
    /** @var array VAT accounting accounts by VAT ID */
    private $vat_account_tvaid = array();

    /** @var array Dolibarr version assessment helper */
    private $dolibarr_version;

    /**
     * Returns the template to use for the specified format
     *
     * @param $format string Format name
     * @return array Template to use
     */
    private static function getFormatTemplate($format)
    {
        return self::$TEMPLATES[self::$FORMATS_TEMPLATES[$format]];
    }

    /**
     * Automatically enforces specific format settings
     * @see FORCED_FORMAT_SETTINGS
     */
    private function enforceFormatSettings()
    {
        if (isset(self::$FORCED_FORMAT_SETTINGS[$this->format])) {
            foreach (self::$FORCED_FORMAT_SETTINGS[$this->format] as $item => $value) {
                $this->$item = $value;
            }
        }
    }

    /**
     * Initializes the file
     *
     * @global \Conf $conf
     *
     * @param \DoliDb $db Database object
     * @param array $journals List of journals to export
     * @param string $start_date Start date of the export
     * @param string $start_time Start time of the export
     * @param string $end_date End date of the export
     * @param string $end_time End time of the export
     */
    public function __construct(
        $db,
        $journals,
        $start_date,
        $start_time,
        $end_date,
        $end_time
    ) {
        global $conf;

        // Assess Dolibarr version
        $this->dolibarr_version = versiondolibarrarray();

        $this->db = $db;
        $this->journals = $journals;
        $this->start_date = str_replace('/', '_', $start_date);
        $this->end_date = str_replace('/', '_', $end_date);
        $this->start_time = $this->db->idate($start_time);
        $this->end_time = $this->db->idate($end_time);
        $this->format = $conf->global->ACCOUNTING_EXPORT_FORMAT;
        $this->file_encoding = $conf->global->ACCOUNTING_EXPORT_FILE_ENCODING;
        $this->file_line_ending = $conf->global->ACCOUNTING_EXPORT_FILE_LINE_ENDING;
        $this->date_format = $conf->global->ACCOUNTING_EXPORT_DATE_FORMAT;
        $this->file_extension = $conf->global->ACCOUNTING_EXPORT_FILE_EXTENSION;
        $this->line_field_delimiter = $conf->global->ACCOUNTING_EXPORT_LINE_FIELD_DELIMITER;
        $this->line_field_enclosure = $conf->global->ACCOUNTING_EXPORT_LINE_FIELD_ENCLOSURE;
        $this->type = $conf->global->ACCOUNTING_EXPORT_TYPE;
        $this->file_header = $conf->global->ACCOUNTING_EXPORT_FILE_HEADER;
        if ('_ggs' === $this->format) {
            $this->uuid = uniqid();
        }

        // VAT loading
        if ((3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1]) || 3 < $this->dolibarr_version[0]) { // DOL_VERSION >= 3.4
            $country = explode(':', $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
        } else {
            $country = explode(':', $conf->global->MAIN_INFO_SOCIETE_PAYS);
        }
        $country_code = "'" . $country[1] . "'";
        $this->loadVATRates($country_code);

        $this->enforceFormatSettings(); // Make sure this is called after direct initialization to prevent fields from being overwritten.

        $this->buildExport();
    }

    /**
     * Create a file, make sanity checks and add headers
     *
     * @return bool File creation status
     */
    private function createFile()
    {
        // Make sure the destination path exists
        $path = dirname($this->name);
        if (!is_dir($path)) {
            if (!mkdir($path, null, true)) {
                $this->errormsg = 'CantCreatePath';
                dol_syslog($this->errormsg . ': ' . $path, LOG_ERR);
                return false;
            }
        };

        // Make sure we can write to it
        if (!is_writable($path)) {
            $this->errormsg = 'CantWritePath';
            dol_syslog($this->errormsg . ': ' . $this->name, LOG_ERR);
            return false;
        }

        // Check for existing file
        if (file_exists($this->name)) {
            $this->errormsg = 'FileAlreadyExists';
            dol_syslog($this->errormsg . ': ' . $this->name, LOG_ERR);
            return false;
        }

        switch ($this->type) {
            default:
            case 'dsv':
                // Open file for writing
                $this->fh = fopen($this->name, 'w+b'); // Read/write at the beginning in binary mode
                if (false === $this->fh) {
                    $this->errormsg = 'CantOpenFile';
                    dol_syslog($this->errormsg . ': ' . $this->name, LOG_ERR);
                    return false;
                }
                break;
            case 'excel5':
            case 'excel2007':
                // Do nothing
        }
        return true;
    }

    /**
     * Closes the file
     */
    public function __destruct()
    {
        if ($this->fh) {
            fclose($this->fh);
        }
    }

    /**
     * Builds the filename from the known data
     *
     * @global \Conf $conf
     *
     * @param string $journal Journal code
     */
    private function makeFilename($journal = null)
    {
        global $conf;

        $this->name = $conf->accountingexport->dir_output;
        $this->name .= '/';
        if (null === $journal) {
            foreach ($this->journals as $journal) {
                $this->name .= $journal;
                $this->name .= '-';
            }
        } else {
            $this->name .= $journal;
            $this->name .= '-';
        }
        $this->name .= $this->start_date;
        $this->name .= '-';
        $this->name .= $this->end_date;
        $this->name .= $this->file_extension;
    }

    /**
     * Builds the requested export
     *
     * @global \Translate $langs
     */
 private function buildExport()
{
    global $langs;

    // Initialize PHPSpreadsheet regardless of type to avoid null reference errors
    Cell::setValueBinder(new AdvancedValueBinder());
    $this->spreadsheet = new Spreadsheet();
    \PhpOffice\PhpSpreadsheet\Settings::setLocale($langs->defaultlang);

    // Remove default worksheet
    $this->spreadsheet->removeSheetByIndex(0);

    $this->makeFilename();

    if (false === $this->createFile()) {
        return;
    }

    if ($this->single_worksheet) {
        $this->addWorksheet($this->getName());
        $this->addHeader();
        foreach ($this->journals as $journal) {
            $this->addJournal($journal);
        }
    } else {
        foreach ($this->journals as $journal) {
            $this->addWorksheet($journal);
            $this->addHeader();
            $this->addJournal($journal);
        }
    }

    $this->checkExport();
}

/**
 * Create individual worksheets
 *
 * @param string $name Worksheet name
 */
private function addWorksheet($name)
{
    if ('dsv' === $this->type) {
        return;
    }

    try {
        $new_worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($this->spreadsheet, $name);
    } catch (\Exception $e) {
        // Excel doesn't support names longer than 31 characters
        $new_worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($this->spreadsheet);
    }

    $this->spreadsheet->addSheet($new_worksheet);
    $new_worksheet_index = $this->spreadsheet->getIndex($new_worksheet);
    $this->spreadsheet->setActiveSheetIndex($new_worksheet_index);
}
    /**
     * Wrapper to add journal content per journal type
     *
     * @global \Conf $conf
     *
     * @param string $journal Journal code
     */
    private function addJournal($journal)
    {
        global $conf;

        if ($conf->global->ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE === $journal) {
            $this->addPurchasesJournal();
        } elseif ($conf->global->ACCOUNTING_EXPORT_SALES_JOURNAL_CODE === $journal) {
            $this->addSalesJournal();
        } else {
            $this->addTreasuryJournal($journal);
        }
    }

    /**
     * Adds the purchases journal to the export
     *
     * @global \Conf $conf
     */
    private function addPurchasesJournal()
    {
        global $conf;

        $purchase = array();

        $sql = 'SELECT';
        $sql .= ' DATE_FORMAT(f.datef, \'' . $this->date_format . '\') as date,';
        $sql .= ' f.datef as sortdate,'; // Sort on unformatted date
        $sql .= ' f.rowid as id,';
        if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION >= 3.4
            $sql .= ' f.ref,';
            $sql .= ' f.ref_supplier,'; // f.ref 3.2 - 3.4
        } else {
            $sql .= ' f.ref as ref_supplier,';
        }
        $sql .= ' s.code_compta_fournisseur, p.accountancy_code_buy, fd.product_type,';
        $sql .= ' s.nom, fd.description,';
        $sql .= ' fd.total_ht, fd.tva, fd.tva_tx,';
        $sql .= ' f.total_ttc as total_fact_fourn,';
        if ('_abysse' === $this->format) {
            $sql .= ' s.code_fournisseur as supplier_code,';
            $sql .= ' DATE_FORMAT(f.date_lim_reglement, \'' . self::$ABYSSE_DATE_FORMAT . '\') as payment_term,';
        }
        if ('_ggs' === $this->format) {
            $sql .= ' DATE_FORMAT(f.date_lim_reglement, \'' . $this->date_format . '\') as payment_term,';
            $sql .= ' p.ref as product_ref,';
        }
        $sql .= ' p.label';
        $sql .= ' FROM ';
        $sql .= MAIN_DB_PREFIX . 'facture_fourn_det AS fd';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'product p ON p.rowid = fd.fk_product';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'facture_fourn f ON f.rowid = fd.fk_facture_fourn';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'societe s ON s.rowid = f.fk_soc';
        $sql .= ' WHERE ';
        $sql .= 'f.fk_statut > \'0\'';
        $sql .= ' AND ';
        $sql .= 'f.entity = \'' . $conf->entity . '\'';
        $sql .= ' AND ';
        $sql .= 'f.datef >= \'' . $this->start_time . '\'';
        $sql .= ' AND ';
        $sql .= 'f.datef <= \'' . $this->end_time . '\'';
        $sql .= ' ORDER BY sortdate';

        $result = $this->db->query($sql);

        if ($result) {
            $num = $this->db->num_rows($result);

            $i = 0;
            $last_fact_seen = '';
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);

                // Écriture du compte collectif fournisseur
                if (!($last_fact_seen === $obj->id)) {
                    $last_fact_seen = $obj->id;
                    // Filter zero amounts
                    if (0 != $obj->total_fact_fourn) {
                        $purchase['code'] = $conf->global->ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE;
                        $purchase['date'] = $obj->date;
                        $purchase['type'] = 'Tiers';
                        if ('yes' === $conf->global->ACCOUNTING_EXPORT_USE_SUPPLIER_REF) {
                            $purchase['id'] = $obj->ref_supplier;
                        } else {
                            if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1]) { // DOL_VERSION >= 3.4
                                $purchase['id'] = $obj->ref;
                            } else {
                                $purchase['id'] = $obj->ref;
                            }
                        }
                        if (!empty($obj->code_compta_fournisseur)) {
                            $purchase['account'] = $obj->code_compta_fournisseur;
                        } else {
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $purchase['account'] = $conf->global->COMPTA_ACCOUNT_SUPPLIER;
                            } else { // DOL_VERSION > 3.7
                                $purchase['account'] = $conf->global->ACCOUNTING_ACCOUNT_SUPPLIER;
                            }
                        }
                        if ('_abysse' === $this->format) {
                            $purchase['supplier_code'] = $obj->supplier_code;
                        }
                        $purchase['label'] = $this->sanitizeString($obj->nom);
                        if ('_abysse' === $this->format) {
                            $purchase['payment_term'] = $obj->payment_term;
                        }
                        // Credit memo management
                        if (0 > $obj->total_fact_fourn) {
							$obj->total_fact_fourn = abs($obj->total_fact_fourn);
                            $purchase['debit'] = number_format($obj->total_fact_fourn,2,",","");
                            $purchase['credit'] = '';
                        } else {
                            $purchase['debit'] = '';
                            $purchase['credit'] = number_format($obj->total_fact_fourn,2,",","");
                        }
                        if ('_ggs' === $this->format) {
                            $purchase['payment_term'] = $obj->payment_term;
                            $purchase['invoice_num'] = $purchase['id'];
                            $purchase['payment_mode'] = '';
                            $purchase['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $purchase['invoice_num'];
                            $purchase['uuid'] = $this->uuid;
                            $purchase['currency'] = $conf->global->MAIN_MONNAIE;
                        }

                        // Let's write this to file
                        $this->addLine($purchase);
                    }
                }

                // Écriture de TVA
                // Filter zero amounts
                if (0 != $obj->tva) {
                    $purchase['code'] = $conf->global->ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE;
                    $purchase['date'] = $obj->date;
                    $purchase['type'] = 'TVA ' . (int)$obj->tva_tx;
                    if ('yes' === $conf->global->ACCOUNTING_EXPORT_USE_SUPPLIER_REF) {
                        $purchase['id'] = $obj->ref_supplier;
                    } else {
                        if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1]) { // DOL_VERSION >= 3.4
                            $purchase['id'] = $obj->ref;
                        } else {
                            $purchase['id'] = $obj->ref;
                        }
                    }
                    $tva_tx = (string)floatval($obj->tva_tx);
                    if (!empty($this->vat_account_tvatx[$tva_tx]['buy'])) {
                        $purchase['account'] = $this->vat_account_tvatx[$tva_tx]['buy'];
                    } else {
                        if (3 == $this->dolibarr_version[0] && 5 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.5
                            $purchase['account'] = $conf->global->ACCOUNTING_EXPORT_INPUT_TAX_ACCOUNT_CODE;
                        } elseif (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                            $purchase['account'] = $conf->global->COMPTA_VAT_BUY_ACCOUNT;
                        } else { // DOL_VERSION >= 3.7
                            $purchase['account'] = $conf->global->ACCOUNTING_VAT_BUY_ACCOUNT;
                        }
                    }
                    if ('_abysse' === $this->format) {
                        $purchase['supplier_code'] = $obj->supplier_code;
                    }
                    $purchase['label'] = $this->sanitizeString($obj->label . ' ' . $obj->description);
                    if ('_abysse' === $this->format) {
                        $purchase['payment_term'] = $obj->payment_term;
                    }
                    // Credit memo management
                    if (0 > $obj->tva) {
						$obj->tva = abs($obj->tva);
                        $purchase['debit'] = '';
                        $purchase['credit'] = number_format($obj->tva,2,",","");
                    } else {
                        $purchase['debit'] = number_format($obj->tva,2,",","");
                        $purchase['credit'] = '';
                    }
                    if ('_ggs' === $this->format) {
                        $purchase['payment_term'] = $obj->payment_term;
                        $purchase['invoice_num'] = $purchase['id'];
                        $purchase['payment_mode'] = '';
                        $purchase['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $purchase['invoice_num'];
                        $purchase['uuid'] = $this->uuid;
                        $purchase['currency'] = $conf->global->MAIN_MONNAIE;
                    }

                    // Let's write this to file
                    $this->addLine($purchase);
                }

                // Écriture de produits
                // Filter zero amounts
                if (0 != $obj->total_ht) {
                    $purchase['code'] = $conf->global->ACCOUNTING_EXPORT_PURCHASES_JOURNAL_CODE;
                    $purchase['date'] = $obj->date;
                    $purchase['type'] = 'Produits';
                    if ('yes' === $conf->global->ACCOUNTING_EXPORT_USE_SUPPLIER_REF) {
                        $purchase['id'] = $obj->ref_supplier;
                    } else {
                        if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1]) { // DOL_VERSION >= 3.4
                            $purchase['id'] = $obj->ref;
                        } else {
                            $purchase['id'] = $obj->ref;
                        }
                    }
                    // Use default account when code empty
                    if ($obj->accountancy_code_buy) {
                        $purchase['account'] = $obj->accountancy_code_buy;
                    } else {
                        if ($obj->product_type) {
                            // Service
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $purchase['account'] = $conf->global->COMPTA_SERVICE_BUY_ACCOUNT;
                            } else { // DOL_VERSION > 3.7
                                $purchase['account'] = $conf->global->ACCOUNTING_SERVICE_BUY_ACCOUNT;
                            }
                        } else {
                            // Product
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $purchase['account'] = $conf->global->COMPTA_PRODUCT_BUY_ACCOUNT;
                            } else { // DOL_VERSION > 3.7
                                $purchase['account'] = $conf->global->ACCOUNTING_PRODUCT_BUY_ACCOUNT;
                            }
                        }
                    }
                    if ('_abysse' === $this->format) {
                        $purchase['supplier_code'] = $obj->supplier_code;
                    }
                    $purchase['label'] = $this->sanitizeString($obj->label . ' ' . $obj->description);
                    if ('_abysse' === $this->format) {
                        $purchase['payment_term'] = $obj->payment_term;
                    }
                    // Credit memo management
                    if (0 > $obj->total_ht) {
						$obj->total_ht = abs($obj->total_ht);
                        $purchase['debit'] = '';
                        $purchase['credit'] = number_format($obj->total_ht,2,",","");
                    } else {
                        $purchase['debit'] = number_format($obj->total_ht,2,",","");
                        $purchase['credit'] = '';
                    }
                    if ('_ggs' === $this->format) {
                        $purchase['payment_term'] = $obj->payment_term;
                        $purchase['invoice_num'] = $purchase['id'];
                        $purchase['payment_mode'] = '';
                        $purchase['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $purchase['invoice_num'];
                        $purchase['uuid'] = $this->uuid;
                        $purchase['currency'] = $conf->global->MAIN_MONNAIE;
                    }

                    // Let's write this to file
                    $this->addLine($purchase);
                }

                $i++;
            }
        } else {
            $this->setSqlError();
        }
    }

    /**
     * Adds the sales journal to the export
     *
     * @global \Conf $conf
     */
    private function addSalesJournal()
    {
        global $conf;

        $sale = array();

        $sql = 'SELECT';
        $sql .= ' DATE_FORMAT(f.datef, \'' . $this->date_format . '\') as date,';
        if((float)DOL_VERSION<9.0) { // DOL_VERSION < 9
			$sql .= ' f.facnumber as ref,';
		} else {
			$sql .= ' f.ref as ref,';
		}
        $sql .= ' f.datef as sortdate,'; // Sort on unformatted date
        $sql .= ' s.code_compta, p.accountancy_code_sell, fd.product_type,';
        $sql .= ' s.nom, fd.description,';
        $sql .= ' fd.total_ht, fd.total_tva as tva, fd.tva_tx,';
        $sql .= ' f.total_ttc as total_fact,';
        if ('_abysse' === $this->format) {
            $sql .= ' s.code_client as customer_code,';
            $sql .= ' DATE_FORMAT(f.date_lim_reglement, \'' . self::$ABYSSE_DATE_FORMAT . '\') as payment_term,';
        }
        if ('_ggs' === $this->format) {
            $sql .= ' DATE_FORMAT(f.date_lim_reglement, \'' . $this->date_format . '\') as payment_term,';
            $sql .= ' p.ref as product_ref,';
        }
        $sql .= ' p.label';
        $sql .= ' FROM ';
        $sql .= MAIN_DB_PREFIX . 'facturedet AS fd';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'product p ON p.rowid = fd.fk_product';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'facture f ON f.rowid = fd.fk_facture';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'societe s ON s.rowid = f.fk_soc';
        $sql .= ' WHERE ';
        $sql .= 'f.fk_statut > \'0\'';
        $sql .= ' AND ';
        $sql .= 'f.entity = \'' . $conf->entity . '\'';
        $sql .= ' AND ';
        $sql .= 'f.datef >= \'' . $this->start_time . '\'';
        $sql .= ' AND ';
        $sql .= 'f.datef <= \'' . $this->end_time . '\'';
        // Strip deposit invoices if deposits are just payments
        if ($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS) {
            $sql .= ' AND ';
            $sql .= 'f.type <> 3';
        }
        $sql .= ' AND ';
        // Strip special lines
        $sql .= 'fd.special_code = 0';
        $sql .= ' ORDER BY sortdate';

        $result = $this->db->query($sql);

        if ($result) {
            $num = $this->db->num_rows($result);

            $i = 0;
            $last_fact_seen = '';
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);

                // Écriture du compte collectif client
                if (!($last_fact_seen === $obj->ref)) {
                    $last_fact_seen = $obj->ref;
                    // Filter zero amounts
                    if (0 != $obj->total_fact) {
                        $sale['code'] = $conf->global->ACCOUNTING_EXPORT_SALES_JOURNAL_CODE;
                        $sale['date'] = $obj->date;
                        $sale['type'] = 'Tiers';
                        $sale['id'] = $obj->ref;
                        if (!empty($obj->code_compta)) {
                            $sale['account'] = $obj->code_compta;
                        } else {
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $sale['account'] = $conf->global->COMPTA_ACCOUNT_CUSTOMER;
                            } else {
                                $sale['account'] = $conf->global->ACCOUNTING_ACCOUNT_CUSTOMER;
                            }
                        }
                        if ('_abysse' === $this->format) {
                            $sale['customer_code'] = $obj->customer_code;
                        }
                        $sale['label'] = $this->sanitizeString($obj->nom);
                        if ('_abysse' === $this->format) {
                            $sale['payment_term'] = $obj->payment_term;
                        }
                        // Credit memo management
						
                        if (0 > $obj->total_fact) {
							$obj->total_fact = abs($obj->total_fact);
                            $sale['debit'] = '';
                            $sale['credit'] = number_format($obj->total_fact,2,",","");
                        } else {
							
                            $sale['debit'] = number_format($obj->total_fact,2,",","");
                            $sale['credit'] = '';
                        }
                        if ('_ggs' === $this->format) {
                            $sale['payment_term'] = $obj->payment_term;
                            $sale['invoice_num'] = $sale['id'];
                            $sale['payment_mode'] = '';
                            $sale['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $sale['invoice_num'];
                            $sale['uuid'] = $this->uuid;
                            $sale['currency'] = $conf->global->MAIN_MONNAIE;
                        }

                        // Let's write this to file
                        $this->addLine($sale);
                    }
                }

                // Écriture de TVA
                // Filter zero amounts
                if (0 != $obj->tva) {
                    $sale['code'] = $conf->global->ACCOUNTING_EXPORT_SALES_JOURNAL_CODE;
                    $sale['date'] = $obj->date;
                    $sale['type'] = 'TVA ' . (int)$obj->tva_tx;
                    $sale['id'] = $obj->ref;
                    $tva_tx = (string)floatval($obj->tva_tx);
                    if (!empty($this->vat_account_tvatx[$tva_tx]['sell'])) {
                        $sale['account'] = $this->vat_account_tvatx[$tva_tx]['sell'];
                    } else {
                        if (3 == $this->dolibarr_version[0] && 5 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.5
                            $sale['account'] = $conf->global->ACCOUNTING_EXPORT_OUTPUT_TAX_ACCOUNT_CODE;
                        } elseif (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                            $sale['account'] = $conf->global->COMPTA_VAT_ACCOUNT;
                        } elseif (3 == $this->dolibarr_version[0] && 7 == $this->dolibarr_version[1]) { // DOL_VERSION = 3.7
                            $sale['account'] = $conf->global->ACCOUNTING_VAT_ACCOUNT;
                        } else { // DOL_VERSION >= 3.8
                            $sale['account'] = $conf->global->ACCOUNTING_VAT_SOLD_ACCOUNT;
                        }
                    }
                    if ('_abysse' === $this->format) {
                        $sale['customer_code'] = '';
                    }
                    $sale['label'] = $this->sanitizeString($obj->label . ' ' . $obj->description);
                    if ('_abysse' === $this->format) {
                        $sale['payment_term'] = '';
                    }
                    // Credit memo management
                    if (0 > $obj->tva) {
						$obj->tva = abs($obj->tva);
                        $sale['debit'] = number_format($obj->tva,2,",","");
                        $sale['credit'] = '';
                    } else {
                        $sale['debit'] = '';
                        $sale['credit'] = number_format($obj->tva,2,",","");
                    }
                    if ('_ggs' === $this->format) {
                        $sale['payment_term'] = '';
                        $sale['invoice_num'] = $sale['id'];
                        $sale['payment_mode'] = '';
                        $sale['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $sale['invoice_num'];
                        $sale['uuid'] = $this->uuid;
                        $sale['currency'] = $conf->global->MAIN_MONNAIE;
                    }

                    // Let's write this to file
                    $this->addLine($sale);
                }

                // Écriture de produits
                // Filter zero amounts
                if (0 != $obj->total_ht) {
                    $sale['code'] = $conf->global->ACCOUNTING_EXPORT_SALES_JOURNAL_CODE;
                    $sale['date'] = $obj->date;
                    $sale['type'] = 'Produits';
                    $sale['id'] = $obj->ref;
                    // Use default account when code empty
                    if ($obj->accountancy_code_sell) {
                        $sale['account'] = $obj->accountancy_code_sell;
                    } else {
                        if ($obj->product_type) {
                            // Service
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $sale['account'] = $conf->global->COMPTA_SERVICE_SOLD_ACCOUNT;
                            } else { // DOL_VERSION > 3.7
                                $sale['account'] = $conf->global->ACCOUNTING_SERVICE_SOLD_ACCOUNT;
                            }
                        } else {
                            // Product
                            if (3 == $this->dolibarr_version[0] && 7 > $this->dolibarr_version[1]) { // DOL_VERSION < 3.7
                                $sale['account'] = $conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT;
                            } else {
                                $sale['account'] = $conf->global->ACCOUNTING_PRODUCT_SOLD_ACCOUNT;
                            }
                        }
                    }
                    if ('_abysse' === $this->format) {
                        $sale['customer_code'] = '';
                    }
                    $sale['label'] = $this->sanitizeString($obj->label . ' ' . $obj->description);
                    if ('_abysse' === $this->format) {
                        $sale['payment_term'] = '';
                    }
                    // Credit memo management
                    if (0 > $obj->total_ht) {
						$obj->total_ht = abs($obj->total_ht);
                        $sale['debit'] = number_format($obj->total_ht,2,",","");
                        $sale['credit'] = '';
                    } else {
                        $sale['debit'] = '';
                        $sale['credit'] = number_format($obj->total_ht,2,",","");
                    }
                    if ('_ggs' === $this->format) {
                        $sale['payment_term'] = '';
                        $sale['invoice_num'] = $sale['id'];
                        $sale['payment_mode'] = '';
                        $sale['label2'] = $obj->product_ref . ' ' . $this->sanitizeString($obj->nom) . ' ' . $sale['invoice_num'];
                        $sale['uuid'] = $this->uuid;
                        $sale['currency'] = $conf->global->MAIN_MONNAIE;
                    }

                    // Let's write this to file
                    $this->addLine($sale);
                }

                $i++;
            }
        } else {
            $this->setSqlError();
        }
    }

    /**
     * Adds the specified treasury journal to the export
     *
     * @global \Conf $conf
     *
     * @param string $journal Journal name
     */
    private function addTreasuryJournal($journal)
    {
        global $conf;

        $treasury = array();
		
		// $sql = "SET sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));";
		// $this->db->query();

        $sql = 'SELECT';
        $sql .= ' DATE_FORMAT(b.dateo, \'' . $this->date_format . '\') as date,';
        $sql .= ' f.datef as sortdate,'; // Sort on unformatted date
        $sql .= ' t.libelle as op_type,';
        $sql .= ' b.num_chq as op_id,';
        $sql .= ' GROUP_CONCAT(CONCAT_WS(\'\', c.code_compta, s.code_compta_fournisseur';
        if (3 == $this->dolibarr_version[0] && 5 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 3.5
            $sql .= ', tc.accountancy_code';
        }
		if (10 == $this->dolibarr_version[0] && 0 <= $this->dolibarr_version[1] || 10 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 10
            $sql .= ', tv.accountancy_code';
        }
        $sql .= ') SEPARATOR \',\') AS tp_account_code,';
        $sql .= ' ba.account_number as bank_account_code,';
        $sql .= ' b.label as bank_label,';
        if ('yes' === $conf->global->ACCOUNTING_EXPORT_USE_SUPPLIER_REF) {
            if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 3.4
                $sql .= ' GROUP_CONCAT(CONCAT_WS(\' \', soc.nom, f.ref, si.ref_supplier) SEPARATOR \', \') as label,';
            } else {
                $sql .= ' GROUP_CONCAT(CONCAT_WS(\' \', soc.nom, f.ref, si.ref) SEPARATOR \', \') as label,';
            }
        } else {
            // Supplier invoice reference is not available before Dolibarr 3.4
            if (3 == $this->dolibarr_version[0] && 4 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 3.4
				if((float)DOL_VERSION<9.0) { // DOL_VERSION < 9
					$sql .= ' GROUP_CONCAT(CONCAT_WS(\' \', soc.nom, f.facnumber, si.rowid) SEPARATOR \', \') as label,';
				} else {
					$sql .= ' GROUP_CONCAT(CONCAT_WS(\' \', soc.nom, f.ref, si.rowid) SEPARATOR \', \') as label,';
				}
            } else {
                $sql .= ' GROUP_CONCAT(CONCAT_WS(\' \', soc.nom, f.ref, si.rowid) SEPARATOR \', \') as label,';
            }
        }
        $sql .= ' CONCAT_WS(\' \', tc.libelle, ch.libelle) as payroll_label,';
        $sql .= ' GROUP_CONCAT(pf.rowid SEPARATOR \',\') AS payment_ids,';
        $sql .= ' GROUP_CONCAT(soc.nom SEPARATOR \',\') AS customers,';
        $sql .= ' GROUP_CONCAT(f.ref SEPARATOR \',\') AS invoice_numbers,';
        $sql .= ' b.amount as amount';
        $sql .= ' FROM ';
        $sql .= MAIN_DB_PREFIX . 'bank AS b';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'bank_account AS ba ON ba.rowid = b.fk_account';
        $sql .= ' JOIN ';
        $sql .= MAIN_DB_PREFIX . 'c_paiement AS t ON b.fk_type = t.code';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'paiement AS p ON p.fk_bank = b.rowid';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'paiement_facture AS pf ON pf.fk_paiement = p.rowid';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'facture AS f ON f.rowid = pf.fk_facture';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'paiementfourn AS sp ON sp.fk_bank = b.rowid';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'paiementfourn_facturefourn AS sip ON sip.fk_paiementfourn = sp.rowid';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'facture_fourn AS si ON si.rowid = sip.fk_facturefourn';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'paiementcharge AS pc ON  pc.fk_bank = b.rowid';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'chargesociales AS ch ON ch.rowid = pc.fk_charge';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'c_chargesociales AS tc ON ch.fk_type = tc.id';
		if (10 == $this->dolibarr_version[0] && 0 <= $this->dolibarr_version[1] || 10 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 10
			$sql .= ' LEFT JOIN ';
			$sql .= MAIN_DB_PREFIX . 'payment_various AS tv ON tv.fk_bank = b.rowid';
		}
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'societe AS soc ON soc.rowid = f.fk_soc OR soc.rowid = si.fk_soc';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'societe AS c ON c.rowid = f.fk_soc';
        $sql .= ' LEFT JOIN ';
        $sql .= MAIN_DB_PREFIX . 'societe AS s ON s.rowid = si.fk_soc';
        $sql .= ' WHERE ';
        $sql .= 'ba.ref = \'' . $journal . '\'';
        $sql .= ' AND ';
        $sql .= 'ba.entity = \'' . $conf->entity . '\'';
        $sql .= ' AND ';
        $sql .= 'b.dateo >= \'' . $this->start_time . '\'';
        $sql .= ' AND ';
        $sql .= 'b.dateo <= \'' . $this->end_time . '\'';
        $sql .= ' GROUP BY';
        $sql .= ' b.rowid';
        $sql .= ', b.dateo';
        $sql .= ', t.libelle';
        $sql .= ', b.num_chq';
        $sql .= ', s.code_compta_fournisseur';
        $sql .= ', ba.account_number';
        $sql .= ', b.label';
        $sql .= ', tc.libelle';
        $sql .= ', ch.libelle';
        $sql .= ', b.amount';
        
        if (3 == $this->dolibarr_version[0] && 5 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 3.5
            $sql .= ', tc.accountancy_code';
        }
		if (10 == $this->dolibarr_version[0] && 0 <= $this->dolibarr_version[1] || 10 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 10
            $sql .= ', tv.accountancy_code';
        }
        $sql .= ' ORDER BY sortdate';

        $result = $this->db->query($sql);

        if ($result) {
            $num = $this->db->num_rows($result);
            for ($i = 0; $i < $num; $i++) {
                $obj = $this->db->fetch_object($result);
                $amounts = array();
                $labels = array();
                $tp_account_codes = array();
                $payment_ids = explode(',', $obj->payment_ids);
                if (1 < count($payment_ids)) {
                    $customers = explode(',', $obj->customers);
                    $invoice_numbers = explode(',', $obj->invoice_numbers);
                    $tp_account_codes = explode(',', $obj->tp_account_code);
                    foreach ($payment_ids as $key => $payment_id) {
                        $sql2 = 'SELECT amount';
                        $sql2 .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture';
                        $sql2 .= ' WHERE rowid=' . $payment_id;

                        $result2 = $this->db->query($sql2);
                        if ($result2) {
                            $num2 = $this->db->num_rows($result2);
                            for ($j = 0; $j < $num2; $j++) {
                                $obj2 = $this->db->fetch_object($result2);
                                array_push($amounts, $obj2->amount);
                            }
                            unset($num2);
                            $labels[$key] = $this->sanitizeString($customers[$key] . ' ' . $invoice_numbers[$key]);
                        }
                    }
                    unset($customers);
                    unset($invoice_numbers);
                } else {
                    // Single payment
                    array_push($amounts, $obj->amount);
                    array_push($labels, $this->sanitizeString($obj->label));
                    // Avoid concat side effect
                    $tmp_tp_account_code = explode(',', $obj->tp_account_code);
                    array_push($tp_account_codes, $tmp_tp_account_code[0]);
                }

                foreach ($amounts as $key => $amount) {
                    // Écriture de banque
                    // Filter zero amounts
                    if (0 != $obj->amount) {
                        // native account
                        $treasury['code'] = $journal;
                        $treasury['date'] = $obj->date;
                        $treasury['type'] = $this->sanitizeString($obj->op_type);
                        $treasury['id'] = $obj->op_id;
                        // Use default account when code empty
                        if ($tp_account_codes[$key]) {
                            $treasury['account'] = $tp_account_codes[$key];
                        } else {
                            $treasury['account'] = $conf->global->ACCOUNTING_EXPORT_SUSPENSE_ACCOUNT_CODE;
                        }
                        if ('_abysse' === $this->format) {
                            $treasury['thirdparty'] = '';
                        }
                        $treasury['label'] = $this->buildTreasuryLabel($obj, $labels[$key]);
                        if ('_abysse' === $this->format) {
                            $treasury['payment_term'] = '';
                        }
                        if (0 > $obj->amount) {
							$obj->amount = abs($obj->amount);
                            $treasury['debit'] = number_format($amount,2,",","");
                            $treasury['credit'] = '';
                        } else {
                            $treasury['debit'] = '';
                            $treasury['credit'] = number_format($amount,2,",","");
                        }
                        if ('_ggs' === $this->format) {
                            $treasury['payment_term'] = '';
                            $treasury['invoice_num'] = '';
                            $treasury['payment_mode']=$treasury['type'];
                            $treasury['label2'] = $treasury['label'];
                            $treasury['uuid'] = $this->uuid;
                            $treasury['currency'] = $conf->global->MAIN_MONNAIE;
                        }

                        // Let's write this to file
                        $this->addLine($treasury);
                    }
                }

                // Écriture de compensation banque
                if (0 != $obj->amount) {
                    $treasury['code'] = $journal;
                    $treasury['date'] = $obj->date;
                    $treasury['type'] = $this->sanitizeString($obj->op_type);
                    $treasury['id'] = $obj->op_id;
                    $treasury['account'] = $obj->bank_account_code;
                    if ('_abysse' === $this->format) {
                        $treasury['thirdparty'] = '';
                    }
                    $treasury['label'] = $this->buildTreasuryLabel($obj);
                    if ('_abysse' === $this->format) {
                        $treasury['payment_term'] = '';
                    }
                    if (0 > $obj->amount) {
						$obj->amount = abs($obj->amount);
                        $treasury['debit'] = '';
                        $treasury['credit'] = number_format($obj->amount,2,",","");
                    } else {
                        $treasury['debit'] = number_format($obj->amount,2,",","");
                        $treasury['credit'] = '';
                    }
                    if ('_ggs' === $this->format) {
                        $treasury['payment_term'] = '';
                        $treasury['invoice_num'] = '';
                        $treasury['payment_mode'] = $treasury['type'];
                        $treasury['label2'] = $treasury['label'];
                        $treasury['uuid'] = $this->uuid;
                        $treasury['currency'] = $conf->global->MAIN_MONNAIE;
                    }

                    // Let's write this to file
                    $this->addLine($treasury);
                }
            }
        } else {
            $this->setSqlError();
        }
    }

    /**
     * Data mapper for formats not compatible with native.
     *
     * @param array $content Line fields
     */
    private function remapData(&$content)
    {
        if ('coala' === $this->format) {
            $remapped = array(
                $content['date'],
                $content['code'],
                $content['account'],
                $content['id'],
                $content['label'],
                $content['debit'],
                $content['credit'],
                'E'
            );
            $content = $remapped;
            unset($remapped);
        }
    }

    /**
     * Adds a line to the export
     *
     * @param array $content Line fields
     */
    private function addLine(&$content)
    {
        $this->remapData($content);

        switch ($this->type) {
            default:
            case 'dsv':
                $this->addDsvLine($content);
                break;
            case 'excel5':
            case 'excel2007':
                $this->addExcelLine($content);
                break;
        }
    }

    /**
     * Adds a delimiter separated values line to the file
     *
     * @param array $content Line fields
     */
    private function addDsvLine(&$content)
    {
        if ('iso-8859-1' === $this->file_encoding) {
            // Encode to ISO-8859-1
            $content = array_map('utf8_decode', $content);
        }

        // Handle empty enclosure
        if (null == $this->line_field_enclosure) {
            $this->line_field_enclosure = '"';
            $this->strip_enclosures = true;
        }

        $before = ftell($this->fh);
        fputcsv($this->fh, $content, $this->line_field_delimiter, $this->line_field_enclosure);

        // Hack to workaround fputcsv not supporting empty enclosures
        if ($this->strip_enclosures) {
            // Rewind to get previously written data
            fseek($this->fh, $before);
            $line = fgets($this->fh);
            $pattern = array(
                '/^' . $this->line_field_enclosure . '/',
                '/' . $this->line_field_delimiter . $this->line_field_enclosure . '/',
                '/' . $this->line_field_enclosure . $this->line_field_delimiter . '/',
                '/' . $this->line_field_enclosure . '$/'
            );
            $replacement = array(
                '',
                $this->line_field_delimiter,
                $this->line_field_delimiter,
                ''
            );
            $line = preg_replace($pattern, $replacement, $line);
            // Rewind to overwrite previously written data
            fseek($this->fh, $before);
            fwrite($this->fh, $line);
            // Get rid of trailing chars
            ftruncate($this->fh, ftell($this->fh));
        }

        // Hack to modify line breaks disregarding php's running platform
        fseek($this->fh, -1, SEEK_CUR);
        if ('dos' === $this->file_line_ending) {
            // CR+LF
            fwrite($this->fh, "\r\n");
        } elseif ('mac' === $this->file_line_ending) {
            // CR
            fwrite($this->fh, "\r");
        } else {
            // The default is unix LF
            fwrite($this->fh, "\n");
        }

        // Reset content
        unset($content);
    }

    /**
     * Adds a line to the Excel file
     *
     * @param array $content Line fields
     */
    private function addExcelLine($content)
    {
        $first_col = 'A';
        $this->spreadsheet->getActiveSheet()
            ->fromArray(
                $content,
                null,
                $first_col . ($this->spreadsheet->getActiveSheet()->getHighestRow($first_col) + 1)
            );

        unset($content);
    }

    /**
     * Adds an header to the export
     */
    private function addHeader()
    {
        if ($this->file_header !== "yes") {
            // Do nothing
            return;
        }

        switch ($this->type) {
            default:
            case 'dsv':
                $this->addDsvHeader();
                break;
            case 'excel5':
            case 'excel2007':
                $this->addExcelHeader();
                break;
        }
    }

    /**
     * Adds a data separated values header to the file
     */
    private function addDsvHeader()
    {
        $this->addDsvLine(self::getFormatTemplate($this->format));

        // Store header size for future file checking
        $stat = fstat($this->fh);
        $this->header_size = $stat['size'];
    }

    /**
     * Adds header to excel file
     */
    private function addExcelHeader()
    {
        $this->addExcelLine(self::getFormatTemplate($this->format));
    }

    /**
     * Sanitize the bank string from parenthesised dolibarr keywords
     *
     * @param  string $string String to sanitize
     *
     * @return string Sanitized string
     */
    private function sanitizeBankString($string)
    {
        // Strip parenthesised dolibarr keywords
        // GOTCHA: In the unlikely event that a label starts with a parenthesis, it may match the regex and get stripped
        $string = preg_replace('/^\(\S+\s/', '', $string);

        return $this->sanitizeString($string);
    }

    /**
     * Sanitize the string from HTML tags and entities
     * and trim whitespaces including non-breakable ones
     *
     * @global \Conf $conf
     *
     * @param  string $string String to sanitize
     *
     * @return string Sanitized string
     */
    private function sanitizeString($string)
    {
        global $conf;

        // Decode HTML entities keeping quotes and encoding
        $string = html_entity_decode(
            $string,
            ENT_QUOTES,
            $this->convertMysqlEncodingToPhp()
        );
        // Remove HTML tags
        $string = strip_tags($string);
        // Strip delimiter and enclosure characters
        $string = str_replace($this->line_field_delimiter, '', $string);
        $string = str_replace($this->line_field_enclosure, '', $string);
        // Remove line feeds
        $string = str_replace(array("\r\n", "\r", "\n"), ' ', $string);
        // Remove tabulations
        $string = str_replace("\t", ' ', $string);
        // Remove double spaces
        while (strpos($string, '  ')) {
            $string = str_replace('  ', ' ', $string);
        }
        // Trims whitespaces including non breakable space, unless trim()!
        $string = preg_replace(
            '/^[\pZ\pC]+|[\pZ\pC]+$/u',
            '',
            $string
        );
        if (isset($conf->global->ACCOUNTING_EXPORT_RESERVED_CHARACTERS)) {
            $string = $this->removeReservedCharacters($string);
        }
        return $string;
    }

    /**
     * Remove characters declared as reserved from string
     *
     * @global \Conf $conf
     *
     * @param string $string String to compute
     *
     * @return string Computed string
     */
    private function removeReservedCharacters($string)
    {
        global $conf;

        $reserved_chars = str_split($conf->global->ACCOUNTING_EXPORT_RESERVED_CHARACTERS);
        return str_replace($reserved_chars, '', $string);
    }

    /**
     * Builds the treasury label
     *
     * @param Object $obj SQL result
     * @param string $replace_label Replace the object label with the passed string
     *
     * @return string Treasury label
     */
    private function buildTreasuryLabel(&$obj, $replace_label = false)
    {
        return $this->sanitizeBankString(
            $obj->bank_label . ' ' . ($replace_label ? $replace_label : $obj->label) . ' ' . $obj->payroll_label
        );
    }

    /**
     * Set the error message on SQL error.
     */
    private function setSqlError()
    {
        $this->errormsg = "ErrorSQL";
        dol_syslog(get_class($this) . " SQL error: " . $this->db->lasterror, LOG_ERR);
    }

    /**
     * File name without path and extension
     *
     * @return string
     */
    public function getName()
    {
        return basename($this->name, $this->file_extension);
    }

    /**
     * File name without path
     *
     * @return string
     */
    public function getFileName()
    {
        return basename($this->name);
    }

    /**
     * Error message
     *
     * @return string
     */
    public function getError()
    {
        return $this->errormsg;
    }

    /**
     * Error check
     *
     * @return boolean
     */
    public function isError()
    {
        if ($this->errormsg) {
            return true;
        }

        return false;
    }

    /**
     * Remove the file
     */
    public function rmFile()
    {
        if (file_exists($this->name)) {
            unlink($this->name);
        }
    }

    /**
     * Export check
     */
    private function checkExport()
    {
        switch ($this->type) {
            case 'dsv':
                if ($this->header_size >= filesize($this->name)) {
                    $this->errormsg = "EmptyExport";
                }
                break;
                
            case 'excel5':
                $writer = new Xls($this->spreadsheet);
                $writer->save($this->name);
                break;
                
            case 'excel2007':
                $writer = new Xlsx($this->spreadsheet);
                $writer->save($this->name);
                break;
                
            default:
                break;
        }
    }

    /**
     * Populates the vat rates for the specified country code
     *
     * @param int $country_code Dolibarr countries dictionary code
     */
    private function loadVATRates($country_code)
    {
        $sql = 'SELECT DISTINCT t.rowid, t.taux, t.accountancy_code_sell, t.accountancy_code_buy';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'c_tva as t,';
        if (3 == $this->dolibarr_version[0] && 7 <= $this->dolibarr_version[1] || 3 < $this->dolibarr_version[0]) { // DOL_VERSION ≥ 3.7
            $sql .= ' ' . MAIN_DB_PREFIX . 'c_country as p';
        } else {
            $sql .= ' ' . MAIN_DB_PREFIX . 'c_pays as p';
        }
        $sql .= ' WHERE t.fk_pays = p.rowid';
        $sql .= ' AND t.active = 1';
        $sql .= ' AND p.code IN (' . $country_code . ')';
        $sql .= ' ORDER BY t.taux ASC, t.recuperableonly ASC';

        $resql = $this->db->query($sql);
        while (($obj = $this->db->fetch_object($resql))) {
            $this->vat_account_tvatx[(string)floatval($obj->taux)] = array(
                'sell' => $obj->accountancy_code_sell,
                'buy' => $obj->accountancy_code_buy,
                'rowid' => $obj->rowid
            );
            $this->vat_account_tvaid[$obj->rowid] = array(
                'sell' => $obj->accountancy_code_sell,
                'buy' => $obj->accountancy_code_buy,
                'taux' => $obj->taux
            );
        }
    }

    /**
     * Converts MySQL encodings to PHP encodings equivalents
     *
     * @see https://dev.mysql.com/doc/refman/5.5/en/charset-charsets.html
     * @see https://secure.php.net/manual/fr/function.html-entity-decode.php#refsect1-function.html-entity-decode-parameters
     *
     * @return string
     */
    private function convertMysqlEncodingToPhp()
    {
        $db_encoding = $this->db->getDefaultCharacterSetDatabase();
        switch ($db_encoding) {
            // Unsupported
            case 'dec8':
            case 'cp850':
            case 'hp8':
            case 'latin2':
            case 'swe7':
            case 'ascii':
            case 'ujis':
            case 'hebrew':
            case 'tis620':
            case 'euckr':
            case 'koi8u':
            case 'gb2312':
            case 'greek':
            case 'cp1250':
            case 'gbk':
            case 'latin5':
            case 'armscii8':
            case 'ucs2':
            case 'keybcs2':
            case 'macce':
            case 'cp852':
            case 'latin7':
            case 'utf16':
            case 'cp1256':
            case 'cp1257':
            case 'utf32':
            case 'binary':
            case 'geostd8':
            case 'cp932':
                trigger_error('Unsupported database encoding', E_USER_WARNING);
                $php_encoding = $db_encoding;
                break;

            case 'big5':
                $php_encoding = 'BIG5';
                break;

            case 'eucjpms':
                $php_encoding = 'EUC-JP';
                break;

            case 'koi8r':
                $php_encoding = 'KOI8-R';
                break;

            case 'latin1':
                $php_encoding = 'cp1252';
                break;

            case 'macroman':
                $php_encoding = 'MacRoman';
                break;

            case 'sjis':
                $php_encoding = 'Shift_JIS';
                break;

            case 'utf8':
            case 'utf8mb4':
                $php_encoding = 'UTF-8';
                break;

            // Directly supported
            default:
            case 'cp866':
            case 'cp1251':
                $php_encoding = $db_encoding;
        }
        return $php_encoding;
    }
}
