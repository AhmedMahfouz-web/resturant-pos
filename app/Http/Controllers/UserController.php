<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        // Validate the request data
        $validatedData = $request->validate([
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'username' => 'string|max:255|unique:users,username,' . $id,
            'email' => 'string|email|max:255|unique:users,email,' . $id,
            'login_code' => 'string|nullable',
            'password' => 'nullable|string|min:6',
        ]);

        // Check if password is present and hash it
        if ($request->filled('password')) {
            $validatedData['password'] = bcrypt($request->password);
        } else {
            unset($validatedData['password']);
        }

        // Debugging: Log the validated data
        Log::info('Updating user with data: ', $validatedData);

        try {
            // Update the user with validated data
            $user->syncRoles([$request->role]);
            $user->update($validatedData);

            // Debugging: Check if the user was updated
            Log::info('User updated: ', $user->toArray());

            return response()->json(['message' => 'User updated successfully', 'user' => $user]);
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update user'], 500);
        }
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
