<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra;

use Brick\DateTime\Duration;
use Brick\DateTime\Instant;
use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\LocalTime;
use Brick\DateTime\Period;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Gammadia\DateTimeExtra\Exceptions\IntervalParseException;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Stringable;
use Traversable;
use Webmozart\Assert\Assert;
use function count;
use function Gammadia\Collections\Functional\contains;
use function Gammadia\Collections\Functional\filter;

#[ORM\Embeddable]
final class LocalDateTimeInterval implements JsonSerializable, Stringable
{
    private function __construct(
        #[ORM\Column(type: 'local_datetime')]
        private ?LocalDateTime $start,

        #[ORM\Column(type: 'local_datetime')]
        private ?LocalDateTime $end,
    ) {
        // Using Assert would have a big performance cost because of the huge number of casts of LocalDateTime to string
        if (null !== $start && null !== $end && $start->isAfter($end)) {
            throw new InvalidArgumentException(sprintf('Start after end: %s / %s', $start, $end));
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /**
     * Creates a finite half-open interval between given time points (inclusive start, exclusive end).
     */
    public static function between(?LocalDateTime $start, ?LocalDateTime $end): self
    {
        return new self($start, $end);
    }

    public static function empty(LocalDateTime $timepoint): self
    {
        return new self($timepoint, $timepoint);
    }

    /**
     * Creates an infinite half-open interval since given start (inclusive).
     */
    public static function since(LocalDateTime $timepoint): self
    {
        return new self($timepoint, null);
    }

    /**
     * Creates an infinite open interval until given end (exclusive).
     */
    public static function until(LocalDateTime $timepoint): self
    {
        return new self(null, $timepoint);
    }

    /**
     * Creates an infinite interval.
     */
    public static function forever(): self
    {
        return new self(null, null);
    }

    public static function day(LocalDate|LocalDateTime $day): self
    {
        $startOfDay = $day instanceof LocalDateTime
            ? $day->withTime(LocalTime::min())
            : $day->atTime(LocalTime::min());

        return new self($startOfDay, $startOfDay->plusDays(1));
    }

    /**
     * If the type of the input argument is known at call-site, usage of the following explicit methods is preferred :
     * - {@see LocalDateTimeInterval::day()}
     * - {@see LocalDateTimeInterval::empty()}
     */
    public static function cast(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): self
    {
        return match (true) {
            $temporal instanceof LocalDate => self::day($temporal),
            $temporal instanceof LocalDateTime => self::empty($temporal),
            $temporal instanceof LocalDateInterval => new self(
                start: $temporal->getStart()?->atTime(LocalTime::min()),
                end: $temporal->getEnd()?->atTime(LocalTime::min())->plusDays(1),
            ),
            default => $temporal,
        };
    }

    /**
     * Creates an interval that contains (encompasses) every provided intervals
     *
     * Returns new timestamp interval or null if the input is empty
     */
    public static function containerOf(null|self|LocalDate|LocalDateTime|LocalDateInterval ...$temporals): ?self
    {
        $starts = $ends = [];
        foreach ($temporals as $temporal) {
            switch (true) {
                case null === $temporal:
                    continue 2;
                case $temporal instanceof LocalDate:
                    $start = $temporal->atTime(LocalTime::min());
                    $starts[] = $start;
                    $ends[] = $start->plusDays(1);
                    break;
                case $temporal instanceof LocalDateTime:
                    $starts[] = $temporal;
                    $ends[] = $temporal;
                    break;
                case $temporal instanceof LocalDateInterval:
                    $starts[] = $temporal->getStart()?->atTime(LocalTime::min());
                    $ends[] = $temporal->getEnd()?->atTime(LocalTime::min())->plusDays(1);
                    break;
                default:
                    $starts[] = $temporal->start;
                    $ends[] = $temporal->end;
                    break;
            }
        }

        return match (count($starts)) {
            0 => null,
            1 => new self($starts[0], $ends[0]),
            default => new self(
                start: contains($starts, value: null) ? null : LocalDateTime::minOf(...$starts),
                end: contains($ends, value: null) ? null : LocalDateTime::maxOf(...$ends),
            ),
        };
    }

    /**
     * Converts this instance to a timestamp interval with
     * dates from midnight to midnight.
     */
    public function toFullDays(): self
    {
        return new self(
            start: $this->start?->withTime(LocalTime::min()),
            end: null === $this->end ? null : (!$this->isEmpty() && $this->end->getTime()->isEqualTo(LocalTime::min())
                ? $this->end
                : $this->end->plusDays(1)->withTime(LocalTime::min())
            ),
        );
    }

    public function isFullDays(): bool
    {
        return $this->isEqualTo($this->toFullDays());
    }

    /**
     * Returns the nullable start time point.
     */
    public function getStart(): ?LocalDateTime
    {
        return $this->start;
    }

    /**
     * Returns the nullable end time point.
     */
    public function getEnd(): ?LocalDateTime
    {
        return $this->end;
    }

    /**
     * @internal this method should only be used if you're absolutely certain of what you're doing (like in Timeline's
     *           internal code)
     */
    public function getInclusiveEnd(): ?LocalDateTime
    {
        return $this->end?->minusNanos(1);
    }

    /**
     * Yields the start time point if not null.
     */
    public function getFiniteStart(): LocalDateTime
    {
        return $this->start ?? throw new RuntimeException(sprintf('The interval "%s" does not have a finite start.', $this));
    }

    /**
     * Yields the end time point if not null.
     */
    public function getFiniteEnd(): LocalDateTime
    {
        return $this->end ?? throw new RuntimeException(sprintf('The interval "%s" does not have a finite end.', $this));
    }

    /**
     * Yields a copy of this interval with given start time.
     */
    public function withStart(?LocalDateTime $timepoint): self
    {
        return new self($timepoint, $this->end);
    }

    /**
     * Yields a copy of this interval with given end time.
     */
    public function withEnd(?LocalDateTime $timepoint): self
    {
        return new self($this->start, $timepoint);
    }

    /**
     * Returns a string representation of this interval.
     */
    public function toString(): string
    {
        return sprintf('%s/%s', $this->start ?? InfinityStyle::SYMBOL, $this->end ?? InfinityStyle::SYMBOL);
    }

    /**
     * Parses the given text as as interval.
     */
    public static function parse(string $text): self
    {
        [$startStr, $endStr] = explode('/', trim($text), 2);

        $startsWithPeriod = str_starts_with($startStr, 'P');
        $startsWithInfinity = InfinityStyle::SYMBOL === $startStr;

        $endsWithPeriod = str_starts_with($endStr, 'P');
        $endsWithInfinity = InfinityStyle::SYMBOL === $endStr;

        if ($startsWithPeriod && $endsWithPeriod) {
            throw IntervalParseException::uniqueDuration($text);
        }

        if (($startsWithPeriod && $endsWithInfinity) || ($startsWithInfinity && $endsWithPeriod)) {
            throw IntervalParseException::durationIncompatibleWithInfinity($text);
        }

        //START
        if ($startsWithInfinity) {
            $ldt1 = null;
        } elseif ($startsWithPeriod) {
            $ldt2 = LocalDateTime::parse($endStr);
            $ldt1 = str_contains($startStr, 'T')
                ? $ldt2->minusDuration(Duration::parse($startStr))
                : $ldt2->minusPeriod(Period::parse($startStr));

            return new self($ldt1, $ldt2);
        } else {
            $ldt1 = LocalDateTime::parse($startStr);
        }

        //END
        if ($endsWithInfinity) {
            $ldt2 = null;
        } elseif ($endsWithPeriod) {
            if (null === $ldt1) {
                throw new RuntimeException('Cannot process end period without start.');
            }
            $ldt2 = str_contains($endStr, 'T')
                ? $ldt1->plusDuration(Duration::parse($endStr))
                : $ldt1->plusPeriod(Period::parse($endStr));
        } else {
            $ldt2 = LocalDateTime::parse($endStr);
        }

        return new self($ldt1, $ldt2);
    }

    /**
     * Moves this interval along the POSIX-axis by the given duration or period.
     */
    public function move(Duration|Period $input): self
    {
        return $input instanceof Period
            ? new self($this->start?->plusPeriod($input), $this->end?->plusPeriod($input))
            : new self($this->start?->plusDuration($input), $this->end?->plusDuration($input));
    }

    /**
     * Return the length of this interval and applies a timezone offset correction.
     *
     * Returns duration including a zonal correction.
     */
    public function getDuration(): Duration
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Returning the duration with infinite boundary is not possible.');
        }

        return Duration::between(
            startInclusive: $this->getUTCInstant($this->start),
            endExclusive: $this->getUTCInstant($this->end),
        );
    }

    /**
     * Iterates through every moments which are the result of adding the given duration or period
     * to the start until the end of this interval is reached.
     *
     * @return Traversable<LocalDateTime>
     */
    public function iterate(Duration|Period $input): Traversable
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Iterate is not supported for infinite intervals.');
        }

        for (
            $start = $this->start;
            $start->isBefore($this->end);
        ) {
            yield $start;

            $start = $input instanceof Period
                ? $start->plusPeriod($input)
                : $start->plusDuration($input);
        }
    }

    /**
     * @return LocalDate[]
     */
    public function days(): array
    {
        $dateRange = LocalDateInterval::containerOf($this);
        Assert::notNull($dateRange, 'The date range cannot be null as we are passing the current time range to containerOf().');

        return $dateRange->days();
    }

    /**
     * Returns slices of this interval.
     *
     * Each slice is at most as long as the given period or duration. The last slice might be shorter.
     *
     * @return Traversable<self>
     */
    public function slice(Duration|Period $input): Traversable
    {
        foreach ($this->iterate($input) as $start) {
            $end = $input instanceof Period
                ? $start->plusPeriod($input)
                : $start->plusDuration($input);

            $end = null !== $this->end
                ? LocalDateTime::minOf($end, $this->end)
                : $end;

            yield new self($start, $end);
        }
    }

    /**
     * Determines if this interval is empty. An interval is empty when the "end" is equal to the "start" boundary.
     */
    public function isEmpty(): bool
    {
        return null !== $this->start
            && null !== $this->end
            && $this->start->isEqualTo($this->end);
    }

    /**
     * Is the finite end of this interval before or equal to the given interval's start?
     */
    public function isBefore(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        if (null === $this->end) {
            return false;
        }

        $timeRange = self::cast($temporal);

        return null !== $timeRange->start && $this->end->isBeforeOrEqualTo($timeRange->start);
    }

    /**
     * Is the finite start of this interval after or equal to the given interval's end?
     */
    public function isAfter(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        if (null === $this->start) {
            return false;
        }

        $timeRange = self::cast($temporal);

        return null !== $timeRange->end && $this->start->isAfterOrEqualTo($timeRange->end);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is a gap between?
     */
    public function precedes(self $other): bool
    {
        return null !== $this->end && null !== $other->start && $this->end->isBefore($other->start);
    }

    /**
     * ALLEN-relation: Equivalent to $other->precedes($this).
     */
    public function precededBy(self $other): bool
    {
        return $other->precedes($this);
    }

    /**
     * ALLEN-relation: Does this interval precede the other one such that
     * there is no gap between?
     */
    public function meets(self $other): bool
    {
        return null !== $this->end && null !== $other->start && $this->end->isEqualTo($other->start);
    }

    /**
     * ALLEN-relation: Equivalent to $other->meets($this).
     */
    public function metBy(self $other): bool
    {
        return $other->meets($this);
    }

    /**
     * ALLEN-relation: Does this interval finish the other one such that
     * both end time points are equal and the start of this interval is after
     * the start of the other one?
     */
    public function finishes(self $other): bool
    {
        return null !== $this->start && (null === $other->start || $this->start->isAfter($other->start))
            && ((null === $this->end && null === $other->end) || (null !== $this->end && null !== $other->end && $this->end->isEqualTo($other->end)));
    }

    /**
     * ALLEN-relation: Equivalent to $other->finishes($this).
     */
    public function finishedBy(self $other): bool
    {
        return $other->finishes($this);
    }

    /**
     * ALLEN-relation: Does this interval start the other one such that both
     * start time points are equal and the end of this interval is before the
     * end of the other one?
     */
    public function starts(self $other): bool
    {
        return ((null === $this->start && null === $other->start) || (null !== $this->start && null !== $other->start && $this->start->isEqualTo($other->start)))
            && null !== $this->end && (null === $other->end || $this->end->isBefore($other->end));
    }

    /**
     * ALLEN-relation: Equivalent to $other->starts($this).
     */
    public function startedBy(self $other): bool
    {
        return $other->starts($this);
    }

    /**
     * ALLEN-relation: Does this interval enclose the other one such that
     * this start is before the start of the other one and this end is after
     * the end of the other one?
     */
    public function encloses(self $other): bool
    {
        return null !== $other->start && (null === $this->start || $this->start->isBefore($other->start))
            && null !== $other->end && (null === $this->end || $this->end->isAfter($other->end));
    }

    /**
     * ALLEN-relation: Equivalent to $other->encloses($this).
     */
    public function enclosedBy(self $other): bool
    {
        return $other->encloses($this);
    }

    /**
     * ALLEN-relation: Does this interval overlaps the other one such that
     * the start of this interval is before the start of the other one and
     * the end of this interval is after the start of the other one but still
     * before the end of the other one?
     */
    public function overlaps(self $other): bool
    {
        return null !== $other->start && (null === $this->start || $this->start->isBefore($other->start))
            && null !== $this->end && $this->end->isAfter($other->start) && (null === $other->end || $this->end->isBefore($other->end));
    }

    /**
     * ALLEN-relation: Equivalent to $other->overlaps($this).
     */
    public function overlappedBy(self $other): bool
    {
        return $other->overlaps($this);
    }

    /**
     * Queries whether or not an interval contains another interval.
     * One interval contains another if it stays within its bounds.
     * An empty interval never contains anything.
     *
     * Implementation is as follow :
     * - the other's start must not be before the start
     * - the other's start must be before the end
     * - the other's end must not be after the end
     */
    public function contains(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        $other = self::cast($temporal);

        return (null === $this->start || (null !== $other->start && !$other->start->isBefore($this->start)))
            && (null === $this->end || null === $other->start || $other->start->isBefore($this->end))
            && (null === $this->end || (null !== $other->end && !$other->end->isAfter($this->end)));
    }

    /**
     * Queries whether or not an interval intersects with another interval.
     * An interval intersects with another if they have a common timepoint.
     * Empty intervals intersects only if within the non-inclusive bounds (even the start!) of a non-empty interval.
     *
     * This differs from {@see LocalDateTimeInterval::contains()} because it also returns true for an interval
     * that crosses a boundary of the other interval.
     *
     * This method is commutative (A intersects B if and only if B intersects A).
     *
     * Implementation is as follow :
     * - one's start must be before the other's end
     * - one's end must be after the other's start
     */
    public function intersects(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        $other = self::cast($temporal);

        return (null === $this->start || null === $other->end || $this->start->isBefore($other->end))
            && (null === $this->end || null === $other->start || $this->end->isAfter($other->start));
    }

    /**
     * Queries whether or not an interval sees another interval.
     * An interval sees another if :
     * 1) they are non-empty intervals with a common timepoint, or
     * 2) they are both empty and are equal, or
     * 3) one empty interval is within the other's non-empty interval's bounds.
     *
     * This differs from {@see LocalDateTimeInterval::intersects()} because it also returns true for an empty interval
     * exactly at the start of the other interval.
     *
     * This method is commutative (A sees B if and only if B sees A).
     *
     * Implementation is as follow :
     * - if both intervals are empty, they must be the same
     * - if one of them is empty, the non-empty one must contain it
     * - otherwise, they must intersect
     */
    public function sees(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        $other = self::cast($temporal);

        if ($this->isEmpty()) {
            return $other->isEmpty() ? $other->isEqualTo($this) : $other->contains($this);
        }
        if ($other->isEmpty()) {
            return $this->contains($other);
        }

        return $this->intersects($other);
    }

    /**
     * Queries if this interval abuts the other one such that there is neither any overlap nor any gap between.
     *
     * Equivalent to the expression {@code this.meets(other) ^ this.metBy(other)}. Empty intervals never abut.
     */
    public function abuts(self $other): bool
    {
        return (bool) ($this->meets($other) ^ $this->metBy($other));
    }

    /**
     * Changes this interval to an empty interval with the same start anchor.
     *
     * Returns empty interval with same start (anchor always inclusive).
     */
    public function collapse(): self
    {
        if (null === $this->start) {
            throw new RuntimeException('An interval with infinite start cannot be collapsed.');
        }

        return new self($this->start, $this->start);
    }

    public function expand(null|self|LocalDate|LocalDateTime|LocalDateInterval ...$temporals): self
    {
        $temporals = filter($temporals);
        if ([] === $temporals) {
            return $this;
        }

        $expanded = self::containerOf($this, ...$temporals);
        Assert::notNull($expanded, 'The result of containerOf() cannot be null as $this is always provided.');

        return $expanded;
    }

    /**
     * Obtains the intersection of this interval and other one if present.
     *
     * Returns a wrapper around the found intersection (which can be empty) or null.
     */
    public function findIntersection(null|self|LocalDate|LocalDateTime|LocalDateInterval $temporal): ?self
    {
        if (null === $temporal) {
            return null;
        }

        $other = self::cast($temporal);
        if (!$this->intersects($other)) {
            return null;
        }

        if (null === $this->start && null === $other->start) {
            $start = null;
        } elseif (null === $this->start) {
            $start = $other->start;
        } elseif (null === $other->start) {
            $start = $this->start;
        } else {
            $start = LocalDateTime::maxOf($this->start, $other->start);
        }

        if (null === $this->end && null === $other->end) {
            $end = null;
        } elseif (null === $this->end) {
            $end = $other->end;
        } elseif (null === $other->end) {
            $end = $this->end;
        } else {
            $end = LocalDateTime::minOf($this->end, $other->end);
        }

        return new self($start, $end);
    }

    /**
     * Compares the boundaries (start and end) of this and the other interval.
     */
    public function isEqualTo(null|self|LocalDate|LocalDateTime|LocalDateInterval $temporal): bool
    {
        if (null === $temporal) {
            return false;
        }

        $other = self::cast($temporal);

        return ((null !== $this->start && null !== $other->start && $this->start->isEqualTo($other->start)) || (null === $this->start && null === $other->start))
            && ((null !== $this->end && null !== $other->end && $this->end->isEqualTo($other->end)) || (null === $this->end && null === $other->end));
    }

    public function compareTo(self|LocalDate|LocalDateTime|LocalDateInterval $temporal): int
    {
        $other = self::cast($temporal);

        if (null === $this->start) {
            if (null !== $other->start) {
                return -1;
            }
        } elseif (null === $other->start) {
            return 1;
        } else {
            $order = $this->start->compareTo($other->start);
            if (0 !== $order) {
                return $order;
            }
        }
        // At this point, both intervals have the same start

        if (null === $this->end) {
            return null === $other->end ? 0 : 1;
        }
        if (null === $other->end) {
            return -1;
        }

        return $this->end->compareTo($other->end);
    }

    /**
     * Determines if this interval has finite boundaries.
     */
    public function isFinite(): bool
    {
        return null !== $this->start && null !== $this->end;
    }

    /**
     * Determines if this interval has infinite start boundary.
     */
    public function hasInfiniteStart(): bool
    {
        return null === $this->start;
    }

    /**
     * Determines if this interval has infinite end boundary.
     */
    public function hasInfiniteEnd(): bool
    {
        return null === $this->end;
    }

    private function getUTCInstant(LocalDateTime $timepoint): Instant
    {
        static $utc;
        $utc ??= new DateTimeZone('UTC');

        return Instant::of(
            epochSecond: (new DateTimeImmutable((string) $timepoint->withNano(0), $utc))->getTimestamp(),
            nanoAdjustment: $timepoint->getNano(),
        );
    }
}
