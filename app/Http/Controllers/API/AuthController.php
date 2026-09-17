<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function Mobilelogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated'], 403);
        }

        if (!$user->hasMobileAccess()) {
            return response()->json(['message' => 'This account does not have mobile access'], 403);
        }

        $face = Face::where('user_id', $user->user_id)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'    => 'Login successful',
            'user'       => $this->formatUser($user, $face),
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function Weblogin(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated'], 403);
        }

        $face = Face::where('user_id', $user->user_id)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'    => 'Login successful',
            'user'       => $this->formatUser($user, $face),
            'token'      => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * GET /api/me  and  GET /api/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $face = Face::where('user_id', $user->user_id)->first();

        return response()->json($this->formatUser($user, $face));
    }

    /**
     * Single source of truth for the flat user payload.
     * Mobile app reads these fields at the top level of response.data.
     */
    private function formatUser(User $user, ?Face $face): array
    {
        return [
            'user_id'             => $user->user_id,
            'email'               => $user->email,
            'firstname'           => $user->firstname,
            'lastname'            => $user->lastname,
            'role'                => $user->role,
            'has_web_access'      => $user->hasWebAccess(),
            'has_mobile_access'   => $user->hasMobileAccess(),
            'profile_image_url'   => $user->profile_image
                ? Storage::url($user->profile_image)
                : null,
            'has_face_registered' => $face !== null,
            'face_encoding'       => $face?->encoding,
        ];
    }
}