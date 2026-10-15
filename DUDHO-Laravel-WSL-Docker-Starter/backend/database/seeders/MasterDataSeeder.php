<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        foreach ([
            ['code' => 'cow', 'name' => 'Cow Milk', 'sort_order' => 10],
            ['code' => 'buffalo', 'name' => 'Buffalo Milk', 'sort_order' => 20],
        ] as $milkType) {
            DB::table('milk_types')->updateOrInsert(
                ['code' => $milkType['code']],
                [...$milkType, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach ([
            ['code' => 'fat', 'name' => 'Fat', 'unit' => '%', 'decimal_places' => 2, 'sort_order' => 10],
            ['code' => 'snf', 'name' => 'SNF', 'unit' => '%', 'decimal_places' => 2, 'sort_order' => 20],
            ['code' => 'density', 'name' => 'Density', 'unit' => null, 'decimal_places' => 2, 'sort_order' => 30],
            ['code' => 'protein', 'name' => 'Protein', 'unit' => '%', 'decimal_places' => 2, 'sort_order' => 40],
            ['code' => 'added_water', 'name' => 'Added Water', 'unit' => '%', 'decimal_places' => 2, 'sort_order' => 50],
        ] as $parameter) {
            DB::table('quality_parameters')->updateOrInsert(
                ['code' => $parameter['code']],
                [
                    ...$parameter,
                    'value_type' => 'decimal',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
