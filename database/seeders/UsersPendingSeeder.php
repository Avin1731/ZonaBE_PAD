<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Dinas;
use App\Models\Region;
use Illuminate\Support\Facades\Hash;

class UsersPendingSeeder extends Seeder
{
    public function run()
    {
        $password = Hash::make('password'); // Password default

        $this->command->info('🚀 Memulai seeding User Pending (Clean)...');

        // ==========================================
        // 1. SEED PENDING DLH PROVINSI (5 Data)
        // ==========================================
        $provinsiRegions = Region::where('type', 'provinsi')->inRandomOrder()->limit(5)->get();
        
        foreach ($provinsiRegions as $region) {
            // 1. Buat Dinas (Hanya kolom yang ada di database)
            $dinas = Dinas::create([
                'nama_dinas' => 'DLH Prov. ' . $region->nama_region . ' (Pending)',
                'region_id'  => $region->id,
                'status'     => 'belum_terdaftar', 
                'kode_dinas' => 'PENDING-PROV-' . rand(1000, 9999),
            ]);

            // 2. Buat User (Hanya kolom yang ada di database)
            User::create([
                'email'     => 'pending.prov.' . rand(100, 999) . '@test.com',
                'password'  => $password,
                'role'      => 'provinsi',
                'dinas_id'  => $dinas->id,
                'is_active' => false, // Pending
            ]);
        }
        
        // ==========================================
        // 2. SEED PENDING DLH KAB/KOTA (10 Data)
        // ==========================================
        $kabKotaRegions = Region::whereIn('type', ['kabupaten', 'kota'])->inRandomOrder()->limit(10)->get();

        foreach ($kabKotaRegions as $region) {
            $dinas = Dinas::create([
                'nama_dinas' => 'DLH ' . $region->nama_region . ' (Pending)',
                'region_id'  => $region->id,
                'status'     => 'belum_terdaftar',
                'kode_dinas' => 'PENDING-KAB-' . rand(1000, 9999)
            ]);

            User::create([
                'email'     => 'pending.kab.' . rand(100, 999) . '@test.com',
                'password'  => $password,
                'role'      => 'kabupaten/kota',
                'dinas_id'  => $dinas->id,
                'is_active' => false, // Pending
            ]);
        }

        $this->command->info('✅ Berhasil membuat 15 User Pending.');
    }
}