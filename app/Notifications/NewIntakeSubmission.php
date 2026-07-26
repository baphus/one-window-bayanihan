<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewIntakeSubmission extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CaseFile $case,
        public readonly string $ofwName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'default',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_intake_submission',
            'case_id' => $this->case->id,
            'ofw_name' => $this->ofwName,
            'message' => "New OFW intake submission from {$this->ofwName} requires review",
            'url' => route('cases.intake-queue'),
        ];
    }
}
