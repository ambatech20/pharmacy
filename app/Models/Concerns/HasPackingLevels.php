<?php

namespace App\Models\Concerns;

/**
 * The packing rungs shared by drugs and products.
 *
 * Stock is stored in exactly one unit - the smallest rung (unit_base) - in
 * purchase_batches.quantity_received / quantity_remaining. Everything here is
 * about translating between that number and the way the shop talks: "2 packs",
 * "3 strips", "47.30 a capsule".
 *
 * The rungs are described per record rather than assumed, because they
 * genuinely differ: a box of 100 strips of 10 tablets, a box of 20 sachets, a
 * bottle of syrup sold whole. Rows that predate the packing columns fall back
 * to the old fixed geometry, so nothing that predates this survives as a
 * special case.
 */
trait HasPackingLevels
{
    /** The words that describe a container rather than a countable piece. */
    public const CONTAINER_UNITS = ['pack', 'box', 'bottle', 'tin', 'vial', 'tube'];

    /** Loose pieces a shelf count is never expressed in. */
    private const LOOSE_BASE_UNITS = ['tablet', 'capsule', 'sachet', 'suppository', 'ampoule', 'plaster', 'pessary'];

    /**
     * The rungs of this record's packing, biggest first.
     *
     * Each rung carries:
     *   key       stable handle: top / middle / base
     *   name      what the shop calls it: pack, strip, capsule, sachet
     *   factor    how many base units one of these is
     *   sellable  may it leave the shelf in this unit
     *
     * @return array<int, array{key:string, name:string, factor:int, sellable:bool}>
     */
    public function packingLevels(): array
    {
        if (! $this->unit_top) {
            return $this->legacyPackingLevels();
        }

        $topName = strtolower(trim((string) $this->unit_top));
        $baseName = strtolower(trim((string) ($this->unit_base ?: 'tablet')));

        // A container cannot contain itself. Rows imported before the packing
        // form was fixed can say "tin holding tins"; counting that as a rung
        // would make 30 tins read as one. It is one countable unit.
        if ($topName === $baseName || $topName === '') {
            return [[
                'key' => 'top',
                'name' => $topName !== '' ? $topName : $baseName,
                'factor' => 1,
                'sellable' => (bool) ($this->sell_top ?? true),
            ]];
        }

        // Without a count we cannot say how many fit inside. Stock is stored in
        // the base unit, so show that rather than inventing a 1:1 container.
        // The edit form asks for the missing count before this can be saved.
        if ($this->top_holds === null || (int) $this->top_holds < 1) {
            return [$this->baseLevel()];
        }

        $levels = [[
            'key' => 'top',
            'name' => $topName,
            'factor' => max(1, $this->unitsPerTop()),
            'sellable' => (bool) ($this->sell_top ?? true),
        ]];

        $middleName = strtolower(trim((string) $this->unit_middle));

        // A middle rung that shares a name with either neighbour is an imported
        // duplicate, not a real rung. Skip it; the factors were already
        // preserved by unitsPerTop().
        if ($middleName !== '' && $middleName !== $topName && $middleName !== $baseName) {
            $levels[] = [
                'key' => 'middle',
                'name' => $middleName,
                'factor' => max(1, (int) $this->middle_holds),
                'sellable' => (bool) ($this->sell_middle ?? true),
            ];
        }

        $levels[] = $this->baseLevel();

        return $levels;
    }

    /**
     * @return array{key:string, name:string, factor:int, sellable:bool}
     */
    private function baseLevel(): array
    {
        return [
            'key' => 'base',
            'name' => strtolower((string) ($this->unit_base ?: 'tablet')),
            'factor' => 1,
            'sellable' => (bool) ($this->sell_base ?? true),
        ];
    }

    /**
     * The old fixed geometry, expressed as rungs.
     *
     * This exists for rows that predate the packing columns and for code that
     * still writes only units_per_strip / strips_per_pack. It follows exactly
     * the same three rules as the migration that backfilled the real drugs, so
     * a row built either way ends up described the same way.
     *
     * @return array<int, array{key:string, name:string, factor:int, sellable:bool}>
     */
    private function legacyPackingLevels(): array
    {
        $base = strtolower((string) ($this->unit_of_measure ?: 'tablet'));
        $perStrip = $this->unitsPerStrip();
        $perPack = $this->stripsPerPack();

        // Sold as it arrives: one rung, named after the container, or "pack"
        // when the old column held something that is not a container word.
        if ($perStrip === 1 && $perPack === 1) {
            $only = in_array($base, self::CONTAINER_UNITS, true) ? $base : 'pack';

            return [['key' => 'top', 'name' => $only, 'factor' => 1, 'sellable' => true]];
        }

        // No strip worth naming: a pack of sachets, ampoules or bottles.
        if ($perStrip === 1) {
            return [
                ['key' => 'top', 'name' => 'pack', 'factor' => $perPack, 'sellable' => true],
                ['key' => 'base', 'name' => $base, 'factor' => 1, 'sellable' => true],
            ];
        }

        return [
            ['key' => 'top', 'name' => 'pack', 'factor' => $perStrip * $perPack, 'sellable' => true],
            ['key' => 'middle', 'name' => 'strip', 'factor' => $perStrip, 'sellable' => true],
            ['key' => 'base', 'name' => $base, 'factor' => 1, 'sellable' => true],
        ];
    }

    /** How many base units one top unit holds. */
    public function unitsPerTop(): int
    {
        if (! $this->unit_top) {
            return $this->unitsPerStrip() * $this->stripsPerPack();
        }

        // The same word on both ends is a container holding itself, not a
        // multiplier. Treat it as the single unit it describes.
        if (strcasecmp((string) ($this->unit_base ?? ''), (string) $this->unit_top) === 0) {
            return 1;
        }

        $top = max(1, (int) $this->top_holds);

        return $this->unit_middle
            ? $top * max(1, (int) $this->middle_holds)
            : $top;
    }

    /** The levels a sale may use, biggest first. */
    public function sellableLevels(): array
    {
        return array_values(array_filter(
            $this->packingLevels(),
            fn (array $level) => $level['sellable']
        ));
    }

    /** @return array<int, string> */
    public function sellableUnitKeys(): array
    {
        return array_column($this->sellableLevels(), 'key');
    }

    /**
     * Which rung a screen's unit word means, or null when this record has no
     * such rung.
     *
     * Understands the level key ('top', 'middle', 'base'), the level's own name
     * ('box', 'strip'), and the old position words ('pack', 'strip', 'tablet').
     */
    public function levelKeyFor(string $unit): ?string
    {
        $unit = strtolower(trim($unit));
        $levels = $this->packingLevels();

        // The three old words are positions, and a record may not have a rung
        // at that position any more. A one-rung bottle has no "tablet"; a pack
        // of 30 capsules has no "strip". The old fixed geometry gave those
        // words the factor of the nearest rung it did have (1 when there was
        // none), so collapse the same way rather than rejecting a form that was
        // cached before the record was re-described.
        $fallbacks = [
            'pack' => ['top', 'middle', 'base'],
            'strip' => ['middle', 'base', 'top'],
            'tablet' => ['base', 'top'],
        ];

        if (isset($fallbacks[$unit])) {
            foreach ($fallbacks[$unit] as $position) {
                foreach ($levels as $level) {
                    if ($level['key'] === $position) {
                        return $position;
                    }
                }
            }
        }

        foreach ($levels as $level) {
            if ($level['key'] === $unit || $level['name'] === $unit) {
                return $level['key'];
            }
        }

        return null;
    }

    /**
     * Which rung a posted unit means, or null when this item does not have it.
     *
     * Unlike levelKeyFor(), this never slides a word onto a different rung.
     * "strip" on a pack of loose tablets is refused rather than silently
     * posted as tablets: a purchase or a sale must name a rung the record
     * actually has. It still understands the rung's key, its own name, and
     * the three old position words ('pack', 'strip', 'tablet') when that
     * position exists.
     */
    public function exactLevelKeyFor(string $unit): ?string
    {
        $unit = strtolower(trim($unit));
        $levels = $this->packingLevels();

        foreach ($levels as $level) {
            if ($level['key'] === $unit || $level['name'] === $unit) {
                return $level['key'];
            }
        }

        $position = ['pack' => 'top', 'strip' => 'middle', 'tablet' => 'base'][$unit] ?? null;

        foreach ($levels as $level) {
            if ($position !== null && $level['key'] === $position) {
                return $level['key'];
            }
        }

        return null;
    }

    /** Whether a sale may leave the shelf as this unit. */
    public function sellsIn(string $unit): bool
    {
        $key = $this->exactLevelKeyFor($unit);

        return $key !== null && in_array($key, $this->sellableUnitKeys(), true);
    }

    /**
     * How many base units one of $unit is.
     *
     * Accepts a level key ('top', 'middle', 'base'), the level's own name
     * ('box', 'strip', 'capsule'), or the three words the screens that have
     * not been converted yet still send: pack, strip, tablet. Those three mean
     * biggest, middle and smallest - they are positions, not names.
     *
     * An unknown rung counts as one, which matches what this method always did
     * for unrecognised input.
     */
    public function baseUnitsPer(string $unit): int
    {
        $unit = strtolower(trim($unit));
        $levels = $this->packingLevels();

        $position = ['pack' => 'top', 'strip' => 'middle', 'tablet' => 'base'][$unit] ?? null;

        if ($position !== null) {
            foreach ($levels as $level) {
                if ($level['key'] === $position) {
                    return max(1, $level['factor']);
                }
            }

            // No such rung: fall through, in case a record really does call one
            // of its levels "strip" or "tablet".
        }

        foreach ($levels as $level) {
            if ($level['key'] === $unit || $level['name'] === $unit) {
                return max(1, $level['factor']);
            }
        }

        // The old screens offered all three for every record, so a rung that
        // does not exist has always meant "one of it".
        return 1;
    }

    public function toBaseQuantity(int $qty, string $unit): int
    {
        return $qty * $this->baseUnitsPer($unit);
    }

    public function pricePerBase(float $price, string $unit): float
    {
        $factor = $this->baseUnitsPer($unit);

        return $factor > 0 ? $price / $factor : $price;
    }

    /** The shelf price of one $unit, given the price of one base unit. */
    public function pricePerUnit(float $basePrice, string $unit): float
    {
        return round($basePrice * $this->baseUnitsPer($unit), 4);
    }

    /* ------------------------------------------------------------------
     |  Reading quantities out loud
     | ------------------------------------------------------------------ */

    public function baseUnitLabel(): string
    {
        $levels = $this->packingLevels();

        return $levels[count($levels) - 1]['name'];
    }

    public function topUnitLabel(): string
    {
        return $this->packingLevels()[0]['name'];
    }

    /**
     * "24 packs", "12 packs 3 strips 4 capsules", "41 bottles".
     *
     * Always starts from the biggest unit and only steps down when something
     * is left over, because that is how the shop reads a shelf. The stored
     * number never changes; this is only how it is said.
     */
    public function describeQuantity($baseQty): string
    {
        $levels = $this->packingLevels();
        $left = max(0, (int) round((float) $baseQty));
        $parts = [];
        $last = count($levels) - 1;

        foreach ($levels as $i => $level) {
            $factor = max(1, $level['factor']);
            $count = intdiv($left, $factor);
            $left -= $count * $factor;

            if ($i === $last) {
                if ($count > 0 || $parts === []) {
                    $parts[] = number_format($count) . ' ' . $this->plural($level['name'], $count);
                }
            } elseif ($count > 0) {
                $parts[] = number_format($count) . ' ' . $this->plural($level['name'], $count);
            }
        }

        return implode(' ', $parts);
    }

    /**
     * The stock as the shelf reads it: strips for strip-packed medicines,
     * bottles/tubes/vials for liquids and creams, packs/boxes for loose
     * tablets. It deliberately never falls through to a loose tablet count.
     *
     * "9.5 strips" is something a person can count off the shelf; "95
     * tablets" is not. The full breakdown is still available through
     * describeQuantity() for receipts and the till.
     */
    public function describeStock($baseQty): string
    {
        $levels = $this->packingLevels();
        $count = count($levels);

        if ($count < 2) {
            return $this->describeQuantity($baseQty);
        }

        $base = $levels[$count - 1]['name'];

        $level = match (true) {
            // A strip, bottle, vial or test strip is already a shelf unit.
            ! in_array($base, self::LOOSE_BASE_UNITS, true) => $levels[$count - 1],
            // Tablets and capsules in strips: read the strip.
            $count >= 3 => $levels[1],
            // Loose tablets with no strip: read the pack/box they came in.
            default => $levels[0],
        };

        $factor = max(1, (float) $level['factor']);
        $value = max(0, (float) $baseQty) / $factor;

        return $this->formatStockValue($value) . ' ' . $this->plural($level['name'], $value);
    }

    /** Whole numbers stay whole; a part strip shows at most two decimals. */
    private function formatStockValue(float $value): string
    {
        $rounded = round($value, 2);

        if (abs($rounded - round($rounded)) < 0.005) {
            return number_format($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ','), '0'), '.');
    }

    /** One readable line for lists: "Pack of 10 strips × 10 capsules". */
    public function packingSummary(): string
    {
        $levels = $this->packingLevels();

        if (count($levels) === 1) {
            return ucfirst($levels[0]['name']);
        }

        $top = $levels[0];
        $next = $levels[1];

        // How many of the next rung sit in one top. Dividing the two factors
        // also covers the case where the top holds a single strip.
        $perTop = intdiv($top['factor'], max(1, $next['factor']));

        $text = ucfirst($top['name']) . ' of ' . number_format($perTop) . ' '
            . $this->plural($next['name'], $perTop);

        if (count($levels) === 3 && $next['factor'] > 1) {
            $text .= ' × ' . number_format($next['factor']) . ' '
                . $this->plural($levels[2]['name'], $next['factor']);
        }

        return $text;
    }

    /** The minimum, expressed in base units, for comparing against stock. */
    public function minimumInBaseUnits(): float
    {
        return (float) $this->minimum_stock_level * (float) $this->unitsPerTop();
    }

    public function isBelowMinimum($stockInBaseUnits): bool
    {
        return (float) $stockInBaseUnits <= $this->minimumInBaseUnits();
    }

    private function plural(string $word, $count): string
    {
        if (abs((float) $count - 1.0) < 0.0001) {
            return $word;
        }

        return match ($word) {
            'sachet' => 'sachets',
            'box' => 'boxes',
            'suppository' => 'suppositories',
            'pessary' => 'pessaries',
            default => $word . 's',
        };
    }

    /* ------------------------------------------------------------------
     |  Form state
     | ------------------------------------------------------------------ */

    /**
     * What the medicine / product form should show, whether or not the new
     * columns have been filled in yet.
     *
     * "inner" is the form's own wording for what sits inside one container:
     * none (sold whole), strip, sachet, ampoule, bottle, vial, tin, tube, or
     * loose.
     *
     * @return array<string, mixed>
     */
    public function packingFormState(): array
    {
        $state = [
            'unit_top' => 'pack',
            'top_holds' => '',
            'inner' => 'none',
            'middle_holds' => '',
            'unit_base' => 'tablet',
            'sell_middle' => true,
            'sell_base' => true,
        ];

        if (! $this->unit_top) {
            $base = strtolower((string) ($this->unit_of_measure ?: 'tablet'));
            $perStrip = $this->unitsPerStrip();
            $perPack = $this->stripsPerPack();

            if ($perStrip === 1 && $perPack === 1) {
                $container = in_array($base, self::CONTAINER_UNITS, true) ? $base : 'pack';
                $state['unit_top'] = $container;
                $state['unit_base'] = $container;
                $state['inner'] = 'none';
                $state['sell_middle'] = false;

                return $state;
            }

            $state['unit_top'] = 'pack';
            $state['top_holds'] = $perPack;
            $state['unit_base'] = $base;

            if ($perStrip === 1) {
                $state['inner'] = in_array($base, ['sachet', 'ampoule', 'bottle'], true) ? $base : 'loose';
            } else {
                $state['inner'] = 'strip';
                $state['middle_holds'] = $perStrip;
            }

            return $state;
        }

        $top = strtolower((string) $this->unit_top);
        $base = strtolower((string) ($this->unit_base ?: 'tablet'));

        $state['unit_top'] = $top;
        $state['unit_base'] = $base;
        $state['top_holds'] = $this->top_holds ?? '';
        $state['middle_holds'] = $this->middle_holds ?? '';
        $state['sell_middle'] = (bool) $this->sell_middle;
        $state['sell_base'] = (bool) $this->sell_base;

        // A container that holds itself is sold whole. Do not show "Tin
        // containing Tins": saving that combination is refused.
        if ($top === $base) {
            $state['inner'] = 'none';
            $state['top_holds'] = '';
            $state['middle_holds'] = '';
            $state['sell_middle'] = false;

            return $state;
        }

        $middle = strtolower(trim((string) $this->unit_middle));

        if ($middle !== '' && $middle !== $top && $middle !== $base) {
            $state['inner'] = 'strip';

            return $state;
        }

        // A duplicate middle was imported. Describe the chain it really means
        // (top -> base) and keep the base-unit count the duplicate carried.
        if ($middle !== '') {
            $state['inner'] = $this->innerWordFor($base);
            $state['top_holds'] = max(1, $this->unitsPerTop());
            $state['middle_holds'] = '';
            $state['sell_middle'] = false;

            return $state;
        }

        if ($this->top_holds === null || (int) $this->top_holds < 1) {
            // The form asks for the missing count before it will save.
            $state['inner'] = $this->innerWordFor($base);
            $state['top_holds'] = '';

            return $state;
        }

        $state['inner'] = $this->innerWordFor($base);

        return $state;
    }

    /** The form's "one container contains ..." word for a base unit. */
    private function innerWordFor(string $base): string
    {
        return in_array($base, ['sachet', 'ampoule', 'bottle', 'vial', 'tin', 'tube'], true)
            ? $base
            : 'loose';
    }

    /* ------------------------------------------------------------------
     |  Internals
     | ------------------------------------------------------------------ */

    private function normalisePacking(): void
    {
        $top = strtolower(trim((string) $this->unit_top));
        $middle = strtolower(trim((string) ($this->unit_middle ?? '')));
        $base = strtolower(trim((string) ($this->unit_base ?: 'tablet')));

        $this->unit_top = $top;
        $this->unit_base = $base;

        // A container cannot contain itself. Collapse it to one countable unit
        // instead of letting the old count invent a 30-tin tin.
        if ($top === $base) {
            $this->unit_middle = null;
            $this->top_holds = null;
            $this->middle_holds = null;
            $this->sell_top = true;
            $this->sell_middle = false;
            $this->sell_base = true;

            return;
        }

        $this->unit_middle = $middle !== '' ? $middle : null;

        if ($this->unit_middle !== null) {
            $topHolds = max(1, (int) $this->top_holds);
            $middleHolds = max(1, (int) $this->middle_holds);

            if ($this->unit_middle === $top || $this->unit_middle === $base) {
                // The same name cannot appear on two rungs. Keep the total
                // conversion the duplicate held, then remove the extra rung.
                $this->unit_middle = null;
                $this->top_holds = $topHolds * $middleHolds;
                $this->middle_holds = null;
            } else {
                $this->top_holds = $topHolds;
                $this->middle_holds = $middleHolds;
            }
        } else {
            // A rung that holds nothing cannot have a count.
            $this->middle_holds = null;

            if ($this->top_holds !== null && $this->top_holds !== '') {
                $this->top_holds = max(1, (int) $this->top_holds);
            } else {
                $this->top_holds = null;
            }
        }

        $this->sell_top = true;
        $this->sell_middle = $this->unit_middle !== null
            ? ($this->sell_middle === null ? true : (bool) $this->sell_middle)
            : false;
        $this->sell_base = $this->sell_base === null ? true : (bool) $this->sell_base;
    }

    /**
     * Keep the three columns the rest of the app still reads in step with the
     * described packing. They are derived; nothing should write to them.
     */
    private function mirrorLegacyPackingColumns(): void
    {
        $this->unit_of_measure = $this->unit_base;
        $this->units_per_strip = $this->unit_middle ? max(1, (int) $this->middle_holds) : 1;
        $this->strips_per_pack = max(1, (int) $this->top_holds);
    }

    public function unitsPerStrip(): int
    {
        if ($this->unit_top) {
            return $this->unit_middle ? max(1, (int) $this->middle_holds) : 1;
        }

        return max(1, (int) $this->units_per_strip);
    }

    public function stripsPerPack(): int
    {
        if ($this->unit_top) {
            return max(1, (int) $this->top_holds);
        }

        return max(1, (int) $this->strips_per_pack);
    }

    public function unitsPerPack(): int
    {
        return $this->unitsPerTop();
    }

    /** @return array<string, string> */
    public function packingOptions(): array
    {
        $options = [];

        foreach ($this->packingLevels() as $level) {
            $label = ucfirst($level['name']);

            if ($level['factor'] > 1) {
                $label .= ' (' . number_format($level['factor']) . ' '
                    . $this->plural($this->baseUnitLabel(), $level['factor']) . ')';
            }

            $options[$level['key']] = $label;
        }

        return $options;
    }
}
