<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistrationRequest;
use App\Services\Auths\AuthApi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    protected $authApi;

    public function __construct(AuthApi $authApi)
    {
        $this->authApi = $authApi;
    }

    public function register(RegistrationRequest $request) 
    {
        try {
            $result = $this->authApi->createUser($request);

            return $result;

            return response()->json([
                'status_code' => 201,
                'message'     => 'Successful',
                'data'        => new UserResource($result)
            ]);
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'status_code' => 400,
                    'message'     => $e->getMessage(),
                ],
                400
            );
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $credentials = [
                'email'    => $request->email,
                'password' => $request->password,
            ];

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Invalid login credentials'
                ], 401);
            }
            
            $response = Http::asForm()->post(config('app.passport_url') . '/oauth/token', [
                'grant_type'    => 'password',
                'client_id'     => env('PASSWORD_CLIENT_ID'),
                'client_secret' => env('PASSWORD_CLIENT_SECRET'),
                'username'      => $request->email,
                'password'      => $request->password,
                'scope'         => '',
            ]);

            $authResponse = $response->json();
            $user         = Auth::user();

            return response()->json([
                'message'       => 'Login successful',
                'expires_in'    => $authResponse['expires_in'],
                'access_token'  => $authResponse['access_token'],
                'refresh_token' => $authResponse['refresh_token']
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status_code' => 400,
                'message'     => $e->getMessage(),
            ], 400);
        }
    }

    public function getAuthUser()
    {
        return Auth::User();
    }
}
