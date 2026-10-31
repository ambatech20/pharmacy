<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugCategory;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The catalogue read off the 22 supplier invoices (2026-10-22).
 *
 * 163 distinct products, imported as drugs. Medical supplies, vitamins and
 * supplements deliberately live on the drugs side of this application; the
 * Products side is cosmetics and hygiene only, so this seeder never creates a
 * product.
 *
 * The data lives in database/seeders/data/catalogue.json, one row per product,
 * with the packing nested. Keeping it as data rather than 163 PHP arrays means
 * a corrected invoice can be re-imported without touching this class.
 *
 * THE UNIT LOGIC IS UNCHANGED
 * ---------------------------
 * Each row becomes the same rungs the rest of the app already uses:
 *
 *   pack -> 10 strips -> 10 tablets   (top -> middle -> base)
 *   pack -> 50 tablets                (top -> base, no strip)
 *   bottle                            (one rung, sold whole)
 *
 * Nothing is inferred from a "10*10" that is really a size, and no count is
 * guessed. The 12 rows whose count the paper never printed are imported as one
 * rung, sold whole (pack / bottle), so they appear on the shelf without
 * pretending to know how many pieces are inside. Correct the count later in
 * the drug form and the rungs follow.
 *
 * Idempotent: matched on name + strength, with explicit aliases for invoice
 * spellings and for the rows that were moved over from Products. Re-running
 * updates the catalogue facts and leaves stock, batches, SKU and the reorder
 * level alone.
 */
class InvoiceCatalogueSeeder extends Seeder
{
    /**
     * The 16 categories the application already uses. The descriptions are
     * only written when the category is created, so an existing one keeps the
     * wording the pharmacy chose.
     */
    private const DRUG_CATEGORIES = [
        'Antibiotics' => 'Antibacterial medicines.',
        'Analgesics and Antipyretics' => 'Pain relief and fever reducers.',
        'Chronic Care' => 'Long-term conditions: cardiovascular, metabolic, endocrine.',
        'Vitamins and Supplements' => 'Vitamins, minerals and supplements.',
        'Gastrointestinal' => 'Stomach, bowel and haemorrhoid medicines.',
        'Hormonal Medications' => 'Hormones and hormone-related medicines.',
        'Cardiovascular' => 'Heart and blood-pressure medicines.',
        'Antivirals' => 'Antiviral medicines and creams.',
        'ENT' => 'Ear, nose and throat preparations.',
        'Other' => 'Medicines and supplies the invoices do not classify further.',
        'Anthelmintics' => 'Deworming medicines: albendazole, mebendazole.',
        'Neurology and Antiepileptics' => 'Epilepsy, neuropathic pain and related nervous-system medicines.',
        'Respiratory' => 'Cough, cold and airway medicines.',
        'Dermatology' => 'Topical corticosteroids, antifungals and skin preparations.',
        'Ophthalmology' => 'Eye drops and ophthalmic preparations.',
        'Gynecological' => 'Vaginal and gynecological preparations.',
    ];

    /**
     * Rows the invoice names differently from the row already on file.
     * Keyed by the catalogue's "name|strength"; the value is the existing
     * name and the strength that identifies it.
     *
     * These are spelling variants and brand-in-brackets cases, not generic
     * merges: Ceftriaxone, Zithrotel, Cipropharm and the like stay separate
     * rows because the invoices show different brands, packs or prices.
     */
    private const ALIASES = [
        'Acyclovir|400mg' => ['Aciclovir', '400mg'],
        'Acyclovir Cream|5% 5g' => ['Aciclovir Cream', '5%'],
        'Albendazole|' => ['Albendazole Suspension', '20ml'],
        'Aspirin (Edsprin)|81mg' => ['Aspirin', '81mg'],
        'Benzanthine Pen (Benzathine penicillin)|2.4MIU' => ['Benzathene Pen', '2.4MU'],
        'Ciprofloxacin (Zindolin)|500mg' => ['Zindolin', '500mg'],
        'Clavomid (Amoxicillin+Clavulanate)|625mg' => ['Clavomid', '625mg'],
        'Coronis (Carvedilol)|6.25mg' => ['Coronis', '6.25mg'],
        'Diphenhydramine (Koflyn)|' => ['Koflyn', ''],
        'Dorzy-T (Dorzolamide+Timolol)|2%/0.5%' => ['Dorzy-T', '2%/0.5%'],
        'Flixase (Fluticasone Furoate)|27.5mcg' => ['Flixase', '27.5mcg'],
        'Hydrocortisone Cream (Cortileb)|15g' => ['Hydrocortisone Cream', '15g'],
        'Imatox (Ondansetron)|4mg' => ['Imatox', '4mg'],
        'Imatox Inj (Ondansetron)|4mg/2ml' => ['Imatox Injection', ''],
        'Ketazol Cream|40g' => ['Ketazol', '40g'],
        'Klamoks (Amoxicillin+Clavulanate)|228mg/5ml' => ['Klamoks', '228mg/5ml'],
        'Livolin (Essential Phospholipids)|' => ['Livolin', ''],
        'Mebendazole|100mg/5ml' => ['Mebendazole Suspension', '100mg/5ml'],
        'Miraclin (Doxycycline)|100mg' => ['Miraclin', '100mg'],
        'Oberem (Ondansetron)|' => ['Oberem', ''],
        'Para Denk (Paracetamol)|125mg' => ['Para Denk', '125mg'],
    ];

    /**
     * Rows that used to be Products and were moved to the drugs side. The SKU
     * is preserved by that move, so it is the reliable way to find the row
     * even when the invoice prints a shorter name.
     */
    private const SKU_ALIASES = [
        'Boso Therm Basic|' => 'MS-THM-BOS',
        'Dextrose|40% 20ml' => 'MS-IV-D40',
        'Flamingo Arm Pouch (L)|L' => 'MS-ARM-L',
        'Flamingo Arm Pouch (M)|M' => 'MS-ARM-M',
        'Flamingo Pediatric Arm Sling|' => 'MS-SLG-NUR',
        'Gauze Swab|10x10cm 8ply' => 'MS-GAU-10',
        'Hydrogen Peroxide|3% 130ml' => 'HY-HP2-130',
        'Lyte Mix Fruit Cocktail|' => 'MS-LYT-FRT',
        'Lyte Mix Lemon|' => 'MS-LYT-LEM',
        'Multivitamin Syrup|200ml' => 'VS-MTV-200',
        'NS (Sodium chloride)|0.9%' => 'MS-IV-NS',
        'On-call Extra Meter|' => 'MS-MTR-ONC',
        'Precisa Blood Glucose Meter|' => 'MS-GLU-MTR',
        'Precisa Blood Glucose Testing Strip|' => 'MS-GLU-STR',
        'Thermometer Digital|' => 'MS-THM-DIG',
    ];

    /**
     * The invoice does not print a generic, but the correction file states it.
     * Only used where the medicine is unambiguous; everything else stays blank
     * rather than guessing an active ingredient.
     */
    private const GENERIC_OVERRIDES = [
        'Valcodin|5mg/160mg' => 'Amlodipine/Valsartan',
        'Valcodin|10mg/160mg' => 'Amlodipine/Valsartan',
    ];

    /**
     * Packing corrections where the paper proves more than the row carries.
     *
     * Sterile gauze is a pack of swabs sold whole, with the reorder level in
     * packs. The invoice prints only the size (10x10), never the count, so the
     * box is not turned into a piece count.
     */
    private const PACKING_OVERRIDES = [
        'Gauze Swab|10x10cm 8ply' => [
            'top' => 'pack',
            'top_holds' => 1,
            'middle' => null,
            'middle_holds' => null,
            'base' => 'pack',
        ],
    ];

    /** Forms where a pack/box/bottle can never be the unit stock is kept in. */
    private const COUNTABLE_FORMS = ['tablet', 'capsule', 'sachet', 'suppository'];

    /**
     * Reorder levels that are not the generic placeholder. Sterile gauze is
     * kept at 100 packs because that is how the shop stocks it; everything
     * else starts at ten upper units and is adjusted on the drug screen.
     */
    private const MIN_STOCK_OVERRIDES = [
        'Gauze Swab|10x10cm 8ply' => 100,
    ];

    /** Placeholder reorder level for a new row, in whole upper units. */
    private const DRUG_MIN_STOCK = 10;

    public function run(): void
    {
        $rows = $this->catalogue();
        $categories = $this->ensureCategories();

        $this->correctKnownMistakes();

        [$byKey, $bySku, $byName] = $this->indexExisting();

        $created = 0;
        $updated = 0;
        $warnings = [];
        $usedExisting = [];

        foreach ($rows as $row) {
            $key = $this->rowKey($row);
            $categoryId = $categories[$row['category']] ?? null;

            if ($categoryId === null) {
                throw new RuntimeException("Category \"{$row['category']}\" is not defined; refusing to file {$row['name']} under nothing.");
            }

            $packing = $this->packingFor($row);
            $forcedPacking = isset(self::PACKING_OVERRIDES[$key]);
            $existing = $this->resolveExisting($row, $byKey, $bySku, $byName);

            // Two catalogue rows must never collapse onto one drug, or the
            // second silently overwrites the first one's packing.
            if ($existing && isset($usedExisting[$existing->id])) {
                $warnings[] = "{$row['name']} ({$row['strength']}) resolved to {$existing->name}, which another row already used; created as a separate row.";
                $existing = null;
            }

            $attributes = [
                'form' => $row['form'] ?: null,
                'kind' => $row['kind'] ?: 'medicine',
                'category_id' => $categoryId,

                // The rungs from the invoice. The model mirrors the old
                // unit_of_measure / units_per_strip / strips_per_pack columns
                // from these on save.
                'unit_top' => $packing['top'],
                'top_holds' => $packing['top_holds'],
                'unit_middle' => $packing['middle'],
                'middle_holds' => $packing['middle_holds'],
                'unit_base' => $packing['base'],

                // Purchases may use every rung; a sale may use each rung the
                // pharmacy has not switched off. Only a row whose rungs are
                // new or resized gets the catalogue defaults below; an
                // existing row keeps the choices made on its edit screen.
            ];

            $geometryChanged = true;

            $generic = self::GENERIC_OVERRIDES[$key] ?? trim((string) ($row['generic_name'] ?? ''));
            if ($generic !== '') {
                $attributes['generic_name'] = $generic;
            }

            if ($existing) {
                // An existing row keeps its spelling, SKU and reorder level.
                if (trim((string) $existing->strength) === '' && trim((string) ($row['strength'] ?? '')) !== '') {
                    $attributes['strength'] = $row['strength'];
                }

                // Stock is counted in the base unit. Moving that base under
                // existing stock would silently reinterpret it (10 packs
                // becoming 10 tablets), so the packing stays as it is and the
                // mismatch is reported instead of guessed away.
                $oldBase = $this->baseNameOf($existing);
                $newBase = strtolower((string) $packing['base']);
                $packingKept = false;

                if ($oldBase !== $newBase && $existing->purchaseBatches()->exists() && ! $forcedPacking) {
                    foreach (['unit_top', 'top_holds', 'unit_middle', 'middle_holds', 'unit_base'] as $field) {
                        unset($attributes[$field]);
                    }

                    $packingKept = true;

                    $warnings[] = sprintf(
                        '%s has stock kept in %s; its packing was left as it is (catalogue says %s per %s). Confirm the count, then edit the drug.',
                        $existing->name . ' ' . $existing->strength,
                        $oldBase ?: 'an unknown unit',
                        $packing['top_holds'] ?? 1,
                        $packing['base']
                    );
                } elseif ($oldBase !== $newBase && $existing->purchaseBatches()->exists()) {
                    $warnings[] = sprintf(
                        '%s had stock kept in %s and its packing was set to %s as decided; check the stock count.',
                        $existing->name . ' ' . $existing->strength,
                        $oldBase ?: 'an unknown unit',
                        $packing['base']
                    );
                }

                $geometryChanged = ! $packingKept && (
                    strcasecmp((string) $existing->unit_top, (string) $packing['top']) !== 0
                    || strcasecmp((string) $existing->unit_middle, (string) ($packing['middle'] ?? '')) !== 0
                    || strcasecmp((string) $existing->unit_base, (string) $packing['base']) !== 0
                    || $existing->unitsPerTop() !== $this->packingUnits($packing)
                );
            }

            // A new or redrawn chain starts fully sellable; an existing chain
            // that did not change keeps whatever the pharmacy ticked.
            if (! $existing || $geometryChanged) {
                $attributes['sell_top'] = true;
                $attributes['sell_middle'] = $packing['middle'] !== null;
                $attributes['sell_base'] = true;
            }

            if ($existing) {
                $existing->update($attributes);
                $usedExisting[$existing->id] = true;
                $drug = $existing;
                $updated++;
            } else {
                $drug = Drug::create(array_merge($attributes, [
                    'name' => $row['name'],
                    'strength' => $row['strength'] ?: null,
                    'minimum_stock_level' => self::MIN_STOCK_OVERRIDES[$key] ?? self::DRUG_MIN_STOCK,
                ]));
                $usedExisting[$drug->id] = true;
                $created++;
            }

            if (($row['packing']['status'] ?? 'missing') !== 'confirmed') {
                $warnings[] = ($row['packing']['status'] ?? 'missing') === 'missing'
                    ? sprintf(
                        '%s %s: pack count not printed; imported sold whole as one %s.',
                        $drug->name,
                        $drug->strength,
                        $drug->unit_top
                    )
                    : sprintf(
                        '%s %s: pack count assumed (%d %s per %s); confirm it against the box.',
                        $drug->name,
                        $drug->strength,
                        $drug->top_holds,
                        $drug->unit_base,
                        $drug->unit_top
                    );
            }
        }

        $this->say('');
        $this->say(sprintf(
            'Invoice catalogue: %d rows (147 confirmed, 4 assumed, 12 sold whole). Drugs created %d, updated %d.',
            count($rows),
            $created,
            $updated
        ));

        if ($warnings !== []) {
            $this->say('');
            $this->say('Needs a human look:');
            foreach ($warnings as $warning) {
                $this->say('  - ' . $warning);
            }
        }
    }

    /**
     * Fixes the two catalogue facts the invoices contradict in the old data.
     * Both are guarded so a row that already carries stock is never
     * reinterpreted behind the pharmacy's back.
     */
    private function correctKnownMistakes(): void
    {
        // Amlo Denk was filed as "5+5". The invoices print two strengths:
        // 5mg (of 50) and 10mg (5*10). Rename the wrong row to 5mg only when
        // it has no batches and no correct 5mg row already exists.
        if (! Drug::where('name', 'Amlo Denk')->where('strength', '5mg')->exists()) {
            foreach (Drug::where('name', 'Amlo Denk')->where('strength', '5+5')->get() as $wrong) {
                if ($wrong->purchaseBatches()->exists()) {
                    $this->say('  Amlo Denk 5+5 has stock; left untouched. The 5mg and 10mg rows will be added beside it.');
                    continue;
                }

                $wrong->update(['strength' => '5mg']);
                $this->say('  Amlo Denk: strength 5+5 corrected to 5mg.');
            }
        }

        // Convenium Plus 150/12.5 and 300/12.5 are different strengths. Only
        // the 300/12.5 row exists in this catalogue; nothing to merge.
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $path = database_path('seeders/data/catalogue.json');

        if (! is_file($path)) {
            throw new RuntimeException("Catalogue data file is missing: {$path}");
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('The catalogue data file is empty or is not valid JSON.');
        }

        return $rows;
    }

    /**
     * @return array<string, int> category id by name
     */
    private function ensureCategories(): array
    {
        $ids = DrugCategory::pluck('id', 'name')->all();

        foreach (self::DRUG_CATEGORIES as $name => $description) {
            if (! isset($ids[$name])) {
                $ids[$name] = DrugCategory::create([
                    'name' => $name,
                    'description' => $description,
                ])->id;
            }
        }

        return $ids;
    }

    /**
     * @return array{0: array<string, array<int, Drug>>, 1: array<string, Drug>, 2: array<string, array<int, Drug>>}
     */
    private function indexExisting(): array
    {
        $byKey = [];
        $bySku = [];
        $byName = [];

        foreach (Drug::with('category')->get() as $drug) {
            $byKey[$this->naturalKey($drug->name, $drug->strength)][] = $drug;
            $byName[$this->naturalKey($drug->name, null)][] = $drug;

            if ($drug->sku) {
                $bySku[$drug->sku] = $drug;
            }
        }

        return [$byKey, $bySku, $byName];
    }

    /**
     * @param  array{0: array<string, array<int, Drug>>, 1: array<string, Drug>, 2: array<string, array<int, Drug>>}  $indexes
     */
    private function resolveExisting(array $row, array $byKey, array $bySku, array $byName): ?Drug
    {
        $key = $this->rowKey($row);

        if (isset(self::SKU_ALIASES[$key], $bySku[self::SKU_ALIASES[$key]])) {
            return $bySku[self::SKU_ALIASES[$key]];
        }

        if (isset(self::ALIASES[$key])) {
            [$name, $strength] = self::ALIASES[$key];
            $candidates = $byName[$this->naturalKey($name, null)] ?? [];
            $match = $this->matchByName($candidates, $strength, $row['form'] ?? null);

            if ($match) {
                return $match;
            }
        }

        return $this->matchExisting(
            $byKey[$this->naturalKey($row['name'], $row['strength'] ?? null)] ?? [],
            $row['form'] ?? null
        );
    }

    /**
     * @param  array<int, Drug>  $candidates
     */
    private function matchByName(array $candidates, ?string $strength, ?string $form): ?Drug
    {
        if ($candidates === []) {
            return null;
        }

        if (trim((string) $strength) !== '') {
            $sameStrength = array_values(array_filter(
                $candidates,
                fn (Drug $drug) => $this->naturalKey(null, $drug->strength) === $this->naturalKey(null, $strength)
            ));

            if ($sameStrength !== []) {
                return $this->matchExisting($sameStrength, $form);
            }
        }

        return $this->matchExisting($candidates, $form);
    }

    /**
     * @param  array<int, Drug>  $candidates
     */
    private function matchExisting(array $candidates, ?string $form): ?Drug
    {
        if ($candidates === []) {
            return null;
        }

        if ($form !== null) {
            foreach ($candidates as $candidate) {
                if (strcasecmp(trim((string) $candidate->form), trim($form)) === 0) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    /**
     * The packing rungs for a catalogue row, after overrides.
     *
     * A "missing" row has no printed count, so it becomes one rung sold whole
     * under the container the invoice uses. That is the only representation
     * that does not invent a number.
     *
     * @return array{top:string, top_holds:int, middle:?string, middle_holds:?int, base:string}
     */
    private function packingFor(array $row): array
    {
        $key = $this->rowKey($row);

        if (isset(self::PACKING_OVERRIDES[$key])) {
            return self::PACKING_OVERRIDES[$key];
        }

        $packing = $row['packing'] ?? [];
        $status = $packing['status'] ?? 'missing';
        $top = strtolower(trim((string) ($packing['top'] ?? 'pack')));

        if ($status === 'missing') {
            return [
                'top' => $top !== '' ? $top : 'pack',
                'top_holds' => 1,
                'middle' => null,
                'middle_holds' => null,
                'base' => $top !== '' ? $top : 'pack',
            ];
        }

        $base = strtolower(trim((string) ($packing['base'] ?? '')));
        $middle = $packing['middle'] ? strtolower(trim((string) $packing['middle'])) : null;
        $topHolds = max(1, (int) ($packing['top_holds'] ?? 1));
        $middleHolds = $middle !== null ? max(1, (int) ($packing['middle_holds'] ?? 1)) : null;

        if ($base === '') {
            throw new RuntimeException("{$row['name']} {$row['strength']} is marked {$status} but has no base unit.");
        }

        if ($status === 'confirmed') {
            $computed = $middle !== null ? $topHolds * $middleHolds : $topHolds;

            if ((int) ($packing['units_per_top'] ?? 0) !== $computed) {
                throw new RuntimeException(sprintf(
                    '%s %s: the invoice says %s per %s but the rungs multiply to %d; refusing to import an unsafe count.',
                    $row['name'], $row['strength'], $packing['units_per_top'] ?? '?', $top, $computed
                ));
            }

            if (in_array(strtolower((string) $row['form']), self::COUNTABLE_FORMS, true)
                && in_array($base, Drug::CONTAINER_UNITS, true)) {
                throw new RuntimeException("{$row['name']} {$row['strength']} is {$row['form']} but keeps stock in {$base}.");
            }
        }

        return [
            'top' => $top,
            'top_holds' => $topHolds,
            'middle' => $middle,
            'middle_holds' => $middleHolds,
            'base' => $base,
        ];
    }

    /** The catalogue's explicit identity for a row. */
    private function rowKey(array $row): string
    {
        return ($row['name'] ?? '') . '|' . ($row['strength'] ?? '');
    }

    /** The last rung: the unit stock is counted in. */
    private function baseNameOf(Drug $drug): ?string
    {
        $levels = $drug->packingLevels();
        $last = end($levels);

        return $last['name'] ?? null;
    }

    /** How many base units one top holds, for a validated packing array. */
    private function packingUnits(array $packing): int
    {
        $top = max(1, (int) ($packing['top_holds'] ?? 1));

        return $packing['middle'] !== null
            ? $top * max(1, (int) ($packing['middle_holds'] ?? 1))
            : $top;
    }

    /**
     * Name and strength with case, spacing, punctuation and micro-sign
     * spelling removed, so "500 mg" and "500mg" are one tablet.
     */
    private function naturalKey(?string $name, ?string $strength): string
    {
        $clean = static function (?string $value): string {
            $value = str_replace(['µ', 'μ'], 'u', strtolower((string) $value));

            return (string) preg_replace('/[^a-z0-9]+/', '', $value);
        };

        return $clean($name) . '|' . $clean($strength);
    }

    private function say(string $line): void
    {
        $this->command?->line($line);
    }
}
