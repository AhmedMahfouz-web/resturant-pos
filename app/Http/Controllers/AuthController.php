<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt', ['except' => ['login']]);
    }
    // Login a user
    public function login(Request $request)
    {
        if ($request->has('login_code')) {
            // Find the user by login_code
            $user = User::where('login_code', $request->login_code)
                ->select('first_name', 'last_name', 'username', 'id')
                ->with(['roles.permissions:id,name'])
                ->first();

            if (!$user) {
                return response()->json(['error' => 'Wrong credentials'], 401);
            }

            // Generate token for the user
            $token = auth()->login($user);
            $shift = app(ShiftController::class);
            $shift = $shift->startShift($user);
            if ($shift) {
                return $this->respondWithToken($token, $shift, $user);
            } else {
                return response()->json(['error' => 'Open Shift first!'], 401);
            }
        }

        // Default to email login with password
        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token, $shift, $user);
    }


    // Get the authenticated user
    public function me()
    {
        return response()->json(auth()->user());
    }

    // Log the user out (Invalidate the token)
    public function logout()
    {
        if (auth()->user()->can('end shift')) {
            $shift = app(ShiftController::class);
            $shift->endShift();
        }
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    // Refresh the JWT Token
    public function refresh()
    {
        // return $this->respondWithToken(auth()->refresh());
    }

    // Helper function to respond with token
    protected function respondWithToken($token, $shift, $user)
    {
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'shift' => $shift,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL()
        ], 200);
    }
}
