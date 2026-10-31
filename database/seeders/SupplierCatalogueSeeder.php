<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Catalogue from the the supplier invoices.
 *
 * Only the catalogue is seeded here: the categories, the drugs and the retail
 * products. The purchases themselves are a separate step, because a purchase
 * creates batches, and a batch needs an expiry date and a price - both of which
 * are taken from the same paper but verified separately.
 *
 * Idempotent by design. Every row is matched on its natural key and updated in
 * place, so running this twice changes nothing and running it after you have
 * hand-corrected something will not undo your correction unless the value
 * differs from what is defined below.
 *
 * HOW THE PACKING IS READ
 * -----------------------
 * The invoice descriptions carry the packing in two shapes:
 *
 *   "2*14", "10*10", "3*10"   -> that many strips of that many tablets
 *   "of 6", "of 30", "of 100" -> that many in the pack, with no strip named
 *
 * Those become the rungs on the drug: a pack of strips of tablets is three
 * rungs, a pack of 30 capsules is two, and a bottle or a tube is one. Nothing
 * is assumed: where the paper does not say what is inside, the row is sold
 * whole, and where one drug arrives in two different pack sizes the size the
 * row carries is recorded in its note.
 *
 * Minimum stock is still a placeholder: the invoices carry no reorder level.
 */
class SupplierCatalogueSeeder extends Seeder
{
    /**
     * Every drug category this seeder files something under.
     *
     * The first group already exists on the current install and is only listed
     * so that this seeder also works against a fresh database, where none of
     * them exist yet. firstOrCreate() finds an existing row and leaves its
     * description alone; it only writes a description when it creates the row.
     *
     * The second group is genuinely new and comes from the invoices.
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
        'Other' => 'Medicines the invoices do not classify further.',
        // --- new, required by the invoices ---
        'Anthelmintics' => 'Deworming medicines: albendazole, mebendazole.',
        'Neurology and Antiepileptics' => 'Epilepsy, neuropathic pain and related nervous-system medicines.',
        'Respiratory' => 'Cough, cold and airway medicines.',
        'Dermatology' => 'Topical corticosteroids, antifungals and skin preparations.',
        'Ophthalmology' => 'Eye drops and ophthalmic preparations.',
        'Gynecological' => 'Vaginal and gynecological preparations.',
    ];

    /**
     * Every product category this seeder files something under.
     */
    private const PRODUCT_CATEGORIES = [
        'Hygiene Products' => 'Soaps, antiseptics and hand hygiene.',
        'Medical Supplies' => 'Consumables and dressings used in the pharmacy.',
        'Vitamins and Supplements' => 'Vitamin and mineral supplements, including syrups and softgels.',
    ];

    /**
     * Drugs from the invoices. Keyed by category name, resolved at run time.
     *
     * name is what staff see and search for, so it keeps the brand name where
     * the brand is what the invoice and the shelf label use. generic_name is
     * where the active ingredient goes, which is what makes a later "show me
     * everything that is a statin" possible.
     *
     * Left null deliberately: lines that carry a brand with no stated active
     * ingredient on the paper. Guessing the generic from the brand name is how
     * a pharmacy ends up dispensing the wrong thing, so they stay blank until
     * someone confirms them against the box.
     *
     * packing, when present, is:
     *   top / top_holds          the purchase unit and what one holds
     *   middle / middle_holds    the strip, when the paper names one
     *   base                     the smallest thing that leaves the shelf
     * With no packing key the row is one rung, sold as it arrives.
     */
    private const DRUGS = [
        // --- Antibiotics -----------------------------------------------------
        [
            'name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '250mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
            'note' => 'Amyn brand, 100ml bottle. Distinct from the existing Amoxicillin 500mg capsule.',
        ],
        [
            'name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '125mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
            'note' => 'The smaller paediatric suspension. A different strength is a different drug record.',
        ],
        [
            'name' => 'Cledomox', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '457mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Negazole Suspension', 'generic_name' => 'Metronidazole', 'strength' => '125mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Klamoks', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '156mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Valcodin', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '5mg/160mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 2, 'middle' => 'strip', 'middle_holds' => 14, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Dispersible, 2*14 on the invoice: 2 strips of 14 tablets.',
        ],
        [
            'name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'strength' => '250mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 6, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Azitro 250mg of 6: a pack of six tablets, no strip named.',
        ],
        [
            'name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'strength' => '500 mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 3, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Azitro 500mg of 3. The strength is written "500 mg" here because that is how the existing row is spelled.',
        ],
        [
            'name' => 'Ciflosin', 'generic_name' => 'Ciprofloxacin', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Ciprofloxacin 500mg of 10.',
        ],
        [
            'name' => 'Cipro-Vir', 'generic_name' => 'Ciprofloxacin', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 14, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Of 14 and of 20 both appear on the invoices. The row carries the 14; buy the 20-pack as 20 tablets until it is split into its own row.',
        ],
        [
            'name' => 'Vigafos', 'generic_name' => 'Fosfomycin', 'strength' => '3g',
            'form' => 'sachet', 'unit_of_measure' => 'pack',
            'category' => 'Antibiotics',
            'note' => 'The invoice says "Sachet" but bills it by the pack and never says how many are inside, so it is sold whole.',
        ],
        [
            'name' => 'Metornorm', 'generic_name' => 'Metronidazole', 'strength' => '60ml',
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
            'note' => 'Metronidazole syrup 60ml. A different preparation from Negazole suspension.',
        ],

        // --- Analgesics and Antipyretics -------------------------------------
        [
            'name' => 'Indometacine', 'generic_name' => 'Indometacin', 'strength' => '25mg',
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'capsule'],
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Spelled "Indometacine" on the invoice, which is the brand spelling. 10*10.',
        ],

        // --- Anthelmintics ---------------------------------------------------
        [
            'name' => 'Albendazole', 'generic_name' => 'Albendazole', 'strength' => '400mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'box', 'top_holds' => 100, 'base' => 'tablet'],
            'category' => 'Anthelmintics',
            'note' => 'Covers both invoiced lines: Alwo 400mg of 100 (box) and Aldaz 400mg 10*5 (pack). The row carries the 100 box; buy the 50-tablet pack as 50 tablets.',
        ],
        [
            'name' => 'Albendazole Suspension', 'generic_name' => 'Albendazole', 'strength' => '20ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Anthelmintics',
            'note' => 'Aldenda 20ml. The invoice states the volume, not the strength.',
        ],
        [
            'name' => 'X Worm', 'generic_name' => null, 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 1, 'base' => 'tablet'],
            'category' => 'Anthelmintics',
            'note' => 'Of 1: one tablet in a pack. A tablet is the base unit, so a pack and a tablet are the same count; the pack is not the thing being counted. Generic left blank because the invoice only gives the brand.',
        ],

        // --- Neurology and Antiepileptics ------------------------------------
        [
            'name' => 'Pregabaline', 'generic_name' => 'Pregabalin', 'strength' => '75mg',
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 2, 'middle' => 'strip', 'middle_holds' => 12, 'base' => 'capsule'],
            'category' => 'Neurology and Antiepileptics',
            'note' => 'Probion brand, 2*12.',
        ],
        [
            'name' => 'Sodium Valproate', 'generic_name' => 'Sodium Valproate', 'strength' => '200mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Neurology and Antiepileptics',
            'note' => 'SSP (sodium valproate semisodium) 200mg, 10*10.',
        ],
        [
            'name' => 'Levuar', 'generic_name' => 'Levetiracetam', 'strength' => '1g',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 3, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Neurology and Antiepileptics',
            'note' => '3*10 on the invoice: 3 strips of 10 tablets.',
        ],
        [
            'name' => 'Kepprolin', 'generic_name' => 'Levetiracetam', 'strength' => '100mg/ml',
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Neurology and Antiepileptics',
        ],
        [
            'name' => 'OneGaba', 'generic_name' => 'Gabapentin', 'strength' => '300mg',
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Neurology and Antiepileptics',
            'note' => 'Of 30 capsules, no strip named.',
        ],

        // --- Respiratory -----------------------------------------------------
        [
            'name' => 'Libitus Cough Syrup', 'generic_name' => null, 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
        ],
        [
            'name' => 'Medofed', 'generic_name' => null, 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
            'note' => 'Compound syrup; the invoice names no active ingredients.',
        ],

        // --- Dermatology -----------------------------------------------------
        [
            'name' => 'Netracort Ointment', 'generic_name' => 'Hydrocortisone', 'strength' => null,
            'form' => 'ointment', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => 'No strength stated on the invoice.',
        ],
        [
            'name' => 'Ketoconazole Shampoo', 'generic_name' => 'Ketoconazole', 'strength' => '100ml',
            'form' => 'shampoo', 'unit_of_measure' => 'bottle',
            'category' => 'Dermatology',
        ],
        [
            'name' => 'Tetracycline Skin Ointment', 'generic_name' => 'Tetracycline', 'strength' => '3%',
            'form' => 'ointment', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
        ],
        [
            'name' => 'Acretine', 'generic_name' => null, 'strength' => '0.025%',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => 'The invoice gives the strength and form but no active ingredient.',
        ],
        [
            'name' => 'Foban', 'generic_name' => 'Fusidic Acid', 'strength' => '2%',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => '15g tube.',
        ],
        [
            'name' => 'Clotrimazole Cream', 'generic_name' => 'Clotrimazole', 'strength' => null,
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
        ],

        // --- Ophthalmology ---------------------------------------------------
        [
            'name' => 'Zocon Eye Drop', 'generic_name' => 'Fluconazole', 'strength' => null,
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
            'note' => 'Zocon is fluconazole per the invoice; no strength stated.',
        ],
        [
            'name' => 'Zoxan D', 'generic_name' => null, 'strength' => null,
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
            'note' => 'No active ingredient or strength on the invoice; the D is not an ingredient name.',
        ],

        // --- Gastrointestinal ------------------------------------------------
        [
            'name' => 'Pylocain', 'generic_name' => null, 'strength' => '30g',
            'form' => 'ointment', 'unit_of_measure' => 'tube',
            'category' => 'Gastrointestinal',
            'note' => 'Anti-haemorrhoid ointment, 30g.',
        ],

        // --- Gynecological ---------------------------------------------------
        [
            'name' => 'Cycloget', 'generic_name' => 'Progesterone', 'strength' => '400mg',
            'form' => 'pessary', 'unit_of_measure' => 'pessary',
            'packing' => ['top' => 'pack', 'top_holds' => 15, 'base' => 'pessary'],
            'category' => 'Gynecological',
            'note' => 'Of 15 pessaries.',
        ],
        [
            'name' => 'Gynozole', 'generic_name' => null, 'strength' => '400mg',
            'form' => 'vaginal capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 3, 'base' => 'capsule'],
            'category' => 'Gynecological',
            'note' => 'Of 3 vaginal capsules.',
        ],
        [
            'name' => 'Polygynax', 'generic_name' => null, 'strength' => null,
            'form' => 'pessary', 'unit_of_measure' => 'pessary',
            'packing' => ['top' => 'pack', 'top_holds' => 6, 'base' => 'pessary'],
            'category' => 'Gynecological',
            'note' => 'Of 6 pessaries.',
        ],

        // --- Hormonal Medications --------------------------------------------
        [
            'name' => 'Duphaston', 'generic_name' => 'Dydrogesterone', 'strength' => '10mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'tablet'],
            'category' => 'Hormonal Medications',
            'note' => 'Of 20 tablets.',
        ],

        // --- Chronic Care ----------------------------------------------------
        [
            'name' => 'Coronis', 'generic_name' => 'Carvedilol', 'strength' => '6.25mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 2, 'middle' => 'strip', 'middle_holds' => 14, 'base' => 'tablet'],
            'category' => 'Chronic Care',
            'note' => 'Cardiovascular. Written "Corinis" on one invoice; the existing row is spelled Coronis. 2*14.',
        ],

        // --- Vitamins and Supplements ----------------------------------------
        [
            'name' => 'Cal D Lift', 'generic_name' => 'Calcium/Vitamin D3', 'strength' => '1000mg/800IU',
            'form' => 'effervescent tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'tablet'],
            'category' => 'Vitamins and Supplements',
            'note' => '20 effervescent tablets per pack.',
        ],
        [
            'name' => 'Vital Iron Drops', 'generic_name' => null, 'strength' => null,
            'form' => 'drops', 'unit_of_measure' => 'tube',
            'category' => 'Vitamins and Supplements',
            'note' => 'Billed by the tube; the invoice gives no strength.',
        ],
    ];

    /**
     * The September and October invoices (CR-00016546, CA-00009742,
     * CR-00016961, CR-00016560, CRSI-ST1-01-0005995/6, CR-00016664).
     *
     * Same rules as DRUGS: packing only where the paper states it. "10 to 10"
     * and "tong" are the same 10x10 notation written two ways, and "of N"
     * stays a pack of N. Where no count is printed the row is one rung, sold
     * whole - a pack of aspirin is not guessed to hold 30.
     */
    private const LATE_DRUGS = [
        // --- Antibiotics -----------------------------------------------------
        [
            'name' => 'Miraclin', 'generic_name' => 'Doxycycline', 'strength' => '100mg',
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'base' => 'capsule'],
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Ceftriaxone', 'generic_name' => 'Ceftriaxone', 'strength' => '1g',
            'form' => 'injection', 'unit_of_measure' => 'vial',
            'category' => 'Antibiotics',
            'note' => 'IM/IV injection, billed by the vial.',
        ],
        [
            'name' => 'Zindolin', 'generic_name' => 'Ciprofloxacin', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Gofen', 'generic_name' => 'Gofen', 'strength' => '400mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 6, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => '6*10 on the invoice.',
        ],
        [
            'name' => 'Gofen Suspension', 'generic_name' => 'Gofen', 'strength' => '100ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Clavomid', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '625mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'tablet'],
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Merolam', 'generic_name' => null, 'strength' => '250mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => 'Of 20. The invoice gives the brand and strength only.',
        ],
        [
            'name' => 'Cetrax', 'generic_name' => null, 'strength' => '30ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Tindazole', 'generic_name' => 'Tinidazole', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Antibiotics',
            'note' => 'Billed by the pack; no count is printed, so it is sold whole.',
        ],
        [
            'name' => 'Cefe', 'generic_name' => 'Cefixime', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Antibiotics',
            'note' => '10*10 on the invoice.',
        ],
        [
            'name' => 'Cefe-Denk', 'generic_name' => null, 'strength' => null,
            'form' => 'injection', 'unit_of_measure' => 'vial',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'vial'],
            'category' => 'Antibiotics',
            'note' => 'Of 20 vials. The invoice names no active ingredient.',
        ],
        [
            'name' => 'Klamoks', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '228mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
            'note' => 'A different strength from the existing Klamoks 156mg/5ml.',
        ],
        [
            'name' => 'Benzathene Pen', 'generic_name' => 'Benzathine Penicillin', 'strength' => '2.4MU',
            'form' => 'injection', 'unit_of_measure' => 'vial',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'base' => 'vial'],
            'category' => 'Antibiotics',
            'note' => 'Of 10 vials.',
        ],

        // --- Antivirals ------------------------------------------------------
        [
            'name' => 'Aciclovir', 'generic_name' => 'Aciclovir', 'strength' => '400mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Antivirals',
            'note' => 'Printed "Acivlovil" and "Acivlovir" on the invoices; 10*10.',
        ],
        [
            'name' => 'Aciclovir Cream', 'generic_name' => 'Aciclovir', 'strength' => '5%',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Antivirals',
        ],

        // --- Analgesics and Antipyretics -------------------------------------
        [
            'name' => 'Paracetamol', 'generic_name' => 'Paracetamol', 'strength' => '250/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Poro brand.',
        ],
        [
            'name' => 'Aspirin', 'generic_name' => 'Aspirin', 'strength' => '81mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Easprin. Billed by the pack with no count, so it is sold whole.',
        ],
        [
            'name' => 'Para Denk', 'generic_name' => 'Paracetamol', 'strength' => '125mg',
            'form' => 'suppository', 'unit_of_measure' => 'suppository',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'base' => 'suppository'],
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Of 10. The invoice does not say whether it is a tablet or a suppository; the strength is the paediatric suppository one.',
        ],
        [
            'name' => 'Arten', 'generic_name' => 'Paracetamol', 'strength' => '250mg',
            'form' => 'suppository', 'unit_of_measure' => 'suppository',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'base' => 'suppository'],
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Suppository of 10.',
        ],
        [
            'name' => 'Panadol Advance', 'generic_name' => 'Paracetamol', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Analgesics and Antipyretics',
            'note' => '10*10 on the invoice.',
        ],

        // --- Gastrointestinal ------------------------------------------------
        [
            'name' => 'Oberem', 'generic_name' => 'Ondansetron', 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Gastrointestinal',
            'note' => 'The invoice writes the generic as "Ondasteron".',
        ],
        [
            'name' => 'Buscopan', 'generic_name' => 'Hyoscine Butylbromide', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'tablet'],
            'category' => 'Gastrointestinal',
            'note' => 'Of 30 tablets.',
        ],
        [
            'name' => 'Dizinil', 'generic_name' => 'Dimenhydrinate', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Gastrointestinal',
            'note' => '"10 to 10" on the invoice: 10 strips of 10.',
        ],
        [
            'name' => 'Imatox', 'generic_name' => 'Ondansetron', 'strength' => '4mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 1, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Gastrointestinal',
            'note' => '1*10: one strip of ten. The strip is a real rung, so a pack is ten tablets - it is not ten packs of ten.',
        ],
        [
            'name' => 'Imatox Injection', 'generic_name' => 'Ondansetron', 'strength' => null,
            'form' => 'injection', 'unit_of_measure' => 'pack',
            'category' => 'Gastrointestinal',
            'note' => 'Billed by the pack; the invoice does not print how many ampoules are inside.',
        ],
        [
            'name' => 'OPS', 'generic_name' => null, 'strength' => '20.5mg',
            'form' => 'sachet', 'unit_of_measure' => 'sachet',
            'packing' => ['top' => 'box', 'top_holds' => 50, 'base' => 'sachet'],
            'category' => 'Gastrointestinal',
            'note' => 'Epharm, of 50 sachets. The invoice gives no active ingredient.',
        ],

        // --- Cardiovascular --------------------------------------------------
        [
            'name' => 'Roxipinn', 'generic_name' => 'Rivaroxaban', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'tablet'],
            'category' => 'Cardiovascular',
            'note' => 'Of 30 tablets. No strength is printed.',
        ],
        [
            'name' => 'Amlo Denk', 'generic_name' => null, 'strength' => '5+5',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Cardiovascular',
            'note' => '"tong" is the 10x10 notation: 10 strips of 10 tablets, and strips are sold.',
        ],
        [
            'name' => 'Clopidogrel', 'generic_name' => 'Clopidogrel', 'strength' => '75mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 2, 'middle' => 'strip', 'middle_holds' => 14, 'base' => 'tablet'],
            'category' => 'Cardiovascular',
            'note' => 'Cladinil, 2*14.',
        ],
        [
            'name' => 'Fito-Alfa', 'generic_name' => 'Alfuzosin', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 3, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Cardiovascular',
            'note' => '3*10.',
        ],
        [
            'name' => 'Convenium Plus', 'generic_name' => null, 'strength' => '150/12.5',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Cardiovascular',
            'note' => 'Billed by the pack; no count is printed.',
        ],

        // --- Ophthalmology ---------------------------------------------------
        [
            'name' => 'Dorzy-T', 'generic_name' => 'Dorzolamide/Timolol', 'strength' => '2%/0.5%',
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
        ],
        [
            'name' => 'Optidrop', 'generic_name' => 'Dorzolamide/Timolol', 'strength' => null,
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
        ],
        [
            'name' => 'Lofto', 'generic_name' => 'Dexamethasone', 'strength' => null,
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
        ],

        // --- ENT and Respiratory ---------------------------------------------
        [
            'name' => 'Flixase', 'generic_name' => 'Fluticasone Furoate', 'strength' => '27.5mcg',
            'form' => 'nasal spray', 'unit_of_measure' => 'pack',
            'category' => 'ENT',
            'note' => 'Billed by the pack; the spray itself is one sellable unit.',
        ],
        [
            'name' => 'Xylometazoline Nasal Drop', 'generic_name' => 'Xylometazoline', 'strength' => '0.1%',
            'form' => 'nasal drop', 'unit_of_measure' => 'tube',
            'category' => 'ENT',
        ],
        [
            'name' => 'Mometasone Nasal Spray', 'generic_name' => 'Mometasone', 'strength' => null,
            'form' => 'nasal spray', 'unit_of_measure' => 'tube',
            'category' => 'ENT',
            'note' => 'Monetin brand; billed by the tube.',
        ],
        [
            'name' => 'Tabunex', 'generic_name' => null, 'strength' => null,
            'form' => 'nasal drop', 'unit_of_measure' => 'tube',
            'category' => 'ENT',
        ],
        [
            'name' => 'Cefol', 'generic_name' => null, 'strength' => '100ml',
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
            'note' => 'Cough syrup.',
        ],
        [
            'name' => 'Diphenhydramine Syrup', 'generic_name' => 'Diphenhydramine', 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
            'note' => 'The paediatric line on CR-00016961.',
        ],
        [
            'name' => 'Koflyn', 'generic_name' => 'Diphenhydramine', 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
        ],
        [
            'name' => 'Ibuclod Plus', 'generic_name' => null, 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
        ],
        [
            'name' => 'A-Ferin Plus', 'generic_name' => null, 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
        ],

        // --- Dermatology -----------------------------------------------------
        [
            'name' => 'FoliH Fort Cream', 'generic_name' => null, 'strength' => '30g',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
        ],
        [
            'name' => 'Hydrocortisone Cream', 'generic_name' => 'Hydrocortisone', 'strength' => '15g',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => 'Cortile brand. Distinct from Netracort ointment: a different form and brand.',
        ],
        [
            'name' => 'Ketazol', 'generic_name' => 'Ketoconazole', 'strength' => '40g',
            'form' => 'cream', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
        ],
        [
            'name' => 'Tridat Gel', 'generic_name' => 'Clindamycin', 'strength' => '1%',
            'form' => 'gel', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => '20g.',
        ],
        [
            'name' => 'Nitrofurazone Ointment', 'generic_name' => 'Nitrofurazone', 'strength' => '50g',
            'form' => 'ointment', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
        ],

        // --- Neurology -------------------------------------------------------
        [
            'name' => 'Risperdone', 'generic_name' => 'Risperidone', 'strength' => '2mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Neurology and Antiepileptics',
            'note' => 'Remedia brand, 10*10.',
        ],
        [
            'name' => 'Chlorpromazine', 'generic_name' => 'Chlorpromazine', 'strength' => '100mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Neurology and Antiepileptics',
            'note' => 'Bipronil. Billed by the pack; no count is printed.',
        ],

        // --- Chronic Care ----------------------------------------------------
        [
            'name' => 'Metformin', 'generic_name' => 'Metformin', 'strength' => '500mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Chronic Care',
            'note' => 'Formenin, 10 to 10.',
        ],
        [
            'name' => 'Cleep', 'generic_name' => 'Glimepiride', 'strength' => '4mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Chronic Care',
            'note' => 'The invoice prints "4g"; glimepiride is dosed in mg, so it is recorded as 4mg. No pack count is printed.',
        ],
        [
            'name' => 'PTU', 'generic_name' => 'Propylthiouracil', 'strength' => '100mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 10, 'middle' => 'strip', 'middle_holds' => 10, 'base' => 'tablet'],
            'category' => 'Chronic Care',
            'note' => '10*10.',
        ],

        // --- Vitamins and Supplements ----------------------------------------
        [
            'name' => 'Nativ', 'generic_name' => null, 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Liver-support supplement, of 30 capsules.',
        ],
        [
            'name' => 'Pregnavie Essentials', 'generic_name' => null, 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 30 capsules.',
        ],
        [
            'name' => 'Pregnavie Complete', 'generic_name' => null, 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 30 capsules.',
        ],
        [
            'name' => 'Juvil', 'generic_name' => 'Essential Phospholipids', 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 30 capsules.',
        ],
        [
            'name' => 'Livolin', 'generic_name' => 'Essential Phospholipids', 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'capsule',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'capsule'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 30 capsules.',
        ],
        [
            'name' => 'Vintro Strong', 'generic_name' => null, 'strength' => null,
            'form' => 'capsule', 'unit_of_measure' => 'pack',
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 3, but the invoice does not say of what, so it is sold whole rather than guessed.',
        ],
        [
            'name' => 'Folic Acid', 'generic_name' => 'Folic Acid', 'strength' => '5mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'tin', 'top_holds' => 30, 'base' => 'tablet'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Vitafolic, of 30 tablets in a tin. The tin holds tablets, so the tablet is the base unit: buying one tin is 30 tablets, not one unit.',
        ],
        [
            'name' => 'Vitacal', 'generic_name' => 'Calcium', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'tablet'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Of 30 tablets.',
        ],
        [
            'name' => 'Calci-Denk', 'generic_name' => 'Calcium', 'strength' => null,
            'form' => 'effervescent tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 20, 'base' => 'tablet'],
            'category' => 'Vitamins and Supplements',
            'note' => 'Effervescent, of 20.',
        ],
        [
            'name' => 'Vitamin D3 Drops', 'generic_name' => 'Cholecalciferol', 'strength' => '400 IU',
            'form' => 'drops', 'unit_of_measure' => 'bottle',
            'category' => 'Vitamins and Supplements',
            'note' => 'The invoice prints "Vitamin K (Vitamin D3) 400 IU"; the strength is the vitamin D3 one.',
        ],

        // --- Hormonal Medications --------------------------------------------
        [
            'name' => 'Yasmin', 'generic_name' => 'Drospirenone/Ethinylestradiol', 'strength' => null,
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Hormonal Medications',
            'note' => 'Billed by the dozen (12 packs); the sellable unit is one pack.',
        ],
        [
            'name' => 'Hanna', 'generic_name' => 'Desogestrel', 'strength' => '75mcg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'box', 'top_holds' => 28, 'base' => 'tablet'],
            'category' => 'Hormonal Medications',
            'note' => 'Of 28 tablets. The invoice prints "75g"; desogestrel is dosed in micrograms, so it is recorded as 75mcg.',
        ],

        // --- Anthelmintics ---------------------------------------------------
        [
            'name' => 'Mebendazole Suspension', 'generic_name' => 'Mebendazole', 'strength' => '100mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Anthelmintics',
            'note' => '30ml bottle.',
        ],

        // --- Other -----------------------------------------------------------
        [
            'name' => 'Aderm', 'generic_name' => null, 'strength' => '20mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 4, 'base' => 'tablet'],
            'category' => 'Other',
            'note' => 'Of 4. The invoice gives no active ingredient.',
        ],
        [
            'name' => 'Biomicotina', 'generic_name' => null, 'strength' => '2.5mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Other',
            'note' => 'Billed by the pack; no count is printed.',
        ],
        [
            'name' => 'Procortinone', 'generic_name' => 'Prednisolone', 'strength' => '5mg',
            'form' => 'tablet', 'unit_of_measure' => 'tablet',
            'packing' => ['top' => 'pack', 'top_holds' => 30, 'base' => 'tablet'],
            'category' => 'Other',
            'note' => 'Of 30 tablets.',
        ],
    ];

    /** Retail products from the invoices. SKUs follow the existing CAT-NAME-SIZE shape. */
    private const PRODUCTS = [
        [
            'name' => 'Denatured Alcohol 70%, 130ml', 'sku' => 'HY-ALC-130',
            'category' => 'Hygiene Products',
            'description' => 'Denatured alcohol 70%, 130ml bottle. Supplier invoice CA-00009817.',
        ],
        [
            'name' => 'Facemask 3 layers (Surgical) of 50', 'sku' => 'MS-FAC-050',
            'category' => 'Medical Supplies',
            'description' => 'Three-layer surgical face mask, 50 per box. Supplier invoice CA-00009817.',
        ],
        [
            'name' => 'Kidovit Syrup 200ml', 'sku' => 'VS-KID-200',
            'category' => 'Vitamins and Supplements',
            'description' => 'Multivitamin syrup, 200ml. Supplier invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Capsules (Vit B1, B2, B6) of 30', 'sku' => 'VS-RVPC-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Vitamin B complex (B1, B2, B6), 30 capsules. Supplier invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Max Capsules of 30', 'sku' => 'VS-RVMX-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Revamp Max, 30 capsules. Supplier invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Max Gold (Multivitamin & multimineral) of 30', 'sku' => 'VS-RVMG-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Multivitamin and multimineral, 30 capsules. Supplier invoice CA-00009743.',
        ],
        [
            'name' => 'Power Plus Gold of 30', 'sku' => 'VS-PWRG-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Power Plus Gold, 30 capsules. Supplier invoice CA-00009743.',
        ],
        [
            'name' => 'Rezumin (Multivitamin) of 30', 'sku' => 'VS-REZU-30',
            'category' => 'Vitamins and Supplements',
            // Flagged: the invoice line carried a quantity but no batch, no
            // expiry and no price, so it cannot be purchased - only listed.
            'description' => 'Multivitamin, 30 capsules. NOTE: the invoice line had no batch, expiry or price, so it cannot be entered as a purchase until Supplier supplies them.',
        ],
        [
            'name' => 'Disposable Syringe 20ml of 50', 'sku' => 'MS-SYR-020',
            'category' => 'Medical Supplies',
            'description' => 'Disposable syringe, 20ml, 50 per box. Supplier invoice CRSI-ST1-01-0005510.',
        ],
        [
            'name' => 'Disposable Syringe 5ml of 100', 'sku' => 'MS-SYR-005',
            'category' => 'Medical Supplies',
            'description' => 'Disposable syringe, 5ml, 100 per box. Supplier invoice CRSI-ST1-01-0005510.',
        ],
        [
            'name' => 'Hydrogen Peroxide 3%, 130ml', 'sku' => 'HY-HP2-130',
            'category' => 'Hygiene Products',
            'description' => 'Hydrogen peroxide 3%, 130ml bottle. Supplier invoice CRSI-ST1-01-0005739.',
        ],
    ];

    /** Devices, dressings and IV fluids from the September/October invoices. */
    private const LATE_PRODUCTS = [
        [
            'name' => 'Flamingo Pediatric Arm Sling Nursery, M', 'sku' => 'MS-SLG-NUR',
            'category' => 'Medical Supplies',
            'description' => 'Paediatric arm sling, nursery size M. Supplier invoice CR-00016546.',
        ],
        [
            'name' => 'Flamingo Arm Pouch, M', 'sku' => 'MS-ARM-M',
            'category' => 'Medical Supplies',
            'description' => 'Arm pouch, size M. Supplier invoice CR-00016546.',
        ],
        [
            'name' => 'Flamingo Arm Pouch, L', 'sku' => 'MS-ARM-L',
            'category' => 'Medical Supplies',
            'description' => 'Arm pouch, size L. Supplier invoice CR-00016546.',
        ],
        [
            'name' => 'Lyte Mix Lemon of 12', 'sku' => 'MS-LYT-LEM',
            'category' => 'Medical Supplies',
            'description' => 'Rehydration sachets, lemon, 12 per box. Supplier invoice CR-00016546.',
        ],
        [
            'name' => 'Lyte Mix Fruit Cocktail of 12', 'sku' => 'MS-LYT-FRT',
            'category' => 'Medical Supplies',
            'description' => 'Rehydration sachets, fruit cocktail, 12 per box. Supplier invoice CR-00016546.',
        ],
        [
            'name' => 'Precisa Blood Glucose Meter', 'sku' => 'MS-GLU-MTR',
            'category' => 'Medical Supplies',
            'description' => 'Blood glucose meter kit. Supplier invoice CA-00009742.',
        ],
        [
            'name' => 'Boso Thermometer', 'sku' => 'MS-THM-BOS',
            'category' => 'Medical Supplies',
            'description' => 'Clinical thermometer. Supplier invoice CA-00009742.',
        ],
        [
            'name' => 'BP Apparatus with Charger (Rolex)', 'sku' => 'MS-BP-ROL',
            'category' => 'Medical Supplies',
            'description' => 'Blood pressure apparatus with charger. Supplier invoice CR-00016560.',
        ],
        [
            'name' => 'Multivitamin Syrup 200ml', 'sku' => 'VS-MTV-200',
            'category' => 'Vitamins and Supplements',
            'description' => 'Multivitamin syrup, 200ml. Two batches arrived on CRSI-ST1-01-0005995; this is one product.',
        ],
        [
            'name' => 'Enderm CM Cream', 'sku' => 'HY-END-CM',
            'category' => 'Hygiene Products',
            'description' => 'Enderm CM cream. Supplier invoice CR-00016560.',
        ],
        [
            'name' => 'Precisa Blood Glucose Testing Strip of 50', 'sku' => 'MS-GLU-STR',
            'category' => 'Medical Supplies',
            'description' => 'Blood glucose test strips, 50 per pack. Supplier invoice CR-00016664.',
        ],
        [
            'name' => 'Sodium Chloride 0.9% (NS)', 'sku' => 'MS-IV-NS',
            'category' => 'Medical Supplies',
            'description' => 'Normal saline 0.9%, IV bag. Supplier invoice CR-00016961.',
        ],
        [
            'name' => 'Dextrose 40% 20ml of 50', 'sku' => 'MS-IV-D40',
            'category' => 'Medical Supplies',
            'description' => 'Dextrose 40%, 20ml ampoules, 50 per box. Supplier invoice CR-00016961.',
        ],
        [
            'name' => 'Zinc Oxide Plaster 2.5cm x 5 of 12', 'sku' => 'MS-PLS-Z25',
            'category' => 'Medical Supplies',
            'description' => 'Zinc oxide plaster 2.5cm x 5, 12 per box. Supplier invoice CRSI-ST1-01-0005996.',
        ],
        [
            'name' => 'Zinc Oxide Plaster 7.5cm x 5m of 6', 'sku' => 'MS-PLS-Z75',
            'category' => 'Medical Supplies',
            'description' => 'Zinc oxide plaster 7.5cm x 5m, 6 per pack. Supplier invoice CRSI-ST1-01-0005996.',
        ],
        [
            'name' => 'Elastic Bandage 7.5cm x 5m', 'sku' => 'MS-BND-E75',
            'category' => 'Medical Supplies',
            'description' => 'Elastic bandage 7.5cm x 5m, per roll. Supplier invoice CRSI-ST1-01-0005996.',
        ],
        [
            'name' => 'Elastic Bandage 12.5cm x 5m', 'sku' => 'MS-BND-E125',
            'category' => 'Medical Supplies',
            'description' => 'Elastic bandage 12.5cm x 5m, per roll. Supplier invoice CRSI-ST1-01-0005996.',
        ],
        [
            'name' => 'Gauze Bandage 12.5cm x 5m', 'sku' => 'MS-BND-G125',
            'category' => 'Medical Supplies',
            'description' => 'Gauze bandage 12.5cm x 5m. Supplier invoice CRSI-ST1-01-0005996.',
        ],
        [
            'name' => 'On-Call Extra Meter', 'sku' => 'MS-MTR-ONC',
            'category' => 'Medical Supplies',
            'description' => 'On-Call Extra blood glucose meter. Supplier invoice CR-00016664.',
        ],
    ];

    /**
     * Rows where a container genuinely is the thing on the shelf, so it is
     * correct for the base unit to be a container even though the form is
     * countable. Anything else in that shape is a packing gap, not a decision.
     *
     * Enforced by tools/catalogue-validate.php. Adding a row here without a
     * reason is how a "pack of 100 tablets" quietly becomes one sellable unit.
     */
    private const SOLD_WHOLE = [
        'Yasmin' => 'a 28-tablet cycle pack is dispensed whole; the pack is the sellable unit.',
        'Flixase' => 'one nasal-spray bottle per pack; the spray itself is one sellable unit.',
    ];

    /**
     * Invoiced items deliberately matched to a row that already exists rather
     * than duplicated. Recorded here so the decision is visible instead of
     * looking like an omission.
     */
    private const MATCHED = [
        'E-Mox 250mg/5ml (Amoxicillin)' => 'same strength as the existing Amoxicillin 250mg/5ml, so one row; the two brands become separate batches at purchase time',
        'Amox+Clav 457mg/5ml (Cledomox)' => 'already in the catalogue as Cledomox 457mg/5ml',
        'Cipro-Vir 500mg of 20' => 'same brand and strength as the of-14 row; see that row\'s note',
        'Azithromycin 500mg of 3' => 'already in the catalogue as Azithromycin 500 mg, tablet',
        'Azithromycin 250mg of 6' => 'already in the catalogue as Azithromycin 250mg',
        'Zithromax 500mg of 3 (CR-00016664)' => 'the same strength as Azithromycin 500 mg; the brand becomes a batch at purchase time',
        'Albendazole Susp. (CR-00016664)' => 'the same row as Albendazole Suspension 20ml',
        'Valcodin 5mg/160mg 2x14 (CR-00016560)' => 'already in the catalogue as Valcodin',
        'Acivlovil / Acvclovir (400mg and cream)' => 'one Aciclovir row each, however the invoice spells it',
        'Multivitamin Syrup 200ml (two lines)' => 'one product row; the two lines are two batches',
        'Koflyn and paediatric Diphenhydramine' => 'two brands of the same syrup, kept as two rows because they are separate stock',
    ];

    /** Placeholder reorder levels, in whole packs. */
    private const DRUG_MIN_STOCK = 10;
    private const PRODUCT_MIN_STOCK = 5;

    /**
     * Progress output. Written through the command when one is attached, and
     * silently skipped when the seeder is called directly, so the class can be
     * used from a test or a console script without a command.
     */
    private function say(string $line): void
    {
        $this->command?->line($line);
    }

    public function run(): void
    {
        [$drugCategories, $newDrugCategories] = $this->ensureCategories(DrugCategory::class, self::DRUG_CATEGORIES);
        [$productCategories, $newProductCategories] = $this->ensureCategories(ProductCategory::class, self::PRODUCT_CATEGORIES);

        $created = 0;
        $updated = 0;

        // Matching is on a normalised key, never on the raw strings. "500 mg"
        // and "500mg" are the same tablet; matching them literally is how the
        // An earlier import once put a second Metformin beside the first.
        $existingByKey = [];
        foreach (Drug::all() as $known) {
            $existingByKey[$this->naturalKey($known->name, $known->strength)][] = $known;
        }

        foreach (array_merge(self::DRUGS, self::LATE_DRUGS) as $row) {
            $categoryId = $this->categoryId($drugCategories, $row['category']);

            $existing = $this->matchExisting(
                $existingByKey[$this->naturalKey($row['name'], $row['strength'])] ?? [],
                $row['form'] ?? null
            );

            $packing = $row['packing'] ?? [
                'top' => $row['unit_of_measure'],
                'base' => $row['unit_of_measure'],
            ];

            $attributes = [
                'generic_name' => $row['generic_name'],
                'form' => $row['form'],
                'category_id' => $categoryId,

                // The rungs the invoice describes. The model mirrors the
                // old unit_of_measure / units_per_strip / strips_per_pack
                // columns from these on save, so nothing here writes them.
                'unit_top' => $packing['top'],
                'top_holds' => $packing['top_holds'] ?? null,
                'unit_middle' => $packing['middle'] ?? null,
                'middle_holds' => $packing['middle_holds'] ?? null,
                'unit_base' => $packing['base'],
                'sell_top' => true,
                'sell_middle' => isset($packing['middle']),
                'sell_base' => true,
            ];

            // The reorder level is an operational setting, not a fact from the
            // invoice. Only a brand new row gets the placeholder; an existing
            // row keeps whatever the pharmacy has already set.
            if (! $existing) {
                $attributes['minimum_stock_level'] = self::DRUG_MIN_STOCK;
            }

            if ($existing) {
                $existing->update($attributes);
                $drug = $existing;
            } else {
                // strength is part of the identity, so it is written only when
                // the row is new. An existing row keeps the spelling it has.
                $drug = Drug::create(array_merge($attributes, [
                    'name' => $row['name'],
                    'strength' => $row['strength'],
                ]));
            }

            $existing ? $updated++ : $created++;
            $this->say(sprintf('  %s %-38s %-12s [%s]', $existing ? 'updated' : 'created', $drug->name, $drug->strength ?? '-', $row['category']));
        }

        $productsCreated = 0;
        $productsUpdated = 0;

        foreach (array_merge(self::PRODUCTS, self::LATE_PRODUCTS) as $row) {
            // Medical supplies, vitamins and antiseptics have moved to the
            // drugs side. Re-seeding must not put them back on the products
            // side; the move command fills the drug row instead.
            if (Drug::where('sku', $row['sku'])->exists()) {
                continue;
            }

            $existing = Product::where('sku', $row['sku'])->first();

            Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $this->categoryId($productCategories, $row['category']),
                    'description' => $row['description'],
                    'minimum_stock' => self::PRODUCT_MIN_STOCK,
                    'active' => true,
                ]
            );

            $existing ? $productsUpdated++ : $productsCreated++;
            $this->say(sprintf('  %s %-46s [%s]', $existing ? 'updated' : 'created', $row['name'], $row['category']));
        }

        $this->say("");
        $this->say(sprintf(
            'Categories created: %d drug, %d product. Drugs created %d, updated %d. Products created %d, updated %d.',
            count($newDrugCategories),
            count($newProductCategories),
            $created,
            $updated,
            $productsCreated,
            $productsUpdated
        ));

        if (self::MATCHED !== []) {
            $this->say("");
            $this->say('Matched to existing rows rather than duplicated:');
            foreach (self::MATCHED as $item => $why) {
                $this->say("  {$item} - {$why}");
            }
        }
    }

    /**
     * Ensure every category this seeder depends on exists.
     *
     * Lists categories that already exist on the current install alongside the
     * new ones, so the seeder works on a fresh database too. firstOrCreate()
     * leaves an existing row - and its description - untouched.
     *
     * @param  class-string  $model
     * @param  array<string, string>  $categories
     * @return array{0: array<string, int>, 1: array<int, string>} ids by name, and the names actually created
     */
    private function ensureCategories(string $model, array $categories): array
    {
        $ids = [];
        $created = [];

        foreach ($model::all() as $existing) {
            $ids[$existing->name] = $existing->id;
        }

        foreach ($categories as $name => $description) {
            if (isset($ids[$name])) {
                continue;
            }

            $ids[$name] = $model::firstOrCreate(['name' => $name], ['description' => $description])->id;
            $created[] = $name;
        }

        return [$ids, $created];
    }

    /**
     * The key two catalogue rows are "the same drug" by: name and strength,
     * with case, spacing, punctuation and micro-sign spelling removed.
     *
     * "500 mg", "500mg" and "500 MG" are one tablet, not three. The same
     * normalisation is used by tools/catalogue-validate.php, so a definition
     * that passes the gate is one the seeder will not duplicate.
     */
    private function naturalKey(?string $name, ?string $strength): string
    {
        $clean = static function (?string $value): string {
            $value = str_replace(['Âµ', 'Î¼'], 'u', strtolower((string) $value));

            return (string) preg_replace('/[^a-z0-9]+/', '', $value);
        };

        return $clean($name) . '|' . $clean($strength);
    }

    /**
     * Choose the row a definition line belongs to. Two rows can share a
     * normalised key when a name is reused for a different form, so an exact
     * form match wins; the first row is the fallback.
     *
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

    /** @param array<string, int> $categories */
    private function categoryId(array $categories, string $name): int
    {
        if (! isset($categories[$name])) {
            throw new \RuntimeException("Category \"{$name}\" was not created; refusing to file a catalogue row under nothing.");
        }

        return $categories[$name];
    }
}

