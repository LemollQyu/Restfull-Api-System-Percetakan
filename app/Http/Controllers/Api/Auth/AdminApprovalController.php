<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class AdminApprovalController extends Controller
{
    //
    public function aprovedAdmin($token)
    {
        $user = User::where('approval_token', $token)->first();

        if (!$user) {
            return response()->json([
                'code' => '404',
                'message' => 'Token tidak valid atau sudah digunakan.'
            ], 404);
        }

        if ($user->is_approved) {
            return response()->json([
                'code' => 409,
                'message' => 'Akun ini sudah disetujui sebelumnya.'
            ], 409); // conflict
        }

        $user->is_approved = true;
        $user->approved_at = now();
        $user->approval_token = null;
        $user->save();

        return response()->json([
            'code' => 201,
            'message' => 'Akun admin berhasil disetujui. Sekarang user bisa login.'
        ]);
    }

}
