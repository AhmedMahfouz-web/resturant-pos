# Restaurant POS System API

## Features

- **Order Management**
  - Create/update/delete orders
  - Calculate totals with tax/discounts
  - Multiple payment method support

- **Menu Management**
  - CRUD operations for menu items
  - Category-based organization
  - Inventory tracking

- **Recipe Management**
  - **Formula Configuration**
    - Create multi-ingredient recipes with quantity ratios
    - Track raw material requirements
    - Auto-update inventory on order completion
    - Version control for recipe changes
    - Cost calculation per menu item

- **User Authentication**
  - JWT-based authentication
  - Role-based access control
  - Session management

- **Real-Time Order Updates (WebSocket)**
  - **Order Subscription**
    - Connect via `ws://localhost:6001/ws/orders`
    - Receive instant updates for:
      - New kitchen orders
      - Order status changes
      - Table service requests
  - **Event Types**:
    ```json
    {
      "event": "order_created",
      "data": {
        "order_id": 123,
        "table_number": 5,
        "items": ["Burger", "Fries"]
      }
    }
    ```

- **Reporting**
  - Sales analytics
  - Inventory reports
  - Customer spending patterns

## Technical Architecture

### Core Components
- **Order Pipeline** (OrderController)
  - Endpoint: `POST /api/orders`
  - Inventory auto-deduction
  - WebSocket event: `order_created`

- **WebSocket Server**
  - Port: 6001
  - Auth: JWT cookie validation
  - Event schema:
    ```json
    {
      "event": "order_updated", 
      "data": {
        "id": 123, 
        "status": "preparing",
        "staff_id": 456
      }
    }
    ```

## Installation
```bash
# Install dependencies
composer install
composer require beyondcode/laravel-websockets

# Configure environment
cp .env.example .env
# Edit .env with actual database credentials

# Start services
php -S localhost:8000 -t public
php artisan websockets:serve --port=6001
```

## API Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/orders | Create order with JSON payload:
POST /api/orders
Content-Type: application/json
```json
{
  "table_id": 5,
  "items": [
    {"product_id": 1, "quantity": 2},
    {"product_id": 3, "quantity": 1}
  ]
}
```

| WS | /ws/orders | Real-time order stream |

## Recipe System
- Version history tracking
- Batch cost calculation
- Ingredient ratios:
```php
// Recipe model relationship
public function ingredients()
{
    return $this->belongsToMany(Ingredient::class)->withPivot('quantity');
}
```

## System Architecture

### Key Components
- **Order Pipeline**:
  - OrderController@store (Order creation)
  - Automatic inventory deduction
  - Real-time WebSocket notifications

- **Recipe Engine**:
  - Ingredient ratio calculations
  - Versioned recipe history
  - Batch cost analysis

## Authentication

1. Obtain JWT token:
```bash
curl -X POST /api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret"}'
```

2. WebSocket authentication via cookie-based JWT
3. Role-based access control:
- Manager: Full access
- Staff: Order operations only

## Usage

```bash
# Start development server
php -S localhost:8000 -t public

# Start WebSocket server
php artisan websockets:serve
```

## Configuration

- Set `JWT_SECRET` in .env
- Configure mail settings for notifications
- Adjust tax rates in `config/pos.php`

## License
MIT License
