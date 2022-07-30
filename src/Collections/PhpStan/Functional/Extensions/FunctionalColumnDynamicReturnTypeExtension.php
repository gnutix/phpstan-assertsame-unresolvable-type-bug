<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayColumnFunctionReturnTypeExtension;

final class FunctionalColumnDynamicReturnTypeExtension extends ArrayColumnFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\column' === strtolower($functionReflection->getName());
    }
}
