<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller {

    public function login(Request $request) {

        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);
            if (!Auth::attempt($credentials)) {

                return response()->json(['message' => 'Invalid login'], 401);
            }

            $user = Auth::user();
            $token = $user->createToken('qms-token')->plainTextToken;
            
            return response()->json([
                        'token' => $token,
                        'user' => $user->load('roles')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                        'message' => 'error occured',
                        'error-message' => $e->getMessage()
            ]);
        }
    }
}
