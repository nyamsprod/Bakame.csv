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
namespace League\Csv;

use InvalidArgumentException;
use IteratorAggregate;
use League\Csv\Config\Controls;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 * Abstract class to set the CSV document properties
 *
 * @package League.csv
 * @since  4.0.0
 */
abstract class AbstractCsv implements IteratorAggregate
{
    use Controls;

    /**
     *  UTF-8 BOM sequence
     */
    const BOM_UTF8 = "\xEF\xBB\xBF";

    /**
     * UTF-16 BE BOM sequence
     */
    const BOM_UTF16_BE = "\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const BOM_UTF16_LE = "\xFF\xFE";

    /**
     * UTF-32 BE BOM sequence
     */
    const BOM_UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-32 LE BOM sequence
     */
    const BOM_UTF32_LE = "\x00\x00\xFF\xFE";

    /**
     * The constructor path
     *
     * can be a SplFileInfo object or the string path to a file
     *
     * @var SplFileObject|string
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * Creates a new instance
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param SplFileObject|string $path      The file path
     * @param string               $open_mode The file open mode flag
     */
    protected function __construct($path, $open_mode = 'r+')
    {
        $this->open_mode = strtolower($open_mode);
        $this->path = $path;
        $this->initStreamFilter($this->path);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->path = null;
    }

    /**
     * Return a new {@link AbstractCsv} from a SplFileObject
     *
     * @param SplFileObject $file
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file)
    {
        $csv = new static($file);
        $controls = $file->getCsvControl();
        $csv->setDelimiter($controls[0]);
        $csv->setEnclosure($controls[1]);
        if (isset($controls[2])) {
            $csv->setEscape($controls[2]);
        }

        return $csv;
    }

    /**
     * Return a new {@link AbstractCsv} from a string
     *
     * The string must be an object that implements the `__toString` method,
     * or a string
     *
     * @param string|object $str the string
     *
     * @return static
     */
    public static function createFromString($str)
    {
        $file = new SplTempFileObject();
        $file->fwrite(static::validateString($str));

        return new static($file);
    }

    /**
     * Return a new {@link AbstractCsv} from a string
     *
     * @param mixed  $path      file path
     * @param string $open_mode the file open mode flag
     *
     * @throws InvalidArgumentException If $path is a SplTempFileObject object
     *
     * @return static
     */
    public static function createFromPath($path, $open_mode = 'r+')
    {
        if ($path instanceof SplTempFileObject) {
            throw new InvalidArgumentException('an `SplTempFileObject` object does not contain a valid path');
        }

        if ($path instanceof SplFileInfo) {
            $path = $path->getPath().'/'.$path->getBasename();
        }

        return new static(static::validateString($path), $open_mode);
    }

    /**
     * Return a new {@link AbstractCsv} instance from another {@link AbstractCsv} object
     *
     * @param string $class     the class to be instantiated
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    protected function newInstance($class, $open_mode)
    {
        $csv = new $class($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->enclosure = $this->enclosure;
        $csv->escape = $this->escape;
        $csv->input_encoding = $this->input_encoding;
        $csv->input_bom = $this->input_bom;
        $csv->output_bom = $this->output_bom;
        $csv->newline = $this->newline;
        $csv->header = $this->header;

        return $csv;
    }

    /**
     * Return a new {@link Writer} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Writer
     */
    public function newWriter($open_mode = 'r+')
    {
        return $this->newInstance(Writer::class, $open_mode);
    }

    /**
     * Return a new {@link Reader} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Reader
     */
    public function newReader($open_mode = 'r+')
    {
        return $this->newInstance(Reader::class, $open_mode);
    }

    /**
     * Returns the inner SplFileObject
     *
     * @return SplFileObject
     */
    public function getIterator()
    {
        $iterator = $this->path;
        if (!$iterator instanceof SplFileObject) {
            $iterator = new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
        }
        $iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        return $iterator;
    }
}
