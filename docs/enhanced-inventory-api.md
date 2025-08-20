# Enhanced Inventory Management API Documentation

## Overview

The Enhanced Inventory Management API provides comprehensive endpoints for managing restaurant inventory with advanced features including real-time stock tracking, FIFO cost calculations, batch management, and expiry tracking.

## Base URL

```
/api/inventory/enhanced
```

## Authentication

All endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your-jwt-token}
```

## Endpoints

### 1. Inventory Dashboard

Get comprehensive inventory overview with summary statistics.

**Endpoint:** `GET /dashboard`

**Response:**

```json
{
    "success": true,
    "data": {
        "summary": {
            "total_materials": 150,
            "low_stock_count": 12,
            "out_of_stock_count": 3,
            "active_alerts_count": 8,
            "total_stock_value": 15420.5,
            "expiring_batches_count": 5
        },
        "recent_movements": [
            {
                "id": 1,
                "material_name": "Flour",
                "type": "receipt",
                "quantity": 50,
                "stock_unit": "kg",
                "created_at": "2024-01-15T10:30:00Z"
            }
        ],
        "timestamp": "2024-01-15T10:30:00Z"
    }
}
```

### 2. Materials List

Get materials with enhanced stock information and filtering options.

**Endpoint:** `GET /materials`

**Query Parameters:**

-   `search` (string): Search by name, SKU, or barcode
-   `category_id` (integer): Filter by category
-   `supplier_id` (integer): Filter by supplier
-   `low_stock` (boolean): Show only low stock materials
-   `out_of_stock` (boolean): Show only out of stock materials
-   `per_page` (integer): Items per page (1-100, default: 20)
-   `page` (integer): Page number

**Example Request:**

```
GET /materials?low_stock=true&per_page=10&search=flour
```

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "All-Purpose Flour",
                "sku": "FLR-001",
                "barcode": "1234567890123",
                "quantity": 25.5,
                "stock_unit": "kg",
                "recipe_unit": "g",
                "conversion_rate": 1000,
                "purchase_price": 2.5,
                "minimum_stock_level": 50,
                "maximum_stock_level": 200,
                "reorder_point": 30,
                "reorder_quantity": 100,
                "storage_location": "Dry Storage A1",
                "is_perishable": false,
                "shelf_life_days": null,
                "supplier": {
                    "id": 1,
                    "name": "ABC Food Supplies"
                },
                "category": {
                    "id": 1,
                    "name": "Baking Ingredients"
                },
                "current_stock_value": 127.5,
                "is_low_stock": true,
                "is_at_reorder_point": true,
                "is_overstock": false,
                "stock_batches": [
                    {
                        "id": 1,
                        "batch_number": "FLR001-20240115-001",
                        "remaining_quantity": 25.5,
                        "unit_cost": 2.5,
                        "total_value": 63.75,
                        "expiry_date": null,
                        "days_until_expiry": null,
                        "is_expiring": false
                    }
                ],
                "active_alerts": [
                    {
                        "id": 1,
                        "alert_type": "low_stock",
                        "priority": 3,
                        "message": "Low stock alert: All-Purpose Flour is below minimum level",
                        "created_at": "2024-01-15T08:00:00Z"
                    }
                ],
                "updated_at": "2024-01-15T10:30:00Z"
            }
        ],
        "per_page": 10,
        "total": 1
    }
}
```

### 3. Stock Adjustment

Create stock adjustments with audit trail.

**Endpoint:** `POST /adjustments`

**Request Body:**

```json
{
    "material_id": 1,
    "adjustment_type": "increase",
    "quantity": 50,
    "reason": "New stock delivery",
    "unit_cost": 2.75,
    "notes": "Received from ABC Food Supplies"
}
```

**Parameters:**

-   `material_id` (integer, required): Material ID
-   `adjustment_type` (string, required): "increase", "decrease", or "set"
-   `quantity` (number, required): Adjustment quantity
-   `reason` (string, required): Reason for adjustment
-   `unit_cost` (number, optional): Unit cost for the adjustment
-   `notes` (string, optional): Additional notes

**Response:**

```json
{
    "success": true,
    "message": "Stock adjustment completed successfully",
    "data": {
        "transaction_id": 123,
        "material_id": 1,
        "old_quantity": 25.5,
        "new_quantity": 75.5,
        "adjustment_quantity": 50
    }
}
```

### 4. Inventory Valuation

Get current inventory valuation using FIFO methodology.

**Endpoint:** `GET /valuation`

**Response:**

```json
{
    "success": true,
    "data": {
        "materials": [
            {
                "material_id": 1,
                "material_name": "All-Purpose Flour",
                "quantity": 75.5,
                "stock_unit": "kg",
                "average_cost": 2.62,
                "total_value": 197.81,
                "purchase_price": 2.5,
                "batches_count": 2
            }
        ],
        "summary": {
            "total_materials": 150,
            "total_value": 15420.5,
            "average_value_per_material": 102.8
        },
        "generated_at": "2024-01-15T10:30:00Z"
    }
}
```

### 5. Movement History

Get inventory movement history with filtering.

**Endpoint:** `GET /movements`

**Query Parameters:**

-   `material_id` (integer): Filter by material
-   `type` (string): Filter by transaction type
-   `date_from` (date): Start date filter
-   `date_to` (date): End date filter
-   `user_id` (integer): Filter by user
-   `per_page` (integer): Items per page

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 123,
                "material_id": 1,
                "material_name": "All-Purpose Flour",
                "type": "adjustment",
                "quantity": 50,
                "stock_unit": "kg",
                "unit_cost": 2.75,
                "total_cost": 137.5,
                "user_id": 1,
                "notes": "New stock delivery - Received from ABC Food Supplies",
                "created_at": "2024-01-15T10:30:00Z"
            }
        ],
        "per_page": 50,
        "total": 1
    }
}
```

### 6. Stock Batches

Get stock batches with filtering options.

**Endpoint:** `GET /batches`

**Query Parameters:**

-   `material_id` (integer): Filter by material
-   `supplier_id` (integer): Filter by supplier
-   `expiring_within_days` (integer): Show batches expiring within X days
-   `expired` (boolean): Show only expired batches
-   `available_only` (boolean): Show only available batches
-   `per_page` (integer): Items per page

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "batch_number": "FLR001-20240115-001",
                "material_id": 1,
                "material_name": "All-Purpose Flour",
                "supplier_name": "ABC Food Supplies",
                "quantity": 50,
                "remaining_quantity": 25.5,
                "stock_unit": "kg",
                "unit_cost": 2.5,
                "total_value": 63.75,
                "usage_percentage": 49,
                "received_date": "2024-01-15",
                "expiry_date": null,
                "days_until_expiry": null,
                "is_expired": false,
                "is_expiring": false,
                "is_available": true
            }
        ],
        "per_page": 50,
        "total": 1
    }
}
```

### 7. Material Batches

Get batches for a specific material.

**Endpoint:** `GET /materials/{materialId}/batches`

**Response:**

```json
{
    "success": true,
    "data": {
        "material": {
            "id": 1,
            "name": "All-Purpose Flour",
            "current_quantity": 75.5,
            "stock_unit": "kg"
        },
        "batches": [
            {
                "id": 1,
                "batch_number": "FLR001-20240115-001",
                "supplier_name": "ABC Food Supplies",
                "quantity": 50,
                "remaining_quantity": 25.5,
                "unit_cost": 2.5,
                "total_value": 63.75,
                "usage_percentage": 49,
                "received_date": "2024-01-15",
                "expiry_date": null,
                "days_until_expiry": null,
                "is_expired": false,
                "is_expiring": false,
                "is_available": true
            }
        ],
        "summary": {
            "total_batches": 2,
            "available_batches": 2,
            "expired_batches": 0,
            "expiring_batches": 0,
            "total_value": 197.81
        }
    }
}
```

### 8. Expiry Tracking

Get expiry tracking information for perishable items.

**Endpoint:** `GET /expiry-tracking`

**Query Parameters:**

-   `days` (integer): Look ahead days (default: 30)

**Response:**

```json
{
    "success": true,
    "data": {
        "expiring_batches": [
            {
                "id": 15,
                "batch_number": "MLK001-20240110-001",
                "material_name": "Fresh Milk",
                "supplier_name": "Dairy Farm Co",
                "remaining_quantity": 10,
                "stock_unit": "L",
                "total_value": 45.0,
                "expiry_date": "2024-01-17",
                "days_until_expiry": 2,
                "urgency_level": "critical"
            }
        ],
        "expired_batches": [
            {
                "id": 12,
                "batch_number": "VEG001-20240105-001",
                "material_name": "Fresh Tomatoes",
                "remaining_quantity": 5,
                "stock_unit": "kg",
                "total_value": 25.0,
                "expiry_date": "2024-01-12",
                "days_expired": 3
            }
        ],
        "summary": {
            "total_expiring": 5,
            "total_expired": 2,
            "value_at_risk": 225.0,
            "expired_value": 75.0,
            "by_urgency": {
                "critical": 2,
                "high": 2,
                "medium": 1,
                "low": 0
            }
        }
    }
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description",
    "error": "Detailed error message",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

## HTTP Status Codes

-   `200` - Success
-   `201` - Created
-   `400` - Bad Request
-   `401` - Unauthorized
-   `403` - Forbidden
-   `404` - Not Found
-   `422` - Validation Error
-   `500` - Internal Server Error

## Rate Limiting

API endpoints are rate limited to 60 requests per minute per user.

## Pagination

List endpoints support pagination with the following parameters:

-   `page` - Page number (default: 1)
-   `per_page` - Items per page (default: 20, max: 100)

Pagination response includes:

-   `current_page` - Current page number
-   `per_page` - Items per page
-   `total` - Total number of items
-   `last_page` - Last page number
-   `from` - First item number on current page
-   `to` - Last item number on current page

## Real-time Updates

The API integrates with WebSocket broadcasting for real-time inventory updates. Subscribe to the following channels:

-   `inventory` - General inventory updates
-   `inventory.material.{id}` - Specific material updates
-   `stock-alerts` - Stock alert notifications
-   `inventory-dashboard` - Dashboard updates

## Best Practices

1. **Filtering**: Use appropriate filters to reduce response size
2. **Pagination**: Always use pagination for large datasets
3. **Caching**: Cache frequently accessed data on the client side
4. **Error Handling**: Always handle error responses appropriately
5. **Real-time**: Subscribe to WebSocket channels for live updates
6. **Permissions**: Ensure users have appropriate permissions before making requests
