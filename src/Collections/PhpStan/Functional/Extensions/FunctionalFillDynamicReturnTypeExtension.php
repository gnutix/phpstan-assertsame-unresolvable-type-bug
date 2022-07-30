<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFillFunctionReturnTypeExtension;

final class FunctionalFillDynamicReturnTypeExtension extends ArrayFillFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\fill' === strtolower($functionReflection->getName());
    }
}
