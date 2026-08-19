<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
        ]);

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
        $user->save();

        Wallet::create(['user_id' => $user->id, 'saldo' => 0]);

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
}
