<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    private array $responses = [
        'hello' => 'Hello! Welcome to the Bayanihan One Window support. How can I assist you today?',
        'help' => 'I can help you with:\n- Case status inquiries\n- Service requirements\n- Agency information\n- Referral process guidance\n- Document requirements',
        'case' => 'To track your case, please visit our public tracking portal at /track and enter your tracker number. You will receive an OTP to verify your identity.',
        'track' => 'To track your case, please visit our public tracking portal at /track and enter your tracker number. You will receive an OTP to verify your identity.',
        'service' => 'Our partner agencies offer various services including:\n- OWWA: Repatriation assistance, welfare support\n- DMW: Legal assistance, employment documentation\n- TESDA: Skills training and assessment\n- DSWD: Social welfare assistance\n- DOLE: Labor law assistance',
        'agency' => 'We work with multiple government agencies to provide comprehensive support. Each agency has its own lane for processing referrals. Contact us for specific agency details.',
        'document' => 'Required documents typically include:\n- Valid government ID\n- Employment contract\n- Passport\n- Proof of engagement with agency\n- Supporting documents for your specific concern',
        'referral' => 'A referral is sent to the appropriate agency based on your needs. The agency will process it and update the status. You can track progress through our portal.',
        'default' => 'I\'m not sure I understand. Please try asking about:\n- Case tracking\n- Required documents\n- Agency services\n- Referral process\nOr type "help" to see all options.',
    ];

    public function message(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = strtolower(trim($request->input('message')));

        $response = $this->getResponse($message);

        return response()->json([
            'reply' => nl2br(e($response)),
        ]);
    }

    private function getResponse(string $message): string
    {
        foreach ($this->responses as $keyword => $response) {
            if (str_contains($message, $keyword)) {
                return $response;
            }
        }

        return $this->responses['default'];
    }
}
