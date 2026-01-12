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
     * - Bikin users untuk semua dinas (Bulk Mode)
     * - Bikin submissions + documents untuk N dinas (configurable)
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Testing Data Seeder (Mode Anti-Stuck)...');
        
        // DB::beginTransaction(); // Matikan transaction global
        try {
            // 1. Create admin & pusdatin users
            $this->command->info('👤 Creating admin & pusdatin users...');
            $this->seedAdminUsers();
            
            // 2. Create users untuk semua dinas (Bulk Insert Mode)
            $this->command->info('👥 Creating users for all dinas...');
            $this->seedDinasUsers();
            
            // 3. Seed Submissions (CUKUP 3 BIJI AJA BUAT TES)
            $this->command->info('📄 Creating submissions & documents...');
            
            // Bikin range otomatis
            $grupA = range(1, 5);   // [1, 2, 3, 4, 5]
            $grupB = range(50, 55); // [50, 51, 52, 53, 54, 55]
            
            // Gabung jadi satu array
            $targetIds = array_merge($grupA, $grupB); 
            
            // Total ada 11 Dinas yang bakal dibuatkan data. Aman, gak bakal berat.
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
     * Create users untuk semua dinas existing (Optimized Bulk Insert)
     */
    private function seedDinasUsers(): void
    {
        // 1. Ambil semua data Dinas (Cukup ID dan Region ID)
        $allDinas = Dinas::with('region')->select('id', 'region_id', 'nama_dinas')->get();
        
        $this->command->info('⚡ Preparing data for ' . $allDinas->count() . ' users...');

        // 2. HASH PASSWORD CUMA SEKALI (Ini hemat CPU banget!)
        $passwordHash = Hash::make('password');
        $now = now(); // Biar created_at seragam

        $userData = [];
        
        // 3. Susun Data di Memory Dulu
        foreach ($allDinas as $index => $dinas) {
            $kodeDinas = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            
            $userData[] = [
                'email' => "dlh{$kodeDinas}@test.com",
                'password' => $passwordHash,
                'role' => $dinas->region->type ?? 'kabupaten/kota',
                'dinas_id' => $dinas->id,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 4. KIRIM GROSIRAN (BULK INSERT)
        // Kita pecah per 500 data biar paketnya gak kegedean
        $chunks = array_chunk($userData, 500);
        
        $this->command->info('🚀 Inserting users into TiDB (Bulk Mode)...');
        
        foreach ($chunks as $chunk) {
            User::upsert($chunk, ['email'], ['password', 'role', 'dinas_id', 'is_active', 'updated_at']);
            $this->command->info('✅ Inserted batch of ' . count($chunk) . ' users.');
        }
        
        $this->command->newLine();
    }
    
    /**
     * Seed Submissions & Documents untuk N dinas tertentu
     * @param array $targetIds Array ID dinas yang akan dibuatkan submission
     * @param int $year Tahun submission
     * @return int Jumlah dinas yang berhasil dibuatkan submission
     */
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
            if (preg_match('/^0*(\d+)[\.\s]/', $filename, $matches)) {
                $nomor = (int) $matches[1];
                if ($nomor >= 1 && $nomor <= 80) {
                    $tabelTemplates[$nomor] = $file;
                }
            }
        }
        
        $this->command->info("📊 Found " . count($tabelTemplates) . " tabel templates");
        
        // Ambil N dinas pertama (ordered by id)
        $dinasIds = Dinas::whereIn('id', $targetIds)->orderBy('id')->pluck('id');
        
        $progressBar = $this->command->getOutput()->createProgressBar($dinasIds->count());
        $progressBar->start();
        
        foreach ($dinasIds as $dinasId) {
            // 🔥 PERBAIKAN 1: Pake firstOrCreate biar gak error duplikat submission
            $submission = Submission::firstOrCreate(
                ['id_dinas' => $dinasId, 'tahun' => $year], // Cek kombinasi ini
                ['status' => 'finalized'] // Kalau gak ada, baru bikin dengan status ini
            );
            
            $basePath = "uploads/{$year}/dlh_{$dinasId}";
            
            // 1. Ringkasan Eksekutif
            $ringkasanPath = "{$basePath}/ringkasan_eksekutif/ringkasan_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($ringkasanPath, $templateDisk->get($sourcePdf));
            
            // 🔥 PERBAIKAN 2: Pake updateOrCreate untuk file anak-anaknya juga
            RingkasanEksekutif::updateOrCreate(
                ['submission_id' => $submission->id],
                ['path' => $ringkasanPath, 'status' => 'finalized']
            );
            
            // 2. Laporan Utama
            $laporanPath = "{$basePath}/laporan_utama/laporan_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($laporanPath, $templateDisk->get($sourcePdf2));
            
            LaporanUtama::updateOrCreate(
                ['submission_id' => $submission->id],
                ['path' => $laporanPath, 'status' => 'finalized']
            );

            // Lampiran
            $lampiranPath = "{$basePath}/lampiran/lampiran_{$dinasId}_{$year}.pdf";
            $dlhDisk->put($lampiranPath, $templateDisk->get($sourcePdf3));
            
            Lampiran::updateOrCreate(
                ['submission_id' => $submission->id],
                ['path' => $lampiranPath, 'status' => 'finalized']
            );
            
            // 3. IKLH
            Iklh::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'status' => 'finalized',
                    'indeks_kualitas_air' => rand(70, 100),
                    'indeks_kualitas_udara' => rand(70, 100),
                    'indeks_kualitas_lahan' => rand(70, 100),
                    'indeks_kualitas_pesisir_laut' => rand(70, 100),
                    'indeks_kualitas_kehati' => rand(70, 100),
                ]
            );
            
            // 4. Tabel Utama
            $allKodeTabel = MatraConstants::getAllKodeTabel();
            
            foreach ($allKodeTabel as $kodeTabel) {
                $matra = MatraConstants::getMatraByKode($kodeTabel);
                $nomorTabel = MatraConstants::extractNomorTabel($kodeTabel);
                
                $matraSanitized = str_replace([' ', ',', '.', '(', ')'], '_', $matra);
                
                $tabelPath = "{$basePath}/tabel_utama/{$matraSanitized}/tabel_{$nomorTabel}_{$dinasId}_{$year}.xlsx";
                
                if (isset($tabelTemplates[$nomorTabel])) {
                    $dlhDisk->put($tabelPath, $templateDisk->get($tabelTemplates[$nomorTabel]));
                } else {
                    // Skip warning biar log gak penuh, cukup continue
                    continue; 
                }
                
                TabelUtama::updateOrCreate(
                    ['submission_id' => $submission->id, 'kode_tabel' => $kodeTabel],
                    [
                        'path' => $tabelPath,
                        'matra' => $matra,
                        'status' => 'finalized',
                    ]
                );
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->command->newLine();
        
        return $dinasIds->count();
    }
}