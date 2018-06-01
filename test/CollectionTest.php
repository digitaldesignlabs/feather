<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use DDL\Feather\Collection;

/**
 * CollectionTest
 * Tests the Collection object
 *
 * @covers Collection
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
final class CollectionTest extends TestCase
{
    public function testMap()
    {
        $result = Collection::map([1, 2, 3], function ($n) {
            return $n * 2;
        });

        $this->assertEquals($result, [2, 4, 6]);
    }

    public function testPick()
    {
        $array = ["foo" => "bar", "baz" => "baf"];

        // String key
        $result = Collection::pick($array, "foo");
        $this->assertEquals($result, ["foo" => "bar"]);

        // Array key
        $result = Collection::pick($array, ["foo"]);
        $this->assertEquals($result, ["foo" => "bar"]);

        // Key which doesn't appear should return empty array
        $result = Collection::pick($array, ["quuz"]);
        $this->assertEquals($result, []);
    }

    public function testOmit()
    {
        $array = ["foo" => "bar", "baz" => "baf"];

        $result = Collection::omit($array, "foo");
        $this->assertEquals($result, ["baz" => "baf"]);

        $result = Collection::omit($array, ["foo"]);
        $this->assertEquals($result, ["baz" => "baf"]);

        $result = Collection::omit($array, ["quuz"]);
        $this->assertEquals($result, $array);
    }

    public function testSlice()
    {
        $result = Collection::slice([1, 2, 3, 4], 2, 2);
        $this->assertEquals($result, [3, 4]);

        $result = Collection::slice([1, 2, 3, 4], 2, 2, YES);
        $this->assertEquals($result, [2 => 3, 3 => 4]);
    }

    public function testReduce()
    {
        $result = Collection::reduce([1, 2, 3, 4, 5], function ($carry, $item) {
            return $carry * $item;
        });
        $this->assertEquals($result, 120);

        $result = Collection::reduce([1, 2, 3, 4, 5], function ($carry, $item) {
            return $carry * $item;
        }, 5);
        $this->assertEquals($result, 600);
    }

    public function testFilter()
    {
        $result = Collection::filter([1, 2, 3, 4, 5], function ($item) {
            return $item >= 3;
        });
        $this->assertEquals($result, [3, 4, 5]);
    }

    public function testSimpleFilter()
    {
        $result = Collection::filter([["foo" => YES], ["foo" => NO], ["foo" => YES]], "foo");
        $this->assertEquals($result, [["foo" => YES], ["foo" => YES]]);
    }

    public function testHas()
    {
        $result = Collection::has([1, 2, 3], 3);
        $this->assertEquals($result, YES);

        $result = Collection::has([1, 2, 3], 4);
        $this->assertEquals($result, NO);
    }

    public function testHasKey()
    {
        $result = Collection::hasKey(["foo" => "bar"], "foo");
        $this->assertEquals($result, YES);

        $result = Collection::hasKey(["foo" => "bar"], "bar");
        $this->assertEquals($result, NO);
    }

    public function testFlatten()
    {
        $array = [[1, 2, 3], [4], [5, 6, 7]];
        $result = Collection::flatten($array);
        $this->assertEquals($result, [1, 2, 3, 4, 5, 6, 7]);

        $array = [[1, 2, 3], [4], [5, [6, 7]]];
        $result = Collection::flatten($array, YES);
        $this->assertEquals($result, [1, 2, 3, 4, 5, 6, 7]);
    }

    public function testFind()
    {
        $array = [["foo", "bar"], ["foo", "baz"], ["baz", "baf"]];

        $result = Collection::find($array, function ($item) {
            return $item[0] === "foo";
        });
        $this->assertEquals($result, ["foo", "bar"]);

        $result = Collection::find($array, function ($item) {
            return $item[1] === "baz";
        });
        $this->assertEquals($result, ["foo", "baz"]);

        $result = Collection::find($array, function ($item) {
            return $item[0] === "quuz";
        });
        $this->assertEquals($result, null);
    }

    public function testEvery()
    {
        $array = [2, 4, 6, 8];

        $result = Collection::every($array, function ($item) {
            return $item % 2 === 0;
        });
        $this->assertEquals($result, YES);

        $result = Collection::every($array, function ($item) {
            return $item === 2;
        });
        $this->assertEquals($result, NO);
    }

    public function testSome()
    {
        $array = [2, 4, 6, 8];

        $result = Collection::some($array, function ($item) {
            return $item % 2 === 0;
        });
        $this->assertEquals($result, YES);

        $result = Collection::some($array, function ($item) {
            return $item === 2;
        });
        $this->assertEquals($result, YES);

        $result = Collection::some($array, function ($item) {
            return $item % 2 === 1;
        });
        $this->assertEquals($result, NO);
    }

    public function testSimpleSort()
    {
        $array = array(
            ["date" => "2018-01-05"],
            ["date" => "2018-01-03"],
            ["date" => "2018-01-04"],
        );

        $result = Collection::sort($array, "date");

        $this->assertEquals($result, array(
            ["date" => "2018-01-03"],
            ["date" => "2018-01-04"],
            ["date" => "2018-01-05"],
        ));
    }

    public function testMultiSort()
    {
        $array = array(
            ["date" => "2018-01-04", "time" => "13:00"],
            ["date" => "2018-01-03", "time" => "13:00"],
            ["date" => "2018-01-04", "time" => "11:00"],
        );

        $result = Collection::sort($array, ["date", "time"]);

        $this->assertEquals($result, array(
            ["date" => "2018-01-03", "time" => "13:00"],
            ["date" => "2018-01-04", "time" => "11:00"],
            ["date" => "2018-01-04", "time" => "13:00"],
        ));
    }

    public function testCustomSort()
    {
        $array = array(1, 2, 3, 4, 5, 6);

        $result = Collection::sort($array, function ($left, $right) {
            $leftIsEven = $left % 2 === 0;
            $rightIsEven = $right % 2 === 0;
            if ($leftIsEven === $rightIsEven) {
                return $left - $right;
            }
            if ($leftIsEven) {
                return -1;
            }
            return 1;
        });

        $this->assertEquals($result, [2, 4, 6, 1, 3, 5]);
    }
}
