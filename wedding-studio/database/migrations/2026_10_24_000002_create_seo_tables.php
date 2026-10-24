<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('seo_redirects',function(Blueprint $table){$table->id();$table->string('source',512)->unique();$table->string('target',2048);$table->unsignedSmallInteger('status')->default(301);$table->foreignId('content_id')->nullable()->constrained('contents')->cascadeOnDelete();$table->timestamps();});
  Schema::create('seo_not_found',function(Blueprint $table){$table->id();$table->string('path',512)->unique();$table->unsignedInteger('hits')->default(1);$table->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('seo_not_found');Schema::dropIfExists('seo_redirects');}
};
