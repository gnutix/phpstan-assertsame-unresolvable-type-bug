<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayValuesFunctionDynamicReturnTypeExtension;

final class FunctionalValuesDynamicReturnTypeExtension extends ArrayValuesFunctionDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\values' === strtolower($functionReflection->getName());
    }
}
