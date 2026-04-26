<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Step 1: Validate incoming data
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        // Step 2: Create new user in database
        $user = new User();
        $user->name     = $request->name;
        $user->email    = $request->email;
        $user->password = bcrypt($request->password); // hash password before saving

        $user->save();

        // Step 3: Create token so user is logged in right after registering
        $token = $user->createToken('auth_token')->plainTextToken;

        // Step 4: Return user info + token
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        // Step 1: Find user by email in database
        $user = User::where('email', $request->email)->first();

        // Step 2: Check if user exists
        if (!$user) {
            return response()->json([
                'message' => 'Email not found',
            ], 404);
        }

        // Step 3: Check if password is correct
        $passwordIsCorrect = Hash::check($request->password, $user->password);

        if (!$passwordIsCorrect) {
            return response()->json([
                'message' => 'Wrong password',
            ], 401);
        }

        // Step 4: Create a new token for this login session
        $token = $user->createToken('auth_token')->plainTextToken;

        // Step 5: Return user info + token
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        // Get the currently logged in user from token
        $user = $request->user();

        // Delete current token → user is logged out
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    public function me(Request $request)
    {
        // Get currently logged in user from token
        $user = $request->user();

        return response()->json($user, 200);
    }
}
