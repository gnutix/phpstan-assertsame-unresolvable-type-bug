<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Extensions;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Php\InArrayFunctionTypeSpecifyingExtension;
use function Gammadia\Collections\Functional\concat;
use function Gammadia\Collections\Functional\reverse;

final class FunctionalContainsFunctionTypeSpecifyingExtension extends InArrayFunctionTypeSpecifyingExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection, FuncCall $node, TypeSpecifierContext $context): bool
    {
        return 'gammadia\collections\functional\contains' === strtolower($functionReflection->getName()) && !$context->null();
    }

    /**
     * Our implementation is in_array() with reversed arguments and strict: true
     */
    public function specifyTypes(FunctionReflection $functionReflection, FuncCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        $adaptedNode = new FuncCall($node->name, concat(reverse($node->getArgs()), [new Arg(new ConstFetch(new Name('true')))]));

        return parent::specifyTypes($functionReflection, $adaptedNode, $scope, $context);
    }
}
