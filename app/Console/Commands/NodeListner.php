<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use App\Models\CustomerSite;
use App\Models\MonitoringLog;
use Carbon\Carbon;
use React\EventLoop\Factory as LoopFactory;

class NodeListner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:node-listner';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handles incoming transcriptions from a WebSocket connection';

    /**
     * Temporary storage for messages.
     *
     * @var array
     */
    protected $tempArray = [];

    /**
     * The interval in seconds to check for missed messages.
     *
     * @var int
     */
    protected $checkInterval;


    public function __construct()
    {
        parent::__construct();

        // Set the check interval from the configuration
        $this->checkInterval = config('app.node_check_interval');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Get WebSocket URL from configuration
        $websocketUrl = config('app.websocket_url');

        // Check if WebSocket URL is defined
        if (!$websocketUrl) {
            $this->error('WebSocket URL is not defined in configuration.');
            return;
        }

        // Create an event loop
        $loop = LoopFactory::create();

        // Create a WebSocket connector
        $connector = new Connector($loop);

        // Establish WebSocket connection
        $connector($websocketUrl)->then(function (WebSocket $conn) use ($loop) {
            // Handle incoming messages
            $conn->on('message', function ($msg) {
                $this->handleMessage($msg);
            });

            // Handle connection close event
            $conn->on('close', function ($code = null, $reason = null) {
                $this->info("Connection closed ({$code} - {$reason})");
            });

            // Periodically check if topics are being received
            $loop->addPeriodicTimer($this->checkInterval, function () {
                $this->checkReceivedMessages();
            });

        }, function (\Exception $e) {
            // Handle connection error
            $this->error("Could not connect: {$e->getMessage()}");
        });

        // Run the event loop
        $loop->run();
    }

    /**
     * Handle incoming WebSocket message.
     *
     * @param  mixed  $message
     * @return void
     */
    private function handleMessage($message)
    {
        // Decode the incoming message
        $messageData = json_decode($message, true);

        // Check if message format is valid
        if (isset($messageData['topic']) && isset($messageData['message'])) {
            // Retrieve the topic and message from the incoming message
            $topic = $messageData['topic'];
            $message = $messageData['message'];

            // Retrieve customer site topics from the database
            $customerSiteTopics = CustomerSite::pluck('topic')->toArray();

            // Check if the topic matches any of the customer site topics
            if (in_array($topic, $customerSiteTopics)) {
                // Retrieve the customer site corresponding to the topic
                $customerSite = CustomerSite::where('topic', $topic)->first();

                // Store message in temp array with timestamp
                $this->tempArray[$topic] = Carbon::now();

                // Create a MonitoringLog entry
                MonitoringLog::create([
                    'customer_site_id' => $customerSite->id,
                    'url' => $customerSite->url,
                    'response_time' => 500, // Placeholder for response time
                    'status_code' => 200, // Consider 200 for successful message receipt
                    // 'message' => $message, // Store the message received
                ]);

                // Update last_check_at for the customer site
                $customerSite->last_check_at = now();
                $customerSite->save();

                // Print information in the terminal
                $this->info("Received $message message for customer site topic $topic");
            }
        }
    }

    /**
     * Periodically check if topics are being received.
     *
     * @return void
     */
    private function checkReceivedMessages()
    {
        $now = Carbon::now();
        foreach ($this->tempArray as $topic => $lastReceived) {
            if ($now->diffInSeconds($lastReceived) >= $this->checkInterval) {
                // Retrieve the customer site corresponding to the topic
                $customerSite = CustomerSite::where('topic', $topic)->first();

                if ($customerSite) {
                    // Log response time as 10000 if no message received within checkInterval
                    MonitoringLog::create([
                        'customer_site_id' => $customerSite->id,
                        'url' => $customerSite->url,
                        'response_time' => 10000,
                        'status_code' => 500, // Consider 500 for failure to receive a message
                        // 'message' => 'No message received within the check interval', // Store a note about the lack of message
                    ]);

                    // Update last_check_at for the customer site
                    $customerSite->last_check_at = now();
                    $customerSite->save();

                    // Print information in the terminal
                    $this->info("No message received for customer site topic $topic within the check interval");
                }
            }
        }
    }
}
