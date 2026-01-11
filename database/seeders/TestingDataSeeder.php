<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Region;
use App\Models\Dinas;
use App\Models\User;
use App\Models\Submission;
use App\Models\Files\RingkasanEksekutif;
use App\Models\Files\LaporanUtama;
use App\Models\Files\TabelUtama;
use App\Models\Files\Iklh;
use App\Helpers\MatraConstants;
use App\Models\Files\Lampiran;

class TestingDataSeeder extends Seeder
{
    /**
     * Seed data untuk testing: menggunakan dinas existing (576 dinas)
     * - Bikin users untuk semua dinas
     * - Bikin submissions + documents untuk N dinas (configurable)
     */
    // public function run(): void
    // {
    //     $this->command->info('🚀 Starting Testing Data Seeder...');
        
    //     // DB::beginTransaction();
    //     try {
    //         // 1. Create admin & pusdatin users
    //         $this->command->info('👤 Creating admin & pusdatin users...');
    //         $this->seedAdminUsers();
            
    //         // 2. Create users untuk semua dinas existing
    //         $this->command->info('👥 Creating users for all dinas...');
    //         $this->seedDinasUsers();
            
    //         // 3. Seed Submissions & Documents Custom Range
    //         $this->command->info('📄 Creating submissions & documents...');
            
    //         // Bikin daftar ID: 1 sampai 10 DAN 50 sampai 60
    //         $ids_grup_1 = range(1, 10);   // [1, 2, ..., 10]
    //         $ids_grup_2 = range(50, 60);  // [50, 51, ..., 60]
            
    //         // Gabung jadi satu array
    //         $targetIds = array_merge($ids_grup_1, $ids_grup_2); 
            
    //         // Panggil function dengan array ID tadi
    //         $dinasCount = $this->seedSubmissionsAndDocuments($targetIds, 2026);
            
    //         // DB::commit();
            
    //         $this->command->info('✅ Testing data seeded successfully!');
    //         $this->command->info("📊 Total users: " . User::count());
    //         $this->command->info("📊 Submissions created for {$dinasCount} dinas");
            
    //     } catch (\Exception $e) {
    //         // DB::rollBack();
    //         $this->command->error('❌ Seeding failed: ' . $e->getMessage());
    //         throw $e;
    //     }
    // }
    
    public function run(): void
    {
        $this->command->info('🚀 Starting Testing Data Seeder (Mode Anti-Stuck)...');
        
        // DB::beginTransaction(); // Matikan transaction global
        try {
            // 1. Create admin & pusdatin users
            $this->command->info('👤 Creating admin & pusdatin users...');
            $this->seedAdminUsers();
            
            // 2. Create users untuk semua dinas (Pake Reconnect di dalamnya biar aman)
            $this->command->info('👥 Creating users for all dinas...');
            $this->seedDinasUsers();

            // --- TAMBAHAN PENTING: DEADLINE (BIAR GAK 403) ---
            $this->command->info('⏰ Creating deadlines...');
            \App\Models\Pusdatin\Deadline::create([
               'tahun' => 2026,
               'start_date' => now()->subDays(1),
               'end_date' => now()->addMonths(6),
               'kategori' => 'submission',
               'is_active' => true
            ]);
            // --------------------------------------------------
            
            // 3. Seed Submissions (CUKUP 3 BIJI AJA BUAT TES)
            $this->command->info('📄 Creating submissions & documents...');
            
            // Kita ambil ID 1, 2, dan 50 (Perwakilan grup)
            // Gak usah range(1,10), kelamaan bang!
            $targetIds = [1, 2, 50]; 
            
            // Panggil function
            $dinasCount = $this->seedSubmissionsAndDocuments($targetIds, 2026);
            
            // DB::commit();
            
            $this->command->info('✅ Testing data seeded successfully!');
            $this->command->info("📊 Submissions created for: " . implode(', ', $targetIds));
            
        } catch (\Exception $e) {
            // DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create admin & pusdatin users
     */
    private function seedAdminUsers(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        User::firstOrCreate(
            ['email' => 'admin2@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'pusdatin@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'pusdatin',
                'is_active' => true,
            ]
        );
        User::firstOrCreate(
            ['email' => 'pusdatin2@test.com'],
            [
                'password' => Hash::make('password'),
                'role' => 'pusdatin',
                'is_active' => true,
            ]
        );
    }
    
    /**
     * Create users untuk semua dinas existing
     */
    private function seedDinasUsers(): void
    {
        $allDinas = Dinas::with('region')->get();
        
        $progressBar = $this->command->getOutput()->createProgressBar($allDinas->count());
        $progressBar->start();
        
        foreach ($allDinas as $index => $dinas) {
            $kodeDinas = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            
            User::firstOrCreate(
                ['email' => "dlh{$kodeDinas}@test.com"],
                [
                    'password' => Hash::make('password'),
                    'role' => $dinas->region->type, // 'provinsi' or 'kabupaten/kota'
                    'dinas_id' => $dinas->id,
                    'is_active' => true,
                ]
            );
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->command->newLine();
    }
    
    /**
     * Seed Submissions & Documents untuk N dinas pertama
     * 
     * @param int $count Jumlah dinas yang akan dibuatkan submission
     * @param int $year Tahun submission
     * @return int Jumlah dinas yang berhasil dibuatkan submission
     */
    // Ganti 'int $count' jadi 'array $targetIds'
    private function seedSubmissionsAndDocuments(array $targetIds, int $year): int
    {
        $templateDisk = Storage::disk('templates');
        $dlhDisk = Storage::disk('dlh');
        
        // Source files path (relative dari disk 'templates')
        $sourcePdf = 'slhd/buku1/erd.pdf';
        $sourcePdf1 = 'slhd/buku1/erd.pdf';
        $sourcePdf2 = 'slhd/buku2/erd.pdf';
        $sourcePdf3 = 'slhd/buku3/erd.pdf';

        
        // Validasi source PDF exist
        if (!$templateDisk->exists($sourcePdf)) {
            throw new \Exception("Source PDF not found: {$sourcePdf}");
        }
        
        // Scan semua file Excel di folder tabel_utama
        $tabelTemplates = [];
        $templateFiles = $templateDisk->files('tabel_utama');
        
        foreach ($templateFiles as $file) {
            $filename = basename($file);
            // Extract nomor dari nama file (format: "28. Nama.xlsx" atau "01 Nama.xlsx")
            if (preg_match('/^0*(\d+)[\.\s]/', $filename, $matches)) {
                $nomor = (int) $matches[1];
                if ($nomor >= 1 && $nomor <= 80) {
                    $tabelTemplates[$nomor] = $file;
                }
            }
        }
        
        $this->command->info("📊 Found " . count($tabelTemplates) . " tabel templates");
        
        if (count($tabelTemplates) < 80) {
            $this->command->warn("⚠️  Warning: Only " . count($tabelTemplates) . " templates found, expected 80");
        }
        
        // Ambil N dinas pertama (ordered by id)
        $dinasIds = Dinas::whereIn('id', $targetIds)->orderBy('id')->pluck('id');
        
        $progressBar = $this->command->getOutput()->createProgressBar($dinasIds->count());
        $progressBar->start();
        
        foreach ($dinasIds as $dinasId) {
            // Create Submission
            $submission = Submission::create([
                'id_dinas' => $dinasId,
                'tahun' => $year,
                'status' => 'finalized', // Auto-finalized untuk testing
            ]);
            
            $basePath = "uploads/{$year}/dlh_{$dinasId}";
            
            // 1. Ringkasan Eksekutif (copy PDF dari templates ke dlh)
            $ringkasanPath = "{$basePath}/ringkasan_eksekutif/ringkasan_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($ringkasanPath, $templateDisk->get($sourcePdf));
            
            RingkasanEksekutif::create([
                'submission_id' => $submission->id,
                'path' => $ringkasanPath,
                'status' => 'finalized',
            ]);
            
            // 2. Laporan Utama (copy PDF dari templates ke dlh)
            $laporanPath = "{$basePath}/laporan_utama/laporan_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($laporanPath, $templateDisk->get($sourcePdf2));
            
            LaporanUtama::create([
                'submission_id' => $submission->id,
                'path' => $laporanPath,
                'status' => 'finalized',
            ]);

            $lampiranPath = "{$basePath}/lampiran/lampiran_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($lampiranPath, $templateDisk->get($sourcePdf3));
            Lampiran::create([
                'submission_id' => $submission->id,
                'path' => "{$basePath}/lampiran/lampiran_{$dinasId}_{$year}.pdf",
                'status' => 'finalized',
            ]);
            
            // 3. IKLH (TIDAK PERLU FILE, hanya indeks)
            Iklh::create([
                'submission_id' => $submission->id,
                'status' => 'finalized',
                'indeks_kualitas_air' => rand(70, 100),
                'indeks_kualitas_udara' => rand(70, 100),
                'indeks_kualitas_lahan' => rand(70, 100),
                'indeks_kualitas_pesisir_laut' => rand(70, 100),
                'indeks_kualitas_kehati' => rand(70, 100),
            ]);
            
            // 4. Tabel Utama (80 files dari MatraConstants)
            $allKodeTabel = MatraConstants::getAllKodeTabel();
            
            foreach ($allKodeTabel as $kodeTabel) {
                $matra = MatraConstants::getMatraByKode($kodeTabel);
                $nomorTabel = MatraConstants::extractNomorTabel($kodeTabel);
                
                // Sanitize for file/folder names
                $matraSanitized = str_replace([' ', ',', '.', '(', ')'], '_', $matra);
                $kodeTabelSanitized = str_replace([' ', '||'], ['_', '-'], $kodeTabel);
                
                $tabelPath = "{$basePath}/tabel_utama/{$matraSanitized}/tabel_{$nomorTabel}_{$dinasId}_{$year}.xlsx";
                
                // Cek apakah ada template untuk nomor ini
                if (isset($tabelTemplates[$nomorTabel])) {
                    // Copy dari template yang sesuai
                    $dlhDisk->put($tabelPath, $templateDisk->get($tabelTemplates[$nomorTabel]));
                } else {
                    // Skip jika template tidak ada
                    $this->command->warn("⚠️  Template for Tabel {$nomorTabel} not found, skipping...");
                    continue;
                }
                
                TabelUtama::create([
                    'submission_id' => $submission->id,
                    'kode_tabel' => $kodeTabel,
                    'path' => $tabelPath,
                    'matra' => $matra,
                    'status' => 'finalized',
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->command->newLine();
        
        return $dinasIds->count();
    }
}
