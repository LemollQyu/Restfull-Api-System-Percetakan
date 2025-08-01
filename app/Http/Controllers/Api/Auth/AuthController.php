<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password'     => 'required|string|min:6',
            'role'         => 'required|in:user,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $user = new User();
        $user->name         = $data['name'];
        $user->email        = $data['email'];
        $user->phone_number = $data['phone_number'];
        $user->password     = Hash::make($data['password']);
        $user->role         = $data['role'];

        if ($user->role === 'admin') {
            $user->is_approved     = false;
            $user->approval_token  = Str::uuid();

            // Kirim email ke developer untuk approval admin
            Mail::raw(
                "Permintaan akun admin dari {$user->name}.\n Dengan email {$user->email} \n\nKlik link berikut untuk menyetujui:\n" .
                url("/api/v1/api/auth/admin/approve/" . $user->approval_token),
                function ($message) use ($user) {
                    $message->to('alyanabilafatimatuzzahra@gmail.com')
                            ->subject("Permintaan Persetujuan Akun Admin - {$user->name} - dengan email {$user->email}");
                }
            );
        } else {
            $user->is_approved = true;
            $user->email_verification_token = Str::uuid();

            // Kirim email verifikasi ke user
            Mail::raw(
                "Hi {$user->name},\n\nKlik link berikut untuk verifikasi akun Anda:\n" .
                url("/api/v1/api/auth/verify-email/" . $user->email_verification_token),
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject("Verifikasi Email Akun Anda");
                }
            );
        }

        $user->save();

        return response()->json([
            'code' => 201,
            'message' => 'Registrasi berhasil. ' .
                ($user->role === 'admin'
                    ? 'Menunggu persetujuan developer.'
                    : 'Silakan verifikasi email Anda terlebih dahulu.'),
        ], 201);
    }

public function verifyEmail($token)
{
    $user = User::where('email_verification_token', $token)->first();

    if (!$user) {
        return response()->json([
            'code' => 404,
            'message' => 'Token verifikasi tidak valid atau sudah digunakan.'
        ], 404);
    }

    if ($user->email_verified_at !== null) {
    return response()->json([
        'code' => 409,
        'message' => 'Email Anda sudah diverifikasi sebelumnya.'
    ], 409);
}


    $user->email_verified_at = now();
    $user->email_verification_token = null;
    $user->save();

    return response()->json([
        'code' => 200,
        'message' => 'Email berhasil diverifikasi. Akun Anda sudah aktif.'
    ]);
}


}
