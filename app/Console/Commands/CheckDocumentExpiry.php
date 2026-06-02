<?php

namespace App\Console\Commands;

use App\Classes\FireBaseNotification;
use App\Models\Document;
use Illuminate\Console\Command;

class CheckDocumentExpiry extends Command
{
    protected $signature = 'app:check-document-expiry';

    protected $description = 'Send push notifications for documents expiring within 30 days';

    public function handle(): void
    {
        $documents = Document::with('car.user.deviceTokens')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), today()->addDays(30)])
            ->get();

        foreach ($documents as $document) {
            $user = $document->car?->user;

            if ($user === null) {
                continue;
            }

            $days = (int) today()->diffInDays($document->expiry_date);

            (new FireBaseNotification)->notifyUser(
                $user,
                ['title' => 'Document Expiring', 'body' => "Your {$document->type} expires in {$days} days"],
                ['type' => 'document_expiry', 'document_id' => (string) $document->id, 'days_remaining' => (string) $days],
            );
        }
    }
}
