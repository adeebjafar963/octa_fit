<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $role = Role::query()->where('name', 'user')->firstOrFail();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $role->id,
                'is_active' => true,
            ]);

            Profile::query()->create([
                'user_id' => $user->id,
                'locale' => $data['locale'] ?? 'en',
            ]);

            $user->load(['role', 'profile']);
            $token = $user->createToken('mobile')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }

    public function login(string $email, string $password): array
    {
        $user = User::query()
            ->with(['role', 'profile'])
            ->where('email', $email)
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();

            return;
        }

        // Fallback for edge cases where the current token instance is unavailable.
        $user->tokens()->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): string
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = $newPassword;
        $user->save();

        $user->tokens()->delete();

        return $user->createToken('mobile')->plainTextToken;
    }
}
