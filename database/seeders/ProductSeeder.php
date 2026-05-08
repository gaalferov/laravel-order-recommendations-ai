<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Coffee beans
            [
                'sku' => 'BEAN-ETH-250',
                'name' => 'Ethiopian Yirgacheffe, 250g',
                'description' => 'Single-origin light roast with floral aroma and bright citrus notes.',
                'price_cents' => 1800,
                'category' => 'Coffee Beans',
                'tags' => ['single-origin', 'light-roast', 'africa', 'floral'],
            ],
            [
                'sku' => 'BEAN-COL-500',
                'name' => 'Colombian Supremo, 500g',
                'description' => 'Medium roast Colombian beans with caramel sweetness and balanced body.',
                'price_cents' => 2200,
                'category' => 'Coffee Beans',
                'tags' => ['single-origin', 'medium-roast', 'americas', 'balanced'],
            ],
            [
                'sku' => 'BEAN-ITA-1000',
                'name' => 'Italian Espresso Blend, 1kg',
                'description' => 'Dark roast blend with deep chocolate and nutty notes, ideal for espresso.',
                'price_cents' => 3400,
                'category' => 'Coffee Beans',
                'tags' => ['blend', 'dark-roast', 'espresso', 'chocolate'],
            ],
            [
                'sku' => 'BEAN-DEC-250',
                'name' => 'Decaf Honduras, 250g',
                'description' => 'Swiss Water decaf with smooth body and subtle cocoa finish.',
                'price_cents' => 1900,
                'category' => 'Coffee Beans',
                'tags' => ['decaf', 'medium-roast', 'americas', 'smooth'],
            ],

            // Brewing equipment
            [
                'sku' => 'EQ-V60-02',
                'name' => 'Hario V60 Pour-Over Dripper',
                'description' => 'Ceramic pour-over cone for clean, bright coffee. Serves 1-4 cups.',
                'price_cents' => 2500,
                'category' => 'Brewing Equipment',
                'tags' => ['pour-over', 'manual-brew', 'single-cup'],
            ],
            [
                'sku' => 'EQ-AEROPRESS',
                'name' => 'AeroPress Original',
                'description' => 'Immersion brewer that produces rich, smooth coffee in under a minute.',
                'price_cents' => 3999,
                'category' => 'Brewing Equipment',
                'tags' => ['immersion', 'portable', 'quick-brew'],
            ],
            [
                'sku' => 'EQ-FRENCH-8C',
                'name' => 'French Press, 8-cup',
                'description' => 'Stainless steel French press for full-bodied coffee. Serves up to 8 cups.',
                'price_cents' => 4500,
                'category' => 'Brewing Equipment',
                'tags' => ['french-press', 'full-bodied', 'multi-cup'],
            ],
            [
                'sku' => 'EQ-GRINDER-MAN',
                'name' => 'Manual Burr Grinder',
                'description' => 'Hand-crank ceramic burr grinder with adjustable coarseness settings.',
                'price_cents' => 5900,
                'category' => 'Brewing Equipment',
                'tags' => ['grinder', 'manual', 'adjustable'],
            ],

            // Accessories
            [
                'sku' => 'ACC-KETTLE-GOOSE',
                'name' => 'Gooseneck Pour-Over Kettle, 1L',
                'description' => 'Precision pour kettle with a thin spout for controlled bloom and pour.',
                'price_cents' => 6500,
                'category' => 'Accessories',
                'tags' => ['kettle', 'pour-over-accessory', 'precision'],
            ],
            [
                'sku' => 'ACC-FILTERS-V60',
                'name' => 'V60 Paper Filters, 100 pack',
                'description' => 'Natural unbleached paper filters compatible with all V60 size 02 drippers.',
                'price_cents' => 1200,
                'category' => 'Accessories',
                'tags' => ['filters', 'pour-over-accessory', 'consumable'],
            ],
            [
                'sku' => 'ACC-SCALE-01',
                'name' => 'Digital Coffee Scale with Timer',
                'description' => 'Precise 0.1g scale with built-in timer for pour-over and espresso.',
                'price_cents' => 4200,
                'category' => 'Accessories',
                'tags' => ['scale', 'precision', 'timer'],
            ],
            [
                'sku' => 'ACC-MUG-CERAMIC',
                'name' => 'Ceramic Tasting Mug, 300ml',
                'description' => 'Handcrafted ceramic mug designed to highlight coffee aromatics.',
                'price_cents' => 1800,
                'category' => 'Accessories',
                'tags' => ['mug', 'ceramic', 'tasting'],
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                $product + ['currency' => 'USD'],
            );
        }
    }
}
