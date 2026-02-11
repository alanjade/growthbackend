<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Land;
use App\Models\LandPriceHistory;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Create Land (without geometry first)
            $land = Land::create([
                'title' => 'Prime Estate Plot',
                'location' => 'Ibeju-Lekki, Lagos',
                'size' => 600,
                'total_units' => 50,
                'available_units' => 50,
                'description' => 'Dry land in a fast-developing area.',
                'lat' => 6.4698,
                'lng' => 3.5852,
                'is_available' => true,
            ]);

            // Add PostGIS Geometry (Polygon Example)
            $wkt = "POLYGON((
                3.5850 6.4695,
                3.5860 6.4695,
                3.5860 6.4705,
                3.5850 6.4705,
                3.5850 6.4695
            ))";

            DB::statement(
                "UPDATE lands SET coordinates = ST_GeomFromText(?, 4326) WHERE id = ?",
                [$wkt, $land->id]
            );

            // Create initial price history
            LandPriceHistory::create([
                'land_id' => $land->id,
                'price_per_unit_kobo' => 500000000, // ₦5,000,000
                'price_date' => now()->toDateString(),
            ]);
        });
    }
}
