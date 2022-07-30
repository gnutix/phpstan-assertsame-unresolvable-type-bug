<?php

declare(strict_types=1);

namespace Gammadia\Collections\PhpStan\Timeline\Extensions;

use Brick\DateTime\LocalDate;
use Gammadia\Collections\Timeline\Timeline;
use Gammadia\DateTimeExtra\LocalDateInterval;
use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StrictMixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;

final class TimelineImportDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Timeline::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'import' === $methodReflection->getName();
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if ([] === $args || 2 < count($args)) {
            throw new ShouldNotHappenException();
        }

        $valuesType = $scope->getType($args[0]->value);
        if (!$valuesType->isIterable()->yes()) {
            return new ErrorType();
        }

        $timeables = TypeCombinator::union(
            new ObjectType(LocalDate::class),
            new ObjectType(LocalDateInterval::class),
            new ObjectType(LocalDateTimeInterval::class),
        );

        $valuesTypes = $valuesType->getIterableValueType();
        $callableType = isset($args[1]) ? $scope->getType($args[1]->value) : new NullType();
        $timelineType = static fn (Type $type): GenericObjectType => new GenericObjectType(Timeline::class, [$type]);

        if ((new NullType())->isSuperTypeOf($callableType)->yes()) {
            return $timeables->isSuperTypeOf($valuesTypes)->yes() ? $timelineType($valuesTypes) : new ErrorType();
        }
        if (!$callableType->isCallable()->yes()) {
            return new ErrorType();
        }

        $callableKeyType = new NeverType();
        $callableValueType = new NeverType();
        foreach ($callableType->getCallableParametersAcceptors($scope) as $parametersAcceptor) {
            $callableReturnKeyType = $callableReturnValueType = $parametersAcceptor->getReturnType();
            if ($callableReturnValueType->isIterable()->yes()) {
                $callableReturnKeyType = $callableReturnKeyType->getIterableKeyType();
                $callableReturnValueType = $callableReturnValueType->getIterableValueType();
            }
            $callableKeyType = TypeCombinator::union($callableKeyType, $callableReturnKeyType);
            $callableValueType = TypeCombinator::union($callableValueType, $callableReturnValueType);
        }

        /** @todo Not supported yet (short function arrows) */
        if ($callableValueType instanceof MixedType) {
            return $timelineType(new StrictMixedType());
        }

        if ($timeables->isSuperTypeOf($callableValueType)->yes()) {
            $finalType = $valuesTypes;
        } else {
            if (!$timeables->isSuperTypeOf($callableKeyType)->yes()) {
                return new ErrorType();
            }
            $finalType = $callableValueType;
        }

        return $timelineType($finalType->generalize(GeneralizePrecision::lessSpecific()));
    }
}
