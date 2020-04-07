<?php

namespace Neat\Router\Test;

use Neat\Router\Splitter;
use PHPUnit\Framework\TestCase;

class SplitterTest extends TestCase
{
    public function provideSplitData()
    {
        return [
            [' ', [], ''],
            [' ', [], ' '],
            [' ', ['foo'], 'foo'],
            [' ', ['foo', 'bar'], 'foo bar'],
            ['/', [], ''],
            ['/', [], '/'],
            ['/', ['foo'], 'foo'],
            ['/', ['foo'], '/foo'],
            ['/', ['foo'], '/foo/'],
            ['/', ['foo'], 'foo/'],
            ['/', ['foo', 'bar'], 'foo/bar'],
            ['/', ['foo', 'bar'], '/foo/bar'],
            ['/', ['foo', 'bar'], '/foo//bar'],
            ['/', ['foo', 'bar'], '/foo/bar/'],
        ];
    }

    /**
     * @dataProvider provideSplitData
     * @param string $delimiter
     * @param array  $expected
     * @param string $path
     */
    public function testSplit(string $delimiter, array $expected, string $path)
    {
        $splitter = new Splitter($delimiter);

        $this->assertSame($expected, $splitter->split($path));
    }
}
