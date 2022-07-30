<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFilterFunctionReturnTypeReturnTypeExtension;

final class FunctionalFilterDynamicReturnTypeExtension extends ArrayFilterFunctionReturnTypeReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\filter' === strtolower($functionReflection->getName());
    }
}
