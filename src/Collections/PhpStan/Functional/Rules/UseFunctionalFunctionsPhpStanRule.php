<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Functional\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Webmozart\Assert\Assert;
use const Gammadia\Collections\Functional\FUNCTIONS_REPLACEMENTS_MAP;

/**
 * @implements Rule<FuncCall>
 */
final class UseFunctionalFunctionsPhpStanRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return string[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        /** @var FuncCall $node */
        Assert::isInstanceOf($node, FuncCall::class);

        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        $functionsToReplace = FUNCTIONS_REPLACEMENTS_MAP;

        if (isset($functionsToReplace[$functionName])) {
            return [
                sprintf(
                    'Please <info>use function %s;</info> instead of PHP\'s %s().',
                    $functionsToReplace[$functionName],
                    $functionName,
                ),
            ];
        }

        return [];
    }
}
