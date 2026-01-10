<?php

// routes/web.php

use Illuminate\Support\Facades\Route;

// Route Debugger (Hapus nanti kalau sudah fix)
Route::any('/{any}', function ($any) {
    return response()->json([
        'status' => 'Laravel Hidup! 🟢',
        'pesan' => 'Tapi route yang kamu tuju tidak ditemukan',
        'path_masuk' => request()->path(),
        'url_asli' => request()->url(),
        'method' => request()->method(),
    ]);
})->where('any', '.*');