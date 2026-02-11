<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Land;
use App\Models\LandPriceHistory;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        $images = collect(Storage::files('public/seed/lands'))
            ->map(fn($path) => str_replace('public/', '', $path))
            ->values();

        for ($i = 1; $i <= 10; $i++) {

            DB::transaction(function () use ($images, $i) {

                // Generate Lagos-like random coordinates
                $lat = fake()->randomFloat(6, 6.40, 6.65);
                $lng = fake()->randomFloat(6, 3.20, 3.65);

                $land = Land::create([
                    'title' => "Premium Estate Plot $i",
                    'location' => fake()->randomElement([
                        'Ibeju-Lekki',
                        'Epe',
                        'Ajah',
                        'Sangotedo',
                        'Abijo'
                    ]) . ', Lagos',
                    'size' => fake()->randomElement([300, 450, 600]),
                    'total_units' => 50,
                    'available_units' => fake()->numberBetween(10, 50),
                    'description' => fake()->sentence(12),
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
                    'price_per_unit_kobo' => fake()->numberBetween(300000000, 800000000),
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
