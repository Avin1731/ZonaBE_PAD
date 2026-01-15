<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Dinas;
use App\Models\ActivityLog; 
use App\Models\Deadline; // Pastikan model ini di-import
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon; // Import Carbon

class AdminController extends Controller
{
    private const USERS_PER_PAGE = 15;
    private const SYSTEM_LOGS_PER_PAGE = 25;

    // =================================================================
    // DEADLINE MANAGEMENT
    // =================================================================

    public function getDeadline($year)
    {
        try {
            $deadline = Deadline::where('year', $year)->first();
            
            if (!$deadline) {
                return response()->json([
                    'year' => $year, 
                    'deadline' => null, 
                    'catatan' => null,
                    'is_passed' => false
                ]);
            }

            $isPassed = Carbon::now()->gt(Carbon::parse($deadline->deadline_at));

            return response()->json([
                'year' => $deadline->year,
                'deadline' => $deadline->deadline_at,
                'catatan' => $deadline->catatan,
                'is_passed' => $isPassed
            ]);
        } catch (\Exception $e) {
            Log::error("GET DEADLINE ERROR: " . $e->getMessage());
            return response()->json(['message' => 'Gagal mengambil data deadline'], 500);
        }
    }

    public function setDeadline(Request $request)
    {
        try {
            $request->validate([
                'year' => 'required|integer',
                'deadline_at' => 'required|date',
                'catatan' => 'nullable|string'
            ]);

            $deadline = Deadline::updateOrCreate(
                ['year' => $request->year],
                [
                    'deadline_at' => $request->deadline_at,
                    'catatan' => $request->catatan
                ]
            );

            // [LOG] Catat aktivitas update deadline
            $formattedDate = Carbon::parse($request->deadline_at)->format('d M Y H:i');
            $this->safeLog('update_deadline', "Mengupdate deadline tahun {$request->year} menjadi {$formattedDate}", null, ['year' => $request->year]);

            return response()->json(['message' => 'Deadline berhasil disimpan', 'data' => $deadline]);

        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("SET DEADLINE ERROR: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan deadline'], 500);
        }
    }

    // =================================================================
    // USER MANAGEMENT
    // =================================================================

    public function approveUser($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $user = User::with('dinas')->findOrFail($id);
                $dinas = $user->dinas()->first(); 
                $targetName = $user->email;

                if ($dinas) {
                    if ($dinas->status === 'terdaftar') {
                        throw new \Exception('User tidak bisa diaktifkan, dinas sudah Terdaftar.');
                    }
                    $dinas->update(['status' => 'terdaftar']);
                    $targetName = $dinas->nama_dinas;
                }

                $user->update(['is_active' => true]);
                $this->safeLog('approve_user', "Menyetujui akun: {$targetName}", $user);
            });

            return response()->json(['message' => 'Berhasil Aktivasi User']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal aktivasi user', 'error' => $e->getMessage()], 400);
        }
    }

    public function rejectUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $email = $user->email; 

            if($user->is_active){
                return response()->json(['message' => 'User sudah diaktifkan, tidak bisa ditolak'], 400);
            }
            
            $this->safeLog('reject_user', "Menolak pendaftaran user: {$email}", null, ['deleted_email' => $email]);
            $user->delete();

            return response()->json(['message' => 'Pendaftaran user ditolak']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menolak user', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $email = $user->email;
            $role = $user->role;

            DB::transaction(function () use ($user) {
                if ($user->dinas) {
                    $user->dinas->update(['status' => 'belum_terdaftar']);
                }
                $user->delete();
            });

            $this->safeLog('delete_user', "Menghapus user {$role}: {$email}", null, ['deleted_email' => $email, 'role' => $role]);

            return response()->json(['message' => 'User berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghapus user', 'error' => $e->getMessage()], 500);
        }
    }

    public function createPusdatin(Request $request){
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'name' => 'nullable|string|max:255',
                'nomor_telepon' => 'nullable|string|max:20',
            ]);
            
            $user = User::create([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'nomor_telepon' => $validated['nomor_telepon'] ?? null,
                'role' => 'pusdatin',
                'is_active' => true,
            ]);

            $this->safeLog('create_pusdatin', "Membuat akun Pusdatin baru: {$user->email} ({$user->name})", $user);
            
            return response()->json(['message' => 'Akun Pusdatin berhasil dibuat', 'user' => $user], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat akun', 'error' => $e->getMessage()], 400);
        }
    }

    // =================================================================
    // DATA FETCHING & LOGS
    // =================================================================

    public function showUser(Request $request, $role, $status)
    {
        $perPage = $request->input('per_page', self::USERS_PER_PAGE);
        $isActive = $this->parseStatusToBoolean($status);
        
        $dbRole = $role;
        if ($role === 'kabupaten' || $role === 'kabkota') {
            $dbRole = 'kabupaten/kota';
        }

        if ($role === 'all') {
            $data = $this->queryUsers($request, null, $isActive, $perPage);
        } else {
            $data = $this->queryUsers($request, $dbRole, $isActive, $perPage);
        }

        return response()->json($this->transformUserData($data));
    }

    public function getSystemLogs(Request $request)
    {
        try {
            $limit = $request->input('limit', self::SYSTEM_LOGS_PER_PAGE);
            $page = $request->input('page', 1);
            $roleFilter = $request->input('role', 'all');
            $yearFilter = $request->input('year');

            $query = ActivityLog::with(['user.dinas', 'subject']);

            if ($roleFilter !== 'all' && !empty($roleFilter)) {
                $query->where(function($q) use ($roleFilter) {
                    $q->where('context_type', $roleFilter)
                      ->orWhereHas('user', function($u) use ($roleFilter) {
                          $u->where('role', $roleFilter);
                      });
                });
            }

            if (!empty($yearFilter)) {
                $query->where('year', $yearFilter);
            }

            $logs = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            $transformed = $logs->getCollection()->map(function ($log) {
                return $this->transformLogItem($log);
            });

            $logs->setCollection($transformed);
            return response()->json($logs);

        } catch (\Exception $e) {
            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    // Proxy Methods
    public function trackingHistoryPusdatin(Request $request, $year = null) { if ($year) $request->merge(['year' => $year]); return $this->getSystemLogs($request); }
    public function getAdminLogs(Request $request) { $request->merge(['role' => 'admin']); return $this->getSystemLogs($request); }
    public function getPusdatinLogs(Request $request) { $request->merge(['role' => 'pusdatin']); return $this->getSystemLogs($request); }
    public function listUsers() { return response()->json(User::with('dinas')->get()); }

    // =================================================================
    // HELPER METHODS
    // =================================================================

    private function queryUsers(Request $request, $role, $isActive, $perPage) {
        $query = User::with('dinas.region.parent')->where('is_active', $isActive);
        if ($role) { $query->where('role', $role); }
        $query->when($request->search, function($q, $search) {
            return $q->where(function($subQ) use ($search) {
                $subQ->where('email', 'like', "%{$search}%")
                     ->orWhereHas('dinas', function($dq) use ($search) {
                         $dq->where('nama_dinas', 'like', "%{$search}%")->orWhere('kode_dinas', 'like', "%{$search}%");
                     });
            });
        });
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    private function transformUserData($data) {
        $data->getCollection()->transform(function ($user) {
            $user->province_name = '-'; $user->regency_name = '-'; $user->display_name = $user->email;
            if ($user->role === 'admin') $user->display_name = 'Administrator';
            elseif ($user->role === 'pusdatin') $user->display_name = 'Tim Pusdatin';
            elseif ($user->dinas) {
                $user->display_name = $user->dinas->nama_dinas;
                if ($user->dinas->region) {
                    $region = $user->dinas->region;
                    if ($region->type === 'provinsi') { $user->province_name = $region->nama_wilayah ?? $region->nama_region; } 
                    else { $user->regency_name = $region->nama_wilayah ?? $region->nama_region; $user->province_name = $region->parent ? ($region->parent->nama_wilayah ?? $region->parent->nama_region) : null; }
                }
            }
            return $user;
        });
        return $data;
    }

    private function transformLogItem($log): array {
        $actorName = 'System / Deleted User'; $actorRole = 'system'; $email = '-';
        if ($log->user) {
            $email = $log->user->email; $actorRole = $log->user->role; 
            if ($log->user->role === 'admin') $actorName = 'Administrator';
            elseif ($log->user->role === 'pusdatin') $actorName = 'Tim Pusdatin';
            elseif ($log->user->dinas) $actorName = $log->user->dinas->nama_dinas;
            else $actorName = $log->user->email;
        }
        $targetStr = '-';
        if ($log->subject_type === 'App\Models\User' && $log->subject) $targetStr = $log->subject->dinas ? $log->subject->dinas->nama_dinas : $log->subject->email;
        elseif ($log->subject_type === 'App\Models\Dinas' && $log->subject) $targetStr = $log->subject->nama_dinas;
        elseif (isset($log->properties['deleted_email'])) $targetStr = $log->properties['deleted_email'];

        return [
            'id' => $log->id, 'user' => $actorName, 'email' => $email, 'role' => $actorRole,
            'action' => $this->formatLogAction($log->action), 'target' => $targetStr,
            'time' => $log->created_at->toISOString(), 'time_formatted' => $log->created_at->format('d/m/Y H:i'),
            'status' => 'success', 'catatan' => $log->description,
            'year' => $log->year ?? $log->created_at->format('Y'), 'stage' => $log->stage ?? '-',
            'document_type' => $log->document_type ?? '-', 'context_type' => $log->context_type ?? 'system',
            'ip_address' => $log->ip_address ?? '-',
        ];
    }

    private function parseStatusToBoolean($status): bool {
        return ($status === 'approved' || $status === '1' || $status === 1 || $status === true);
    }

    private function safeLog($action, $description, $subject = null, $properties = [])
    {
        try {
            $user = Auth::user();
            $contextType = 'system';
            if ($user) {
                if ($user->role === 'admin') $contextType = 'admin';
                elseif ($user->role === 'pusdatin') $contextType = 'pusdatin';
            }

            ActivityLog::create([
                'user_id'      => $user ? $user->id : null,
                'action'       => $action,
                'description'  => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject ? $subject->id : null,
                'context_type' => $contextType,
                'properties'   => !empty($properties) ? json_encode($properties) : null,
                'ip_address'   => request()->ip(),
                'year'         => date('Y'),
            ]);
        } catch (\Exception $e) {
            Log::error("ACTIVITY LOG ERROR [{$action}]: " . $e->getMessage());
        }
    }

    private function formatLogAction($actionSlug): string {
        $map = [
            'create_pusdatin' => 'Membuat Akun Pusdatin', 'delete_user' => 'Menghapus User',
            'approve_user' => 'Menyetujui User', 'reject_user' => 'Menolak User',
            'update_deadline' => 'Update Deadline',
        ];
        return $map[$actionSlug] ?? ucwords(str_replace('_', ' ', $actionSlug));
    }
}