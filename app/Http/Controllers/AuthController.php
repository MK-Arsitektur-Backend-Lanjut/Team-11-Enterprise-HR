<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\AuthRepository;

class AuthController extends Controller
{
    private AuthRepository $repository;

    public function __construct(AuthRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * POST /api/v1/auth/register
     * Registrasi user baru dan langsung terbitkan token.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|unique:users,email',
            'password'    => 'required|string|min:6|confirmed',
            'employee_id' => 'nullable|integer',
        ]);

        try {
            $user = $this->repository->register($request->only(['name', 'email', 'password', 'employee_id']));
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil.',
                'data'    => [
                    'user'  => $user,
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registrasi gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/auth/login
     * Login user dan terbitkan token JWT-style (Sanctum).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = $this->repository->findByEmail($request->email);

        if (!$user || !$this->repository->verifyPassword($user, $request->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Hapus token lama (optional: single session)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke current access token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     * Ambil data user yang sedang login beserta employee info.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'user'     => $user,
                'employee' => [
                    'id' => $user->employee_id,
                ],
            ],
        ]);
    }
}
