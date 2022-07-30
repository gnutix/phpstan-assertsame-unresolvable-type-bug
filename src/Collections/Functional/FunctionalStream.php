<?php

declare(strict_types=1);

namespace Gammadia\Collections\Functional;

use Generator;
use MultipleIterator;
use Webmozart\Assert\Assert;
use function count;
use function Gammadia\Common\Comparison\equals;

/*
 * See
 *  - [https://github.com/laravel/framework/blob/master/src/Illuminate/Support/Collection.php]
 *  - [https://lodash.com/docs/]
 * for inspiration
*/

/**
 * {@see FunctionalStreamTest::testAll()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): bool)|null $predicate
 */
function sall(iterable $stream, ?callable $predicate = null): bool
{
    foreach ($stream as $key => $value) {
        $result = null === $predicate ? (bool) $value : $predicate($value, $key);
        if (!$result) {
            return false;
        }
    }

    return true;
}

/**
 * {@see FunctionalStreamTest::testChunk()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param positive-int $size
 *
 * @return Generator<int, T[]>
 */
function schunk(iterable $stream, int $size): Generator
{
    Assert::notEq($size, 0, 'The size cannot be zero.');

    $chunk = [];
    $chunkSize = 0;
    foreach ($stream as $value) {
        $chunk[] = $value;
        if ($size === ++$chunkSize) {
            yield $chunk;
            $chunk = [];
            $chunkSize = 0;
        }
    }
    if (0 < $chunkSize) {
        yield $chunk;
    }
}

/**
 * {@see FunctionalStreamTest::testCollect()}
 *
 * @template K
 * @template T
 * @template L
 * @template U
 *
 * @param iterable<K, T> $stream
 * @param callable(T, K): iterable<L, U> $fn
 *
 * @return Generator<L, U>
 */
function scollect(iterable $stream, callable $fn): Generator
{
    foreach ($stream as $key => $value) {
        yield from $fn($value, $key);
    }
}

/**
 * {@see FunctionalStreamTest::testConcat()}
 *
 * @todo Improve typing to deal with sconcat() taking different arrays of different types.
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> ...$streams
 *
 * @return Generator<K, T>
 */
function sconcat(iterable ...$streams): Generator
{
    foreach ($streams as $stream) {
        yield from $stream;
    }
}

/**
 * {@see FunctionalStreamTest::testContains()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param T $value
 */
function scontains(iterable $stream, mixed $value): bool
{
    foreach ($stream as $item) {
        if (equals($item, $value)) {
            return true;
        }
    }

    return false;
}

/**
 * {@see FunctionalStreamTest::testCombine()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K> $keys
 * @param iterable<T> $values
 *
 * @return Generator<K, T>
 */
function scombine(iterable $keys, iterable $values): Generator
{
    $multiple = new MultipleIterator();
    $multiple->attachIterator(sgenerator($keys));
    $multiple->attachIterator(sgenerator($values));

    foreach ($multiple as [$key, $value]) {
        yield $key => $value;
    }
}

/**
 * {@see FunctionalStreamTest::testFilter()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): bool)|null $predicate
 *
 * @return Generator<K, T>
 */
function sfilter(iterable $stream, ?callable $predicate = null): Generator
{
    foreach ($stream as $key => $value) {
        if (null !== $predicate ? $predicate($value, $key) : $value) {
            yield $key => $value;
        }
    }
}

/**
 * This is a shortcut / an optimized combination of `sfirst(sfilter(...))`
 *
 * {@see FunctionalStreamTest::testFind()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): bool) $fn
 *
 * @return T|null
 */
function sfind(iterable $stream, callable $fn): mixed
{
    foreach ($stream as $key => $value) {
        if ($fn($value, $key)) {
            return $value;
        }
    }

    return null;
}

/**
 * This is a shortcut / an optimized combination of `sfirst(skeys(sfilter(...)))`
 *
 * {@see FunctionalStreamTest::testFindKey()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): bool) $fn
 *
 * @return K|null
 *
 * @noinspection PhpMixedReturnTypeCanBeReducedInspection as we're talking about streams here, the key could be anything
 */
function sfindKey(iterable $stream, callable $fn): mixed
{
    foreach ($stream as $key => $value) {
        if ($fn($value, $key)) {
            return $key;
        }
    }

    return null;
}

/**
 * {@see FunctionalStreamTest::testFirst()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return T|null
 */
function sfirst(iterable $stream): mixed
{
    /** @noinspection LoopWhichDoesNotLoopInspection */
    foreach ($stream as $value) {
        return $value;
    }

    return null;
}

/**
 * {@see FunctionalStreamTest::testFirstKey()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return K|null
 * @noinspection PhpMixedReturnTypeCanBeReducedInspection as we're talking about streams here, the key could be anything
 */
function sfirstKey(iterable $stream): mixed
{
    /** @noinspection LoopWhichDoesNotLoopInspection */
    foreach ($stream as $key => $value) {
        return $key;
    }

    return null;
}

/**
 * {@see FunctionalStreamTest::testFlatten()}
 *
 * @template K
 * @template T
 *
 * @param iterable<iterable<K, T>> $stream
 *
 * @return Generator<K, T>
 */
function sflatten(iterable $stream): Generator
{
    foreach ($stream as $substream) {
        yield from $substream;
    }
}

/**
 * {@see FunctionalStreamTest::testFlip()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<T, K>
 */
function sflip(iterable $stream): Generator
{
    foreach ($stream as $key => $value) {
        yield $value => $key;
    }
}

/**
 * This method is untested.
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<K, T>
 */
function sgenerator(iterable $stream): Generator
{
    if ($stream instanceof Generator) {
        return $stream;
    }

    return (static function () use ($stream): Generator {
        foreach ($stream as $key => $value) {
            yield $key => $value;
        }
    })();
}

/**
 * {@see FunctionalStreamTest::testIndexBy()}
 *
 * @template T
 * @template U
 *
 * @param iterable<T> $stream
 * @param callable(T): U $keyFn
 *
 * @return Generator<U, T>
 */
function sindexBy(iterable $stream, callable $keyFn): Generator
{
    foreach ($stream as $value) {
        yield $keyFn($value) => $value;
    }
}

/**
 * {@see FunctionalStreamTest::testInit()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<K, T>
 */
function sinit(iterable $stream): Generator
{
    foreach ($stream as $key => $value) {
        if (isset($previous)) {
            [$previousKey, $previousValue] = $previous;

            yield $previousKey => $previousValue;
        }

        $previous = [$key, $value];
    }
}

/**
 * {@see FunctionalStreamTest::testKeyExists()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param K $key
 */
function skeyExists(iterable $stream, mixed $key): bool
{
    foreach ($stream as $streamKey => $streamValue) {
        if (equals($key, $streamKey)) {
            return true;
        }
    }

    return false;
}

/**
 * {@see FunctionalStreamTest::testKeys()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<int, K>
 */
function skeys(iterable $stream): Generator
{
    foreach ($stream as $key => $value) {
        yield $key;
    }
}

/**
 * {@see FunctionalStreamTest::testLast()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return T|null
 */
function slast(iterable $stream): mixed
{
    $last = null;
    foreach ($stream as $value) {
        $last = $value;
    }

    return $last;
}

/**
 * {@see FunctionalStreamTest::testMap()}
 *
 * @template K
 * @template T
 * @template U
 *
 * @param iterable<K, T> $stream
 * @param callable(T, K): U $fn
 *
 * @return Generator<K, U>
 */
function smap(iterable $stream, callable $fn): Generator
{
    foreach ($stream as $key => $value) {
        yield $key => $fn($value, $key);
    }
}

/**
 * {@see FunctionalStreamTest::testSome()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): bool)|null $predicate
 */
function ssome(iterable $stream, ?callable $predicate = null): bool
{
    foreach ($stream as $key => $value) {
        if (null === $predicate ? $value : $predicate($value, $key)) {
            return true;
        }
    }

    return false;
}

/**
 * {@see FunctionalStreamTest::testOffset()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<K, T>
 */
function soffset(iterable $stream, int $n): Generator
{
    foreach ($stream as $key => $value) {
        if ($n > 0) {
            --$n;
        } else {
            yield $key => $value;
        }
    }
}

/**
 * {@see FunctionalStreamTest::testPairs()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> ...$streams
 *
 * @return Generator<int, array{0: K, 1: T}>
 */
function spairs(iterable ...$streams): Generator
{
    foreach ($streams as $stream) {
        foreach ($stream as $key => $value) {
            yield [$key, $value];
        }
    }
}

/**
 * {@see FunctionalStreamTest::testPartition()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param callable(T, K): bool $predicate
 *
 * @return array{0: Generator<K, T>, 1: Generator<K, T>}
 */
function spartition(iterable $stream, callable $predicate): array
{
    return [
        sfilter(sgenerator($stream), $predicate),
        sfilter(sgenerator($stream), static fn (mixed $value, mixed $key) => !$predicate($value, $key)),
    ];
}

/**
 * {@see FunctionalStreamTest::testReduce()}
 *
 * @template K
 * @template T
 * @template U
 * @template V
 *
 * @param iterable<K, T> $stream
 * @param callable(U|V, T, K): V $reducer
 * @param U $initial
 *
 * @return U|V
 */
function sreduce(iterable $stream, callable $reducer, mixed $initial = null): mixed
{
    foreach ($stream as $key => $value) {
        $initial = $reducer($initial, $value, $key);
    }

    return $initial;
}

/**
 * {@see FunctionalStreamTest::testTail()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<K, T>
 */
function stail(iterable $stream): Generator
{
    $first = true;
    foreach ($stream as $key => $value) {
        if ($first) {
            $first = false;
            continue;
        }

        yield $key => $value;
    }
}

/**
 * {@see FunctionalStreamTest::testUnique()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param (callable(T, K): mixed)|null $key
 *
 * @return Generator<K, T>
 */
function sunique(iterable $stream, ?callable $key = null): Generator
{
    $exists = [];

    foreach ($stream as $skey => $value) {
        $k = null !== $key ? $key($value, $skey) : $value;
        if (!contains($exists, $k)) {
            $exists[] = $k;
            yield $skey => $value;
        }
    }
}

/**
 * {@see FunctionalStreamTest::testValues()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 *
 * @return Generator<int, T>
 */
function svalues(iterable $stream): Generator
{
    foreach ($stream as $value) {
        yield $value;
    }
}

/**
 * {@see FunctionalStreamTest::testWindow()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> $stream
 * @param positive-int $width
 *
 * @return Generator<int, T[]>
 */
function swindow(iterable $stream, int $width): Generator
{
    Assert::notEq($width, 0, 'The width cannot be zero.');

    $window = [];
    foreach ($stream as $value) {
        $window[] = $value;
        $count = count($window);

        switch (true) {
            case $count > $width:
                array_shift($window);
                // no break
            case $count === $width:
                yield $window;
        }
    }

    Assert::count($window, $width, 'Not enough items in stream.');
}

/**
 * {@see FunctionalStreamTest::testZip()}
 *
 * @template K
 * @template T
 *
 * @param iterable<K, T> ...$streams
 *
 * @return Generator<int, array<int, T|null>>
 */
function szip(iterable ...$streams): Generator
{
    $multiple = new MultipleIterator(MultipleIterator::MIT_NEED_ANY | MultipleIterator::MIT_KEYS_NUMERIC);
    foreach ($streams as $stream) {
        $multiple->attachIterator(sgenerator($stream));
    }

    foreach ($multiple as $values) {
        yield $values;
    }
}
