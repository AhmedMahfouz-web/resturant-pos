<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Inventory Management Channels
Broadcast::channel('inventory', function ($user) {
    // Allow all authenticated users to listen to general inventory updates
    return $user !== null;
});

Broadcast::channel('inventory.material.{materialId}', function ($user, $materialId) {
    // Allow authenticated users to listen to specific material updates
    return $user !== null;
});

Broadcast::channel('stock-alerts', function ($user) {
    // Allow all authenticated users to listen to stock alerts
    return $user !== null;
});

Broadcast::channel('recipe-costs', function ($user) {
    // Allow authenticated users to listen to recipe cost updates
    return $user !== null;
});

Broadcast::channel('recipe-costs.recipe.{recipeId}', function ($user, $recipeId) {
    // Allow authenticated users to listen to specific recipe cost updates
    return $user !== null;
});

Broadcast::channel('orders', function ($user) {
    // Allow authenticated users to listen to order updates
    return $user !== null;
});

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    // Allow authenticated users to listen to specific order updates
    return $user !== null;
});

Broadcast::channel('inventory-dashboard', function ($user) {
    // Allow authenticated users to listen to dashboard updates
    return $user !== null;
});
