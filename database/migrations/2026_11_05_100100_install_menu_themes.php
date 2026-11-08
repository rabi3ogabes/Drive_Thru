<?php

use App\Domain\Themes\ThemeCatalog;
use Illuminate\Database\Migrations\Migration;

/*
 * The built-in theme templates. Restaurants keep their current look: until an
 * owner saves a theme, it is read from their brand colour and menu layout.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ThemeCatalog::class)->install();
    }

    public function down(): void
    {
        // Templates stay; the table is dropped by the previous migration's down().
    }
};
