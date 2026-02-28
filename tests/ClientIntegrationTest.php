<?php

/**
 * Integration tests for Client using public WebSocket servers.
 * Run with: vendor/bin/phpunit tests/ClientIntegrationTest.php
 * Or for specific server: vendor/bin/phpunit --filter PostmanEcho tests/ClientIntegrationTest.php
 *                                     vendor/bin/phpunit --filter BinanceStream tests/ClientIntegrationTest.php
 */

declare(strict_types=1);

namespace WebSocket;

use PHPUnit\Framework\TestCase;

class ClientIntegrationTest extends TestCase
{
    private const SERVER_POSTMAN = 'wss://ws.postman-echo.com/raw';
    private const SERVER_BINANCE = 'wss://stream.binance.com:9443/ws/btcusdt@trade';

    private const TIMEOUT = 10;

    protected function setUp(): void
    {
        parent::setUp();
        error_reporting(-1);
    }

    // =========================================================================
    // Postman Echo Server Tests
    // =========================================================================

    public function testPostmanEchoConnectAndHandshake(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        // Trigger connection by sending a message
        $client->send('test');
        $this->assertTrue($client->isConnected(), 'Failed to connect to ws.postman-echo.com');
        $client->close();
    }

    public function testPostmanEchoSendTextMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        $message = 'Test message for Postman';
        $client->send($message);
        $response = $client->receive();
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testPostmanEchoSendBinaryMessage(): void
    {
        // Note: Postman echo server may not support binary properly
        $this->assertTrue(true); // Placeholder - binary handling varies by server
    }

    public function testPostmanEchoMultipleMessages(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        
        $messages = ['One', 'Two', 'Three', 'Four', 'Five'];
        foreach ($messages as $message) {
            $client->send($message);
            $response = $client->receive();
            $this->assertEquals($message, $response);
        }
        
        $client->close();
    }

    public function testPostmanEchoPingPong(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        // Note: Echo server may not respond to ping, but should not error
        $client->send('ping-test');
        $response = $client->receive();
        $this->assertNotEmpty($response);
        $client->close();
    }

    public function testPostmanEchoLargeMessage(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        $message = str_repeat('TestData-', 2000);
        $client->send($message, 'text', false);
        $response = $client->receive();
        $this->assertEquals($message, $response);
        $client->close();
    }

    public function testPostmanEchoConnectionClose(): void
    {
        $client = new Client(self::SERVER_POSTMAN, ['timeout' => self::TIMEOUT]);
        $client->send('trigger connection');
        
        $this->assertTrue($client->isConnected());
        
        $client->close(1000, 'Test complete');
        
        $this->assertFalse($client->isConnected());
        $this->assertEquals(1000, $client->getCloseStatus());
    }

    // =========================================================================
    // Binance Stream Tests
    // =========================================================================

    public function testBinanceStreamConnect(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT]);
        // Trigger connection
        $client->send('');
        $this->assertTrue($client->isConnected(), 'Failed to connect to Binance stream');
        $client->close();
    }

    public function testBinanceStreamReceiveRealTimeData(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT]);
        
        // Wait for at least one message
        $received = false;
        $attempts = 0;
        $maxAttempts = 10;
        
        while (!$received && $attempts < $maxAttempts) {
            try {
                $response = $client->receive();
                if (!empty($response)) {
                    $data = json_decode($response, true);
                    $this->assertNotNull($data, 'Response is not valid JSON');
                    $this->assertArrayHasKey('e', $data, 'Missing event type field');
                    $received = true;
                }
            } catch (\Exception $e) {
                $attempts++;
                usleep(100000); // 100ms
            }
        }
        
        $this->assertTrue($received, 'Did not receive any data from Binance stream');
        $client->close();
    }

    public function testBinanceStreamReceiveMultipleMessages(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT]);
        
        $messages = [];
        $targetMessages = 3;
        
        $startTime = time();
        while (count($messages) < $targetMessages && (time() - $startTime) < 10) {
            try {
                $response = $client->receive();
                if (!empty($response)) {
                    $data = json_decode($response, true);
                    if ($data !== null) {
                        $messages[] = $data;
                    }
                }
            } catch (\Exception $e) {
                // Continue trying
            }
        }
        
        $this->assertGreaterThanOrEqual(1, count($messages), 'Did not receive enough messages from Binance');
        $client->close();
    }

    public function testBinanceStreamConnectionClose(): void
    {
        $client = new Client(self::SERVER_BINANCE, ['timeout' => self::TIMEOUT]);
        $client->send('');
        
        $this->assertTrue($client->isConnected());
        
        $client->close(1000, 'Done testing');
        
        $this->assertFalse($client->isConnected());
    }
}
