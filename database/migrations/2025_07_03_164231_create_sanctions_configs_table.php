<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions_configs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['retards', 'absences']);
            $table->unsignedInteger('threshold')->default(1);
            $table->decimal('hours_deducted', 8, 2); 
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['type', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions_configs');
    }
};
