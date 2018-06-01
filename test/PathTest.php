<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use DDL\Feather\Path;

/**
 * CollectionTest
 * Tests the Collection object
 *
 * @covers Collection
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
final class PathTest extends TestCase
{
    public function testPath()
    {
        $this->assertEquals(Path::resolve("/usr", "foo.txt"), "/usr/foo.txt");
    }

    public function testDotPath()
    {
        $this->assertEquals(Path::resolve("/usr", "./foo.txt"), "/usr/foo.txt");
    }

    public function testDotDotPath()
    {
        $this->assertEquals(Path::resolve("/usr/bin", "../foo.txt"), "/usr/foo.txt");
    }
}
