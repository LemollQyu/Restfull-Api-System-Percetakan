<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordOtp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                'success' => false,
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
        $user->email_verification_token = Str::uuid();

        if ($user->role === 'admin') {
            $user->is_approved = false;
            $user->approval_token = Str::uuid();
            $user->email_verified_at = null;

            // Kirim email verifikasi ke admin
            Mail::raw(
                "Hi {$user->name},\n\nKlik link berikut untuk verifikasi email Anda sebelum akun disetujui oleh developer:\n" .
                url("/api/v1/auth/verify-email/" . $user->email_verification_token),
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject("Verifikasi Email Akun Admin");
                }
            );
        } else {
            $user->is_approved = true;

            // Kirim email verifikasi ke user biasa
            Mail::raw(
                "Hi {$user->name},\n\nKlik link berikut untuk verifikasi akun Anda:\n" .
                url("/api/v1/auth/verify-email/" . $user->email_verification_token),
                function ($message) use ($user) {
                    $message->to($user->email)
                            ->subject("Verifikasi Email Akun Anda");
                }
            );
        }

        $user->save();

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Registrasi berhasil. ' .
                ($user->role === 'admin'
                    ? 'Silakan verifikasi email Anda. Setelah itu akun akan ditinjau developer.'
                    : 'Silakan verifikasi email Anda terlebih dahulu.'),
        ], 201);
    }

    public function verifyEmail($token)
    {
        $user = User::where('email_verification_token', $token)->first();

        // Token tidak ditemukan
        if (!$user) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Token verifikasi tidak valid atau sudah digunakan.'
            ], 404);
        }

        // Sudah diverifikasi sebelumnya
        if ($user->email_verified_at !== null) {
            return response()->json([
                'code' => 409,
                'success' => false,
                'message' => 'Email Anda sudah diverifikasi sebelumnya.'
            ], 409);
        }

        // Tandai email sudah diverifikasi
        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        // Kirim email ke developer jika admin
        if ($user->role === 'admin') {
            Mail::raw(
                "Permintaan akun admin dari {$user->name} ({$user->email}).\n\nKlik link berikut untuk menyetujui:\n" .
                url("/api/v1/auth/admin/approve/" . $user->approval_token),
                function ($message) use ($user) {
                    $message->to('alyanabilafatimatuzzahra@gmail.com')
                            ->subject("Persetujuan Admin Baru: {$user->name}");
                }
            );
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Email berhasil diverifikasi.'
        ]);
    }

    public function login(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'user'     => 'required|string', // Bisa email atau no. HP
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Cari user berdasarkan email atau phone_number
        $user = User::where('email', $data['user'])
                    ->orWhere('phone_number', $data['user'])
                    ->first();

        // Cek apakah user ditemukan dan password cocok
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'code'    => 401,
                'success' => false,
                'message' => 'Kredensial tidak valid.'
            ], 401);
        }

        // Cek apakah email sudah diverifikasi
        if (!$user->email_verified_at) {
            return response()->json([
                'code'    => 403,
                'success' => false,
                'message' => 'Email belum diverifikasi.'
            ], 403);
        }

        // Cek apakah admin sudah disetujui developer
        if ($user->role === 'admin' && !$user->is_approved) {
            return response()->json([
                'code'    => 403,
                'success' => false,
                'message' => 'Akun admin belum disetujui oleh developer.'
            ], 403);
        }

        // Buat token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Respon sukses
        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user'  => [
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'phone_number' => $user->phone_number,
                    'role'         => $user->role,
                ]
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.'
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password'      => 'required|string',
            'new_password'      => 'required|string|min:6',
            'confirm_password'  => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Cek password lama cocok atau tidak
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'code' => 403,
                'success' => false,
                'message' => 'Password lama salah.'
            ], 403);
        }

        // Ubah password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Password berhasil diubah.'
        ]);
    }

    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => 'required|string',
            'method' => 'required|in:email,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        // ✅ Hanya boleh akses OTP untuk dirinya sendiri
        if ($data['email_or_phone'] !== $user->email && $data['email_or_phone'] !== $user->phone_number) {
            return response()->json([
                'success' => false,
                'message' => 'Anda hanya bisa mengirim OTP ke akun milik Anda sendiri.'
            ], 403);
        }

        // ✅ Validasi: email harus pakai method email, phone harus pakai method whatsapp
        $isEmail = filter_var($data['email_or_phone'], FILTER_VALIDATE_EMAIL);
        if ($isEmail && $data['method'] !== 'email') {
            return response()->json([
                'success' => false,
                'message' => 'Jika menggunakan email, method harus email.'
            ], 422);
        }

        if (!$isEmail && $data['method'] !== 'whatsapp') {
            return response()->json([
                'success' => false,
                'message' => 'Jika menggunakan nomor telepon, method harus whatsapp.'
            ], 422);
        }

        // ✅ Cek OTP aktif
        $now = now();
        $existingOtp = PasswordOtp::where('email_or_phone', $data['email_or_phone'])
            ->where('method', $data['method'])
            ->where('used', false)
            ->where('expires_at', '>', $now)
            ->first();

        if ($existingOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP masih aktif. Silakan tunggu hingga kadaluarsa.'
            ], 429);
        }

        // ✅ Generate dan simpan OTP
        $otp = random_int(100000, 999999);

        PasswordOtp::create([
            'email_or_phone' => $data['email_or_phone'],
            'otp_code' => $otp,
            'method' => $data['method'],
            'expires_at' => $now->addSeconds(60),
        ]);

        // ✅ Kirim OTP
        // OTP ini itu trial yang via WA, jadi kemungkinan harus berubah nanti
        // seperti scretnya, api keynya, yangdi env, hanya buat akun, terus masukin api key sama scretnya saja nanti dan login device
        if ($data['method'] === 'email') {
            Mail::raw("Kode OTP Anda adalah: {$otp}", function ($message) use ($data) {
                $message->to($data['email_or_phone'])
                        ->subject('Kode OTP Reset Password');
            });
        } else {
            $phone = preg_replace('/[^0-9]/', '', $data['email_or_phone']); // pastikan format bersih

            $response = Http::withHeaders([
                'Authorization' => config('services.wablas.api_key'),
            ])->post('https://sby.wablas.com/api/v2/send-message', [
                'data' => [[
                    'phone' => $phone,
                    'message' => "Kode OTP Anda adalah: {$otp}",
                    'secret' => config('services.wablas.secret'),
                    'priority' => true,
                ]]
            ]);


            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim OTP ke WhatsApp.',
                    'wablas_response' => $response->body()
                ], 500);
            }
        }


        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil dikirim.'
        ]);
    }









}
