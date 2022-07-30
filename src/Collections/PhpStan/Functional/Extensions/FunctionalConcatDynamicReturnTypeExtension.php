<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayMergeFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Type;

final class FunctionalConcatDynamicReturnTypeExtension extends ArrayMergeFunctionDynamicReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\concat' === strtolower($functionReflection->getName());
    }

    /**
     * Our implementation is array_map with an empty array as the first argument
     */
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        $args = $functionCall->getArgs();
        array_unshift($args, new Arg(new Array_()));

        $functionCallWithEmptyArray = new FuncCall($functionCall->name, $args);

        return parent::getTypeFromFunctionCall($functionReflection, $functionCallWithEmptyArray, $scope);
    }
}
