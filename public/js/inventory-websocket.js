/**
 * Inventory WebSocket Client
 *
 * This JavaScript client demonstrates how to connect to the inventory WebSocket
 * channels and handle real-time updates.
 */

class InventoryWebSocketClient {
    constructor(config = {}) {
        this.config = {
            host: config.host || 'ws://localhost:6001',
            appKey: config.appKey || 'your-app-key',
            cluster: config.cluster || 'mt1',
            encrypted: config.encrypted || false,
            ...config
        };

        this.pusher = null;
        this.channels = {};
        this.eventHandlers = {};

        this.init();
    }

    /**
     * Initialize Pusher connection
     */
    init() {
        if (typeof Pusher === 'undefined') {
            console.error('Pusher library not loaded. Please include Pusher JS library.');
            return;
        }

        this.pusher = new Pusher(this.config.appKey, {
            wsHost: this.config.host.replace('ws://', '').replace('wss://', ''),
            wsPort: 6001,
            wssPort: 6001,
            forceTLS: this.config.encrypted,
            enabledTransports: ['ws', 'wss'],
            disableStats: true
        });

        this.pusher.connection.bind('connected', () => {
            console.log('Connected to WebSocket server');
            this.onConnected();
        });

        this.pusher.connection.bind('disconnected', () => {
            console.log('Disconnected from WebSocket server');
            this.onDisconnected();
        });

        this.pusher.connection.bind('error', (error) => {
            console.error('WebSocket connection error:', error);
            this.onError(error);
        });
    }

    /**
     * Subscribe to inventory updates channel
     */
    subscribeToInventory(callback) {
        const channel = this.pusher.subscribe('inventory');

        channel.bind('inventory.updated', (data) => {
            console.log('Inventory updated:', data);
            if (callback) callback('inventory.updated', data);
            this.trigger('inventory.updated', data);
        });

        channel.bind('stock-alert.triggered', (data) => {
            console.log('Stock alert triggered:', data);
            if (callback) callback('stock-alert.triggered', data);
            this.trigger('stock-alert.triggered', data);
        });

        channel.bind('recipe-cost.updated', (data) => {
            console.log('Recipe cost updated:', data);
            if (callback) callback('recipe-cost.updated', data);
            this.trigger('recipe-cost.updated', data);
        });

        this.channels.inventory = channel;
        return channel;
    }

    /**
     * Subscribe to specific material updates
     */
    subscribeToMaterial(materialId, callback) {
        const channelName = `inventory.material.${materialId}`;
        const channel = this.pusher.subscribe(channelName);

        channel.bind('inventory.updated', (data) => {
            console.log(`Material ${materialId} updated:`, data);
            if (callback) callback('inventory.updated', data);
            this.trigger('material.updated', data);
        });

        this.channels[channelName] = channel;
        return channel;
    }

    /**
     * Subscribe to stock alerts
     */
    subscribeToStockAlerts(callback) {
        const channel = this.pusher.subscribe('stock-alerts');

        channel.bind('stock-alert.triggered', (data) => {
            console.log('Stock alert:', data);
            if (callback) callback('stock-alert.triggered', data);
            this.trigger('stock-alert.triggered', data);

            // Show browser notification if supported
            this.showNotification('Stock Alert', data.message, 'warning');
        });

        this.channels['stock-alerts'] = channel;
        return channel;
    }

    /**
     * Subscribe to recipe cost updates
     */
    subscribeToRecipeCosts(callback) {
        const channel = this.pusher.subscribe('recipe-costs');

        channel.bind('recipe-cost.updated', (data) => {
            console.log('Recipe cost updated:', data);
            if (callback) callback('recipe-cost.updated', data);
            this.trigger('recipe-cost.updated', data);
        });

        this.channels['recipe-costs'] = channel;
        return channel;
    }

    /**
     * Subscribe to order updates
     */
    subscribeToOrders(callback) {
        const channel = this.pusher.subscribe('orders');

        channel.bind('order.inventory-processed', (data) => {
            console.log('Order inventory processed:', data);
            if (callback) callback('order.inventory-processed', data);
            this.trigger('order.inventory-processed', data);
        });

        this.channels.orders = channel;
        return channel;
    }

    /**
     * Subscribe to dashboard updates
     */
    subscribeToDashboard(callback) {
        const channel = this.pusher.subscribe('inventory-dashboard');

        channel.bind('dashboard.updated', (data) => {
            console.log('Dashboard updated:', data);
            if (callback) callback('dashboard.updated', data);
            this.trigger('dashboard.updated', data);
        });

        this.channels['inventory-dashboard'] = channel;
        return channel;
    }

    /**
     * Add event listener
     */
    on(event, callback) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(callback);
    }

    /**
     * Remove event listener
     */
    off(event, callback) {
        if (this.eventHandlers[event]) {
            const index = this.eventHandlers[event].indexOf(callback);
            if (index > -1) {
                this.eventHandlers[event].splice(index, 1);
            }
        }
    }

    /**
     * Trigger event
     */
    trigger(event, data) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in event handler for ${event}:`, error);
                }
            });
        }
    }

    /**
     * Show browser notification
     */
    showNotification(title, message, type = 'info') {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: message,
                icon: this.getNotificationIcon(type),
                tag: `inventory-${type}`,
                requireInteraction: type === 'warning' || type === 'error'
            });

            notification.onclick = () => {
                window.focus();
                notification.close();
            };

            // Auto close after 5 seconds for info notifications
            if (type === 'info') {
                setTimeout(() => notification.close(), 5000);
            }
        }
    }

    /**
     * Get notification icon based on type
     */
    getNotificationIcon(type) {
        const icons = {
            info: '/images/icons/info.png',
            warning: '/images/icons/warning.png',
            error: '/images/icons/error.png',
            success: '/images/icons/success.png'
        };
        return icons[type] || icons.info;
    }

    /**
     * Request notification permission
     */
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }
    }

    /**
     * Connection established callback
     */
    onConnected() {
        this.trigger('connected');
    }

    /**
     * Connection lost callback
     */
    onDisconnected() {
        this.trigger('disconnected');
    }

    /**
     * Connection error callback
     */
    onError(error) {
        this.trigger('error', error);
    }

    /**
     * Disconnect from WebSocket
     */
    disconnect() {
        if (this.pusher) {
            this.pusher.disconnect();
        }
    }

    /**
     * Get connection state
     */
    getConnectionState() {
        return this.pusher ? this.pusher.connection.state : 'disconnected';
    }
}

// Usage example:
/*
const inventoryWS = new InventoryWebSocketClient({
    host: 'ws://localhost:6001',
    appKey: 'your-app-key'
});

// Request notification permission
inventoryWS.requestNotificationPermission();

// Subscribe to general inventory updates
inventoryWS.subscribeToInventory((event, data) => {
    switch (event) {
        case 'inventory.updated':
            updateInventoryDisplay(data);
            break;
        case 'stock-alert.triggered':
            showStockAlert(data);
            break;
        case 'recipe-cost.updated':
            updateRecipeCosts(data);
            break;
    }
});

// Subscribe to specific material
inventoryWS.subscribeToMaterial(123, (event, data) => {
    updateMaterialDisplay(123, data);
});

// Subscribe to dashboard updates
inventoryWS.subscribeToDashboard((event, data) => {
    updateDashboard(data);
});

// Add custom event listeners
inventoryWS.on('connected', () => {
    console.log('WebSocket connected');
    document.getElementById('connection-status').textContent = 'Connected';
});

inventoryWS.on('disconnected', () => {
    console.log('WebSocket disconnected');
    document.getElementById('connection-status').textContent = 'Disconnected';
});
*/
