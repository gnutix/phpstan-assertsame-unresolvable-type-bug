<?php

declare(strict_types=1);

namespace Gammadia\DateTimeExtra;

use Brick\DateTime\LocalDate;
use Brick\DateTime\LocalDateTime;
use Brick\DateTime\Period;
use Brick\DateTime\YearWeek;
use Countable;
use Doctrine\ORM\Mapping as ORM;
use Gammadia\DateTimeExtra\Exceptions\IntervalParseException;
use JsonSerializable;
use RuntimeException;
use Stringable;
use Traversable;
use Webmozart\Assert\Assert;
use function count;
use function Gammadia\Collections\Functional\contains;
use function Gammadia\Collections\Functional\filter;
use function Gammadia\Collections\Functional\map;
use function Gammadia\Collections\Functional\sort;

#[ORM\Embeddable]
final class LocalDateInterval implements JsonSerializable, Countable, Stringable
{
    private function __construct(
        #[ORM\Column(type: 'local_date')]
        private ?LocalDate $start,

        #[ORM\Column(type: 'local_date')]
        private ?LocalDate $end,
    ) {
        Assert::false($start && $end && $start->isAfter($end), sprintf('Start after end: %s / %s', $start, $end));
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
     * Creates a closed interval between given dates.
     */
    public static function between(?LocalDate $start, ?LocalDate $end): self
    {
        return new self($start, $end);
    }

    /**
     * Creates an infinite interval since given start date.
     */
    public static function since(LocalDate $start): self
    {
        return new self($start, null);
    }

    /**
     * Creates an infinite interval until given end date.
     */
    public static function until(LocalDate $end): self
    {
        return new self(null, $end);
    }

    /**
     * Creates a closed interval including only given date.
     */
    public static function atomic(LocalDate $date): self
    {
        return new self($date, $date);
    }

    public static function forever(): self
    {
        return new self(null, null);
    }

    /**
     * Creates an interval that contains (encompasses) every provided intervals
     *
     * Returns new timestamp interval or null if the input is empty
     */
    public static function containerOf(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval ...$temporals): ?self
    {
        $starts = $ends = [];
        foreach ($temporals as $temporal) {
            switch (true) {
                case null === $temporal:
                    continue 2;
                case $temporal instanceof LocalDate:
                    $starts[] = $temporal;
                    $ends[] = $temporal;
                    break;
                case $temporal instanceof LocalDateTime:
                    $starts[] = $temporal->getDate();
                    $ends[] = $temporal->getDate();
                    break;
                case $temporal instanceof LocalDateTimeInterval:
                    $starts[] = $temporal->getStart()?->getDate();
                    $ends[] = $temporal->toFullDays()->getEnd()?->getDate()->minusDays(1);
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
                start: contains($starts, value: null) ? null : LocalDate::minOf(...$starts),
                end: contains($ends, value: null) ? null : LocalDate::maxOf(...$ends),
            ),
        };
    }

    /**
     * @return array<self>
     */
    public static function disjointContainersOf(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval ...$temporals): array
    {
        $temporals = filter($temporals);
        if ([] === $temporals) {
            return [];
        }

        $dateRanges = sort(
            map($temporals, static fn (self|LocalDate|LocalDateTime|LocalDateTimeInterval $temporal): LocalDateInterval
                => self::safeContainerOf($temporal),
            ),
            static fn (LocalDateInterval $a, LocalDateInterval $b): int => $a->compareTo($b),
        );

        /** @var array{start: LocalDate|null, end: LocalDate|null}|null $nextContainer */
        $nextContainer = null;
        $containers = [];
        foreach ($dateRanges as $dateRange) {
            if (null === $nextContainer) {
                $nextContainer = ['start' => $dateRange->start, 'end' => $dateRange->end];
            } elseif (null !== $nextContainer['end']
                && null !== $dateRange->start
                && $nextContainer['end']->isBefore($dateRange->start->minusDays(1))
            ) {
                $containers[] = new self($nextContainer['start'], $nextContainer['end']);
                $nextContainer = ['start' => $dateRange->start, 'end' => $dateRange->end];
            } elseif (null === $nextContainer['end'] || null === $dateRange->end) {
                $containers[] = new self($nextContainer['start'], null);

                return $containers;
            } elseif ($dateRange->end->isAfter($nextContainer['end'])) {
                $nextContainer['end'] = $dateRange->end;
            }
        }

        Assert::notNull($nextContainer);

        $containers[] = new self($nextContainer['start'], $nextContainer['end']);

        return $containers;
    }

    public static function forWeek(YearWeek $yearWeek): self
    {
        return new self($yearWeek->getFirstDay(), $yearWeek->getLastDay());
    }

    public function expand(null|self|LocalDate|LocalDateTime|LocalDateTimeInterval ...$others): self
    {
        $others = filter($others);
        if ([] === $others) {
            return $this;
        }

        return self::safeContainerOf($this, ...$others);
    }

    public function toFullWeeks(): self
    {
        return new self(
            start: $this->start?->minusDays($this->start->getDayOfWeek()->getValue() - 1),
            end: $this->end?->plusDays(7 - $this->end->getDayOfWeek()->getValue()),
        );
    }

    /**
     * Yields the length of this interval in days.
     */
    public function getLengthInDays(): int
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('An infinite interval has no finite duration.');
        }

        return $this->start->daysUntil($this->end) + 1;
    }

    /**
     * Yields the length of this interval in given calendrical units.
     */
    public function getPeriod(): Period
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('An infinite interval has no finite duration.');
        }

        return Period::between($this->start, $this->end->plusDays(1));
    }

    /**
     * Moves this interval along the time axis by given units.
     */
    public function move(Period $period): self
    {
        return new self($this->start?->plusPeriod($period), $this->end?->plusPeriod($period));
    }

    /**
     * Obtains a stream iterating over every calendar date between given interval boundaries.
     *
     * @return Traversable<LocalDate>
     */
    public static function iterateDaily(LocalDate $start, LocalDate $end): Traversable
    {
        return (new self($start, $end))->iterate(Period::ofDays(1));
    }

    /**
     * Obtains a stream iterating over every calendar date which is the result of addition of given duration
     * to start until the end of this interval is reached.
     *
     * @return Traversable<LocalDate>
     */
    public function iterate(Period $period): Traversable
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Iterate is not supported for infinite interval.');
        }

        for (
            $start = $this->start;
            $start->isBeforeOrEqualTo($this->end);
             $start = $start->plusPeriod($period)
        ) {
            yield $start;
        }
    }

    /**
     * @return LocalDate[]
     */
    public function days(): array
    {
        return iterator_to_array($this->iterate(Period::ofDays(1)), false);
    }

    /**
     * @return int<1, max>
     */
    public function count(): int
    {
        if (null === $this->start || null === $this->end) {
            throw new RuntimeException('Count is not supported for infinite interval.');
        }

        $count = $this->end->toEpochDay() - $this->start->toEpochDay() + 1;
        Assert::positiveInteger($count, 'The number of days of a date range must be 1 or more.');

        return $count;
    }

    /**
     * Interpretes given ISO-conforming text as interval.
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
            $ld1 = null;
        } elseif ($startsWithPeriod) {
            $ld2 = LocalDate::parse($endStr);
            $ld1 = $ld2->minusPeriod(Period::parse($startStr));

            return new self($ld1, $ld2);
        } else {
            $ld1 = LocalDate::parse($startStr);
        }

        //END
        if ($endsWithInfinity) {
            $ld2 = null;
        } elseif ($endsWithPeriod) {
            if (null === $ld1) {
                throw new RuntimeException('Cannot process end period without start.');
            }
            $ld2 = $ld1->plusPeriod(Period::parse($endStr));
        } else {
            $ld2 = LocalDate::parse($endStr);
        }

        return new self($ld1, $ld2);
    }

    /**
     * Yields a descriptive string of start and end.
     */
    public function toString(): string
    {
        return sprintf(
            '%s/%s',
            $this->getStartIso(),
            $this->getEndIso(),
        );
    }

    public function getStart(): ?LocalDate
    {
        return $this->start;
    }

    public function getEnd(): ?LocalDate
    {
        return $this->end;
    }

    public function getStartIso(): string
    {
        return null === $this->start ? InfinityStyle::SYMBOL : (string) $this->start;
    }

    public function getEndIso(): string
    {
        return null === $this->end ? InfinityStyle::SYMBOL : (string) $this->end;
    }

    /**
     * Yields a copy of this interval with given start time.
     */
    public function withStart(?LocalDate $startDate): self
    {
        return new self($startDate, $this->end);
    }

    /**
     * Yields a copy of this interval with given end time.
     */
    public function withEnd(?LocalDate $endDate): self
    {
        return new self($this->start, $endDate);
    }

    /**
     * Is this interval before the given time point?
     */
    public function isBefore(LocalDate $date): bool
    {
        return null !== $this->end && $this->end->isBefore($date);
    }

    public function isBeforeInterval(self $other): bool
    {
        return null !== $this->end && null !== $other->start && $this->end->isBefore($other->start);
    }

    /**
     * Is this interval after the other one?
     */
    public function isAfter(LocalDate $date): bool
    {
        return null !== $this->start && $this->start->isAfter($date);
    }

    public function isAfterInterval(self $other): bool
    {
        return null !== $this->start && null !== $other->end && $this->start->isAfter($other->end);
    }

    /**
     * Queries if given time point belongs to this interval.
     */
    public function contains(LocalDate $date): bool
    {
        return (null === $this->start || $this->start->isBeforeOrEqualTo($date))
            && (null === $this->end || $this->end->isAfterOrEqualTo($date));
    }

    public function containsInterval(self $other): bool
    {
        return (null === $this->start || (null !== $other->start && $this->start->isBeforeOrEqualTo($other->start)))
            && (null === $this->end || (null !== $other->end && $this->end->isAfterOrEqualTo($other->end)));
    }

    /**
     * Compares the boundaries (start and end) and also the time axis
     * of this and the other interval.
     */
    public function isEqualTo(self $other): bool
    {
        return ((null !== $this->start && null !== $other->start && $this->start->isEqualTo($other->start)) || (null === $this->start && null === $other->start))
            && ((null !== $this->end && null !== $other->end && $this->end->isEqualTo($other->end)) || (null === $this->end && null === $other->end));
    }

    public function compareTo(self $other): int
    {
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
     * ALLEN-relation: Does this interval precede the other one such that
     * there is a gap between?
     */
    public function precedes(self $other): bool
    {
        return null !== $this->end && null !== $other->start && $this->end->isBefore($other->start->minusDays(1));
    }

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
        return null !== $this->end && null !== $other->start && $this->end->isEqualTo($other->start->minusDays(1));
    }

    public function metBy(self $other): bool
    {
        return $other->meets($this);
    }

    /**
     * ALLEN-relation: Does this interval overlaps the other one such that
     * the start of this interval is still before the start of the other
     * one?
     */
    public function overlaps(self $other): bool
    {
        return null !== $other->start && (null === $this->start || $this->start->isBefore($other->start))
            && null !== $this->end && $this->end->isAfterOrEqualTo($other->start) && (null === $other->end || $this->end->isBeforeOrEqualTo($other->end));
    }

    public function overlappedBy(self $other): bool
    {
        return $other->overlaps($this);
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

    public function enclosedBy(self $other): bool
    {
        return $other->encloses($this);
    }

    /**
     * Queries if this interval intersects the other one such that there is at least one common time point.
     */
    public function intersects(self $other): bool
    {
        return (null === $this->start || null === $other->end || $this->start->isBeforeOrEqualTo($other->end))
            && (null === $this->end || null === $other->start || $this->end->isAfterOrEqualTo($other->start));
    }

    /**
     * Obtains the intersection of this interval and other one if present.
     */
    public function findIntersection(null|self $other): ?self
    {
        if (null === $other) {
            return null;
        }

        if ($this->intersects($other)) {
            if (null === $this->start || null === $other->start) {
                $start = $this->start ?? $other->start;
            } else {
                $start = LocalDate::maxOf($this->start, $other->start);
            }

            if (null === $this->end || null === $other->end) {
                $end = $this->end ?? $other->end;
            } else {
                $end = LocalDate::minOf($this->end, $other->end);
            }

            return new self($start, $end);
        }

        return null;
    }

    /**
     * Queries if this interval abuts the other one such that there is neither any overlap nor any gap between.
     */
    public function abuts(self $other): bool
    {
        return (bool) ($this->meets($other) ^ $this->metBy($other));
    }

    public function hasInfiniteStart(): bool
    {
        return null === $this->start;
    }

    public function hasInfiniteEnd(): bool
    {
        return null === $this->end;
    }

    public function isFinite(): bool
    {
        return null !== $this->start && null !== $this->end;
    }

    public function getFiniteEnd(): LocalDate
    {
        if (null === $this->end) {
            throw new RuntimeException(sprintf('The interval "%s" does not have a finite end.', $this));
        }

        return $this->end;
    }

    public function getFiniteStart(): LocalDate
    {
        if (null === $this->start) {
            throw new RuntimeException(sprintf('The interval "%s" does not have a finite start.', $this));
        }

        return $this->start;
    }

    /**
     * @todo Remove once a PHPStan extension has been written and containerOf() does not need Asserts anymore
     */
    private static function safeContainerOf(self|LocalDate|LocalDateTime|LocalDateTimeInterval ...$temporals): self
    {
        $container = self::containerOf(...$temporals);
        Assert::notNull($container, sprintf('You cannot give an empty array to %s.', __METHOD__));

        return $container;
    }
}
