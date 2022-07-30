<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayReduceFunctionReturnTypeExtension;

final class FunctionalReduceDynamicReturnTypeExtension extends ArrayReduceFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\reduce' === strtolower($functionReflection->getName());
    }
}
