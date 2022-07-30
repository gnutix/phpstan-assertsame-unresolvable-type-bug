<?php

declare(strict_types=1);

namespace Gammadia\Collections\Test\Unit\Timeline;

use Gammadia\Collections\Timeline\Timeline;
use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use PHPUnit\Framework\TestCase;
use function Gammadia\Collections\Functional\concat;

/**
 * @coversDefaultClass \Gammadia\Collections\Timeline\Timeline
 */
final class TimelineTest extends TestCase
{
    public function testZip(): void
    {
        // Zipping many timelines
        $timeline1 = Timeline::constant(1);
        $timeline2 = Timeline::with(LocalDateTimeInterval::parse('2020-01-01T00:00/2020-01-04T00:00'), 2);
        $timeline3 = Timeline::with(LocalDateTimeInterval::parse('2020-01-01T00:00/2020-01-05T00:00'), 3);
        $timeline4 = Timeline::with(LocalDateTimeInterval::parse('2020-01-03T00:00/-'), 4);
        $expected = [
            [1, null, null, null],
            [1, 2, 3, null],
            [1, 2, 3, 4],
            [1, null, 3, 4],
            [1, null, null, 4],
        ];

        // Dumped type: array{array{1, null, null, null}, array{1, 2, 3, null}, array{1, 2, 3, 4}, array{1, null, 3, 4}, array{1, null, null, 4}}
        // \PHPStan\dumpType($expected);
        self::assertSame($expected, $this->values($timeline1->zip($timeline2, $timeline3, $timeline4)));

        // Dumped type: array{array{1, *NEVER*, *NEVER*, *NEVER*}, array{1, 2, 3, *NEVER*}, array{1, 2, 3, 4}, array{1, *NEVER*, 3, 4}, array{1, *NEVER*, *NEVER*, 4}}
        // \PHPStan\dumpType($expected);
        self::assertSame($expected, $this->values(Timeline::zipAll($timeline1, $timeline2, $timeline3, $timeline4)));
    }

    /**
     * @template T
     *
     * @param Timeline<T> $timeline
     *
     * @return T[]
     */
    private function values(Timeline $timeline): array
    {
        return $timeline->reduce(static fn (array $carry, $value): array => concat($carry, [$value]), initial: []);
    }
}
