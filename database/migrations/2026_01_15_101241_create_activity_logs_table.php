<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // AKTOR: Siapa yang melakukan aksi?
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null'); // Log tetap ada walau user dihapus

            // ACTION: Jenis aksi
            $table->string('action'); // Contoh: 'approve_user', 'create_pusdatin', 'review_document'

            // DESCRIPTION: Keterangan detail human-readable
            $table->text('description'); // Contoh: "Menyetujui akun DLH Jawa Barat"

            // SUBJECT (Polymorphic): Objek apa yang kena dampak?
            $table->nullableMorphs('subject'); // subject_type, subject_id

            // KONTEKS TAMBAHAN untuk filtering
            $table->string('context_type')->nullable(); // 'admin', 'pusdatin', 'dinas', 'system'
            $table->integer('year')->nullable(); // Untuk filter tahun
            $table->string('stage')->nullable(); // 'review', 'penilaian_slhd', 'validasi_1', dll
            $table->string('document_type')->nullable(); // 'ringkasan_eksekutif', 'laporan_utama', dll

            // PROPERTIES: Data tambahan dalam format JSON
            $table->json('properties')->nullable();

            // DATA TEKNIS: Untuk audit keamanan
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Index untuk performa query
            $table->index(['user_id', 'created_at']);
            $table->index(['context_type', 'created_at']);
            $table->index(['year', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};