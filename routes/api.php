<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
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
        Route::post('/', [UserController::class, 'createUser']);        // Create a new user
        Route::put('/{id}', [UserController::class, 'updateUser']);     // Update user details
        Route::delete('/{id}', [UserController::class, 'deleteUser']);  // Delete a user
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);               // Get all products
        Route::post('/', [ProductController::class, 'createProduct']);       // Create a new product
        Route::put('/{id}', [ProductController::class, 'updateProduct']);    // Update a product
        Route::delete('/{id}', [ProductController::class, 'deleteProduct']); // Delete a product
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);               // Get all categories
        Route::post('/', [CategoryController::class, 'createCategory']);     // Create a new category
        Route::put('/{id}', [CategoryController::class, 'updateCategory']);  // Update a category
        Route::delete('/{id}', [CategoryController::class, 'deleteCategory']); // Delete a category
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);               // Get all orders
        Route::get('/live', [OrderController::class, 'liveOrders']);               // Get live orders
        Route::post('/', [OrderController::class, 'createOrder']);        // Create a new order
        Route::put('/{id}', [OrderController::class, 'updateOrder']);     // Update an order
        Route::delete('/{id}', [OrderController::class, 'deleteOrder']);  // Delete an order
    });

    Route::prefix('orderItem')->group(function () {
        Route::get('/', [OrderItemController::class, 'index']);               // Get all orders
        Route::post('/', [OrderItemController::class, 'create']);        // Create a new order
        Route::put('/{id}', [OrderItemController::class, 'update']);     // Update an order
        Route::delete('/{id}', [OrderItemController::class, 'destroy']);  // Delete an order
    });

    Route::prefix('tables')->group(function () {
        Route::get('/', [TableController::class, 'index']);               // Get all tables
        Route::post('/', [TableController::class, 'createTable']);        // Create a new table
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
        Route::get('/{id}', [RecipeController::class, 'show']);
        Route::post('', [RecipeController::class, 'store']);
        Route::put('/{id}', [RecipeController::class, 'update']);
        Route::delete('/{id}', [RecipeController::class, 'destroy']);
    });
});
