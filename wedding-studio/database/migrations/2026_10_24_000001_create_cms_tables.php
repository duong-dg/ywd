<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('contents',function(Blueprint $table){$table->id();$table->string('type',30)->index();$table->json('data');$table->timestamps();});
  Schema::create('site_settings',function(Blueprint $table){$table->string('key')->primary();$table->json('value');});
 }
 public function down(): void {Schema::dropIfExists('contents');Schema::dropIfExists('site_settings');}
};
