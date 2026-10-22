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
        Schema::create('messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('chat_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role');

            $table->text('content')
                ->nullable();

            $table->string('media_path')
                ->nullable();

            $table->string('media_type')
                ->nullable();

            $table->string('workflow')
                ->nullable();

            $table->string('comfy_prompt_id')
                ->nullable()
                ->index();

            $table->string('status')
                ->default('completed');

            $table->integer('queue_position')
                ->nullable();

            $table->string('detected_language')
                ->nullable();

            $table->text('positive_prompt')
                ->nullable();

            $table->text('negative_prompt')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
