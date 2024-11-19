<!DOCTYPE html>
<html>
<head>
    <title>WebSocket Test</title>
    <script src="https://js.pusher.com/7.0/pusher.min.js"></script>
</head>
<body>
    <h1>WebSocket Test</h1>
    <div id="messages"></div>

    <script>
        // Enable Pusher logging
        Pusher.logToConsole = true;

        const pusher = new Pusher('12345', {  // Make sure this matches PUSHER_APP_KEY
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    encrypted: true,
    enabledTransports: ['ws', 'wss'],
    disableStats: true
});

        pusher.connection.bind('connected', () => {
            console.log('Successfully connected to WebSocket');
            document.getElementById('messages').innerHTML += '<p>Connected to WebSocket!</p>';
        });

        pusher.connection.bind('error', (error) => {
            console.error('WebSocket connection error:', error);
            document.getElementById('messages').innerHTML += '<p>Error: ' + JSON.stringify(error) + '</p>';
        });

        const channel = pusher.subscribe('orders-channel');
        
        channel.bind('pusher:subscription_succeeded', () => {
            console.log('Successfully subscribed to orders-channel');
            document.getElementById('messages').innerHTML += '<p>Subscribed to orders-channel</p>';
        });

        channel.bind('order-updated', function(data) {
            console.log('Received order:', data);
            document.getElementById('messages').innerHTML += 
                '<p>Order received: ' + JSON.stringify(data) + '</p>';
        });
    </script>
</body>
</html>