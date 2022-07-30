<?php

declare(strict_types=1);

namespace Gammadia\Collections\Timeline;

use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Gammadia\Collections\Timeline\Exception\TimelineImportConflictException;
use Gammadia\Collections\Timeline\Exception\TimelineRangeConflictException;
use Gammadia\DateTimeExtra\LocalDateInterval;
use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use Generator;
use IteratorAggregate;
use Traversable;
use Webmozart\Assert\Assert;
use function array_slice;
use function count;
use function Gammadia\Collections\Functional\collect;
use function Gammadia\Collections\Functional\concat;
use function Gammadia\Collections\Functional\filter;
use function Gammadia\Collections\Functional\first;
use function Gammadia\Collections\Functional\init;
use function Gammadia\Collections\Functional\last;
use function Gammadia\Collections\Functional\map;
use function Gammadia\Collections\Functional\reduce;
use function Gammadia\Collections\Functional\some;
use function Gammadia\Collections\Functional\sort;
use function Gammadia\Collections\Functional\unique;
use function Gammadia\Collections\Functional\values;
use function Gammadia\Collections\Functional\window;
use function Gammadia\Common\Comparison\equals;
use function is_callable;
use function is_object;

/**
 * A timeline of a time-varying values for a single concept.
 *
 * This is basically a collection of non-overlapping time ranges, each representing
 * a continuous/non-empty time range during which the value of the concept does not vary.
 * Each change of value is associated with a new time range in the collection.
 *
 * The value might be undefined for some duration in the timeline, in which case it
 * is assumed to be null. While a timeline can safely store and manipulate null values,
 * some functions might not provide a way to distinguish between an explicit null value
 * and the absence of value. Purposely storing nulls in a timeline is discouraged.
 *
 * @see \Gammadia\Collections\Test\Unit\Timeline\TimelineTest
 *
 * @template T
 * @implements \IteratorAggregate<LocalDateTimeInterval, T>
 */
final class Timeline implements IteratorAggregate
{
    /**
     * We assume that items are correctly sorted and without overlapping/empty time range
     * (this is enforced by {@see Timeline::add()}).
     *
     * @param array{0: LocalDateTimeInterval, 1: T}[] $items
     */
    private function __construct(
        private array $items,
    ) {
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        /** @var self<never> $empty */
        $empty = new self([]);

        return $empty;
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function constant(mixed $value): self
    {
        return self::with(LocalDateTimeInterval::forever(), $value);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function with(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        return self::empty()->add($range, $value);
    }

    /**
     * Alias for add() that simplifies adding values able to provide a LocalDateTimeInterval
     *
     * @template K
     * @template U
     * @template V
     *
     * @param iterable<U> $values An iterable of mixed values, or an iterable of time ranges (will be used as values too)
     * @param (callable(U, K): (LocalDate|LocalDateInterval|LocalDateTimeInterval)|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V>|callable(U, K): V)|null $callable
     *
     * @return self<mixed> {@see TimelineImportDynamicReturnTypeExtension}
     */
    public static function import(iterable $values, ?callable $callable = null): self
    {
        $timeline = self::empty();
        $originalValuesTimeline = self::empty();

        /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $range */
        foreach (self::assembleValues($values, $callable) as $range => [$value, $originalValue]) {
            try {
                $timeline = $timeline->add($range, $value);
                $originalValuesTimeline = $originalValuesTimeline->add($range, $originalValue);
            } catch (TimelineRangeConflictException $exception) {
                throw new TimelineImportConflictException(
                    LocalDateTimeInterval::cast($range),
                    $originalValue,
                    $originalValuesTimeline->keep($range),
                    $exception,
                );
            }
        }

        return $timeline;
    }

    /**
     * Alias for `Timeline::zipAll()->map()` with intermediate array unpacking. Argument is a list of Timeline
     * objects, then a callable. The arguments in the callable must all be nullable (as there might be no value
     * for a given time frame).
     *
     * @return self<mixed>
     */
    public static function merge(mixed ...$arguments): self
    {
        $callable = last($arguments);
        if (is_callable($callable)) {
            $timelines = init($arguments);
        } else {
            $timelines = $arguments;
            $callable = static fn (...$values) => first(filter($values, static fn (mixed $value): bool => null !== $value));
        }

        Assert::allIsInstanceOf($timelines, self::class);
        Assert::isCallable($callable);

        return self::zipAll(...$timelines)
            ->map(static function (array $values, LocalDateTimeInterval $timeRange) use ($callable): mixed {
                // Add the range as the last parameter to map
                $values[] = $timeRange;

                return $callable(...$values);
            });
    }

    /**
     * Return a new timeline with values being an array containing all timeline values for each possible boundary.
     *
     * @template U
     *
     * @param self<U> ...$timelines
     *
     * @return self<U[]>
     */
    public static function zipAll(self ...$timelines): self
    {
        $hasInfiniteStart = false;
        $starts = collect($timelines, function (self $timeline) use (&$hasInfiniteStart): Generator {
            yield from collect($timeline->items, static function (array $item) use (&$hasInfiniteStart): Generator {
                /**
                 * @var LocalDateTimeInterval $timeRange
                 */
                [$timeRange,] = $item;
                $start = $timeRange->getStart();
                if (null === $start) {
                    $hasInfiniteStart = true;
                } else {
                    yield $start;
                }
            });
        });

        $hasInfiniteEnd = false;
        $ends = collect($timelines, function (self $timeline) use (&$hasInfiniteEnd): Generator {
            yield from collect($timeline->items, static function (array $item) use (&$hasInfiniteEnd): Generator {
                /**
                 * @var LocalDateTimeInterval $timeRange
                 */
                [$timeRange,] = $item;
                $end = $timeRange->getEnd();
                if (null === $end) {
                    $hasInfiniteEnd = true;
                } else {
                    yield $end;
                }
            });
        });

        $boundaries = concat(
            $hasInfiniteStart ? [null] : [],
            sort(
                unique(concat($starts, $ends), static fn (LocalDateTime $boundary): string => (string) $boundary),
                static fn (LocalDateTime $a, LocalDateTime $b): int => $a->compareTo($b),
            ),
            $hasInfiniteEnd ? [null] : [],
        );
        if (empty($boundaries)) {
            return self::empty();
        }

        $items = map(
            map(window($boundaries, 2), static fn (array $window): LocalDateTimeInterval
                => LocalDateTimeInterval::between(...$window),
            ),
            static fn (LocalDateTimeInterval $timeRange): array
                => [$timeRange, map($timelines, static fn (self $timeline) => $timeline->valueFor($timeRange))],
        );

        // Remove slices of the timeline without any matching items in all other timelines
        return (new self($items))->filter(static fn (array $zipped): bool
            => some($zipped, static fn (mixed $value): bool => null !== $value),
        );
    }

    /**
     * @param T $value
     *
     * @return self<T>
     */
    public function fillBlanks(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        /** @var self<T> $timeline */
        $timeline = $this
            ->keep($range)
            ->zip(self::with($range, $value))
            ->filter(fn (array $values, LocalDateTimeInterval $sliceRange): bool => empty($this->keep($sliceRange)->items))
            ->reduce(static function (self $carry, array $values, LocalDateTimeInterval $range): self {
                [, $value] = $values;

                return $carry->add($range, $value);
            }, initial: $this);

        return $timeline;
    }

    /**
     * Alias for Timeline::zipAll($this, ...$others).
     *
     * @param self<T> ...$others
     *
     * @return self<T[]>
     */
    public function zip(self ...$others): self
    {
        return self::zipAll($this, ...$others);
    }

    /**
     * Returns a new timeline with a new value for the given range.
     *
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     *
     * @throws TimelineRangeConflictException If the range overlaps an already defined range in this timeline
     */
    public function add(LocalDate|LocalDateInterval|LocalDateTimeInterval $range, mixed $value): self
    {
        $timeRange = LocalDateTimeInterval::cast($range);

        // A timeline does not support storing elements with an empty time range
        if ($timeRange->isEmpty()) {
            return $this;
        }

        // Respect immutability
        $items = $this->items;

        /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
        for (
            $start = 0, $end = count($items);
            $i = $start + (int) (($end - $start) / 2), $i < $end;
        ) {
            /**
             * @var LocalDateTimeInterval $itemTimeRange
             */
            [$itemTimeRange,] = $items[$i];
            if ($itemTimeRange->intersects($timeRange)) {
                throw new TimelineRangeConflictException($timeRange, $itemTimeRange);
            }

            if ($itemTimeRange->isBefore($timeRange)) {
                // If $items[$i] is before the range, we drop the lower half of indices
                $start = $i + 1;
            } else {
                // If $items[$i] is after the range, we instead drop the higher half of indices
                $end = $i;
            }
        }

        array_splice($items, $i, 0, [[$timeRange, $value]]);

        /** @var self<U> $timeline */
        $timeline = new self($items);

        return $timeline;
    }

    /**
     * @return T|null
     */
    public function valueAt(LocalDateTime $timepoint): mixed
    {
        $items = $this->items;

        /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
        for (
            $start = 0, $end = count($items);
            $i = $start + (int) (($end - $start) / 2), $i < $end;
        ) {
            /**
             * @var LocalDateTimeInterval $itemTimeRange
             * @var T $value
             */
            [$itemTimeRange, $value] = $items[$i];
            if ($itemTimeRange->contains($timepoint)) {
                return $value;
            } elseif ($itemTimeRange->isBefore($timepoint)) {
                $start = $i + 1;
            } else {
                $end = $i;
            }
        }

        return null;
    }

    /**
     * Returns a timeline without any ranges outside the given range.
     *
     * If some ranges in this timeline cross the given range boundaries, they will be truncated.
     *
     * @return self<T>
     */
    public function keep(LocalDate|LocalDateInterval|LocalDateTimeInterval $range): self
    {
        $timeRange = LocalDateTimeInterval::cast($range);
        $items = $this->items;

        $min = $timeRange->getStart();
        if (null !== $min) {
            /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
            for (
                $start = 0, $end = count($items);
                $i = $start + (int) (($end - $start) / 2), $i < $end;
            ) {
                /**
                 * @var LocalDateTimeInterval $itemTimeRange
                 */
                [$itemTimeRange,] = $items[$i];
                if ($itemTimeRange->contains($min)) {
                    $items[$i][0] = $itemTimeRange->withStart($min);
                    break;
                } elseif ($itemTimeRange->isBefore($min)) {
                    $start = $i + 1;
                } else {
                    $end = $i;
                }
            }

            $items = array_slice($items, $i);
        }

        $max = $timeRange->getInclusiveEnd();
        if (null !== $max) {
            /** @noinspection SuspiciousLoopInspection use of the "comma operator" is a rare syntax only for `for()` */
            for (
                $start = 0, $end = count($items);
                $i = $start + (int) (($end - $start) / 2), $i < $end;
            ) {
                /**
                 * @var LocalDateTimeInterval $itemTimeRange
                 */
                [$itemTimeRange,] = $items[$i];
                if ($itemTimeRange->contains($max)) {
                    $items[$i][0] = $itemTimeRange->withEnd($timeRange->getFiniteEnd());
                    ++$i;
                    break;
                } elseif ($itemTimeRange->isBefore($max)) {
                    $start = $i + 1;
                } else {
                    $end = $i;
                }
            }

            $items = array_slice($items, 0, $i);
        }

        /** @var self<T> $timeline */
        $timeline = new self($items);

        return $timeline;
    }

    /**
     * Applies the given function to every ranges of value in this timeline.
     *
     * @template U
     *
     * @param callable(T, LocalDateTimeInterval): U $fn
     *
     * @return self<U>
     */
    public function map(callable $fn): self
    {
        return new self(map($this->items, static function (array $item) use ($fn): array {
            [$timeRange, $value] = $item;

            return [$timeRange, $fn($value, $timeRange)];
        }));
    }

    /**
     * Filters the timeline, keeping only ranges of value for which the predicate returns true.
     *
     * @param (callable(T, LocalDateTimeInterval): bool)|null $predicate
     *
     * @return self<T>
     */
    public function filter(?callable $predicate = null): self
    {
        /** @var self<T> $timeline */
        $timeline = new self(values(filter($this->items, static function (array $item) use ($predicate): bool {
            [$timeRange, $value] = $item;

            return null !== $predicate ? $predicate($value, $timeRange) : (bool) $value;
        })));

        return $timeline;
    }

    /**
     * Reduces this interval by invoking the reducer with every range of values in this timeline.
     *
     * @template U
     * @template V
     *
     * @param callable(U|V, T, LocalDateTimeInterval): V $reducer
     * @param U $initial
     *
     * @return U|V
     */
    public function reduce(callable $reducer, mixed $initial = null): mixed
    {
        return reduce($this->items, static function (mixed $carry, array $item) use ($reducer): mixed {
            /**
             * @var U|V $carry
             * @var T $value
             * @var LocalDateTimeInterval $timeRange
             */
            [$timeRange, $value] = $item;

            return $reducer($carry, $value, $timeRange);
        }, $initial);
    }

    /**
     * Simplifies the timeline such that there is no two item with meeting ranges and equal values,
     * merging adjacent items if necessary. How equality is compared can be customized by passing a callable.
     *
     * @param (callable(T, T): bool)|null $equals
     *
     * @return self<T>
     */
    public function simplify(?callable $equals = null): self
    {
        /** @var array{0: LocalDateTimeInterval, 1: T}[] $values */
        $values = $this->reduce(
            static function (array $items, mixed $value, LocalDateTimeInterval $timeRange) use ($equals): array {
                /** @var array{0: ?LocalDateTimeInterval, 1: T} $lastItem */
                $lastItem = last($items);
                [$lastRange, $lastValue] = $lastItem;

                if (null !== $lastRange && $lastRange->meets($timeRange) &&
                    (null === $equals ? equals($value, $lastValue) : $equals($value, $lastValue))
                ) {
                    array_splice($items, -1, 1, [[
                        LocalDateTimeInterval::between($lastRange->getStart(), $timeRange->getEnd()),
                        $value,
                    ]]);
                } else {
                    $items[] = [$timeRange, $value];
                }

                return $items;
            },
            initial: [],
        );

        /** @var self<T> $timeline */
        $timeline = new self($values);

        return $timeline;
    }

    /**
     * Returns a iterator over every ranges of values in this timeline.
     * Keys are the time ranges of each item.
     *
     * Beware : as a time range is an invalid as a PHP array key, one cannot call
     * `iterator_to_array($timeline->getIterator())` as it will result in an "Illegal offset type" error.
     *
     * @return Traversable<LocalDateTimeInterval, T>
     */
    public function getIterator(): Traversable
    {
        /**
         * @var LocalDateTimeInterval $timeRange
         * @var T $value
         */
        foreach ($this->items as [$timeRange, $value]) {
            yield $timeRange => $value;
        }
    }

    /**
     * @template K
     * @template U
     * @template V
     *
     * @param iterable<U> $values
     * @param (callable(U, K): (LocalDate|LocalDateInterval|LocalDateTimeInterval)|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|callable(U, K): V|callable(U, K): iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V>)|null $callable
     *
     * @return iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, array{0: mixed, 1: mixed}>
     */
    private static function assembleValues(iterable $values, ?callable $callable): iterable
    {
        foreach ($values as $valueKey => $value) {
            if (null === $callable) {
                /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $value */
                yield $value => [$value, $value];
                continue;
            }

            /**
             * @var LocalDate|LocalDateInterval|LocalDateTimeInterval|iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval>|iterable<LocalDate|LocalDateInterval|LocalDateTimeInterval, V> $result
             */
            $result = $callable($value, $valueKey);

            foreach (is_iterable($result) ? $result : [$result] as $timeRange => $newValue) {
                // The user returned an array of ranges or yielded only ranges, we need to reverse the arguments
                if (!is_object($timeRange) && is_object($newValue)) {
                    $timeRange = $newValue;
                    $newValue = $value;
                }

                /** @var LocalDate|LocalDateInterval|LocalDateTimeInterval $timeRange */
                yield $timeRange => [$newValue, $value];
            }
        }
    }

    /**
     * @return T|null
     */
    private function valueFor(LocalDate|LocalDateInterval|LocalDateTimeInterval $range): mixed
    {
        $timeline = $this->keep($range);
        if (empty($timeline->items)) {
            return null;
        }
        Assert::maxCount($timeline->items, 1, 'Found more than one value in this time range.');

        /** @var Generator<LocalDateTimeInterval, T> $generator */
        $generator = $timeline->getIterator();

        /** @var LocalDateTimeInterval $itemTimeRange */
        $itemTimeRange = $generator->key();
        Assert::true($itemTimeRange->isEqualTo($range), 'Found one value in this time range, but it does not cover the entire range.');

        return $generator->current();
    }
}
