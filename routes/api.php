<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialReceiptController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeCostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockAlertController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\WebSocketController;
use App\Http\Controllers\InventoryDashboardController;
use App\Http\Controllers\EnhancedInventoryController;
use App\Http\Controllers\SupplierPerformanceController;
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

Route::middleware(['jwt', 'check.token.blacklist'])->group(function () {

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
        Route::post('/import', [ProductController::class, 'import']);
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
        Route::post('/import', [MaterialController::class, 'import']);
        Route::get('/{id}', [MaterialController::class, 'show']);
        Route::post('', [MaterialController::class, 'store']);
        Route::post('/{id}', [MaterialController::class, 'update']);
        Route::delete('/{id}', [MaterialController::class, 'destroy']);
    });

    Route::prefix('material-receipts')->group(function () {
        Route::get('/', [MaterialReceiptController::class, 'index']);           // Get all material receipts
        Route::get('/create', [MaterialReceiptController::class, 'create']);    // Get form data for creating receipt
        Route::post('/', [MaterialReceiptController::class, 'store']);          // Create new material receipt
        Route::get('/statistics', [MaterialReceiptController::class, 'statistics']); // Get receipt statistics
        Route::get('/{id}', [MaterialReceiptController::class, 'show']);        // Show specific receipt
        Route::get('/{id}/batch', [MaterialReceiptController::class, 'getBatch']); // Get batch info for receipt
        Route::post('/{id}', [MaterialReceiptController::class, 'update']);     // Update receipt (changed to POST for consistency)
        Route::delete('/{id}', [MaterialReceiptController::class, 'destroy']);  // Delete receipt
    });

    Route::prefix('recipes')->group(function () {
        Route::get('', [RecipeController::class, 'index']);
        Route::get('/show/{id}', [RecipeController::class, 'show']);
        Route::get('create', [RecipeController::class, 'create']);
        Route::post('', [RecipeController::class, 'store']);
        Route::get('/{id}', [RecipeController::class, 'edit']);
        Route::post('/{id}', [RecipeController::class, 'update']);
        Route::delete('/{id}', [RecipeController::class, 'destroy']);
        Route::post('/import', [RecipeController::class, 'import']);
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

    Route::prefix('dashboard')->group(function () {
        Route::get('/sales', [ReportController::class, 'totalSalesThisMonth']);
        Route::get('/orders', [ReportController::class, 'totalOrdersThisMonth']);
        Route::get('/canceled-orders', [ReportController::class, 'totalCanceledOrders']);
        Route::get('/average-order-value', [ReportController::class, 'averageOrderValue']);
        Route::get('/unique-customers', [ReportController::class, 'uniqueCustomerCount']);
        Route::get('/top-selling-products', [ReportController::class, 'topSellingProducts']);
        Route::get('/daily-sales-trend', [ReportController::class, 'dailySalesTrend']);
        Route::get('/payment-method-breakdown', [ReportController::class, 'paymentMethodBreakdown']);
        Route::get('/inventory-levels', [ReportController::class, 'inventoryLevels']);
        Route::get('/user-engagement', [ReportController::class, 'userEngagementMetrics']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'salesReport']);
        Route::get('/inventory', [ReportController::class, 'inventoryReport']);
        Route::get('/user-activity', [ReportController::class, 'userActivityReport']);
        Route::get('/monthly', [ReportController::class, 'monthlyReport']);
        Route::get('/top-selling-products', [ReportController::class, 'sellingProducts']);
        Route::get('/customer-purchase-history', [ReportController::class, 'customerPurchaseHistory']);
        Route::get('/sales-by-category', [ReportController::class, 'salesByCategory']);
        Route::get('/refunds-and-returns', [ReportController::class, 'refundsAndReturns']);
        Route::get('/monthly-sales-growth', [ReportController::class, 'monthlySalesGrowth']);
        Route::get('/user-engagement', [ReportController::class, 'userEngagement']);
        Route::get('/inventory-turnover', [ReportController::class, 'inventoryTurnover']);
        Route::get('/payment-method-performance', [ReportController::class, 'paymentMethodPerformance']);
        Route::get('/shifts', [ReportController::class, 'getShifts']);
        Route::get('/monthly-cost', [ReportController::class, 'monthlyCost']);
        Route::get('/{id}/cost-analysis', [ReportController::class, 'productCostComparison']); // Product cost analysis

        // Enhanced inventory reporting routes
        Route::get('/types', [ReportController::class, 'reportTypes']);           // Get available report types
        Route::get('/dashboard-summary', [ReportController::class, 'dashboard']); // Get dashboard summary
        Route::get('/stock-valuation', [ReportController::class, 'stockValuation']); // Stock valuation report
        Route::get('/inventory-movement', [ReportController::class, 'inventoryMovement']); // Inventory movement report
        Route::get('/stock-aging', [ReportController::class, 'stockAging']);      // Stock aging report
        Route::get('/waste-tracking', [ReportController::class, 'wasteReport']); // Waste tracking report
        Route::get('/cost-analysis-enhanced', [ReportController::class, 'costAnalysis']); // Enhanced cost analysis report
        Route::get('/profitability', [ReportController::class, 'profitability']); // Profitability report
        Route::post('/export', [ReportController::class, 'exportReport']);        // Export report data
    });

    Route::prefix('inventory')->group(function () {
        Route::post('/receipt', [InventoryController::class, 'storeReceipt']);
        Route::post('/adjust', [InventoryController::class, 'adjustStock']);
        Route::post('/history', [ReportController::class, 'transactionHistory']);
    });

    // Enhanced Inventory Management API Endpoints
    Route::prefix('inventory/enhanced')->group(function () {
        Route::get('/dashboard', [EnhancedInventoryController::class, 'dashboard']); // Inventory overview dashboard
        Route::get('/materials', [EnhancedInventoryController::class, 'materials']); // List materials with stock info
        Route::post('/adjustments', [EnhancedInventoryController::class, 'adjustStock']); // Create stock adjustment
        Route::get('/valuation', [EnhancedInventoryController::class, 'valuation']); // Get inventory valuation
        Route::get('/movements', [EnhancedInventoryController::class, 'movements']); // Get stock movement history
        Route::get('/batches', [EnhancedInventoryController::class, 'batches']); // List stock batches
        Route::get('/materials/{materialId}/batches', [EnhancedInventoryController::class, 'materialBatches']); // Get batches for material
        Route::get('/expiry-tracking', [EnhancedInventoryController::class, 'expiryTracking']); // Expiry tracking endpoints
    });

    Route::prefix('suppliers')->group(function () {
        Route::get('/', [SupplierController::class, 'index']);                    // Get all suppliers
        Route::post('/', [SupplierController::class, 'store']);                   // Create new supplier
        Route::get('/{supplier}', [SupplierController::class, 'show']);           // Show specific supplier
        Route::put('/{supplier}', [SupplierController::class, 'update']);         // Update supplier
        Route::delete('/{supplier}', [SupplierController::class, 'destroy']);     // Delete supplier
        Route::get('/{supplier}/performance', [SupplierController::class, 'performance']); // Get supplier performance
        Route::post('/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']); // Toggle active status
    });

    // Supplier Performance Tracking Routes
    Route::prefix('suppliers/performance')->group(function () {
        Route::get('/comparison', [SupplierPerformanceController::class, 'getPerformanceComparison']); // Compare supplier performance
        Route::post('/bulk-update', [SupplierPerformanceController::class, 'bulkUpdatePerformanceMetrics']); // Bulk update metrics
        Route::get('/{supplier}/metrics', [SupplierPerformanceController::class, 'getPerformanceMetrics']); // Get detailed performance metrics
        Route::post('/{supplier}/metrics/update', [SupplierPerformanceController::class, 'updatePerformanceMetrics']); // Update performance metrics
        Route::get('/{supplier}/delivery', [SupplierPerformanceController::class, 'getDeliveryPerformance']); // Get delivery performance
        Route::get('/{supplier}/communication', [SupplierPerformanceController::class, 'getCommunicationAnalysis']); // Get communication analysis
        Route::post('/{supplier}/communication', [SupplierPerformanceController::class, 'createCommunication']); // Create communication record
        Route::put('/communication/{communication}/response', [SupplierPerformanceController::class, 'updateCommunicationResponse']); // Update communication response
    });

    Route::prefix('stock-alerts')->group(function () {
        Route::get('/', [StockAlertController::class, 'index']);                  // Get all stock alerts
        Route::get('/statistics', [StockAlertController::class, 'statistics']);   // Get alert statistics
        Route::post('/generate', [StockAlertController::class, 'generate']);      // Generate new alerts
        Route::post('/bulk-resolve', [StockAlertController::class, 'bulkResolve']); // Bulk resolve alerts
        Route::post('/cleanup', [StockAlertController::class, 'cleanup']);        // Cleanup old resolved alerts
        Route::get('/{stockAlert}', [StockAlertController::class, 'show']);       // Show specific alert
        Route::post('/{stockAlert}/resolve', [StockAlertController::class, 'resolve']); // Resolve alert
        Route::post('/{stockAlert}/unresolve', [StockAlertController::class, 'unresolve']); // Unresolve alert
    });

    Route::prefix('recipe-costs')->group(function () {
        Route::get('/statistics', [RecipeCostController::class, 'getCostCalculationStatistics']); // Get cost statistics
        Route::post('/update-all', [RecipeCostController::class, 'updateAllProductCosts']); // Update all product costs
        Route::post('/compare', [RecipeCostController::class, 'compareRecipeCosts']); // Compare recipe costs
        Route::post('/analysis-report', [RecipeCostController::class, 'generateCostAnalysisReport']); // Generate cost analysis report
        Route::post('/recipes/{recipe}/calculate', [RecipeCostController::class, 'calculateRecipeCost']); // Calculate recipe cost
        Route::get('/recipes/{recipe}/analysis', [RecipeCostController::class, 'getRecipeCostAnalysis']); // Get recipe cost analysis
        Route::get('/recipes/{recipe}/history', [RecipeCostController::class, 'getRecipeCostHistory']); // Get recipe cost history
        Route::post('/products/{product}/theoretical-vs-actual', [RecipeCostController::class, 'getTheoreticalVsActualCost']); // Get cost comparison
    });

    Route::prefix('websocket')->group(function () {
        Route::get('/dashboard', [WebSocketController::class, 'getDashboardData']);     // Get real-time dashboard data
        Route::post('/dashboard/broadcast', [WebSocketController::class, 'broadcastDashboardUpdate']); // Force dashboard update
        Route::post('/materials/status', [WebSocketController::class, 'getMaterialStatus']); // Get material status
        Route::get('/alerts/active', [WebSocketController::class, 'getActiveAlerts']);  // Get active alerts
        Route::post('/test', [WebSocketController::class, 'testBroadcast']);            // Test WebSocket broadcast
        Route::get('/info', [WebSocketController::class, 'getConnectionInfo']);         // Get connection info
    });

    // Real-time Inventory Dashboard Routes
    Route::prefix('inventory/realtime')->group(function () {
        Route::get('/dashboard', [InventoryDashboardController::class, 'getDashboardData']); // Get real-time dashboard data
        Route::get('/status', [InventoryDashboardController::class, 'getInventoryStatus']); // Get inventory status
        Route::get('/alerts', [InventoryDashboardController::class, 'getActiveAlerts']); // Get active alerts
        Route::get('/expiring-batches', [InventoryDashboardController::class, 'getExpiringBatches']); // Get expiring batches
        Route::get('/movements', [InventoryDashboardController::class, 'getRecentMovements']); // Get recent movements
        Route::get('/materials/{materialId}', [InventoryDashboardController::class, 'getMaterialData']); // Get material-specific data
        Route::post('/broadcast-update', [InventoryDashboardController::class, 'broadcastDashboardUpdate']); // Trigger dashboard update
    });
});
