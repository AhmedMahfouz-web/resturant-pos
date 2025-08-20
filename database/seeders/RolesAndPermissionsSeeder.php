<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'start shift',
            'end shift',
            'create category',
            'create product',
            'create user',
            'create material',
            'create table',
            'edit table',
            'edit category',
            'edit product',
            'edit user',
            'edit material',
            'delete table',
            'delete category',
            'delete product',
            'delete user',
            'delete material',
            'place order',
            'reprint bill',
            'edit order',
            'cancel order',
            'split order',
            'merge order',
            'make discount',
            'remove discount',
            'create discount',
            'edit discount',
            'delete discount',
            'old reciept',
            'reprint reciept',
            'shift details',
            'order item discount',
            'view material stock',
            'update material stock',
            'view material history',
            'create material adjustment',
        ];

        // Create each permission from the array
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles and the permissions they should have
        $roles = [
            'Admin' => [
                'start shift',
                'end shift',
                'create category',
                'create product',
                'create user',
                'create material',
                'create table',
                'edit table',
                'edit category',
                'edit product',
                'edit user',
                'edit material',
                'delete table',
                'delete category',
                'delete product',
                'delete user',
                'delete material',
                'place order',
                'reprint bill',
                'edit order',
                'cancel order',
                'split order',
                'merge order',
                'make discount',
                'remove discount',
                'create discount',
                'edit discount',
                'delete discount',
                'old reciept',
                'reprint reciept',
                'shift details',
                'order item discount',
                'view material stock',
                'view material history',
            ],
            'Manager' => [
                'start shift',
                'end shift',
                'create category',
                'create product',
                'create user',
                'create material',
                'create table',
                'edit table',
                'edit category',
                'edit product',
                'edit user',
                'edit material',
                'delete table',
                'delete category',
                'delete product',
                'delete user',
                'delete material',
                'place order',
                'reprint bill',
                'edit order',
                'cancel order',
                'split order',
                'merge order',
                'make discount',
                'remove discount',
                'create discount',
                'edit discount',
                'delete discount',
                'old reciept',
                'reprint reciept',
                'shift details',
                'order item discount',
                'view material stock',
                'view material history',
            ],
            'Accountant' => [
                'start shift',
                'end shift',
                'create category',
                'create product',
                'create material',
                'create table',
                'edit table',
                'edit category',
                'edit product',
                'edit material',
                'delete table',
                'delete category',
                'delete product',
                'delete material',
                'place order',
                'reprint bill',
                'edit order',
                'cancel order',
                'split order',
                'merge order',
                'make discount',
                'remove discount',
                'create discount',
                'edit discount',
                'delete discount',
                'old reciept',
                'reprint reciept',
                'shift details',
                'order item discount',
                'view material stock',
                'update material stock',
                'view material history',
                'create material adjustment',
            ],
            'Waiter' => [],
            'Cashier' => [
                'start shift',
                'place order',
                'reprint bill',
            ],
        ];

        // Create each role and assign the respective permissions
        foreach ($roles as $role => $rolePermissions) {
            $roleInstance = Role::firstOrCreate(['name' => $role]);
            $roleInstance->syncPermissions($rolePermissions);
        }
    }
}
