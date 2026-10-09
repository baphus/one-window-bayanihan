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

    /**
     * Staff inbox payload with a readable client reference and deep link.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $clientName = trim($this->ofwName) !== '' ? trim($this->ofwName) : 'An OFW client';
        $caseNumber = $this->case->case_number ?? $this->case->id;

        return [
            'type' => 'new_intake_submission',
            'title' => "New intake submission from {$clientName}",
            'message' => "New OFW intake submission from {$clientName} (case {$caseNumber}) requires review",
            'case_number' => $caseNumber,
            'actor_name' => trim($this->ofwName) !== '' ? trim($this->ofwName) : null,
            'url' => route('cases.intake-queue'),
            'case_id' => $this->case->id,
            'ofw_name' => $this->ofwName,
        ];
    }

    /**
     * Keep array/broadcast serialization identical to the database payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
