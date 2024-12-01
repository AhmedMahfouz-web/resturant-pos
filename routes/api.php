<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('login', [AuthController::class, 'login']);

Route::group(['middleware' => 'jwt'], function () {

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);              // Get all users
        Route::get('/create', [UserController::class, 'createUser']);   // Create a new user
        Route::post('/', [UserController::class, 'storeUser']);         // Create a new user
        Route::get('/{id}', [UserController::class, 'editUser']);       // Edit a user
        Route::post('/{id}', [UserController::class, 'updateUser']);     // Update user details
        Route::delete('/{id}', [UserController::class, 'deleteUser']);  // Delete a user
    });

    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);                // Get all roles
        Route::get('/create', [RoleController::class, 'createRole']);     // Create a new role
        Route::post('/', [RoleController::class, 'storeRole']);           // Create a new role
        Route::get('/{id}', [RoleController::class, 'editRole']);         // Update role details
        Route::post('/{id}', [RoleController::class, 'updateRole']);       // Update role details
        Route::delete('/{id}', [RoleController::class, 'deleteRole']);    // Delete a role
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);                 // Get all products
        Route::get('/show/{id}', [ProductController::class, 'show']);         // Get specific product
        Route::get('/create', [ProductController::class, 'createProduct']);   // Create a new product
        Route::post('/', [ProductController::class, 'storeProduct']);         // Create a new product
        Route::get('/{id}', [ProductController::class, 'editProduct']);       // Update a product
        Route::post('/{id}', [ProductController::class, 'updateProduct']);     // Update a product
        Route::delete('/{id}', [ProductController::class, 'deleteProduct']);  // Delete a product
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);                      // Get all categories
        Route::post('/', [CategoryController::class, 'storeCategory']);             // Create a new category
        Route::post('/create', [CategoryController::class, 'createCategory']);      // Create a new category
        Route::post('/{id}', [CategoryController::class, 'updateCategory']);         // Update a category
        Route::delete('/{id}', [CategoryController::class, 'deleteCategory']);      // Delete a category
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);                    // Get all orders
        Route::get('/live', [OrderController::class, 'liveOrders']);           // Get live orders
        Route::get('/completed', [OrderController::class, 'completedOrder']);  // Get completed orders
        Route::get('/canceled', [OrderController::class, 'canceledOrder']);    // Get canceled orders
        Route::get('/show/{id}', [OrderController::class, 'show']);            // Show Specific order
        Route::post('/', [OrderController::class, 'createOrder']);             // Create a new order
        Route::post('/{id}/discount', [OrderController::class, 'discount']);   // Update an order
        Route::get('/{id}/discount', [OrderController::class, 'cancelDiscount']);    // Update an order
        Route::put('/{id}', [OrderController::class, 'updateOrder']);                // Update an order
        Route::post('/{id}/cancel', [OrderController::class, 'cancelOrder']);       // Cancel an order
        Route::post('/{id}/split', [OrderController::class, 'splitOrder']);         // Split order
    });

    Route::prefix('orderItem')->group(function () {
        Route::get('/', [OrderItemController::class, 'index']);                     // Get all orderItems
        Route::post('/', [OrderItemController::class, 'create']);                   // Create a new orderItem
        Route::put('/{id}', [OrderItemController::class, 'update']);                // Update an orderItem
        Route::put('/{id}/add_note', [OrderItemController::class, 'add_note']);     // Note an orderItem
        Route::delete('/{id}', [OrderItemController::class, 'destroy']);            // Delete an orderItem
        Route::post('/{id}/discount', [OrderItemController::class, 'discount']);    // Discount on orderItem
    });

    Route::prefix('tables')->group(function () {
        Route::get('/', [TableController::class, 'index']);               // Get all tables
        Route::post('/', [TableController::class, 'createTable']);        // Create a new table
        Route::get('/{id}', [TableController::class, 'editTable']);       // Edit a table
        Route::put('/{id}', [TableController::class, 'updateTable']);     // Update a table
        Route::delete('/{id}', [TableController::class, 'deleteTable']);  // Delete a table
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);               // Get all transactions
        Route::post('/', [TransactionController::class, 'createTransaction']);  // Create a new transaction
        Route::get('/{id}', [TransactionController::class, 'showTransaction']); // Show a specific transaction
    });

    Route::prefix('materials')->group(function () {
        Route::get('/', [MaterialController::class, 'index']);
        Route::get('/{id}', [MaterialController::class, 'show']);
        Route::post('', [MaterialController::class, 'store']);
        Route::put('/{id}', [MaterialController::class, 'update']);
        Route::delete('/{id}', [MaterialController::class, 'destroy']);
    });

    Route::prefix('recipes')->group(function () {
        Route::get('', [RecipeController::class, 'index']);
        Route::get('/show/{id}', [RecipeController::class, 'show']);
        Route::get('create', [RecipeController::class, 'create']);
        Route::post('', [RecipeController::class, 'store']);
        Route::get('/{id}', [RecipeController::class, 'edit']);
        Route::put('/{id}', [RecipeController::class, 'update']);
        Route::delete('/{id}', [RecipeController::class, 'destroy']);
    });

    Route::prefix('payment')->group(function () {
        Route::post('/', [PaymentController::class, 'create']);
    });

    Route::prefix('permission')->group(function () {
        Route::post('/', [RoleController::class, 'checkPermission']);
    });

    Route::prefix('shift')->group(function () {
        Route::get('{shiftId}/details', [ShiftController::class, 'getShiftDetails']);
        Route::post('{shiftId}/close', [ShiftController::class, 'closeShift']);
    });

    Route::get('/reports/sales', [ReportController::class, 'salesReport']);
    Route::get('/reports/inventory', [ReportController::class, 'inventoryReport']);
    Route::get('/reports/user-activity', [ReportController::class, 'userActivityReport']);
});
