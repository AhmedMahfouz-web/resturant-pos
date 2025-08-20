# Restaurant Management System - Project Overview

## System Architecture & Purpose

This is a comprehensive **Restaurant Point of Sale (POS) and Management System** built with Laravel PHP backend and designed for full-service restaurants, cafes, and food service establishments. The system handles the complete restaurant operation lifecycle from order taking to financial reporting.

## Core Business Domains

### 1. Order Management System

**Purpose**: Complete order lifecycle management from creation to completion
**Key Components**:

-   **Order States**: `live` → `completed` | `canceled`
-   **Order Types**: `dine-in` (table-based) | `takeaway`
-   **Order Operations**: Create, Update, Split, Cancel, Apply Discounts
-   **Order Tracking**: Real-time status updates with WebSocket support

**Critical Business Rules**:

-   Orders must be associated with a shift
-   Table orders require table assignment
-   Order totals include: `sub_total + tax + service - discounts`
-   Material consumption is tracked automatically on order completion

### 2. Inventory & Recipe Management

**Purpose**: Cost control through ingredient tracking and recipe-based consumption
**Key Components**:

-   **Materials**: Raw ingredients with stock tracking
-   **Recipes**: Product-to-material mappings with quantities
-   **Stock Units vs Recipe Units**: Conversion rate system for different measurement units
-   **FIFO Costing**: First-in-first-out inventory valuation

**Critical Business Rules**:

-   Each product can have one recipe
-   Materials are automatically decremented when orders complete
-   Stock conversions: `stock_unit` → `recipe_unit` via `conversion_rate`
-   Inventory transactions track all material movements

### 3. Financial & Shift Management

**Purpose**: Operational control and financial accountability
**Key Components**:

-   **Shifts**: Work periods with start/end times and financial reconciliation
-   **Payments**: Multiple payment methods with change calculation
-   **Discounts**: Percentage, cash, or saved discount types
-   **Tax & Service**: Configurable rates based on order type

**Critical Business Rules**:

-   Only one active shift at a time
-   All orders must belong to a shift
-   Shift closure requires all orders to be completed
-   Payment amount must equal or exceed order total

### 4. User Management & Permissions

**Purpose**: Role-based access control for different staff levels
**Key Components**:

-   **JWT Authentication**: Token-based auth with blacklist support
-   **Role-Based Permissions**: Granular permission system
-   **User Sessions**: Shift-based user tracking

**Critical Permissions**:

-   `start shift`: Can open new shifts
-   `old receipt`: Can view historical orders beyond current shift

## Data Flow & Relationships

### Order Processing Flow

```
1. User creates order → Order (status: live)
2. Add products → OrderItems created
3. Calculate totals → sub_total, tax, service computed
4. Apply discounts → discount calculations
5. Process payment → Payment record created
6. Complete order → status: completed, materials decremented
```

### Inventory Flow

```
1. Materials imported/created → Stock levels set
2. Recipes created → Product-Material relationships
3. Order completed → Material quantities decremented via recipe
4. Inventory transactions logged → FIFO cost tracking
```

### Shift Flow

```
1. User starts shift → Shift record created
2. Orders processed → Associated with active shift
3. Payments collected → Tracked per shift
4. Shift closed → Financial reconciliation, all users logged out
```

## Key Technical Components

### Controllers & Responsibilities

-   **OrderController**: Order CRUD, discounts, splitting, status management
-   **MaterialController**: Inventory management, Excel import, stock tracking
-   **RecipeController**: Recipe CRUD, material relationships
-   **PaymentController**: Payment processing, change calculation
-   **ShiftController**: Shift lifecycle, financial reporting
-   **ProductController**: Menu management, categorization
-   **TableController**: Seating management for dine-in orders

### Models & Key Relationships

-   **Order** → hasMany(OrderItem), belongsTo(Shift, Table, User)
-   **Product** → belongsTo(Category), hasOne(Recipe)
-   **Recipe** → belongsToMany(Material) with pivot(material_quantity)
-   **Material** → hasMany(InventoryTransaction)
-   **Shift** → hasMany(Order), belongsTo(User)

### Important Database Fields

#### Orders Table

-   `status`: live, completed, canceled
-   `type`: dine-in, takeaway
-   `sub_total`, `tax`, `service`, `discount_value`, `total_amount`
-   `shift_id`: Links to active shift
-   `table_id`: For dine-in orders

#### Materials Table

-   `stock_unit`: How material is purchased/stored (kg, liters)
-   `recipe_unit`: How material is used in recipes (grams, ml)
-   `conversion_rate`: Conversion between units
-   `quantity`: Current stock level

#### Recipes Table (Pivot: material_recipe)

-   `material_quantity`: Amount of material needed per product unit

## Business Logic Helpers

### Helper Functions (app/Helpers/helpers.php)

-   `calculate_discount()`: Handles percentage, cash, saved discounts
-   `calculate_tax_service()`: Computes tax and service based on order type
-   `updateOrderTotals()`: Recalculates order totals after changes

### Jobs & Background Processing

-   **DecrementMaterials**: Async material consumption processing
-   **WebSocket Events**: Real-time order updates

## Import/Export Capabilities

### Excel Integration

-   **Materials Import**: Bulk material creation with validation
-   **Products Import**: Menu item bulk creation
-   **Recipes Import**: Recipe-material relationship import

**Required Headers**:

-   Materials: `name, current_stock, stock_unit, recipe_unit, conversion_rate`
-   Products: `name, description, price, category_id, image, discount_type, discount, tax`
-   Recipes: `product_id, material_id, material_quantity`

## Reporting & Analytics

### Dashboard Metrics

-   Total sales, orders, canceled orders
-   Average order value, unique customers
-   Top-selling products, daily sales trends
-   Payment method breakdown, inventory levels

### Detailed Reports

-   Sales reports with filtering
-   Inventory turnover analysis
-   User activity tracking
-   Monthly cost analysis
-   Product cost comparison

## Security & Authentication

### JWT Implementation

-   Token-based authentication with refresh capability
-   Token blacklisting on shift closure
-   Middleware: `jwt`, `check.token.blacklist`

### Permission System

-   Role-based access control via Spatie Laravel Permission
-   Granular permissions for different operations
-   Shift-based data isolation for non-privileged users

## API Structure

### RESTful Endpoints

-   All endpoints under `/api/` prefix
-   JWT middleware protection (except login)
-   JSON responses with consistent error handling
-   Pagination for large datasets (orders, reports)

### Key Route Groups

-   `/orders`: Order management operations
-   `/materials`: Inventory management
-   `/recipes`: Recipe CRUD operations
-   `/payment`: Payment processing
-   `/shift`: Shift management
-   `/reports`: Analytics and reporting
-   `/dashboard`: Real-time metrics

## Development Notes

### Dependencies

-   **Laravel 10**: PHP framework
-   **JWT Auth**: Authentication system
-   **Laravel Permission**: Role management
-   **Laravel WebSockets**: Real-time updates
-   **Maatwebsite Excel**: Import/export functionality
-   **Pusher**: WebSocket server

### Database Considerations

-   Uses pivot tables for many-to-many relationships
-   Soft deletes not implemented (hard deletes used)
-   Timestamps tracked on all major entities
-   Foreign key constraints for data integrity

### Performance Optimizations

-   Eager loading for related models
-   Query optimization with select statements
-   Pagination for large result sets
-   Indexed fields for common queries

## Common Patterns & Conventions

### Validation Patterns

-   Request validation in controllers
-   Custom validation for business rules
-   File upload validation for imports

### Response Patterns

-   Consistent JSON response structure
-   HTTP status codes following REST conventions
-   Error messages with validation details

### Naming Conventions

-   Snake_case for database fields
-   CamelCase for model relationships
-   Kebab-case for route parameters

This documentation serves as the foundation for understanding the system architecture and business logic when making modifications or enhancements to the restaurant management system.
