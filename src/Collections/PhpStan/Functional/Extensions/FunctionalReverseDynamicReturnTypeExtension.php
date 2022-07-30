<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayReverseFunctionReturnTypeExtension;

final class FunctionalReverseDynamicReturnTypeExtension extends ArrayReverseFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\reverse' === strtolower($functionReflection->getName());
    }
}
