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

/**
 *  Thrown when a data is not validated prior to insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class InvalidRowException extends Exception
{
    /**
     * Validator which did not validated the data
     * @var string
     */
    private $name;

    /**
     * Validator Data which caused the error
     * @var array
     */
    private $data;

    /**
     * New Instance
     *
     * @param string $name    validator name
     * @param array  $data    invalid  data
     * @param string $message exception message
     */
    public function __construct(string $name, array $data = [], $message = '')
    {
        parent::__construct($message);
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * return the validator name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * return the invalid data submitted
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
