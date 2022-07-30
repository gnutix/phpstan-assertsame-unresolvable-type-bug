<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Php\ArrayCombineFunctionReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use function Gammadia\Collections\Functional\filter;

final class FunctionalCombineDynamicReturnTypeExtension extends ArrayCombineFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return 'gammadia\collections\functional\combine' === strtolower($functionReflection->getName());
    }

    /**
     * Our implementation never returns false, as it throws an exception instead
     */
    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
    {
        $type = parent::getTypeFromFunctionCall($functionReflection, $functionCall, $scope);
        $false = new ConstantBooleanType(false);

        if ($type->equals($false)) {
            return new NeverType();
        }

        if ($type instanceof UnionType) {
            return new UnionType(filter($type->getTypes(), static fn (Type $type): bool => !$type->equals($false)));
        }

        return $type;
    }
}
