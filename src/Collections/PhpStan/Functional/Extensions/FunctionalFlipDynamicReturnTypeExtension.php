<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayFlipFunctionReturnTypeExtension;

final class FunctionalFlipDynamicReturnTypeExtension extends ArrayFlipFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\flip' === strtolower($functionReflection->getName());
    }
}
