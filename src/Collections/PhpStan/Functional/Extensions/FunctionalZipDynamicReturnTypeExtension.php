<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayMapFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class FunctionalZipDynamicReturnTypeExtension extends ArrayMapFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\zip' === strtolower($functionReflection->getName());
    }

    /**
     * Our implementation is array_map with null as the first argument
     */
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        $args = $functionCall->getArgs();
        array_unshift($args, new Arg(new ConstFetch(new Name('null'))));

        $functionCallWithNull = new FuncCall($functionCall->name, $args);

        return parent::getTypeFromFunctionCall($functionReflection, $functionCallWithNull, $scope);
    }
}
