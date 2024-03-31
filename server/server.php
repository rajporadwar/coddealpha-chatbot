<?php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Chat implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $apiKey = '';//update your api key check pricing on openai.com
        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $msg
                )
            ),
            'max_tokens' => 200
        );
        $postData = json_encode($data);
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        echo $response = curl_exec($ch);
        if ($response === false) {
            echo 'Error: ' . curl_error($ch);
        } else {
            $responseData = json_decode($response, true);
            $generatedText = $responseData['choices'][0]['message']['content'];
            foreach ($this->clients as $client) {
                if ($client == $from) {
                    $client->send($generatedText);
                }
            }
        }
        curl_close($ch);
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8080
);

echo "Server running at 127.0.0.1:8080\n";

$server->run();

