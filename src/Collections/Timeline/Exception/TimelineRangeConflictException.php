<?php

declare(strict_types=1);

namespace Gammadia\Collections\Timeline\Exception;

use Gammadia\DateTimeExtra\LocalDateTimeInterval;
use RangeException;
use Throwable;

class TimelineRangeConflictException extends RangeException
{
    public function __construct(
        private LocalDateTimeInterval $conflictingTimeRange,
        private LocalDateTimeInterval $existingTimeRange,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('"%s" conflicts with "%s".', $conflictingTimeRange, $existingTimeRange),
            0,
            $previous,
        );
    }

    protected function conflictingTimeRange(): LocalDateTimeInterval
    {
        return $this->conflictingTimeRange;
    }

    protected function existingTimeRange(): LocalDateTimeInterval
    {
        return $this->existingTimeRange;
    }
}
