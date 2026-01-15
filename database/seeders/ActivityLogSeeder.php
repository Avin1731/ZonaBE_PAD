<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class ActivityLogSeeder extends Seeder
{
    public function run()
    {
        // 1. Bersihkan Data Lama
        ActivityLog::truncate();

        // 2. Setup User Dummy
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true
            ]
        );

        $pusdatin = User::firstOrCreate(
            ['email' => 'pusdatin@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'pusdatin',
                'is_active' => true
            ]
        );
        
        $dinasUser = User::firstOrCreate(
            ['email' => 'dlh.bogor@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'kabupaten/kota',
                'is_active' => true
            ]
        );

        $logs = [];

        // ==========================================
        // HELPER BUILDER BIAR KONSISTEN
        // ==========================================
        $buildLog = function($userId, $action, $desc, $context, $subjectType = null, $subjectId = null, $stage = null, $docType = null) {
            $date = Carbon::now()->subDays(rand(1, 30))->subHours(rand(1, 12));
            return [
                'user_id' => $userId,
                'action' => $action,
                'description' => $desc,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'context_type' => $context,
                'year' => 2026,
                'stage' => $stage, // Konsisten: null jika tidak ada
                'document_type' => $docType, // Konsisten: null jika tidak ada
                'properties' => json_encode(['ip' => '127.0.0.1', 'browser' => 'Chrome']),
                'ip_address' => '192.168.1.' . rand(1, 255),
                'created_at' => $date,
                'updated_at' => $date,
            ];
        };

        // ==========================================
        // SKENARIO 1: ADMIN LOGS (20 Data)
        // ==========================================
        $adminActions = [
            ['approve_user', 'Menyetujui akun Dinas Lingkungan Hidup Kab. Bogor'],
            ['reject_user', 'Menolak pendaftaran user spam@gmail.com'],
            ['create_pusdatin', 'Membuat akun Pusdatin baru: staff.pusdatin@test.com'],
            ['delete_user', 'Menghapus user lama yang tidak aktif']
        ];

        for ($i = 0; $i < 20; $i++) {
            $act = $adminActions[array_rand($adminActions)];
            $logs[] = $buildLog(
                $admin->id, 
                $act[0], 
                $act[1], 
                'admin', 
                'App\Models\User', 
                $dinasUser->id,
                null, 
                null
            );
        }

        // ==========================================
        // SKENARIO 2: PUSDATIN LOGS (20 Data)
        // ==========================================
        $pusdatinActions = [
            ['review_document', 'Review Dokumen Laporan Utama'],
            ['finalize_penilaian', 'Finalisasi Penilaian Tahap 1'],
            ['upload_penilaian', 'Upload Nilai Validasi 2'],
            ['unfinalized', 'Membuka kembali akses upload untuk Kab. Bandung']
        ];

        for ($i = 0; $i < 20; $i++) {
            $act = $pusdatinActions[array_rand($pusdatinActions)];
            $logs[] = $buildLog(
                $pusdatin->id,
                $act[0],
                $act[1],
                'pusdatin',
                'App\Models\Dinas',
                1,
                'review', 
                'laporan_utama' 
            );
        }

        // SKENARIO 3: AUTH LOGS (DIHAPUS SESUAI REQUEST)

        // Insert Batch
        ActivityLog::insert($logs);
    }
}