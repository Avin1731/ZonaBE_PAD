<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade'); // Siapa adminnya
            $table->unsignedBigInteger('target_user_id')->nullable(); // Siapa user yang diotak-atik
            $table->string('action'); // Contoh: approve, reject, delete, create_pusdatin
            $table->text('details')->nullable(); // Simpan email/info penting jaga-jaga kalau user dihapus
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};