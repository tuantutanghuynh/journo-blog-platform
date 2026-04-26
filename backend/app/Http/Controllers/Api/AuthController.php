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
        // Bước 1: Kiểm tra dữ liệu gửi lên có hợp lệ không
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        // Bước 2: Tạo user mới trong database
        $user = new User();
        $user->name     = $request->name;
        $user->email    = $request->email;
        $user->password = bcrypt($request->password); // mã hóa password trước khi lưu

        $user->save();

        // Bước 3: Tạo token để user đăng nhập luôn sau khi đăng ký
        $token = $user->createToken('auth_token')->plainTextToken;

        // Bước 4: Trả về thông tin user + token
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        // Bước 1: Tìm user theo email trong database
        $user = User::where('email', $request->email)->first();

        // Bước 2: Kiểm tra user có tồn tại không
        if (!$user) {
            return response()->json([
                'message' => 'Email không tồn tại',
            ], 404);
        }

        // Bước 3: Kiểm tra password có đúng không
        $passwordIsCorrect = Hash::check($request->password, $user->password);

        if (!$passwordIsCorrect) {
            return response()->json([
                'message' => 'Sai mật khẩu',
            ], 401);
        }

        // Bước 4: Tạo token mới cho phiên đăng nhập này
        $token = $user->createToken('auth_token')->plainTextToken;

        // Bước 5: Trả về thông tin user + token
        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        // Lấy user đang đăng nhập từ token
        $user = $request->user();

        // Xóa token hiện tại → user bị đăng xuất
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công',
        ], 200);
    }

    public function me(Request $request)
    {
        // Lấy thông tin user đang đăng nhập từ token
        $user = $request->user();

        return response()->json($user, 200);
    }
}
