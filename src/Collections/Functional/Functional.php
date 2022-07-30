<?php

declare(strict_types=1);

namespace Gammadia\Collections\Functional;

use InvalidArgumentException;
use TypeError;
use UnexpectedValueException;
use Webmozart\Assert\Assert;
use function array_key_exists;
use function array_slice;
use function asort;
use function count;
use function Gammadia\Common\Comparison\equals;
use function sort as phpSort;

const FUNCTIONS_REPLACEMENTS_MAP = [
    'array_chunk' => __NAMESPACE__ . '\\chunk',
    'array_column' => __NAMESPACE__ . '\\column',
    'array_combine' => __NAMESPACE__ . '\\combine',
    'array_merge' => __NAMESPACE__ . '\\concat',
    'in_array' => __NAMESPACE__ . '\\contains',
    'array_diff' => __NAMESPACE__ . '\\diff',
    'array_walk' => __NAMESPACE__ . '\\each',
    'array_fill' => __NAMESPACE__ . '\\fill',
    'array_filter' => __NAMESPACE__ . '\\filter',
    'reset' => __NAMESPACE__ . '\\first',
    'array_flip' => __NAMESPACE__ . '\\flip',
    'array_intersect' => __NAMESPACE__ . '\\intersect',
    'array_intersect_key' => __NAMESPACE__ . '\\intersectKeys',
    'array_key_exists' => __NAMESPACE__ . '\\keyExists',
    'array_pop' => __NAMESPACE__ . '\\init',
    'array_keys' => __NAMESPACE__ . '\\keys',
    'end' => __NAMESPACE__ . '\\last',
    'array_map' => __NAMESPACE__ . '\\map',
    'array_reduce' => __NAMESPACE__ . '\\reduce',
    'array_reverse' => __NAMESPACE__ . '\\reverse',
    'array_shift' => __NAMESPACE__ . '\\tail',
    'array_unique' => __NAMESPACE__ . '\\unique',
    'array_values' => __NAMESPACE__ . '\\values',
    'sort' => __NAMESPACE__ . '\\sort',
    'usort' => __NAMESPACE__ . '\\sort',
    'asort' => __NAMESPACE__ . '\\sort',
    'uasort' => __NAMESPACE__ . '\\sort',
];

/*
 * See
 *  - [https://github.com/laravel/framework/blob/master/src/Illuminate/Support/Collection.php]
 *  - [https://lodash.com/docs/]
 * for inspiration
*/

/**
 * {@see FunctionalTest::testAll()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, K): bool)|null $predicate
 */
function all(array $array, ?callable $predicate = null): bool
{
    foreach ($array as $key => $item) {
        $result = null === $predicate ? (bool) $item : $predicate($item, $key);
        if (!$result) {
            return false;
        }
    }

    return true;
}

/**
 * {@see FunctionalTest::testChunk()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param positive-int $size
 *
 * @return ($preserveKeys is true ? array<K, T>[] : array<int, T>[])
 */
function chunk(array $array, int $size, bool $preserveKeys = false): array
{
    /** @var ($preserveKey is true ? array<K, T>[] : array<int, T>[]) $chunks */
    $chunks = array_chunk($array, $size, $preserveKeys);

    return $chunks;
}

/**
 * {@see FunctionalTest::testCollect()}
 *
 * @template K of array-key
 * @template T
 * @template U
 *
 * @param array<K, T> $array
 * @param callable(T, K=): iterable<U> $fn
 *
 * @return array<int, U>
 */
function collect(array $array, callable $fn): array
{
    return iterator_to_array(scollect($array, $fn), preserve_keys: false);
}

/**
 * WARNING: this function's typing isn't compatible with short arrow functions because of a bug in PHPStan !
 * {@see https://github.com/phpstan/phpstan/issues/7031}
 *
 * {@see FunctionalTest::testCollectWithKeys()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 * @template U
 *
 * @param array<K, T> $array
 * @param callable(T, K): iterable<L, U> $fn
 *
 * @return array<L, U>
 */
function collectWithKeys(array $array, callable $fn): array
{
    $values = [];
    $counter = 0;

    foreach (scollect($array, $fn) as $key => $value) {
        try {
            $values[$key] = $value;
            /** @phpstan-ignore-next-line That's probably a PHPStan bug as the catch can definitely happen */
        } catch (TypeError) {
            throw new UnexpectedValueException('The key yielded in the callable is not compatible with the type "array-key".');
        }

        ++$counter;
    }

    if ($counter !== count($values)) {
        throw new UnexpectedValueException(
            'Data loss occurred because of duplicated keys. Use `collect()` if you do not care about ' .
            'the yielded keys, or use `scollect()` if you need to support duplicated keys (as arrays cannot).',
        );
    }

    return $values;
}

/**
 * {@see FunctionalTest::testColumn()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array {@see FunctionalColumnDynamicReturnTypeExtension}
 */
function column(array $array, string|int|null $column, string|int|null $index = null): array
{
    return array_column($array, $column, $index);
}

/**
 * {@see FunctionalTest::testConcat()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> ...$arrays
 *
 * @return array {@see FunctionalConcatDynamicReturnTypeExtension}
 *
 * @todo Remove/deprecate once PHP 8.1 is available ? {@see https://php.watch/versions/8.1/spread-operator-string-array-keys}
 */
function concat(array ...$arrays): array
{
    return array_merge([], ...$arrays);
}

/**
 * {@see FunctionalTest::testContains()}
 * {@see FunctionalContainsFunctionTypeSpecifyingExtension}
 *
 * @template K of array-key
 * @template T
 * @template U
 *
 * @param array<K, T> $array
 * @param U $value
 */
function contains(array $array, mixed $value): bool
{
    foreach ($array as $item) {
        if (equals($item, $value)) {
            return true;
        }
    }

    return false;
}

/**
 * {@see FunctionalTest::testCombine()}
 *
 * @template K of array-key
 * @template T
 *
 * @param K[] $keys
 * @param T[] $values
 *
 * @return array {@see FunctionalCombineDynamicReturnTypeExtension}
 */
function combine(array $keys, array $values): array
{
    /** @var mixed[]|false $result */
    $result = array_combine($keys, $values);

    if (false === $result) {
        throw new InvalidArgumentException('The number of elements for each array is not equal or the arrays are empty.');
    }

    return $result;
}

/**
 * WARNING: this method is not strict. @todo Refactor it so it is.
 *
 * {@see FunctionalTest::testDiff()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param array<K, T> ...$others
 *
 * @return array<K, T>
 */
function diff(array $array, array ...$others): array
{
    return array_diff($array, ...$others);
}

/**
 * {@see FunctionalTest::testEach()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param callable(T, K): (void|bool) $fn
 *
 * @return array<K, T>
 */
function each(array $array, callable $fn): array
{
    foreach ($array as $key => $value) {
        if (false === $fn($value, $key)) {
            break;
        }
    }

    return $array;
}

/**
 * {@see FunctionalTest::testFill()}
 *
 * @return array {@see FunctionalFillDynamicReturnTypeExtension}
 */
function fill(int $startIndex, int $count, mixed $value): array
{
    return array_fill($startIndex, $count, $value);
}

/**
 * {@see FunctionalTest::testFillWith()}
 *
 * @todo Write a PHPStan extension so we could know the individual types for each indexes ?
 *
 * @template T
 * @template U
 *
 * @param array<int, T> $array
 * @param callable(): U $generator
 *
 * @return ($count is positive-int ? array<int, T|U> : array<int, T>)
 */
function fillWith(array $array, int $start, int $count, callable $generator): array
{
    Assert::isList($array, 'The array provided must be a list.');

    for ($i = 0; $i < $count; ++$i) {
        $array[$i + $start] = $generator();
    }

    return $array;
}

/**
 * {@see FunctionalTest::testFilter()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (
 *     $mode is ARRAY_FILTER_USE_BOTH
 *     ? (callable(T, K): bool)
 *     : (
 *         $mode is ARRAY_FILTER_USE_KEY
 *         ? (callable(K): bool)
 *         : (callable(T): bool)
 *     )
 * )|null $predicate
 * @param int-mask<ARRAY_FILTER_USE_BOTH, ARRAY_FILTER_USE_KEY> $mode
 *
 * @return array {@see FunctionalFilterDynamicReturnTypeExtension}
 */
function filter(array $array, ?callable $predicate = null, int $mode = ARRAY_FILTER_USE_BOTH): array
{
    // We cannot call array_filter with "null" as the callback, otherwise it results in this error :
    // TypeError: array_filter() expects parameter 2 to be a valid callback, no array or string given
    return null !== $predicate
        /** @phpstan-ignore-next-line array_filter() is as well typed as our wrapper: it expects (callable(T): mixed) */
        ? array_filter($array, $predicate, $mode)
        : array_filter($array);
}

/**
 * {@see FunctionalTest::testFind()}
 *
 * This is a shortcut / an optimized combination of `first(filter(...))`
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, K): bool) $fn
 *
 * @return ($array is non-empty-array ? T|null : null)
 */
function find(array $array, callable $fn): mixed
{
    foreach ($array as $key => $value) {
        if ($fn($value, $key)) {
            return $value;
        }
    }

    return null;
}

/**
 * This is a shortcut / an optimized combination of `first(keys(filter(...)))`
 *
 * {@see FunctionalTest::testFindKey()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, K): bool) $fn
 *
 * @return ($array is non-empty-array ? K|null : null)
 */
function findKey(array $array, callable $fn): string|int|null
{
    foreach ($array as $key => $value) {
        if ($fn($value, $key)) {
            return $key;
        }
    }

    return null;
}

/**
 * {@see FunctionalTest::testFirst()}
 *
 * @todo Write a PHPStan extension so that we could properly follow positional arguments
 *       (example with first() + tail() within a map()'s callback in {@see ExtraHoursReportView}) ?
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return ($array is non-empty-array ? T : null)
 */
function first(array $array): mixed
{
    return [] !== $array ? $array[array_key_first($array)] : null;
}

/**
 * {@see FunctionalTest::testFirstKey()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return string|int|null {@see FunctionalFirstKeyDynamicReturnTypeExtension}
 */
function firstKey(array $array): string|int|null
{
    return array_key_first($array);
}

/**
 * {@see FunctionalTest::testFlatten()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T>[] $arrays
 *
 * @return array<K, T>
 */
function flatten(array $arrays): array
{
    Assert::allIsArray($arrays, '$arrays must be an array of arrays (it cannot be a map).');

    return array_merge([], ...values($arrays));
}

/**
 * {@see FunctionalTest::testFlip()}
 *
 * @todo Throw exception if the array is empty (and adapt PHPStan's extension), as it throws a warning currently ?
 *
 * @template K of array-key
 * @template T of array-key
 *
 * @param array<K, T> $array
 *
 * @return array {@see FunctionalFlipDynamicReturnTypeExtension}
 */
function flip(array $array): array
{
    return array_flip($array);
}

/**
 * {@see FunctionalTest::testGroupBy()
 *
 * @todo Write a PHPStan extension that would allow to merge all these groupBy* functions while having proper typing
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param callable(T, K): L $groupBy
 *
 * @return array<L, T[]>
 */
function groupBy(array $array, callable $groupBy): array
{
    $result = [];
    foreach ($array as $key => $value) {
        $result[$groupBy($value, $key)][] = $value;
    }

    return $result;
}

/**
 * @experimental
 * {@see FunctionalTest::testGroupByWithKeys()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param callable(T, K): L $groupBy
 *
 * @return array<L, array<K, T>>
 */
function groupByWithKeys(array $array, callable $groupBy): array
{
    $result = [];
    foreach ($array as $key => $value) {
        $result[$groupBy($value, $key)][$key] = $value;
    }

    return $result;
}

/**
 * @experimental
 * {@see FunctionalTest::testGroupByMany()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param callable(T, K): L[] $groupBy
 *
 * @return array<L, T[]>
 */
function groupByMany(array $array, callable $groupBy): array
{
    $result = [];
    foreach ($array as $key => $value) {
        foreach ($groupBy($value, $key) as $groupKey) {
            $result[$groupKey][] = $value;
        }
    }

    return $result;
}

/**
 * @experimental
 * {@see FunctionalTest::testGroupByMany()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param callable(T, K): L[] $groupBy
 *
 * @return array<L, array<K, T>>
 */
function groupByManyWithKeys(array $array, callable $groupBy): array
{
    $result = [];
    foreach ($array as $key => $value) {
        foreach ($groupBy($value, $key) as $groupKey) {
            $result[$groupKey][$key] = $value;
        }
    }

    return $result;
}

/**
 * @experimental
 * {@see FunctionalTest::testGroupByRecursive()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param (callable(T, K): L)[] $groups
 *
 * @return array<L, T[]>
 */
function groupByRecursive(array $array, array $groups): array
{
    $nextGroups = $groups;
    $groupBy = first($nextGroups) ?? throw new InvalidArgumentException('The $groups argument cannot be empty.');
    $nextGroups = tail($nextGroups);

    $results = [];
    foreach ($array as $key => $value) {
        $results[$groupBy($value, $key)][] = $value;
    }

    if ([] !== $nextGroups) {
        $self = __FUNCTION__;

        /** @phpstan-ignore-next-line Recursive typing is too hard... */
        $results = map($results, static fn (array $group): array => $self($group, $nextGroups));
    }

    /** @var array<L, T[]> $results Recursive typing is too hard... */
    return $results;
}

/**
 * @experimental
 * {@see FunctionalTest::testGroupByRecursiveWithKeys()}
 *
 * @template K of array-key
 * @template T
 * @template L of array-key
 *
 * @param array<K, T> $array
 * @param (callable(T, K): L)[] $groups
 *
 * @return array<L, array<K, T>>
 */
function groupByRecursiveWithKeys(array $array, array $groups): array
{
    $nextGroups = $groups;
    $groupBy = first($nextGroups) ?? throw new InvalidArgumentException('The $groups argument cannot be empty.');
    $nextGroups = tail($nextGroups);

    $results = [];
    foreach ($array as $key => $value) {
        $results[$groupBy($value, $key)][$key] = $value;
    }

    if ([] !== $nextGroups) {
        $self = __FUNCTION__;
        $results = map($results, static fn (array $group): array => $self($group, $nextGroups));
    }

    /** @var array<L, array<K, T>> $results Recursive typing is too hard... */
    return $results;
}

/**
 * {@see FunctionalTest::testIntersect()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param array<K, T> ...$others
 *
 * @return array<K, T>
 */
function intersect(array $array, array ...$others): array
{
    return filter($array, static fn (mixed $value): bool
        => all($others, static fn (array $other): bool => contains($other, $value)),
    );
}

/**
 * {@see FunctionalTest::testIntersectKeys()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param array<K, T> ...$others
 *
 * @return array<K, T>
 */
function intersectKeys(array $array, array ...$others): array
{
    return array_intersect_key($array, ...$others);
}

/**
 * {@see FunctionalTest::testIndexBy()}
 *
 * @template K of array-key
 * @template T
 * @template U of array-key
 *
 * @param array<K, T> $array
 * @param callable(T, K): U $keyFn
 *
 * @return array<U, T>
 */
function indexBy(array $array, callable $keyFn): array
{
    $indexed = [];
    foreach ($array as $key => $item) {
        $indexed[$keyFn($item, $key)] = $item;
    }

    return $indexed;
}

/**
 * {@see FunctionalTest::testInit()}
 *
 * @todo Write a custom PHPStan extension that removes the last element's type from the return types
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array<K, T>
 */
function init(array $array): array
{
    array_pop($array);

    return $array;
}

/**
 * {@see FunctionalTest::testKeyExists()}
 * {@see FunctionalKeyExistsFunctionTypeSpecifyingExtension}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param K $key
 */
function keyExists(array $array, int|string $key): bool
{
    return array_key_exists($key, $array);
}

/**
 * {@see FunctionalTest::testKeys()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array {@see FunctionalKeysDynamicReturnTypeExtension}
 */
function keys(array $array): array
{
    return array_keys($array);
}

/**
 * {@see FunctionalTest::testLast()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return ($array is non-empty-array ? T : null)
 */
function last(array $array): mixed
{
    return [] !== $array ? $array[array_key_last($array)] : null;
}

/**
 * {@see FunctionalTest::testMap()}
 *
 * Our implementation is not compatible with intval, see {@see https://3v4l.org/hJeS6}.
 *
 * @template K of array-key
 * @template T
 * @template U
 *
 * @param array<K, T> $array
 * @param callable(T, K): U $fn
 *
 * @return array {@see FunctionalMapDynamicReturnTypeExtension}
 */
function map(array $array, callable $fn): array
{
    $keys = keys($array);

    return combine($keys, array_map($fn, $array, $keys));
}

/**
 * {@see FunctionalTest::testPairs()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> ...$arrays
 *
 * @return array{0: K, 1: T}[]
 */
function pairs(array ...$arrays): array
{
    return reduce($arrays, static function (array $carry, array $array): array {
        foreach ($array as $key => $value) {
            $carry[] = [$key, $value];
        }

        return $carry;
    }, initial: []);
}

/**
 * {@see FunctionalTest::testPartition()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param callable(T, K): bool $predicate
 *
 * @return ($array is list<T> ? array{0: array<T>, 1: array<T>} : array{0: array<K, T>, 1: array<K, T>})
 */
function partition(array $array, callable $predicate): array
{
    $successes = $failures = [];

    if (array_is_list($array)) {
        foreach ($array as $key => $value) {
            if ($predicate($value, $key)) {
                $successes[] = $value;
            } else {
                $failures[] = $value;
            }
        }
    } else {
        foreach ($array as $key => $value) {
            if ($predicate($value, $key)) {
                $successes[$key] = $value;
            } else {
                $failures[$key] = $value;
            }
        }
    }

    return [$successes, $failures];
}

/**
 * {@see FunctionalTest::testReduce()}
 *
 * @template K of array-key
 * @template T
 * @template U
 * @template V
 *
 * @param array<K, T> $array
 * @param callable(U|V, T, K): V $reducer
 * @param U $initial
 *
 * @return U|V {@see FunctionalReduceDynamicReturnTypeExtension}
 */
function reduce(array $array, callable $reducer, mixed $initial = null): mixed
{
    return array_reduce(
        keys($array),
        static fn (mixed $carry, mixed $key): mixed => $reducer($carry, $array[$key], $key),
        $initial,
    );
}

/**
 * {@see FunctionalTest::testReverse()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array {@see FunctionalReverseDynamicReturnTypeExtension}
 */
function reverse(array $array, bool $preserveKey = false): array
{
    return array_reverse($array, $preserveKey);
}

/**
 * {@see FunctionalTest::testSome()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, K): bool)|null $predicate
 */
function some(array $array, ?callable $predicate = null): bool
{
    foreach ($array as $key => $item) {
        if (null === $predicate ? (bool) $item : $predicate($item, $key)) {
            return true;
        }
    }

    return false;
}

/**
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, T): int)|null $comparator
 *
 * @return ($preserveKeys is true ? array<K, T> : array<int, T>)
 */
function sort(array $array, ?callable $comparator = null, bool $preserveKeys = false, int $flags = SORT_REGULAR): array
{
    if (null === $comparator) {
        if ($preserveKeys) {
            asort($array, $flags);
        } else {
            phpSort($array, $flags);
        }
    } else {
        if ($preserveKeys) {
            uasort($array, $comparator);
        } else {
            usort($array, $comparator);
        }
    }

    return $array;
}

/**
 * {@see FunctionalTest::testTail()}
 *
 * @todo Write a custom PHPStan extension that removes the first element's type from the return types
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array<K, T>
 */
function tail(array $array): array
{
    array_shift($array);

    return $array;
}

/**
 * {@see FunctionalTest::testUnique()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param (callable(T, K): mixed)|null $identifier
 *
 * @return array<K, T>
 */
function unique(array $array, ?callable $identifier = null): array
{
    $exists = [];

    return array_filter($array, static function (mixed $item, int|string $key) use ($identifier, &$exists): bool {
        $id = $identifier ? $identifier($item, $key) : $item;

        if (!contains($exists, $id)) {
            $exists[] = $id;

            return true;
        }

        return false;
    }, ARRAY_FILTER_USE_BOTH);
}

/**
 * {@see FunctionalTest::testValues()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 *
 * @return array {@see FunctionalValuesDynamicReturnTypeExtension}
 */
function values(array $array): array
{
    return array_values($array);
}

/**
 * {@see FunctionalTest::testWindow()}
 *
 * @todo Write a PHPStan extension to deal with exact positioning and the exception?
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> $array
 * @param positive-int $width
 *
 * @return (
 *     K is int
 *     ? array<int, array<int, T>>
 *     : (
 *         K is string
 *         ? array<int, array<string, T>>
 *         : array<int, array<int|string, T>>
 *     )
 * )
 */
function window(array $array, int $width): array
{
    $count = count($array);
    Assert::notEq($width, 0);
    Assert::lessThanEq($width, $count, 'Not enough items in array');

    $windows = [];
    for ($i = 0; ($i + $width - 1) < $count; ++$i) {
        $windows[] = array_slice($array, offset: $i, length: $width, preserve_keys: false);
    }

    return $windows;
}

/**
 * {@see FunctionalTest::testZip()}
 *
 * @template K of array-key
 * @template T
 *
 * @param array<K, T> ...$arrays
 *
 * @return array {@see FunctionalZipDynamicReturnTypeExtension}
 */
function zip(array ...$arrays): array
{
    Assert::notEmpty($arrays, 'You must provide an argument to zip()');

    return array_map(1 === count($arrays) ? static fn (mixed $item): array => [$item] : null, ...$arrays);
}
