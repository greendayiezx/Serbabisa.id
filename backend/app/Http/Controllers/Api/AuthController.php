<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['customer', 'mitra'])],
            'kode_referral' => ['nullable', 'string', 'max:20'],
        ]);

        // Kode pengundang (opsional). Ditolak eksplisit bila diisi tapi tak ada,
        // supaya user tidak mengira bonusnya berjalan padahal kodenya salah.
        $referrer = null;
        if (! empty($validated['kode_referral'])) {
            $referrer = User::where('kode_referral', $validated['kode_referral'])->first();
            if (! $referrer) {
                throw ValidationException::withMessages([
                    'kode_referral' => 'Kode referral tidak ditemukan.',
                ]);
            }
        }

        $user = DB::transaction(function () use ($validated, $referrer) {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make($validated['password']),
            ]);

            // Kolom sensitif di-set eksplisit, bukan dari mass assignment.
            // 'role' sudah divalidasi hanya boleh customer|mitra (admin tidak
            // pernah bisa mendaftar sendiri). is_active default aktif.
            $user->role = $validated['role'];
            $user->is_active = true;
            $user->kode_referral = $this->generateReferralCode($validated['name']);
            $user->direferal_oleh_id = $referrer?->id;
            $user->save();

            Wallet::create(['user_id' => $user->id, 'saldo' => 0]);

            // Referral tercatat "pending"; bonus pengundang cair saat pesanan
            // pertama yang direferal selesai (lihat BelanjaCheckoutController@complete).
            if ($referrer) {
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referred_id' => $user->id,
                    'status' => 'pending',
                    'reward_cashback' => Referral::DEFAULT_REWARD,
                ]);
            }

            return $user;
        });

        AuditLog::record('auth.register', $request, [
            'user_id' => $user->id,
            'role' => $user->role,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            AuditLog::record('auth.login.failed', $request, [
                'email' => $credentials['email'],
            ]);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            AuditLog::record('auth.login.blocked_inactive', $request, [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        $user->last_login_at = now();
        $user->save();

        AuditLog::record('auth.login.success', $request, [
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request, [
            'user_id' => $request->user()->id,
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['mitraProfile', 'wallet']));
    }

    /** Kode referral unik milik user: prefix dari nama + 4 karakter acak. */
    private function generateReferralCode(string $name): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
        $prefix = substr($prefix ?: 'USER', 0, 4);

        do {
            $kode = $prefix.strtoupper(Str::random(4));
        } while (User::where('kode_referral', $kode)->exists());

        return $kode;
    }
}
