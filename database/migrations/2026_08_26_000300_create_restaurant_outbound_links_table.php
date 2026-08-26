<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('restaurant_outbound_links', function (Blueprint $table): void { $table->id(); $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete(); $table->string('token', 64)->unique(); $table->string('label', 80); $table->text('destination_url'); $table->boolean('is_active')->default(true); $table->unsignedBigInteger('click_count')->default(0); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('restaurant_outbound_links'); } };
