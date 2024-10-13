<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
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
}
