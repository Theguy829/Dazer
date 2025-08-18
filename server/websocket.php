<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class PriceTracker implements MessageComponentInterface {
    protected $clients;
    protected $currentPrice;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->currentPrice = 0;
        echo "Price Tracker WebSocket Server started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId}). Total connections: " . count($this->clients) . "\n";
        
        // Send current price to new client
        $welcomeMessage = json_encode([
            'type' => 'welcome',
            'price' => $this->currentPrice,
            'message' => 'Connected to Price Tracker'
        ]);
        $conn->send($welcomeMessage);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: {$msg}\n";
        
        try {
            $data = json_decode($msg, true);
            
            // Validate message structure
            if (isset($data['color'], $data['x'], $data['y'], $data['price'])) {
                // Update current price
                $this->currentPrice = $data['price'];
                
                echo "Broadcasting price update: {$data['color']} square at ({$data['x']}, {$data['y']}) - Price: \${$data['price']}\n";
                
                // Broadcast to all connected clients
                foreach ($this->clients as $client) {
                    $client->send($msg);
                }
            } else {
                echo "Invalid message format received\n";
                $errorMessage = json_encode([
                    'type' => 'error',
                    'message' => 'Invalid message format'
                ]);
                $from->send($errorMessage);
            }
        } catch (Exception $e) {
            echo "Error processing message: " . $e->getMessage() . "\n";
            $errorMessage = json_encode([
                'type' => 'error',
                'message' => 'Error processing message'
            ]);
            $from->send($errorMessage);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected. Total connections: " . count($this->clients) . "\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new PriceTracker()
        )
    ),
    8080
);

echo "WebSocket server running on port 8080\n";
echo "Clients can connect to: ws://localhost:8080\n";
echo "Press Ctrl+C to stop the server\n\n";

$server->run();
