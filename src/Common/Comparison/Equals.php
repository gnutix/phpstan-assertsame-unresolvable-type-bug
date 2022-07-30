<?php

declare(strict_types=1);

namespace Gammadia\Common\Comparison;

use function is_object;

function equals(mixed $a, mixed $b): bool
{
    if (is_object($a) && is_object($b) && $a::class === $b::class) {
        return match (true) {
            method_exists($a, 'equals') => $a->equals($b),
            method_exists($a, 'isEqualTo') => $a->isEqualTo($b),
            method_exists($a, 'equalTo') => $a->equalTo($b),
            method_exists($a, 'equalsTo') => $a->equalsTo($b),
            default => $a === $b,
        };
    }

    return $a === $b;
}
