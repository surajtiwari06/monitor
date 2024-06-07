<?php

namespace App\Console\Commands;

use App\Models\CustomerSite;
use App\Models\MonitoringLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotifyUser extends Command
{
    protected $signature = 'notify-user';

    protected $description = 'Notify user for website down';

    public function handle(): void
    {
        if (empty(config('services.telegram_notifier.token')) || empty(env('DISCORD_BOT_TOKEN')) || empty(env('DISCORD_CHANNEL_ID'))) {
            return;
        }

        $customerSites = CustomerSite::where('is_active', 1)->get();

        foreach ($customerSites as $customerSite) {
            if (!$customerSite->canNotifyUser()) {
                continue;
            }
            $responseTimes = MonitoringLog::query()
                ->where('customer_site_id', $customerSite->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(['response_time', 'created_at']);
            $responseTimeAverage = $responseTimes->avg('response_time');
            if ($responseTimes->avg('response_time') >= ($customerSite->down_threshold * 0.9)) {
                $this->notifyUser($customerSite, $responseTimes);
                $customerSite->last_notify_user_at = Carbon::now();
                $customerSite->save();
            }
        }

        $this->info('Done!');
    }

    private function notifyUser(CustomerSite $customerSite, Collection $responseTimes): void
    {
        if (is_null($customerSite->owner)) {
            Log::channel('daily')->info('Missing customer site owner', $customerSite->toArray());
            return;
        }

        // $this->notifyTelegram($customerSite, $responseTimes);
        $this->notifyDiscord($customerSite, $responseTimes);
    }

    private function notifyTelegram(CustomerSite $customerSite, Collection $responseTimes): void
    {
        $telegramChatId = $customerSite->owner->telegram_chat_id;
        if (is_null($telegramChatId)) {
            Log::channel('daily')->info('Missing telegram_chat_id from owner', $customerSite->toArray());
            return;
        }

        $endpoint = 'https://api.telegram.org/bot'.config('services.telegram_notifier.token').'/sendMessage';
        $text = "Uptime: Website Down\n\n{$customerSite->name} ({$customerSite->url})\n\nLast 5 response times:\n";
        foreach ($responseTimes as $responseTime) {
            $text .= $responseTime->created_at->format('H:i:s').':   '.$responseTime->response_time.' ms'."\n";
        }
        $text .= "\nCheck here:\n".route('customer_sites.show', [$customerSite->id]);

        Http::post($endpoint, [
            'chat_id' => $telegramChatId,
            'text' => $text,
        ]);
    }

    private function notifyDiscord(CustomerSite $customerSite, Collection $responseTimes): void
    {
        // Ensure the correct Discord token and channel ID are being retrieved from environment variables
        $discordToken = env('DISCORD_BOT_TOKEN');
        $channelId = env('DISCORD_CHANNEL_ID');
        
        // Log channel ID for debugging
        $this->info($channelId);
    
        // Construct the message content
        $text = "Boondock Monitor\n\n**{$customerSite->name}** ({$customerSite->url})\n\n**Last 5 response times:**\n";
        foreach ($responseTimes as $responseTime) {
            $text .= $responseTime->created_at->format('H:i:s').':   '.$responseTime->response_time.' ms'."\n";
        }
    
        // Create a simplified test message
        // $text1 = 'test';
    
        // Define the endpoint for sending messages to the Discord channel
        $endpoint = "https://discord.com/api/v10/channels/{$channelId}/messages";
    
        // Use Http::withHeaders to set the Authorization header correctly
        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $discordToken,
            'Content-Type' => 'application/json'
        ])->post($endpoint, [
            'content' => $text,
        ]);
    
        // Log the response for debugging purposes
        if ($response->failed()) {
            Log::channel('daily')->error('Failed to send Discord notification', [
                'response' => $response->body(),
                'customer_site' => $customerSite->toArray(),
            ]);
        }
    }
    
}
