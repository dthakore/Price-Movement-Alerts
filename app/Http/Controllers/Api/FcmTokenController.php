<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function register(Request $request)
    {
        if (empty($request->fcm_token)) {
            return response()->json(['success' => false, 'message' => 'fcm_token is required'], 400);
        }

        if (empty($request->platform)) {
            return response()->json(['success' => false, 'message' => 'platform is required'], 400);
        }

        if (!in_array($request->platform, ['android', 'ios', 'web'])) {
            return response()->json(['success' => false, 'message' => 'platform must be android, ios, or web'], 400);
        }

        $userId = $request->user()->id;
        $token  = $request->fcm_token;

        // Reassign token to current user if it belonged to someone else,
        // or upsert if it already belongs to the current user.
        UserFcmToken::where('fcm_token', $token)
            ->where('user_id', '!=', $userId)
            ->delete();

        UserFcmToken::updateOrCreate(
            ['user_id' => $userId, 'fcm_token' => $token],
            ['platform' => $request->platform]
        );

        return response()->json([
            'success' => true,
            'message' => 'Token registered successfully',
        ]);
    }

    public function unregister(Request $request)
    {
        if (empty($request->fcm_token)) {
            return response()->json(['success' => false, 'message' => 'fcm_token is required'], 400);
        }

        $deleted = UserFcmToken::where('user_id', $request->user()->id)
            ->where('fcm_token', $request->fcm_token)
            ->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Token not found'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Token removed successfully']);
    }
}
