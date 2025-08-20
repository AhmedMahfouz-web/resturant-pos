/**
 * Real-time Inventory Management WebSocket Client
 *
 * This script handles WebSocket connections for real-time inventory updates
 * using Laravel Echo with Pusher.
 */

class InventoryRealtimeClient {
    constructor(options = {}) {
        this.options = {
            hot || '127.0.0.1',
            port: options.port || 6001,
            key: options.key || '12345',
            cluster: options.cluster || 'mt1',
            encrypted: options.encrypted || false,
            ...options
        };

        this.echo = null;
        this.channels = {};
        this.callbacks = {};
        this.isConnected = false;

        this.init();
    }

    /**
     * Initialize the WebSocket connection
     */
    init() {
        try {
            // Initialize Laravel Echo with Pusher
            this.echo = new Echo({
                broadcaster: 'pusher',
                key: this.options.key,
                cluster: this.options.cluster,
                encrypted: this.options.encrypted,
                wsHost: this.options.host,
                wsPort: this.options.port,
                wssPort: this.options.port,
                forceTLS: false,
                enabledTransports: ['ws', 'wss'],
                disableStats: true
            });

            this.setupConnectionHandlers();
            this.subscribeToChannels();

            console.log('Inventory WebSocket client initialized');
        } catch (error) {
            console.error('Failed to initialize WebSocket client:', error);
        }
    }

    /**
     * Setup connection event handlers
     */
    setupConnectionHandlers() {
        // Connection established
        this.echo.connector.pusher.connection.bind('connected', () => {
            this.isConnected = true;
            console.log('WebSocket connected');
            this.trigger('connected');
        });

        // Connection lost
        this.echo.connector.pusher.connection.bind('disconnected', () => {
            this.isConnected = false;
            console.log('WebSocket disconnected');
            this.trigger('disconnected');
        });

        // Connection error
        this.echo.connector.pusher.connection.bind('error', (error) => {
            console.error('WebSocket error:', error);
            this.trigger('error', error);
        });
    }

    /**
     * Subscribe to inventory channels
     */
    subscribeToChannels() {
        // General inventory updates
        this.subscribeToInventory();

        // Stock alerts
        this.subscribeToStockAlerts();

        // Recipe cost updates
        this.subscribeToRecipeCosts();

        // Dashboard updates
        this.subscribeToDashboard();

        // Order inventory processing
        this.subscribeToOrders();
    }

    /**
     * Subscribe to general inventory updates
     */
    subscribeToInventory() {
        this.channels.inventory = this.echo.channel('inventory')
            .listen('inventory.updated', (data) => {
                console.log('Inventory updated:', data);
                this.trigger('inventory.updated', data);

                // Update UI elements
                this.updateMaterialDisplay(data);
            })
            .listen('dashboard.updated', (data) => {
                console.log('Dashboard updated:', data);
                this.trigger('dashboard.updated', data);

                // Update dashboard
                this.updateDashboard(data);
            });
    }

    /**
     * Subscribe to stock alerts
     */
    subscribeToStockAlerts() {
        this.channels.stockAlerts = this.echo.channel('stock-alerts')
            .listen('stock-alert.triggered', (data) => {
                console.log('Stock alert triggered:', data);
                this.trigger('stock-alert.triggered', data);

                // Show alert notification
                this.showAlertNotification(data);
            });
    }

    /**
     * Subscribe to recipe cost updates
     */
    subscribeToRecipeCosts() {
        this.channels.recipeCosts = this.echo.channel('recipe-costs')
            .listen('recipe-cost.updated', (data) => {
                console.log('Recipe cost updated:', data);
                this.trigger('recipe-cost.updated', data);

                // Update recipe cost displays
                this.updateRecipeCostDisplay(data);
            });
    }

    /**
     * Subscribe to dashboard updates
     */
    subscribeToDashboard() {
        this.channels.dashboard = this.echo.channel('inventory-dashboard')
            .listen('dashboard.updated', (data) => {
                console.log('Dashboard updated:', data);
                this.trigger('dashboard.updated', data);

                // Update dashboard
                this.updateDashboard(data);
            });
    }

    /**
     * Subscribe to order updates
     */
    subscribeToOrders() {
        this.channels.orders = this.echo.channel('orders')
            .listen('order.inventory-processed', (data) => {
                console.log('Order inventory processed:', data);
                this.trigger('order.inventory-processed', data);

                // Update inventory after order processing
                this.handleOrderInventoryProcessed(data);
            });
    }

    /**
     * Subscribe to specific material updates
     */
    subscribeToMaterial(materialId) {
        const channelName = `inventory.material.${materialId}`;

        if (this.channels[channelName]) {
            return; // Already subscribed
        }

        this.channels[channelName] = this.echo.channel(channelName)
            .listen('inventory.updated', (data) => {
                console.log(`Material ${materialId} updated:`, data);
                this.trigger(`material.${materialId}.updated`, data);

                // Update specific material display
                this.updateMaterialDisplay(data);
            });
    }

    /**
     * Unsubscribe from specific material updates
     */
    unsubscribeFromMaterial(materialId) {
        const channelName = `inventory.material.${materialId}`;

        if (this.channels[channelName]) {
            this.echo.leave(channelName);
            delete this.channels[channelName];
        }
    }

    /**
     * Update material display in UI
     */
    updateMaterialDisplay(data) {
        const materialElement = document.querySelector(`[data-material-id="${data.material_id}"]`);
        if (materialElement) {
            // Update quantity
            const quantityElement = materialElement.querySelector('.material-quantity');
            if (quantityElement) {
                quantityElement.textContent = `${data.new_quantity} ${data.stock_unit}`;

                // Add visual feedback for changes
                quantityElement.classList.add('updated');
                setTimeout(() => quantityElement.classList.remove('updated'), 2000);
            }

            // Update low stock indicator
            const lowStockIndicator = materialElement.querySelector('.low-stock-indicator');
            if (lowStockIndicator) {
                lowStockIndicator.style.display = data.is_low_stock ? 'block' : 'none';
            }

            // Update last updated timestamp
            const timestampElement = materialElement.querySelector('.last-updated');
            if (timestampElement) {
                timestampElement.textContent = new Date(data.timestamp).toLocaleString();
            }
        }
    }

    /**
     * Show alert notification
     */
    showAlertNotification(data) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert-notification alert-${data.alert_type}`;
        notification.innerHTML = `
            <div class="alert-content">
                <strong>${data.material_name}</strong>
                <p>${data.message}</p>
                <small>${new Date(data.created_at).toLocaleString()}</small>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;

        // Add to notifications container
        const container = document.querySelector('.notifications-container') || document.body;
        container.appendChild(notification);

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);

        // Play notification sound (if enabled)
        this.playNotificationSound(data.alert_type);
    }

    /**
     * Update recipe cost display
     */
    updateRecipeCostDisplay(data) {
        const recipeElement = document.querySelector(`[data-recipe-id="${data.recipe_id}"]`);
        if (recipeElement) {
            const costElement = recipeElement.querySelector('.recipe-cost');
            if (costElement) {
                costElement.textContent = `$${data.total_cost.toFixed(2)}`;

                // Show cost change indicator
                if (data.cost_change !== null) {
                    const changeElement = recipeElement.querySelector('.cost-change');
                    if (changeElement) {
                        const changeText = data.cost_change > 0 ? `+$${data.cost_change.toFixed(2)}` : `-$${Math.abs(data.cost_change).toFixed(2)}`;
                        const changeClass = data.cost_change > 0 ? 'cost-increase' : 'cost-decrease';

                        changeElement.textContent = changeText;
                        changeElement.className = `cost-change ${changeClass}`;
                    }
                }
            }
        }
    }

    /**
     * Update dashboard
     */
    updateDashboard(data) {
        if (data.dashboard_data) {
            // Update summary statistics
            if (data.dashboard_data.summary) {
                this.updateDashboardSummary(data.dashboard_data.summary);
            }

            // Update recent alerts
            if (data.dashboard_data.recent_alerts) {
                this.updateRecentAlerts(data.dashboard_data.recent_alerts);
            }

            // Update low stock materials
            if (data.dashboard_data.low_stock_materials) {
                this.updateLowStockMaterials(data.dashboard_data.low_stock_materials);
            }
        }
    }

    /**
     * Update dashboard summary
     */
    updateDashboardSummary(summary) {
        Object.keys(summary).forEach(key => {
            const element = document.querySelector(`[data-summary="${key}"]`);
            if (element) {
                element.textContent = summary[key];
            }
        });
    }

    /**
     * Update recent alerts list
     */
    updateRecentAlerts(alerts) {
        const alertsContainer = document.querySelector('.recent-alerts-list');
        if (alertsContainer) {
            alertsContainer.innerHTML = alerts.map(alert => `
                <div class="alert-item alert-${alert.alert_type}">
                    <strong>${alert.material_name}</strong>
                    <p>${alert.message}</p>
                    <small>${new Date(alert.created_at).toLocaleString()}</small>
                </div>
            `).join('');
        }
    }

    /**
     * Update low stock materials list
     */
    updateLowStockMaterials(materials) {
        const materialsContainer = document.querySelector('.low-stock-materials-list');
        if (materialsContainer) {
            materialsContainer.innerHTML = materials.map(material => `
                <div class="material-item">
                    <strong>${material.name}</strong>
                    <span class="quantity">${material.quantity} ${material.stock_unit}</span>
                    <span class="reorder-point">Reorder at: ${material.reorder_point}</span>
                </div>
            `).join('');
        }
    }

    /**
     * Handle order inventory processed
     */
    handleOrderInventoryProcessed(data) {
        // Show order processing notification
        const notification = document.createElement('div');
        notification.className = 'order-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <strong>Order ${data.order_code} Processed</strong>
                <p>Inventory updated for ${data.consumption_summary.materials_affected} materials</p>
                <small>Total cost: $${data.consumption_summary.total_cost.toFixed(2)}</small>
            </div>
        `;

        const container = document.querySelector('.notifications-container') || document.body;
        container.appendChild(notification);

        setTimeout(() => notification.remove(), 5000);
    }

    /**
     * Play notification sound
     */
    playNotificationSound(alertType) {
        if (this.options.enableSounds !== false) {
            const audio = new Audio(`/sounds/alert-${alertType}.mp3`);
            audio.volume = 0.3;
            audio.play().catch(() => {
                // Ignore audio play errors (user interaction required)
            });
        }
    }

    /**
     * Register event callback
     */
    on(event, callback) {
        if (!this.callbacks[event]) {
            this.callbacks[event] = [];
        }
        this.callbacks[event].push(callback);
    }

    /**
     * Trigger event callbacks
     */
    trigger(event, data = null) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => callback(data));
        }
    }

    /**
     * Get connection status
     */
    isConnected() {
        return this.isConnected;
    }

    /**
     * Disconnect from WebSocket
     */
    disconnect() {
        if (this.echo) {
            this.echo.disconnect();
        }
    }
}

// Initialize the client when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize inventory real-time client
    window.inventoryClient = new InventoryRealtimeClient({
        enableSounds: true
    });

    // Example usage:

    // Listen for inventory updates
    window.inventoryClient.on('inventory.updated', function(data) {
        console.log('Inventory updated:', data);
        // Custom handling here
    });

    // Listen for stock alerts
    window.inventoryClient.on('stock-alert.triggered', function(data) {
        console.log('Stock alert:', data);
        // Custom alert handling here
    });

    // Subscribe to specific material updates (example)
    // window.inventoryClient.subscribeToMaterial(123);
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = InventoryRealtimeClient;
}
st: opt
