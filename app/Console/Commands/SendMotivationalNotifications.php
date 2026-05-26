<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRoadmap;
use App\Services\FcmService;
use Illuminate\Console\Command;

class SendMotivationalNotifications extends Command
{
    protected $signature   = 'notifications:motivate';
    protected $description = 'Send daily motivational push notifications to job seekers';

    private array $messages = [
        [
            'title' => '🚀 Keep Going!',
            'body'  => 'You\'re one step closer to your dream job. Open the app and continue your learning journey today!',
        ],
        [
            'title' => '💡 Daily Reminder',
            'body'  => 'Top candidates never stop learning. Complete a skill on your roadmap and stand out from the crowd!',
        ],
        [
            'title' => '🎯 Stay Focused',
            'body'  => 'Your career goal is within reach. Log in and check your AI roadmap progress!',
        ],
        [
            'title' => '⚡ New Opportunities Await',
            'body'  => 'Companies are actively searching for talent like you. Make sure your CV is up to date!',
        ],
        [
            'title' => '🏆 You\'ve Got This!',
            'body'  => 'Every skill you master brings you closer to your goal. Keep pushing forward!',
        ],
        [
            'title' => '📈 Level Up Today',
            'body'  => 'Take a quick quiz to test your skills and boost your match score with top companies!',
        ],
    ];

    public function handle(): void
    {
        $fcm = new FcmService();

        // Get job seekers who have an active roadmap and an FCM token
        $users = User::where('role', 'job')
            ->whereNotNull('fcm_token')
            ->whereHas('resume') // only users who uploaded a CV
            ->get();

        if ($users->isEmpty()) {
            $this->info('No eligible users found.');
            return;
        }

        $count = 0;
        foreach ($users as $user) {
            // Pick a random motivational message
            $msg = $this->messages[array_rand($this->messages)];

            // Check if user has an active roadmap — personalize the message
            $roadmap = UserRoadmap::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            $title = $msg['title'];
            $body  = $msg['body'];

            if ($roadmap) {
                $completed = count($roadmap->completed_skills ?? []);
                $total     = count($roadmap->missing_skills ?? []);
                if ($total > 0 && $completed < $total) {
                    $remaining = $total - $completed;
                    $body = "You've completed {$completed}/{$total} skills on your {$roadmap->target_job} roadmap. Only {$remaining} more to go — you're almost there!";
                }
            }

            $sent = $fcm->send($user->fcm_token, $title, $body, ['type' => 'motivational']);
            if ($sent) $count++;
        }

        $this->info("Sent motivational notifications to {$count} users.");
    }
}
