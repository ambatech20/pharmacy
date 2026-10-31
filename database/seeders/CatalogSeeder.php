<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $drugCategories = [
            'Analgesics and Antipyretics' => 'Medicines commonly used for pain relief and fever management.',
            'Antibiotics' => 'Prescription medicines used to treat susceptible bacterial infections.',
            'Antimalarials' => 'Medicines used for malaria treatment and prevention according to clinical guidance.',
            'Antihistamines and Allergy' => 'Medicines for common allergic symptoms such as itching, sneezing, and rashes.',
            'Gastrointestinal' => 'Medicines and oral rehydration products for common digestive complaints.',
            'Chronic Care' => 'Long-term medicines commonly used for blood pressure and blood sugar management.',
            'Vitamins and Supplements' => 'Nutritional supplements and medicines used to address common deficiencies.',
        ];

        foreach ([
            'Analgesic' => 'Analgesics and Antipyretics',
            'Antibiotic' => 'Antibiotics',
            'Antimalarial' => 'Antimalarials',
        ] as $legacyName => $canonicalName) {
            $legacyCategory = DrugCategory::where('name', $legacyName)->first();
            $canonicalCategory = DrugCategory::where('name', $canonicalName)->first();

            if ($legacyCategory && $canonicalCategory) {
                Drug::where('category_id', $legacyCategory->id)->update(['category_id' => $canonicalCategory->id]);
                $legacyCategory->delete();
            } elseif ($legacyCategory) {
                $legacyCategory->update(['name' => $canonicalName]);
            }
        }

        foreach (array_keys($drugCategories) as $name) {
            $categories = DrugCategory::where('name', $name)->get();
            $primaryCategory = $categories->first();

            foreach ($categories->skip(1) as $duplicateCategory) {
                Drug::where('category_id', $duplicateCategory->id)->update(['category_id' => $primaryCategory->id]);
                $duplicateCategory->delete();
            }
        }

        $drugCategoryIds = [];
        foreach ($drugCategories as $name => $description) {
            $drugCategoryIds[$name] = DrugCategory::updateOrCreate(
                ['name' => $name],
                ['description' => $description]
            )->id;
        }

        $drugs = [
            ['name' => 'Paracetamol', 'generic_name' => 'Paracetamol', 'strength' => '500 mg', 'form' => 'Tablet', 'category' => 'Analgesics and Antipyretics', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 100],
            ['name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'strength' => '400 mg', 'form' => 'Tablet', 'category' => 'Analgesics and Antipyretics', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 80],
            ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '500 mg', 'form' => 'Capsule', 'category' => 'Antibiotics', 'unit_of_measure' => 'capsule', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 100],
            ['name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'strength' => '500 mg', 'form' => 'Tablet', 'category' => 'Antibiotics', 'unit_of_measure' => 'tablet', 'units_per_strip' => 3, 'strips_per_pack' => 1, 'minimum_stock_level' => 30],
            ['name' => 'Ciprofloxacin', 'generic_name' => 'Ciprofloxacin', 'strength' => '500 mg', 'form' => 'Tablet', 'category' => 'Antibiotics', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 80],
            ['name' => 'Artemether/Lumefantrine', 'generic_name' => 'Artemether/Lumefantrine', 'strength' => '20/120 mg', 'form' => 'Tablet', 'category' => 'Antimalarials', 'unit_of_measure' => 'tablet', 'units_per_strip' => 6, 'strips_per_pack' => 1, 'minimum_stock_level' => 30],
            ['name' => 'Chloroquine', 'generic_name' => 'Chloroquine', 'strength' => '150 mg', 'form' => 'Tablet', 'category' => 'Antimalarials', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 30],
            ['name' => 'Cetirizine', 'generic_name' => 'Cetirizine', 'strength' => '10 mg', 'form' => 'Tablet', 'category' => 'Antihistamines and Allergy', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 80],
            ['name' => 'Loratadine', 'generic_name' => 'Loratadine', 'strength' => '10 mg', 'form' => 'Tablet', 'category' => 'Antihistamines and Allergy', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 60],
            ['name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'strength' => '20 mg', 'form' => 'Capsule', 'category' => 'Gastrointestinal', 'unit_of_measure' => 'capsule', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 80],
            ['name' => 'Metformin', 'generic_name' => 'Metformin', 'strength' => '500 mg', 'form' => 'Tablet', 'category' => 'Chronic Care', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 100],
            ['name' => 'Amlodipine', 'generic_name' => 'Amlodipine', 'strength' => '5 mg', 'form' => 'Tablet', 'category' => 'Chronic Care', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 100],
            ['name' => 'Ferrous Sulfate', 'generic_name' => 'Ferrous Sulfate', 'strength' => '200 mg', 'form' => 'Tablet', 'category' => 'Vitamins and Supplements', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 80],
            ['name' => 'Vitamin B Complex', 'generic_name' => 'Vitamin B Complex', 'strength' => 'B1/B6/B12', 'form' => 'Tablet', 'category' => 'Vitamins and Supplements', 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10, 'minimum_stock_level' => 60],
            ['name' => 'Oral Rehydration Salts', 'generic_name' => 'ORS', 'strength' => '20.5 g sachet', 'form' => 'Powder', 'category' => 'Gastrointestinal', 'unit_of_measure' => 'sachet', 'units_per_strip' => 1, 'strips_per_pack' => 20, 'minimum_stock_level' => 40],
        ];

        foreach ($drugs as $drug) {
            $categoryId = $drugCategoryIds[$drug['category']];
            unset($drug['category']);

            Drug::updateOrCreate(
                ['name' => $drug['name'], 'generic_name' => $drug['generic_name']],
                [...$this->withPacking($drug), 'category_id' => $categoryId]
            );
        }

        $productCategories = [
            'Baby Care' => 'Everyday hygiene and comfort products for infants and young children.',
            'Hygiene Products' => 'Antiseptics, disinfectants, and products for everyday cleanliness.',
            'Medical Supplies' => 'Non-prescription equipment and consumables used for basic home and clinic care.',
            'Oral Care' => 'Toothpaste, toothbrushes, and other products for daily oral hygiene.',
            'Personal Care' => 'Daily-use products for personal cleanliness and convenient self-care.',
            'Skin Care' => 'Moisturizers and protective products for routine skin care.',
            'Hair Care' => 'Shampoos, conditioners, and treatments for routine hair care.',
            'Cosmetics' => 'Beauty and grooming products stocked for general retail customers.',
            'Other Products' => 'Useful pharmacy counter items that do not fit another product category.',
        ];

        $productCategoryIds = [];
        foreach ($productCategories as $name => $description) {
            $productCategoryIds[$name] = ProductCategory::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'active' => true]
            )->id;
        }

        $products = [
            ['name' => 'Savlon Antiseptic Liquid 250 ml', 'sku' => 'HY-SAV-250', 'category' => 'Hygiene Products', 'brand' => 'Savlon', 'description' => 'Antiseptic liquid for household and first-aid use.', 'purchase_price' => 95, 'selling_price' => 120, 'minimum_stock' => 20],
            ['name' => 'Dettol Antiseptic Liquid 250 ml', 'sku' => 'HY-DET-250', 'category' => 'Hygiene Products', 'brand' => 'Dettol', 'description' => 'Antiseptic liquid for external use.', 'purchase_price' => 110, 'selling_price' => 140, 'minimum_stock' => 20],
            ['name' => 'Cotton Wool 100 g', 'sku' => 'MS-COT-100', 'category' => 'Medical Supplies', 'brand' => 'Local', 'description' => 'Absorbent cotton wool for wound care.', 'purchase_price' => 55, 'selling_price' => 75, 'minimum_stock' => 25],
            ['name' => 'Sterile Gauze Swabs 10 cm x 10 cm', 'sku' => 'MS-GAU-10', 'category' => 'Medical Supplies', 'brand' => 'Local', 'description' => 'Sterile gauze swabs for dressing wounds.', 'purchase_price' => 18, 'selling_price' => 25, 'minimum_stock' => 100],
            ['name' => 'Disposable Examination Gloves, Large', 'sku' => 'MS-GLV-L', 'category' => 'Medical Supplies', 'brand' => 'Local', 'description' => 'Single-use examination gloves.', 'purchase_price' => 8, 'selling_price' => 12, 'minimum_stock' => 200],
            ['name' => 'Digital Thermometer', 'sku' => 'MS-THM-DIG', 'category' => 'Medical Supplies', 'brand' => 'Rossmax', 'description' => 'Digital thermometer for home temperature checks.', 'purchase_price' => 320, 'selling_price' => 400, 'minimum_stock' => 10],
            ['name' => 'Toothpaste 100 ml', 'sku' => 'OC-TOP-100', 'category' => 'Oral Care', 'brand' => 'Colgate', 'description' => 'Fluoride toothpaste for daily oral care.', 'purchase_price' => 85, 'selling_price' => 110, 'minimum_stock' => 25],
            ['name' => 'Soft Toothbrush', 'sku' => 'OC-BRS-SFT', 'category' => 'Oral Care', 'brand' => 'Colgate', 'description' => 'Soft-bristle adult toothbrush.', 'purchase_price' => 45, 'selling_price' => 60, 'minimum_stock' => 30],
            ['name' => 'Petroleum Jelly 100 ml', 'sku' => 'SC-PJV-100', 'category' => 'Skin Care', 'brand' => 'Vaseline', 'description' => 'Moisturizing jelly for dry skin.', 'purchase_price' => 95, 'selling_price' => 125, 'minimum_stock' => 20],
            ['name' => 'Moisturizing Lotion 200 ml', 'sku' => 'SC-LOT-200', 'category' => 'Skin Care', 'brand' => 'Nivea', 'description' => 'Body lotion for normal and dry skin.', 'purchase_price' => 260, 'selling_price' => 325, 'minimum_stock' => 15],
            ['name' => 'Baby Diapers, Medium, 20 Pack', 'sku' => 'BC-DIA-M20', 'category' => 'Baby Care', 'brand' => 'Molfix', 'description' => 'Disposable baby diapers, medium size.', 'purchase_price' => 360, 'selling_price' => 450, 'minimum_stock' => 12],
            ['name' => 'Baby Wipes, 72 Count', 'sku' => 'BC-WIP-72', 'category' => 'Baby Care', 'brand' => 'Johnson\'s', 'description' => 'Gentle cleansing wipes for babies.', 'purchase_price' => 170, 'selling_price' => 220, 'minimum_stock' => 15],
            ['name' => 'Hand Sanitizer 500 ml', 'sku' => 'PC-SAN-500', 'category' => 'Personal Care', 'brand' => 'Local', 'description' => 'Alcohol-based hand sanitizer.', 'purchase_price' => 130, 'selling_price' => 165, 'minimum_stock' => 20],
            ['name' => 'Baby Shampoo 200 ml', 'sku' => 'BC-SHP-200', 'category' => 'Baby Care', 'brand' => 'Johnson\'s', 'description' => 'Mild shampoo formulated for babies.', 'purchase_price' => 210, 'selling_price' => 270, 'minimum_stock' => 12],
            ['name' => 'Liquid Hand Soap 250 ml', 'sku' => 'HY-SOA-250', 'category' => 'Hygiene Products', 'brand' => 'Local', 'description' => 'Liquid soap for routine hand washing.', 'purchase_price' => 65, 'selling_price' => 85, 'minimum_stock' => 25],
            ['name' => 'Elastic Crepe Bandage 10 cm', 'sku' => 'MS-BAN-10', 'category' => 'Medical Supplies', 'brand' => 'Local', 'description' => 'Reusable elastic bandage for support and compression.', 'purchase_price' => 75, 'selling_price' => 100, 'minimum_stock' => 20],
            ['name' => 'Mouthwash 500 ml', 'sku' => 'OC-MOU-500', 'category' => 'Oral Care', 'brand' => 'Listerine', 'description' => 'Daily mouthwash for fresh breath and oral hygiene.', 'purchase_price' => 230, 'selling_price' => 290, 'minimum_stock' => 15],
            ['name' => 'Shampoo 400 ml', 'sku' => 'HC-SHP-400', 'category' => 'Hair Care', 'brand' => 'Sunsilk', 'description' => 'Everyday shampoo for normal hair.', 'purchase_price' => 180, 'selling_price' => 230, 'minimum_stock' => 15],
            ['name' => 'Hair Conditioner 200 ml', 'sku' => 'HC-CON-200', 'category' => 'Hair Care', 'brand' => 'Dove', 'description' => 'Conditioner for softer, easier-to-manage hair.', 'purchase_price' => 190, 'selling_price' => 245, 'minimum_stock' => 12],
            ['name' => 'Lip Balm', 'sku' => 'CO-LIP-001', 'category' => 'Cosmetics', 'brand' => 'Nivea', 'description' => 'Protective balm for dry lips.', 'purchase_price' => 95, 'selling_price' => 125, 'minimum_stock' => 15],
            ['name' => 'Body Spray 150 ml', 'sku' => 'CO-SPR-150', 'category' => 'Cosmetics', 'brand' => 'Rexona', 'description' => 'Personal fragrance and underarm spray.', 'purchase_price' => 180, 'selling_price' => 230, 'minimum_stock' => 12],
            ['name' => 'Hot Water Bottle 2 L', 'sku' => 'OT-HWB-2L', 'category' => 'Other Products', 'brand' => 'Local', 'description' => 'Reusable hot water bottle for household comfort care.', 'purchase_price' => 280, 'selling_price' => 350, 'minimum_stock' => 8],
            ['name' => 'Pill Organizer, Weekly', 'sku' => 'OT-PIL-WK', 'category' => 'Other Products', 'brand' => 'Local', 'description' => 'Seven-day organizer for separating routine medicines.', 'purchase_price' => 120, 'selling_price' => 160, 'minimum_stock' => 10],
        ];

        foreach ($products as $product) {
            // Once a medical supply or antiseptic has moved to the drugs side,
            // re-seeding must not recreate it as a product.
            if (Drug::where('sku', $product['sku'])->exists()) {
                continue;
            }

            $categoryId = $productCategoryIds[$product['category']];
            unset($product['category']);

            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [...$product, 'category_id' => $categoryId, 'active' => true]
            );
        }
    }

    /**
     * Turn the table's simple tablet / strip / pack numbers into the rungs the
     * model now stores, and move the minimum into the top unit.
     *
     * The rows above are kept in their original, readable form so the seed data
     * still reads like the shelf: "10 tablets per strip, 10 strips per pack".
     *
     * @param  array<string, mixed>  $drug
     * @return array<string, mixed>
     */
    private function withPacking(array $drug): array
    {
        $base = $drug['unit_of_measure'];
        $perStrip = max(1, (int) $drug['units_per_strip']);
        $perPack = max(1, (int) $drug['strips_per_pack']);

        if ($perStrip > 1) {
            // Pack -> strip -> tablet, the shape the old columns described.
            $drug['unit_top'] = 'pack';
            $drug['unit_middle'] = 'strip';
            $drug['unit_base'] = $base;
            $drug['top_holds'] = $perPack;
            $drug['middle_holds'] = $perStrip;
            $factor = $perPack * $perStrip;
        } elseif ($perPack > 1) {
            // A pack of sachets: something countable sits directly inside it.
            $drug['unit_top'] = 'pack';
            $drug['unit_middle'] = null;
            $drug['unit_base'] = $base;
            $drug['top_holds'] = $perPack;
            $drug['middle_holds'] = null;
            $factor = $perPack;
        } else {
            // Sold as it arrives.
            $drug['unit_top'] = $base;
            $drug['unit_middle'] = null;
            $drug['unit_base'] = $base;
            $drug['top_holds'] = null;
            $drug['middle_holds'] = null;
            $factor = 1;
        }

        $drug['sell_top'] = true;
        $drug['sell_middle'] = $drug['unit_middle'] !== null;
        $drug['sell_base'] = true;

        // The table's minimum was written in base units; the column now means
        // top units, so the number has to be divided and rounded up.
        $drug['minimum_stock_level'] = $factor > 1
            ? (int) ceil((int) $drug['minimum_stock_level'] / $factor)
            : (int) $drug['minimum_stock_level'];

        return $drug;
    }
}