<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use CallbackFilterIterator;
use Countable;
use DOMDocument;
use DOMElement;
use Generator;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Config\ValidatorTrait;
use LimitIterator;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class RecordSet implements JsonSerializable, IteratorAggregate, Countable
{
    use ValidatorTrait;

    /**
     * The CSV iterator result
     *
     * @var Iterator
     */
    protected $iterator;

    /**
     * The CSV header
     *
     * @var array
     */
    protected $header = [];

    /**
     * Charset Encoding for the CSV
     *
     * This information is used when converting the CSV to XML or JSON
     *
     * @var string
     */
    protected $conversion_input_encoding = 'UTF-8';

    /**
     * Tell whether to export the header value
     * on XML/HTML conversion
     *
     * @var bool
     */
    protected $use_header_on_xml_conversion = true;

    /**
     * New instance
     *
     * @param Iterator $iterator a CSV iterator created from Statement
     * @param array    $header   the CSV header
     */
    public function __construct(Iterator $iterator, array $header)
    {
        $this->iterator = $iterator;
        $this->header = $header;
    }

    /**
     * @inheritdoc
     */
    public function __destruct()
    {
        $this->iterator = null;
    }

    /**
     * Returns the column header associate with the RecordSet
     *
     * @return string[]
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setConversionInputEncoding(string $str): self
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $str = trim($str);
        if ('' === $str) {
            throw new Exception('you should use a valid charset');
        }
        $this->conversion_input_encoding = strtoupper($str);

        return $this;
    }

    /**
     * Tell whether to add the header content in the XML/HTML
     * conversion output
     *
     * @param bool $status
     *
     * @return self
     */
    public function useHeaderOnXmlConversion(bool $status)
    {
        $this->use_header_on_xml_conversion = $status;

        return $this;
    }

    /**
     * Returns a HTML table representation of the CSV Table
     *
     * @param string $class_attr optional classname
     *
     * @return string
     */
    public function toHTML(string $class_attr = 'table-csv-data'): string
    {
        $doc = $this->toXML('table', 'tr', 'td');
        $doc->documentElement->setAttribute('class', $class_attr);

        return $doc->saveHTML($doc->documentElement);
    }

    /**
     * Transforms a CSV into a XML
     *
     * @param string $root_name XML root node name
     * @param string $row_name  XML row node name
     * @param string $cell_name XML cell node name
     *
     * @return DOMDocument
     */
    public function toXML(string $root_name = 'csv', string $row_name = 'row', string $cell_name = 'cell'): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement($root_name);
        if (!empty($this->header) && $this->use_header_on_xml_conversion) {
            $root->appendChild($this->toDOMNode($doc, $this->header, $row_name, $cell_name));
        }

        foreach ($this->convertToUtf8($this->iterator) as $row) {
            $root->appendChild($this->toDOMNode($doc, $row, $row_name, $cell_name));
        }
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * convert a Record into a DOMNode
     *
     * @param DOMDocument $doc       The DOMDocument
     * @param array       $row       The CSV record
     * @param string      $row_name  XML row node name
     * @param string      $cell_name XML cell node name
     *
     * @return DOMElement
     */
    protected function toDOMNode(DOMDocument $doc, array $row, string $row_name, string $cell_name): DOMElement
    {
        $rowElement = $doc->createElement($row_name);
        foreach ($row as $value) {
            $content = $doc->createTextNode($value);
            $cell = $doc->createElement($cell_name);
            $cell->appendChild($content);
            $rowElement->appendChild($cell);
        }

        return $rowElement;
    }

    /**
     * Convert Csv file into UTF-8
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function convertToUtf8(Iterator $iterator): Iterator
    {
        if (stripos($this->conversion_input_encoding, 'UTF-8') !== false) {
            return $iterator;
        }

        $convert_cell = function ($value) {
            return mb_convert_encoding((string) $value, 'UTF-8', $this->conversion_input_encoding);
        };

        $convert_row = function (array $row) use ($convert_cell) {
            $res = [];
            foreach ($row as $key => $value) {
                $res[$convert_cell($key)] = $convert_cell($value);
            }

            return $res;
        };

        return new MapIterator($iterator, $convert_row);
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        return $this->iterator;
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return iterator_count($this->iterator);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->iterator), false);
    }

    /**
     * Returns a sequential array of all CSV lines
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return iterator_to_array($this->iterator, false);
    }

    /**
     * Returns a single row from the CSV
     *
     * By default if no offset is provided the first row of the CSV is selected
     *
     * @param int $offset the CSV row offset
     *
     * @return array
     */
    public function fetchOne(int $offset = 0): array
    {
        $offset = $this->filterInteger($offset, 0, 'the submitted offset is invalid');
        $it = new LimitIterator($this->iterator, $offset, 1);
        $it->rewind();

        return (array) $it->current();
    }

    /**
     * Returns the next value from a single CSV column
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param string|int $column CSV column index
     *
     * @return Iterator
     */
    public function fetchColumn($column = 0): Iterator
    {
        $column = $this->getFieldIndex($column, 'the column index value is invalid');
        $filter = function (array $row) use ($column) {
            return isset($row[$column]);
        };

        $select = function ($row) use ($column) {
            return $row[$column];
        };

        return new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
    }

    /**
     * Filter a field name against the CSV header if any
     *
     * @param string|int $field         the field name or the field index
     * @param string     $error_message the associated error message
     *
     * @throws Exception if the field is invalid
     *
     * @return string|int
     */
    protected function getFieldIndex($field, $error_message)
    {
        if (false !== array_search($field, $this->header, true) || is_string($field)) {
            return $field;
        }

        $index = $this->filterInteger($field, 0, $error_message);
        if (empty($this->header)) {
            return $index;
        }

        if (false !== ($index = array_search($index, array_flip($this->header), true))) {
            return $index;
        }

        throw new Exception($error_message);
    }

    /**
     * Fetches the next key-value pairs from a result set (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * @param string|int $offset_index The column index to serve as offset
     * @param string|int $value_index  The column index to serve as value
     *
     * @return Generator
     */
    public function fetchPairs($offset_index = 0, $value_index = 1): Generator
    {
        $offset = $this->getFieldIndex($offset_index, 'the offset index value is invalid');
        $value = $this->getFieldIndex($value_index, 'the value index value is invalid');
        $filter = function ($row) use ($offset) {
            return isset($row[$offset]);
        };

        $select = function ($row) use ($offset, $value) {
            return [$row[$offset], $row[$value] ?? null];
        };

        $it = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
        foreach ($it as $row) {
            yield $row[0] => $row[1];
        }
    }
}