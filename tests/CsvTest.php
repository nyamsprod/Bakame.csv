<?php

namespace LeagueTest\Csv;

use DOMDocument;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Reader;
use PHPUnit_Framework_TestCase;
use SplTempFileObject;

/**
 * @group csv
 */
class CsvTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    public function testInterface()
    {
        $this->assertInstanceOf(IteratorAggregate::class, $this->csv);
        $this->assertInstanceOf(JsonSerializable::class, $this->csv);
    }

    public function testToHTML()
    {
        $this->assertContains('<table', $this->csv->toHTML());
    }

    public function testToXML()
    {
        $this->assertInstanceOf(DOMDocument::class, $this->csv->toXML());
    }

    public function testJsonSerialize()
    {
        $this->assertSame($this->expected, json_decode(json_encode($this->csv), true));
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputSize()
    {
        $this->assertSame(60, $this->csv->output(__DIR__.'/data/test.csv'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputHeaders()
    {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped();
        }
        $this->csv->output('test.csv');
        $headers = \xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        $this->assertContains('content-type: text/csv', strtolower($headers[0]));
        $this->assertSame($headers[1], 'Content-Transfer-Encoding: binary');
        $this->assertSame($headers[2], 'Content-Disposition: attachment; filename="test.csv"');
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, $this->csv->__toString());
    }
}
