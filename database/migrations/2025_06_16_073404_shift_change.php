<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift_changes', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['grh', 'employe']);
            $table->foreignId('user_id');
            $table->foreignId('shift_id')->constrained()->onDelete('restrict');
            $table->date('date_debut');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_changes');
    }
};
