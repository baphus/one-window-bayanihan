<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $recipientEmail = config('mail.contact_recipient', config('mail.from.address'));

        Mail::to($recipientEmail)
            ->send(new ContactMessageMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                messageBody: $validated['message'],
            ));

        Log::info('Contact form submission', [
            'sender_email' => $validated['email'],
            'ip' => $request->ip(),
        ]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Your message has been sent successfully. We\'ll get back to you soon.',
        ]);
    }
}
