<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de traducciones bíblicas (RVA1909, WEB, KJV, NVI, NIV, RVR60...).
 * `can_display_full_text` controla si la app envía el texto o solo la referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // RVA1909, WEB, KJV, NVI, NIV, RVR60
            $table->string('language', 5);                 // es, en
            $table->string('name');                        // "Reina-Valera Antigua 1909"
            $table->boolean('is_public_domain')->default(false);
            $table->string('license_status')->default('none'); // none|pending|licensed
            $table->boolean('can_display_full_text')->default(false);
            $table->text('attribution')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
