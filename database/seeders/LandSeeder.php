<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Land;
use App\Models\LandPriceHistory;
use Faker\Factory as Faker;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Get all seed images
        $images = collect(Storage::files('public/seed/lands'))
            ->map(fn($path) => str_replace('public/', '', $path))
            ->values();

        for ($i = 1; $i <= 10; $i++) {

            DB::transaction(function () use ($images, $i, $faker) {

                // Generate Lagos-like random coordinates
                $lat = $faker->randomFloat(6, 6.40, 6.65);
                $lng = $faker->randomFloat(6, 3.20, 3.65);

                $land = Land::create([
                    'title' => "Premium Estate Plot $i",
                    'location' => $faker->randomElement([
                        'Ibeju-Lekki',
                        'Epe',
                        'Ajah',
                        'Sangotedo',
                        'Abijo'
                    ]) . ', Lagos',
                    'size' => $faker->randomElement([300, 450, 600]),
                    'total_units' => 50,
                    'available_units' => $faker->numberBetween(1000, 2500),
                    'description' => $faker->sentence(12),
                    'lat' => $lat,
                    'lng' => $lng,
                    'is_available' => true,
                ]);

                // Create Polygon (small square around point)
                $offset = 0.002;
                $wkt = "POLYGON((
                    " . ($lng - $offset) . " " . ($lat - $offset) . ",
                    " . ($lng + $offset) . " " . ($lat - $offset) . ",
                    " . ($lng + $offset) . " " . ($lat + $offset) . ",
                    " . ($lng - $offset) . " " . ($lat + $offset) . ",
                    " . ($lng - $offset) . " " . ($lat - $offset) . "
                ))";

                DB::statement(
                    "UPDATE lands SET coordinates = ST_GeomFromText(?, 4326) WHERE id = ?",
                    [$wkt, $land->id]
                );

                // Price history
                LandPriceHistory::create([
                    'land_id' => $land->id,
                    'price_per_unit_kobo' => $faker->numberBetween(300_000, 800_000),
                    'price_date' => now()->toDateString(),
                ]);

                // Attach 1–3 random images
                $selectedImages = $images->random(min(3, $images->count()));

                foreach ((array) $selectedImages as $img) {
                    $land->images()->create([
                        'image_path' => $img
                    ]);
                }
            });
        }
    }
}
