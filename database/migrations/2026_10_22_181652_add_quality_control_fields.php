<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('quality_requirements')->nullable();
            $table->json('visual_facts')->nullable();
            $table->unsignedSmallInteger('observed_subject_count')->nullable();
            $table->json('quality_hard_failures')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'quality_requirements',
                'visual_facts',
                'observed_subject_count',
                'quality_hard_failures',
            ]);
        });
    }
};
