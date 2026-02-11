<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use App\Models\Land;
use App\Models\LandPriceHistory;

class LandSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent reseeding in production
        if (Land::count() > 0) {
            return;
        }

        $seedPath = database_path('seeders/images/lands');

        $images = collect(
            glob($seedPath . '/*.{jpg,jpeg,png}', GLOB_BRACE)
        );

        for ($i = 1; $i <= 10; $i++) {

            DB::transaction(function () use ($images, $i) {

                // Lagos-like coordinates
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

                // Create small polygon square
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

                // Create 3 price history records (more realistic)
                for ($m = 3; $m >= 0; $m--) {
                    LandPriceHistory::create([
                        'land_id' => $land->id,
                        'price_per_unit_kobo' => fake()->numberBetween(200000, 800000),
                        'price_date' => now()->subMonths($m)->toDateString(),
                    ]);
                }

                // Attach 1–3 images
                if ($images->count() > 0) {

                    $selectedImages = $images->random(
                        min(3, $images->count())
                    );

                    foreach ((array) $selectedImages as $imgPath) {

                        $storedPath = Storage::disk('public')->putFile(
                            'land_images',
                            new File($imgPath)
                        );

                        $land->images()->create([
                            'image_path' => $storedPath
                        ]);
                    }
                }
            });
        }
    }
}
