<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\DataTable\Renderer;

use Exception;
use Piwik\DataTable;
use Piwik\DataTable\DataTableInterface;
use Piwik\DataTable\Renderer;

/**
 * Simple HTML output
 * Does not work with recursive DataTable (i.e., when a row can be associated with a subDataTable).
 *
 */
class Html extends Renderer
{
    protected $tableId;
    protected $allColumns;
    protected $tableStructure;
    protected $i;

    /**
     * Sets the table id
     *
     * @param string $id
     */
    public function setTableId(string $id): void
    {
        $this->tableId = str_replace('.', '_', $id);
    }

    /**
     * Computes the dataTable output and returns the string/binary
     *
     * @return string
     */
    public function render(): string
    {
        $this->tableStructure = array();
        $this->allColumns = array();
        $this->i = 0;

        return $this->renderTable($this->table);
    }

    /**
     * Computes the output for the given data table
     *
     * @param DataTableInterface|array $table
     * @return string
     */
    protected function renderTable($table): string
    {
        if (is_array($table)) {
            // convert array to DataTable
            $table = DataTable::makeFromSimpleArray($table);
        }

        if ($table instanceof DataTable\Map) {
            foreach ($table->getDataTables() as $date => $subtable) {
                if ($subtable->getRowsCount()) {
                    $this->buildTableStructure($subtable, '_' . $table->getKeyName(), $date);
                }
            }
        } else {
            // Simple

            if ($table->getRowsCount()) {
                $this->buildTableStructure($table);
            }
        }

        return $this->renderDataTable();
    }

    /**
     * Adds the given data table to the table structure array
     *
     * @param DataTable|DataTable\Map $table
     * @param null|string $columnToAdd
     * @param null|mixed $valueToAdd
     * @throws Exception
     */
    protected function buildTableStructure($table, ?string $columnToAdd = null, $valueToAdd = null): void
    {
        $i = $this->i;
        $someMetadata = false;
        $someIdSubTable = false;

        /*
         * table = array
         * ROW1 = col1 | col2 | col3 | metadata | idSubTable
         * ROW2 = col1 | col2 (no value but appears) | col3 | metadata | idSubTable
         */
        if (!($table instanceof DataTable)) {
            throw new Exception("HTML Renderer does not work with this combination of parameters");
        }
        foreach ($table->getRows() as $row) {
            if (isset($columnToAdd) && isset($valueToAdd)) {
                $this->allColumns[$columnToAdd] = true;
                $this->tableStructure[$i][$columnToAdd] = $valueToAdd;
            }

            foreach ($row->getColumns() as $column => $value) {
                $this->allColumns[$column] = true;
                $this->tableStructure[$i][$column] = $value;
            }

            if (!$this->hideMetadata) {
                $metadata = [];
                foreach ($row->getMetadata() as $name => $value) {
                    if (is_string($value)) {
                        $value = "'$value'";
                    } elseif (is_array($value)) {
                        $value = var_export($value, true);
                    } elseif ($value instanceof DataTable\DataTableInterface) {
                        $value = $this->renderTable($value);
                    }
                    $metadata[] = "'$name' => $value";
                }

                if (count($metadata) != 0) {
                    $someMetadata = true;
                    $metadata = implode("<br />", $metadata);
                    $this->tableStructure[$i]['_metadata'] = $metadata;
                }

                if (!$this->hideIdSubDatatable) {
                    $idSubtable = $row->getIdSubDataTable();
                    if (!is_null($idSubtable)) {
                        $someIdSubTable = true;
                        $this->tableStructure[$i]['_idSubtable'] = $idSubtable;
                    }
                }
            }

            $i++;
        }
        $this->i = $i;

        if (!$this->hideMetadata) {
            $this->allColumns['_metadata'] = $someMetadata;
            if (!$this->hideIdSubDatatable) {
                $this->allColumns['_idSubtable'] = $someIdSubTable;
            }
        }
    }

    /**
     * Computes the output for the table structure array
     *
     * @return string
     */
    protected function renderDataTable(): string
    {
        $html = "<table " . ($this->tableId ? "id=\"{$this->tableId}\" " : "") . "border=\"1\">\n<thead>\n\t<tr>\n";

        foreach ($this->allColumns as $name => $toDisplay) {
            if ($toDisplay !== false) {
                if ($name === 0) {
                    $name = 'value';
                }
                if ($this->translateColumnNames) {
                    $name = $this->translateColumnName($name);
                }
                $html .= "\t\t<th>$name</th>\n";
            }
        }

        $html .= "\t</tr>\n</thead>\n<tbody>\n";

        foreach ($this->tableStructure as $row) {
            $html .= "\t<tr>\n";
            foreach ($this->allColumns as $name => $toDisplay) {
                if ($toDisplay !== false) {
                    $value = "-";
                    if (isset($row[$name])) {
                        if (is_array($row[$name])) {
                            $value = "<pre>" . self::formatValueXml(var_export($row[$name], true)) . "</pre>";
                        } else {
                            $value = self::formatValueXml($row[$name]);
                        }
                    }

                    $html .= "\t\t<td>$value</td>\n";
                }
            }
            $html .= "\t</tr>\n";
        }

        $html .= "</tbody>\n</table>\n";

        return $html;
    }
}
