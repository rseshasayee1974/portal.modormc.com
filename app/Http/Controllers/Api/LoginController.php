<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    #[OA\Post(
        path: "/auth/login",
        summary: "User Login",
        tags: ["Authentication"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "mobile", type: "string", example: "9876543210"),
                new OA\Property(property: "password", type: "string", example: "123456")
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Login Success")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid mobile or password',
            ], 401);
        }

        $token = $user->createToken('mobile_app')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login Success',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'plant_id' => $user->default_plant_id
            ]
        ]);
    }

    #[OA\Post(path: "/auth/logout", summary: "User Logout", tags: ["Authentication"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Logged out successfully")]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
