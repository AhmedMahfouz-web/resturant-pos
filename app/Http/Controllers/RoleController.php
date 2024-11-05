<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    public function createRole()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    public function storeRole(Request $request)
    {
        $role = Role::create($request->only('name'));
        $role->syncPermissions($request->permissions);
        return response()->json($role);
    }

    public function editRole($id)
    {
        $role = Role::with('permissions')->get();
        $permissions = Permission::all();
        return response()->json(['role' => $role, 'permissions' => $permissions]);
    }

    public function updateRole($id, Request $request)
    {
        $role = Role::find($id);
        $role->update($request->only('name'));
        $role->syncPermissions($request->permissions);
        return response()->json($role);
    }

    public function deleteRole($id)
    {
        $role = Role::find($id);

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function checkPermission(Request $request)
    {
        $user = User::where('login_code', $request->code)->first();

        if ($user->hasAllDirectPermissions($request->permission)) {
            return response()->json(['message' => true], 200);
        } else {
            return response()->json(['message' => false], 401);
        }
    }
}
