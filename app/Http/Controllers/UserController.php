<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function createUser()
    {
        $roles = Role::all();
        return response()->json(['roles' => $roles]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'login_code' => 'required|integer|unique:users,login_code',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'login_code' => $request->login_code,
        ]);

        $user->assignRole($request->role);

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    public function editUser($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();
        return response()->json(['user' => $user, 'roles' => $roles]);
    }

    // Admin can update user info
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($request->has('password')) {
            $request->validate(['password' => 'string|min:6']);
            $user->update($request->only(['name', 'email', 'login_code', 'password']));
        } else {
            $user->update($request->only(['name', 'email', 'login_code']));
        }
        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    // Admin deletes a user
    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    // List all users
    public function index()
    {
        return response()->json(User::with('roles')->get());
    }
}
