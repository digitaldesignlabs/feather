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

    public function testNakedFilter()
    {
        $result = Collection::filter(["foo", null, false, "bar", 0, NO, "baz"]);
        $this->assertEquals($result, ["foo", "bar", "baz"]);
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

    public function testFirst()
    {
        $array = ["foo" => "bar", "baz" => "baf", "quux" => "corge"];

        next($array);
        $ptr = key($array);

        $first = Collection::first($array);
        $this->assertEquals($first, "bar");
        $this->assertEquals($ptr, key($array), "Collection::first() moved array pointer");
    }

    public function testEmptyFirst()
    {
        $first = Collection::first(array());
        $this->assertEquals($first, null);
    }

    public function testFirstKey()
    {
        $array = ["foo" => "bar", "baz" => "baf", "quux" => "corge"];

        next($array);
        $ptr = key($array);

        $first = Collection::firstKey($array);
        $this->assertEquals($first, "foo");
        $this->assertEquals($ptr, key($array), "Collection::firstKey() moved array pointer");
    }

    public function testEmptyFirstKey()
    {
        $first = Collection::firstKey(array());
        $this->assertEquals($first, null);
    }

    public function testLast()
    {
        $array = ["foo" => "bar", "baz" => "baf", "quux" => "corge"];

        next($array);
        $ptr = key($array);

        $last = Collection::last($array);
        $this->assertEquals($last, "corge");
        $this->assertEquals($ptr, key($array), "Collection::last() moved array pointer");
    }

    public function testEmptyLast()
    {
        $last = Collection::last(array());
        $this->assertEquals($last, null);
    }

    public function testLastKey()
    {
        $array = ["foo" => "bar", "baz" => "baf", "quux" => "corge"];

        next($array);
        $ptr = key($array);

        $last = Collection::lastKey($array);
        $this->assertEquals($last, "quux");
        $this->assertEquals($ptr, key($array), "Collection::lastKey() moved array pointer");
    }

    public function testEmptyLastKey()
    {
        $last = Collection::lastKey(array());
        $this->assertEquals($last, null);
    }

    public function testUniqueSimple()
    {
        $duplicates = [1, 1, 3, 5, 5, 6, 7, 8, 8, 8, 3, 1, 6, 7];
        $unique = Collection::unique($duplicates);
        $this->assertEquals($unique, [1, 3, 5, 6, 7, 8]);
    }

    public function testUniqueNested()
    {
        $duplicates = [["foo" => YES], ["foo" => NO], ["foo" => YES], ["bar" => NO]];
        $unique = Collection::unique($duplicates);
        $this->assertEquals($unique, [["foo" => YES], ["foo" => NO], ["bar" => NO]]);
    }

    public function testMapFilter()
    {
        $array = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $mapped = Collection::mapFilter($array, function ($element) {
            if ($element % 2 === 0) {
                return $element * 2;
            }
            return NO;
        });
        $this->assertEquals($mapped, [4, 8, 12, 16]);
    }

    public function testSeek()
    {
        $array = array(
            "group" => ["label" => "A"]
        );

        $value = Collection::seek($array, ["group", "label"]);
        $this->assertEquals($value, "A");
    }

    public function testGroupBy()
    {
        $array = ["foo", "bar", "baz", "quux"];
        $grouped = Collection::groupBy($array, function ($element) {
            return strlen($element);
        });

        $this->assertEquals($grouped, array(
            "3" => ["foo", "bar", "baz"],
            "4" => ["quux"],
        ));
    }

    public function testGroup()
    {
        $array = array(
            ["name" => "foo", "group" => "A"],
            ["name" => "bar", "group" => "A"],
            ["name" => "baz", "group" => "B"],
            ["name" => "baf", "group" => "B"],
        );

        $grouped = Collection::groupBy($array, "group");

        $this->assertEquals($grouped, array(
            "A" => array(
                ["name" => "foo", "group" => "A"],
                ["name" => "bar", "group" => "A"],
            ),
            "B" => array(
                ["name" => "baz", "group" => "B"],
                ["name" => "baf", "group" => "B"],
            )
        ));
    }
}
