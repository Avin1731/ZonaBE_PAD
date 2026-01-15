<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Submission;
use App\Models\TahapanPenilaianStatus;
use App\Models\Pusdatin\RekapPenilaian;
use App\Models\Deadline;
use App\Models\Dinas; // Ensure Dinas model is imported
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $year = $request->input('year', now()->year);
        $cacheKey = "admin_dashboard_stats_{$year}";

        // Cache result for 10 minutes to reduce DB load
        return Cache::remember($cacheKey, 600, function () use ($year) {
            
            // 1. Optimized User Stats (Single Query)
            $userStats = User::selectRaw("
                count(*) as total,
                sum(case when is_active = 0 then 1 else 0 end) as pending,
                sum(case when is_active = 1 then 1 else 0 end) as active,
                sum(case when role = 'admin' then 1 else 0 end) as admin_count,
                sum(case when role = 'pusdatin' then 1 else 0 end) as pusdatin_count,
                sum(case when role = 'dinas' then 1 else 0 end) as dinas_count
            ")->first();

            // 2. Dinas by Region Type (Optimized Join)
            $dinasStats = DB::table('users')
                ->join('dinas', 'users.dinas_id', '=', 'dinas.id')
                ->join('regions', 'dinas.region_id', '=', 'regions.id')
                ->where('users.role', 'dinas')
                ->selectRaw("
                    sum(case when regions.type = 'provinsi' then 1 else 0 end) as provinsi,
                    sum(case when regions.type = 'kabupaten' OR regions.type = 'kota' then 1 else 0 end) as kabupaten_kota
                ")->first();

            // 3. Submission Stats (Single Query)
            $submissionStats = Submission::where('tahun', $year)
                ->selectRaw("
                    count(*) as total,
                    sum(case when status = 'draft' then 1 else 0 end) as draft,
                    sum(case when status = 'finalized' then 1 else 0 end) as finalized,
                    sum(case when status = 'approved' then 1 else 0 end) as approved
                ")->first();

            // 4. Storage Usage (Cached separately due to IO cost)
            $storageUsed = $this->getCachedStorageSize();

            // 5. Timeline Logic
            $timelinePenilaian = $this->getTimelinePenilaian($year);

            return response()->json([
                'total_users_aktif' => $userStats->active,
                'total_users_pending' => $userStats->pending,
                'year' => $year,
                'users' => [
                    'total' => $userStats->total,
                    'pending_approval' => $userStats->pending,
                    'active' => $userStats->active,
                    'by_role' => [
                        'admin' => $userStats->admin_count,
                        'pusdatin' => $userStats->pusdatin_count,
                        'dinas' => $userStats->dinas_count,
                    ],
                    'dinas_by_type' => [
                        'provinsi' => $dinasStats->provinsi ?? 0,
                        'kabupaten_kota' => $dinasStats->kabupaten_kota ?? 0,
                    ],
                ],
                'submissions' => [
                    'total' => $submissionStats->total,
                    'by_status' => [
                        'draft' => $submissionStats->draft,
                        'finalized' => $submissionStats->finalized,
                        'approved' => $submissionStats->approved,
                    ],
                ],
                'storage' => [
                    'used_mb' => round($storageUsed, 2),
                    'used_gb' => round($storageUsed / 1024, 2),
                ],
                'timeline_penilaian' => $timelinePenilaian,
            ]);
        });
    }

    private function getTimelinePenilaian($year)
    {
        $tahapan = TahapanPenilaianStatus::where('year', $year)->first();
        $deadlineSubmission = Deadline::where('year', $year)
            ->where('stage', 'submission')
            ->where('is_active', true)
            ->first();

        // Optimized Aggregate Query for RekapPenilaian
        $rekapStats = RekapPenilaian::where('year', $year)
            ->selectRaw("
                count(*) as total,
                sum(case when lolos_slhd = 1 then 1 else 0 end) as lolos_slhd,
                sum(case when masuk_penghargaan = 1 then 1 else 0 end) as masuk_penghargaan,
                sum(case when lolos_validasi1 = 1 then 1 else 0 end) as lolos_validasi1,
                sum(case when lolos_validasi2 = 1 then 1 else 0 end) as lolos_validasi2
            ")->first();

        $totalDinas = Dinas::count(); 
        // Or if strictly based on rekap entries: $rekapStats->total ?? 0;
        
        $submissionCounts = Submission::where('tahun', $year)
             ->selectRaw("count(*) as total, sum(case when status = 'finalized' then 1 else 0 end) as finalized")
             ->first();

        $tahapanOrder = [
            'submission' => 1, 'penilaian_slhd' => 2, 'penilaian_penghargaan' => 3,
            'validasi_1' => 4, 'validasi_2' => 5, 'wawancara' => 6, 'selesai' => 7,
        ];

        $currentTahap = $tahapan?->tahap_aktif ?? 'submission';
        $currentOrder = $tahapanOrder[$currentTahap] ?? 1;

        $getStatus = fn($order) => $currentOrder > $order ? 'completed' : ($currentOrder == $order ? 'active' : 'pending');

        $timeline = [
            [
                'tahap' => 'submission',
                'label' => 'Submission DLH',
                'order' => 1,
                'status' => $getStatus(1),
                'deadline' => $deadlineSubmission ? [
                    'tanggal' => $deadlineSubmission->deadline_at->format('Y-m-d H:i:s'),
                    'tanggal_formatted' => $deadlineSubmission->deadline_at->translatedFormat('d F Y'),
                    'is_passed' => $deadlineSubmission->isPassed(),
                ] : null,
                'statistik' => [
                    'total_submission' => $submissionCounts->total ?? 0,
                    'finalized' => $submissionCounts->finalized ?? 0,
                ],
            ],
            [
                'tahap' => 'penilaian_slhd',
                'label' => 'Penilaian SLHD',
                'order' => 2,
                'status' => $getStatus(2),
                'statistik' => [
                    'total_dinilai' => $rekapStats->total ?? 0,
                    'lolos' => $rekapStats->lolos_slhd ?? 0,
                    'tidak_lolos' => ($rekapStats->total ?? 0) - ($rekapStats->lolos_slhd ?? 0),
                ],
            ],
            [
                'tahap' => 'penilaian_penghargaan',
                'label' => 'Penilaian Penghargaan',
                'order' => 3,
                'status' => $getStatus(3),
                'statistik' => [
                    'total_peserta' => $rekapStats->lolos_slhd ?? 0,
                    'masuk_penghargaan' => $rekapStats->masuk_penghargaan ?? 0,
                ],
            ],
            [
                'tahap' => 'validasi_1',
                'label' => 'Validasi Tahap 1',
                'order' => 4,
                'status' => $getStatus(4),
                'statistik' => [
                    'total_peserta' => $rekapStats->masuk_penghargaan ?? 0,
                    'lolos' => $rekapStats->lolos_validasi1 ?? 0,
                    'tidak_lolos' => ($rekapStats->masuk_penghargaan ?? 0) - ($rekapStats->lolos_validasi1 ?? 0),
                ],
            ],
            [
                'tahap' => 'validasi_2',
                'label' => 'Validasi Tahap 2',
                'order' => 5,
                'status' => $getStatus(5),
                'statistik' => [
                    'total_peserta' => $rekapStats->lolos_validasi1 ?? 0,
                    'lolos' => $rekapStats->lolos_validasi2 ?? 0,
                    'tidak_lolos' => ($rekapStats->lolos_validasi1 ?? 0) - ($rekapStats->lolos_validasi2 ?? 0),
                ],
            ],
            [
                'tahap' => 'wawancara',
                'label' => 'Wawancara',
                'order' => 6,
                'status' => $getStatus(6),
                'statistik' => [
                    'total_peserta' => $rekapStats->lolos_validasi2 ?? 0,
                ],
            ],
            [
                'tahap' => 'selesai',
                'label' => 'Penilaian Selesai',
                'order' => 7,
                'status' => $currentOrder >= 7 ? 'completed' : 'pending',
            ],
        ];

        return [
            'year' => $year,
            'tahap_aktif' => $currentTahap,
            'tahap_label' => $this->getTahapLabel($currentTahap),
            'pengumuman_terbuka' => $tahapan?->pengumuman_terbuka ?? false,
            'keterangan' => $tahapan?->keterangan ?? 'Menunggu proses dimulai',
            'tahap_mulai_at' => $tahapan?->tahap_mulai_at,
            'progress_percentage' => round(($currentOrder / 7) * 100),
            'timeline' => $timeline,
            'summary' => [
                'total_dinas_terdaftar' => $totalDinas,
                'total_submission' => $submissionCounts->total ?? 0,
                'lolos_slhd' => $rekapStats->lolos_slhd ?? 0,
                'masuk_penghargaan' => $rekapStats->masuk_penghargaan ?? 0,
                'lolos_validasi_1' => $rekapStats->lolos_validasi1 ?? 0,
                'lolos_validasi_2' => $rekapStats->lolos_validasi2 ?? 0,
            ],
        ];
    }

    private function getTahapLabel($tahap)
    {
        return match ($tahap) {
            'submission' => 'Submission DLH',
            'penilaian_slhd' => 'Penilaian SLHD',
            'penilaian_penghargaan' => 'Penilaian Penghargaan',
            'validasi_1' => 'Validasi Tahap 1',
            'validasi_2' => 'Validasi Tahap 2',
            'wawancara' => 'Wawancara',
            'selesai' => 'Penilaian Selesai',
            default => $tahap,
        };
    }

    private function getCachedStorageSize()
    {
        // Cache for 1 hour as this is resource intensive
        return Cache::remember('storage_size_dlh', 3600, function () {
            $storagePath = storage_path('app/dlh');
            if (file_exists($storagePath)) {
                return $this->getDirSize($storagePath) / (1024 * 1024); // Convert to MB
            }
            return 0;
        });
    }

    private function getDirSize($dir)
    {
        $size = 0;
        $files = glob(rtrim($dir, '/') . '/*', GLOB_NOSORT);
        foreach ($files as $each) {
            $size += is_file($each) ? filesize($each) : $this->getDirSize($each);
        }
        return $size;
    }

    public function getRecentActivities(Request $request)
    {
        $limit = $request->input('limit', 10);

        // Fetch limited data columns for performance
        $recentUsers = User::with(['dinas:id,nama_dinas'])
            ->select('id', 'email', 'role', 'dinas_id', 'is_active', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $recentSubmissions = Submission::with(['dinas:id,nama_dinas'])
            ->select('id', 'dinas_id', 'tahun', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Merge logic
        $activities = collect();
        foreach ($recentUsers as $user) {
            $activities->push([
                'type' => 'user_registration',
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'dinas_name' => $user->dinas?->nama_dinas,
                'status' => $user->is_active ? 'approved' : 'pending',
                'timestamp' => $user->created_at,
            ]);
        }
        foreach ($recentSubmissions as $sub) {
            $activities->push([
                'type' => 'submission',
                'submission_id' => $sub->id,
                'dinas_name' => $sub->dinas?->nama_dinas,
                'year' => $sub->tahun,
                'status' => $sub->status,
                'timestamp' => $sub->created_at,
            ]);
        }

        return response()->json([
            'activities' => $activities->sortByDesc('timestamp')->take($limit)->values(),
            'total' => $activities->count(), // Note: Total count of this slice, not DB total
        ]);
    }

    public function getUserDetail($id)
    {
        $user = User::with([
            'dinas.region.parent',
            'submissions' => function ($query) {
                $query->orderBy('tahun', 'desc');
            },
        ])->findOrFail($id);

        // ... existing user detail mapping logic ...
        // Keeping original mapping logic for safety, assuming it's correct
        $region = $user->dinas?->region;
        $provinsi = null;
        $kabupatenKota = null;
        
        if ($region) {
            if ($region->type === 'provinsi') {
                $provinsi = $region->nama_region;
            } else {
                $kabupatenKota = $region->nama_region;
                $provinsi = $region->parent?->nama_region;
            }
        }
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => ['name' => $user->role],
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'dinas' => $user->dinas ? [
                'id' => $user->dinas->id,
                'nama' => $user->dinas->nama_dinas,
                'kode' => $user->dinas->kode_dinas,
                'provinsi' => $provinsi,
                'kabupaten_kota' => $kabupatenKota,
                'type' => $region?->type,
                'kategori' => $region?->kategori,
            ] : null,
            'submissions' => $user->submissions->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'tahun' => $submission->tahun,
                    'status' => $submission->status,
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at,
                ];
            }),
            'submissions_count' => $user->submissions->count(),
        ]);
    }
}