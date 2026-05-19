<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\JobSeeker;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user (Job Seeker or Company)
     * Accepts both JSON and multipart/form-data
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                    => 'required|string|max:255',
            'email'                   => 'required|string|email|max:255|unique:users,email',
            'password'                => 'required|string|min:6',
            'role'                    => 'required|in:job,company',
            'phone'                   => 'nullable|string|max:20',
            'business_type'           => 'nullable|string|max:255',
            'commercial_register_file'=> 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Create the base user
        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'role'          => $request->role,
            'phone'         => $request->phone,
            'business_type' => $request->business_type,
        ]);

        // Create role-specific profile
        if ($request->role === 'company') {
            $commercialRegisterPath = null;

            if ($request->hasFile('commercial_register_file')) {
                $commercialRegisterPath = $request->file('commercial_register_file')
                    ->store('commercial_registers', 'public');
            }

            Company::create([
                'user_id'                  => $user->id,
                'phone'                    => $request->phone,
                'business_type'            => $request->business_type,
                'commercial_register_path' => $commercialRegisterPath,
            ]);

            $user->load('company');
        } else {
            JobSeeker::create([
                'user_id' => $user->id,
                'phone'   => $request->phone,
            ]);

            $user->load('jobSeeker');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    /**
     * Login user (Job Seeker or Company)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            'role'     => 'required|in:job,company',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        if ($user->role !== $request->role) {
            return response()->json([
                'message' => 'This account is registered as a ' . ucfirst($user->role) . ', not a ' . ucfirst($request->role),
            ], 403);
        }

        // Load profile based on role
        if ($user->role === 'company') {
            $user->load('company');
        } else {
            $user->load('jobSeeker');
        }

        // Revoke old tokens and issue a fresh one
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'token'   => $token,
            'user'    => $user,
        ], 200);
    }

    /**
     * Logout authenticated user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Get the authenticated user's profile
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'company') {
            $user->load('company');
        } else {
            $user->load('jobSeeker');
        }

        return response()->json([
            'user' => $user,
        ], 200);
    }
}
