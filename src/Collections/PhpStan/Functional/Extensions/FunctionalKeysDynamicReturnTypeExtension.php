<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeysFunctionDynamicReturnTypeExtension;

final class FunctionalKeysDynamicReturnTypeExtension extends ArrayKeysFunctionDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\keys' === strtolower($functionReflection->getName());
    }
}
