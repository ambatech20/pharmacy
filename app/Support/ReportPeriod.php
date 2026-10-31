<?php

namespace App\Support;

use App\Models\IssuedItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The months / year a report is being read for.
 *
 * The reports screens let the reader tick any number of months in one year.
 * Ticking January and March means January and March only - not the whole
 * January-to-March span - because the shop often wants "the two months we
 * closed early" rather than a range. A year on its own still covers all twelve
 * months, and both blank means all time.
 *
 * `month` and `range()` are kept for the single-month callers and for old
 * bookmarked URLs. New callers should use `months()` and `ranges()`.
 *
 * Values are sanitised rather than validated. A hand-edited URL saying
 * month=13 should quietly mean "all months", not bounce the reader off the
 * report with a validation error.
 */
class ReportPeriod
{
    public const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /** The single month, when exactly one is ticked. Null otherwise. */
    public readonly ?int $month;

    /** @var array<int, int> Ticked months, ascending. Empty means "all". */
    public readonly array $months;

    public function __construct(
        ?int $month,
        public readonly ?int $year,
        public readonly ?int $branchId = null,
        array $months = [],
    ) {
        $months = array_values(array_unique(array_filter(
            array_map('intval', $months),
            fn (int $value) => $value >= 1 && $value <= 12,
        )));
        sort($months);

        if ($months === [] && $month !== null) {
            $months = [$month];
        }

        $this->months = $months;
        $this->month = count($months) === 1 ? $months[0] : null;
    }

    public static function fromRequest(Request $request, ?int $branchId = null): self
    {
        $rawMonths = $request->input('months', []);
        if (! is_array($rawMonths)) {
            $rawMonths = [];
        }

        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;

        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : null;

        $months = array_values(array_unique(array_filter(
            array_map('intval', $rawMonths),
            fn (int $value) => $value >= 1 && $value <= 12,
        )));
        sort($months);

        if ($months === [] && $month !== null) {
            $months = [$month];
        }

        // Ticking months without a year means the current year, so the screen
        // does not silently show nothing after a fresh install.
        if ($months !== [] && $year === null) {
            $year = (int) now()->year;
        }

        return new self($month, $year, $branchId, $months);
    }

    public function isFiltered(): bool
    {
        return $this->months !== [] || $this->year !== null;
    }

    /**
     * One [start, end] pair per ticked month, or one pair for a whole year.
     * Null means all time.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>|null
     */
    public function ranges(): ?array
    {
        if ($this->months !== []) {
            $year = $this->year ?? (int) now()->year;

            return array_map(function (int $month) use ($year) {
                $start = Carbon::create($year, $month, 1)->startOfDay();

                return [$start, $start->copy()->endOfMonth()->endOfDay()];
            }, $this->months);
        }

        if ($this->year) {
            return [[
                Carbon::create($this->year, 1, 1)->startOfDay(),
                Carbon::create($this->year, 12, 31)->endOfDay(),
            ]];
        }

        return null;
    }

    /**
     * A single bounded range for callers that cannot express a list. For
     * several months this is the span from the first to the last; anything
     * that needs the gaps left out should use ranges().
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function range(): ?array
    {
        $ranges = $this->ranges();

        if ($ranges === null) {
            return null;
        }

        if (count($ranges) === 1) {
            return $ranges[0];
        }

        $starts = array_map(fn (array $range) => $range[0], $ranges);
        $ends = array_map(fn (array $range) => $range[1], $ranges);

        return [min($starts), max($ends)];
    }

    /**
     * The last moment the period covers - what a balance sheet is a snapshot
     * of. Null means "right now", the unfiltered case.
     */
    public function end(): ?Carbon
    {
        $ranges = $this->ranges();

        if ($ranges === null) {
            return null;
        }

        return max(array_map(fn (array $range) => $range[1], $ranges));
    }

    /**
     * Plain-English name for the card headings, so a figure on screen always
     * says which period produced it.
     */
    public function label(): string
    {
        if ($this->months !== []) {
            $year = $this->year ?? (int) now()->year;

            if (count($this->months) === 12) {
                return 'All of ' . $year;
            }

            $names = array_map(fn (int $month) => self::MONTH_NAMES[$month], $this->months);

            return implode(', ', $names) . ' ' . $year;
        }

        if ($this->year) {
            return 'All of ' . $this->year;
        }

        return 'All time';
    }

    /**
     * Every year from the first sale up to this one, newest first.
     *
     * Derived from MIN(issue_date) rather than a DISTINCT YEAR(...) so it
     * behaves identically on MySQL and on the SQLite the test suite runs on.
     */
    public function years(): array
    {
        $firstSale = IssuedItem::query()
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->min('issue_date');

        $currentYear = (int) now()->year;
        $firstYear = $firstSale ? (int) Carbon::parse($firstSale)->year : $currentYear;

        return range($currentYear, max(2000, min($firstYear, $currentYear)));
    }
}
