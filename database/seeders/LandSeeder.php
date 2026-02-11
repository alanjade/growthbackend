<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Land;
use App\Models\LandPriceHistory;

class LandSeeder extends Seeder
{
    private array $locations = [
        'Ibeju-Lekki', 'Epe', 'Ajah', 'Sangotedo', 
        'Abijo', 'Lakowe', 'Bogije'
    ];

    private array $sizes = [300, 450, 600];
    private array $totalUnits = [300, 450, 600, 750, 1000];

    private array $descriptions = [
        'Prime investment opportunity in rapidly developing area with excellent ROI potential.',
        'Strategic location with proximity to major infrastructure developments and amenities.',
        'Exclusive estate development with world-class facilities and security features.',
        'High-value land in emerging commercial corridor with strong appreciation prospects.',
        'Premium residential plots in gated community with modern infrastructure.',
        'Waterfront property with scenic views and exceptional investment value.',
        'Commercial land in high-traffic area ideal for mixed-use development.',
        'Residential estate plots with government-approved layouts and C of O.',
        'Investment-grade land with verified title documents and immediate allocation.',
        'Luxury estate development in prime location with guaranteed returns.',
    ];

    public function run(): void
    {
        // Get all seed images
        $images = collect(Storage::files('public/seed/lands'))
            ->map(fn($path) => str_replace('public/', '', $path))
            ->values();

        if ($images->isEmpty()) {
            $this->command->warn('No seed images found. Seeding without images.');
        } else {
            $this->command->info("Found {$images->count()} seed images");
        }

        for ($i = 1; $i <= 10; $i++) {
            DB::transaction(function () use ($images, $i) {
                
                // Generate Lagos-like random coordinates
                $lat = $this->randomFloat(6, 6.40, 6.65);
                $lng = $this->randomFloat(6, 3.20, 3.65);

                // Pick random values from arrays
                $totalUnits = $this->totalUnits[array_rand($this->totalUnits)];
                $size = $this->sizes[array_rand($this->sizes)];
                $location = $this->locations[array_rand($this->locations)];
                $description = $this->descriptions[$i - 1];

                $land = Land::create([
                    'title' => "Premium Estate Plot $i",
                    'location' => "$location, Lagos",
                    'size' => $size,
                    'total_units' => $totalUnits,
                    'available_units' => $totalUnits,
                    'description' => $description,
                    'lat' => $lat,
                    'lng' => $lng,
                    'is_available' => true,
                ]);

                // Create Polygon
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

                $pricePerUnit = $this->randomInt(300_000, 800_000);
                
                LandPriceHistory::create([
                    'land_id' => $land->id,
                    'price_per_unit_kobo' => $pricePerUnit,
                    'price_date' => now()->toDateString(),
                ]);

                // Attach images
                if ($images->isNotEmpty()) {
                    $imageCount = min(3, $images->count());
                    $selectedImages = $images->random($imageCount);

                    foreach ($selectedImages as $img) {
                        $land->images()->create([
                            'image_path' => $img
                        ]);
                    }
                }
            });
        }

        $this->command->info('Successfully seeded 10 land records');
    }

    // Helper methods to replace Faker
    private function randomFloat(int $decimals, float $min, float $max): float
    {
        $scale = pow(10, $decimals);
        return mt_rand($min * $scale, $max * $scale) / $scale;
    }

    private function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }
}