<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Landlord self-registration. Tenants are only ever created by their landlord. */
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'An account with this email already exists.',
        ], [
            'firstName' => 'first name',
            'lastName' => 'last name',
        ]);

        $user = User::create([
            'role' => User::ROLE_LANDLORD,
            'first_name' => trim($data['firstName']),
            'last_name' => trim($data['lastName']),
            'email' => $data['email'],
            'password' => $data['password'],
            'auth_provider' => 'password',
            'last_login_at' => now(),
        ]);

        return $this->tokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            $message = $user && ! $user->password
                ? 'This account uses Google sign-in. Tap "Continue with Google".'
                : 'Incorrect email or password.';
            throw ValidationException::withMessages(['email' => $message]);
        }

        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['email' => 'Admin accounts sign in at the admin panel.']);
        }
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'This account has been suspended. Please contact Letting World.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->tokenResponse($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('landlordProfile'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function changePassword(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            // Google-only accounts have no current password to confirm.
            'currentPassword' => [$user->password ? 'required' : 'nullable', 'string'],
            'newPassword' => ['required', 'string', Password::min(8)->letters()->numbers(), 'different:currentPassword'],
        ], [
            'newPassword.different' => 'Your new password must be different from the current one.',
        ], [
            'currentPassword' => 'current password',
            'newPassword' => 'new password',
        ]);

        if ($user->password && ! Hash::check($data['currentPassword'], $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'Current password is incorrect.']);
        }

        $user->forceFill(['password' => $data['newPassword'], 'must_change_password' => false])->save();

        // Sign out every other device.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return new UserResource($user->load('landlordProfile'));
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('landlordProfile')),
        ], $status);
    }
}
