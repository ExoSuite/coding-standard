<?php

namespace Xyz;

use Foo\Bar;
use Foo\Bar\Baz;
use const PHP_EOL;
use function strlen;

use function Foo\f;
use function Foo\Bar\g;

use const Foo\C;
use const Foo\Bar\D;

function () use ($x) {
};

class Abc
{
    use SomeTrait;
}

new class {
    use SomeTrait;
};
