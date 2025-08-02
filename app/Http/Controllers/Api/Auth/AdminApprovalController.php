<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class AdminApprovalController extends Controller
{
    public function aprovedAdmin($token)
{
    $user = User::where('approval_token', $token)->first();

    // Token tidak valid
    if (!$user) {
        return response()->json([
            'code' => 404,
            'success' => false,
            'message' => 'Token tidak valid atau sudah digunakan.'
        ], 404);
    }

    // Pastikan role admin
    if ($user->role !== 'admin') {
        return response()->json([
            'code' => 403,
            'success' => false,
            'message' => 'Token ini bukan milik akun admin.'
        ], 403);
    }

    // Belum verifikasi email
    if (!$user->email_verified_at) {
        return response()->json([
            'code' => 403,
            'success' => false,
            'message' => 'Admin belum memverifikasi emailnya. Tidak bisa disetujui.'
        ], 403);
    }

    // Sudah di-approve
    if ($user->is_approved) {
        return response()->json([
            'code' => 409,
            'success' => false,
            'message' => 'Akun ini sudah disetujui sebelumnya.'
        ], 409);
    }

    // Setujuin admin
    $user->is_approved = true;
    $user->approved_at = now();
    $user->approval_token = null;
    $user->save();

    return response()->json([
        'code' => 201,
        'success' => true,
        'message' => 'Akun admin berhasil disetujui. Sekarang user bisa login.'
    ]);
}



}
