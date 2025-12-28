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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('source_id')->index()->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('link');
            $table->string('main_image')->nullable();
            $table->json('additional_images')->nullable();
            $table->string('price')->nullable();
            $table->string('sale_price')->nullable();
            $table->string('reference')->nullable();
            $table->string('colour')->nullable();
            $table->string('size')->nullable();
            $table->integer('stock')->nullable();
            $table->json('categories')->nullable(); // category + subcategories
            $table->json('raw')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
