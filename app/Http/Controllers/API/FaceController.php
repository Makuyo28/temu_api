<?php
// backend/app/Http/Controllers/API/FaceController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FaceController extends Controller
{
    /**
     * Verify API key from request
     */
    private function verifyApiKey(Request $request)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->header('Api-Key');
        $validKey = env('FACE_SERVICE_API_KEY', 'qih16CqnbrRsNMiZTOCBwPvNX_R5WvAHruFc2HhOtyQ');

        Log::info('🔑 API Key check:', [
            'received' => $apiKey ? substr($apiKey, 0, 10) . '...' : 'null',
            'expected' => substr($validKey, 0, 10) . '...',
            'matches' => $apiKey === $validKey
        ]);

        return $apiKey === $validKey;
    }

    /**
     * Register a face encoding for a user with photo
     */
    public function register(Request $request)
{
    if (!$this->verifyApiKey($request)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Invalid API Key.',
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'user_id' => 'required|exists:users,user_id',
        'face_encoding' => 'required|array',
        'confidence' => 'required|numeric|min:0|max:1',
        'quality_score' => 'nullable|numeric|min:0|max:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    try {
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $profileImagePath = null;
        if ($request->has('face_image') && !empty($request->face_image)) {
            $profileImagePath = $this->saveProfileImage($request->face_image, $user->user_id);
            if ($profileImagePath) {
                if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                    Storage::disk('public')->delete($user->profile_image);
                }
                $user->profile_image = $profileImagePath;
                $user->save();
            }
        }

        $face = Face::where('user_id', $request->user_id)->first();
        if ($face) {
            $face->update([
                'encoding' => $request->face_encoding,
                'confidence' => $request->confidence,
                'quality_score' => $request->quality_score ?? 0.5,
                'is_active' => true,
            ]);
            $message = 'Face updated successfully';
        } else {
            $face = Face::create([
                'user_id' => $request->user_id,
                'encoding' => $request->face_encoding,
                'confidence' => $request->confidence,
                'quality_score' => $request->quality_score ?? 0.5,
                'is_active' => true,
            ]);
            $message = 'Face registered successfully';
        }

        $user->has_face_registered = true;
        $user->save();

        // =============== NOTIFICATION: Face Registered ===============
        try {
            $confidence = round(($request->confidence ?? 0) * 100, 1);

            \App\Services\NotificationService::notifyUser(
                $user->user_id,
                'Face Registered',
                "Your face has been registered with {$confidence}% confidence",
                \App\Models\Notification::TYPE_FACE_REGISTERED,
                'user',
                $user->user_id
            );

            \App\Services\NotificationService::notifyAdmins(
                'Face Registration',
                "{$user->firstname} {$user->lastname} registered their face",
                \App\Models\Notification::TYPE_FACE_REGISTERED,
                'user',
                $user->user_id
            );
        } catch (\Throwable $ne) {
            Log::warning('Face registration notification failed: ' . $ne->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'face' => $face,
            'user_id' => $request->user_id,
            'profile_image' => $profileImagePath ? Storage::url($profileImagePath) : null,
        ], 201);
    } catch (\Exception $e) {
        Log::error('Face registration error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to register face: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Save profile image from base64
     */
    private function saveProfileImage($base64Image, $userId)
    {
        try {
            // Decode base64 image
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]);
            } else {
                $imageData = $base64Image;
                $type = 'jpg';
            }

            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $extension = in_array($type, $allowedTypes) ? $type : 'jpg';

            // Generate filename
            $filename = 'users/' . $userId . '_' . time() . '_' . Str::random(8) . '.' . $extension;

            // Save to storage
            Storage::disk('public')->put($filename, base64_decode($imageData));

            // Log success
            Log::info('✅ Profile image saved: ' . $filename);

            return $filename;
        } catch (\Exception $e) {
            Log::error('❌ Failed to save profile image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all face encodings for mobile sync
     */
    public function getEncodings(Request $request)
    {
        // ✅ Check API key
        if (!$this->verifyApiKey($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid API Key.'
            ], 401);
        }

        $faces = Face::where('is_active', true)
            ->with('user')
            ->get();

        $encodings = $faces->map(function ($face) {
            return [
                'user_id' => $face->user_id,
                'encoding' => $face->encoding,
                'confidence' => $face->confidence,
                'user_data' => [
                    'name' => $face->user->firstname . ' ' . $face->user->lastname,
                    'email' => $face->user->email,
                    'role' => $face->user->role,
                    'profile_image' => $face->user->profile_image ? Storage::url($face->user->profile_image) : null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'encodings' => $encodings,
            'count' => $encodings->count(),
        ]);
    }

    /**
     * Delete a face registration
     */
    public function delete(Request $request, $userId)
    {
        // ✅ Check API key
        if (!$this->verifyApiKey($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid API Key.'
            ], 401);
        }

        $face = Face::where('user_id', $userId)->first();

        if (!$face) {
            return response()->json([
                'success' => false,
                'message' => 'Face not found',
            ], 404);
        }

        $face->delete();

        $user = User::find($userId);
        if ($user) {
            $user->has_face_registered = false;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Face deleted successfully',
        ]);
    }
}