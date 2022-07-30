<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\ArrayKeyExistsFunctionTypeSpecifyingExtension;
use function Gammadia\Collections\Functional\reverse;

final class FunctionalKeyExistsFunctionTypeSpecifyingExtension extends ArrayKeyExistsFunctionTypeSpecifyingExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection, FuncCall $node, TypeSpecifierContext $context): bool
    {
        return 'gammadia\collections\functional\keyexists' === strtolower($functionReflection->getName()) && !$context->null();
    }

    public function specifyTypes(FunctionReflection $functionReflection, FuncCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        $reversedFunctionCall = new FuncCall($node->name, reverse($node->getArgs()));

        return parent::specifyTypes($functionReflection, $reversedFunctionCall, $scope, $context);
    }
}
