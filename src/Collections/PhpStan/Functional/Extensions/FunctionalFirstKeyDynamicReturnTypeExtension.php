<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeyFirstDynamicReturnTypeExtension;

final class FunctionalFirstKeyDynamicReturnTypeExtension extends ArrayKeyFirstDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\firstkey' === strtolower($functionReflection->getName());
    }
}
