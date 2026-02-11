<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up()
    {
        // Ensure PostGIS exists
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        // Add new geometry column
        Schema::table('lands', function (Blueprint $table) {
            $table->point('coordinates_new', 4326)->nullable();
        });

        // Convert existing data (only if not null)
        DB::statement("
            UPDATE lands
            SET coordinates_new = ST_SetSRID(
                ST_MakePoint(
                    coordinates[1],
                    coordinates[2]
                ),
                4326
            )
            WHERE coordinates IS NOT NULL
        ");

        // Drop old column
        Schema::table('lands', function (Blueprint $table) {
            $table->dropColumn('coordinates');
        });

        // Rename new column
        Schema::table('lands', function (Blueprint $table) {
            $table->renameColumn('coordinates_new', 'coordinates');
        });
    }

    public function down()
    {
        Schema::table('lands', function (Blueprint $table) {
            $table->point('coordinates_old')->nullable();
        });

        DB::statement("
            UPDATE lands
            SET coordinates_old = POINT(
                ST_X(coordinates),
                ST_Y(coordinates)
            )
            WHERE coordinates IS NOT NULL
        ");

        Schema::table('lands', function (Blueprint $table) {
            $table->dropColumn('coordinates');
            $table->renameColumn('coordinates_old', 'coordinates');
        });
    }
};
