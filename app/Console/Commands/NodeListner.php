<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use App\Models\CustomerSite;
use App\Models\MonitoringLog;
use MQTT;
use Carbon\Carbon;

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

        // Create a WebSocket connector
        $connector = new Connector();

        // Establish WebSocket connection
        $connector($websocketUrl)->then(function (WebSocket $conn) {
            // Handle incoming messages
            $conn->on('message', function ($msg) {
                $this->handleMessage($msg);
            });

            // Handle connection close event
            $conn->on('close', function ($code = null, $reason = null) {
                $this->info("Connection closed ({$code} - {$reason})");
            });
        }, function (\Exception $e) {
            // Handle connection error
            $this->error("Could not connect: {$e->getMessage()}");
        });
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

            // Retrieve the previous logs for the customer site
            $previousLogs = MonitoringLog::where('customer_site_id', $customerSite->id)
                ->latest()
                ->limit(3)
                ->get();

            // Check if there are at least 3 previous logs to compare
            if ($previousLogs->count() >= 3) {
                // Calculate the differences in consecutive response times
                $differences = [];
                $previousResponseTime = null;
                foreach ($previousLogs as $log) {
                    if ($previousResponseTime !== null) {
                        $differences[] = abs($log->response_time - $previousResponseTime);
                    }
                    $previousResponseTime = $log->response_time;
                }

                // Calculate the average difference
                $averageDifference = array_sum($differences) / count($differences);

                // Set the response time to the average difference
                $responseTime = $averageDifference;
            } else {
                // If there are less than 3 previous logs, set response time to 500
                $responseTime = 500;
            }

            // Create a MonitoringLog entry
            MonitoringLog::create([
                'customer_site_id' => $customerSite->id,
                'url' => $customerSite->url,
                'response_time' => $responseTime, // MQTT doesn't have a response time in the same sense
                'status_code' => 200, // Consider 200 for successful message receipt
                // 'message' => $message, // Store the message received
            ]);

            // Update last_check_at for the customer site
            $customerSite->last_check_at = now();
            $customerSite->save();

            // Print information in the terminal
            $this->info("Received 'online' message for customer site topic $topic");
        }
    } 
}

 
}
