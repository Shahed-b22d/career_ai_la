<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\JobSeeker;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

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
            'governorate'             => 'required|string|max:255',
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
            'governorate'   => $request->governorate,
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
     * POST /api/auth/fcm-token — save or update FCM device token
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['message' => 'FCM token updated']);
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

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'          => 'nullable|string|max:255',
            'email'         => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone'         => 'nullable|string|max:20',
            'governorate'   => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'avatar'        => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('governorate')) $user->governorate = $request->governorate;
        if ($request->has('phone')) $user->phone = $request->phone;

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        if ($user->role === 'company') {
            $company = $user->company;
            if ($company) {
                if ($request->has('phone')) $company->phone = $request->phone;
                if ($request->has('business_type')) $company->business_type = $request->business_type;
                if ($request->has('description')) $company->description = $request->description;
                $company->save();
            }
            $user->load('company');
        } else {
            $jobSeeker = $user->jobSeeker;
            if ($jobSeeker) {
                if ($request->has('phone')) $jobSeeker->phone = $request->phone;
                $jobSeeker->save();
            }
            $user->load('jobSeeker');
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ], 200);
    }

    /**
     * GET /reset-password — show the reset form (web page opened from email link)
     */
    public function showResetForm(\Illuminate\Http\Request $request)
    {
        return view('auth.reset-password', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    /**
     * POST /reset-password — handle form submission from the web page
     */
    public function handleResetForm(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return view('auth.reset-success');
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    /**
     * Send password reset link to email
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ], 200);
        }

        return response()->json([
            'message' => __($status),
        ], 400);
    }

    /**
     * Reset the user's password using token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ], 200);
        }

        return response()->json([
            'message' => __($status),
        ], 400);
    }
}
