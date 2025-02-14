<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Mail\EventReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;


class SendEventEmails extends Command
{
    protected $signature = 'events:email';
    protected $description = 'Send email reminders for upcoming events';
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $events = Event::where('startDate', '<=', now()->addMinutes(5))
                       ->where('is_notification_sent', 0)
                       ->get();

        foreach ($events as $event) {
            foreach ($event->participants as $participant) {
                Mail::to($participant->participant_email)->send(new EventReminder($event));
            }
            $event->is_notification_sent = 1;
            $event->save();
        }
    }
}
