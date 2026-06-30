<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class ChatbotController extends Controller
{
    private array $responses = [
        'hello' => 'Hello! I\'m **Bayani**, your assistant for **Bayanihan One Window**. How can I help you today?',
        'help' => 'I can help you with:\n- OFW case status inquiries\n- Agency information (OWWA, DMW, TESDA, DSWD, DOLE)\n- Service availability and requirements\n- Referral process guidance\n- Document requirements for OFW assistance\n- Case tracking instructions',
        'case' => 'I can help you with case information. If you are a case manager, please log in to access your cases. If you are an OFW wanting to check your case, I can send a verification code to your registered email.',
        'track' => 'To track your case, visit our public tracking portal at /track and enter your tracker number. You will receive an OTP to verify your identity. Alternatively, I can help you verify right here — just tell me your tracker number!',
        'ofw' => 'Our system supports Overseas Filipino Workers (OFWs) and their families with inter-agency referrals. Partner agencies include OWWA (welfare and repatriation), DMW (legal assistance and documentation), TESDA (skills training), DSWD (social welfare), and DOLE (labor law assistance). Each agency has dedicated services to support OFWs and their families.',
        'service' => 'Our partner agencies offer various services including:\n- OWWA: Repatriation assistance, welfare support, emergency assistance\n- DMW: Legal assistance, employment documentation, contract verification\n- TESDA: Skills training and competency assessment\n- DSWD: Social welfare assistance, family support\n- DOLE: Labor law assistance, employment standards',
        'agency' => 'We work with multiple government agencies to provide comprehensive support for OFWs:\n\n**OWWA** — Overseas Workers Welfare Administration (welfare and repatriation)\n**DMW** — Department of Migrant Workers (legal and documentation)\n**TESDA** — Technical Education and Skills Development Authority (training)\n**DSWD** — Department of Social Welfare and Development (social services)\n**DOLE** — Department of Labor and Employment (labor standards)\n\nEach agency has its own lane for processing referrals.',
        'document' => 'Required documents typically include:\n- Valid government ID (passport, UMID, driver\'s license)\n- Employment contract or proof of engagement\n- OFW information sheets\n- Proof of agency engagement\n- Supporting documents for your specific concern\n- Case referral form (if already filed)\n\nSpecific requirements vary by agency and service type.',
        'referral' => 'A referral is sent to the appropriate agency based on your needs. The process works as follows:\n1. DMW Case Manager assesses your situation\n2. Referral is created with required services\n3. Referral is sent to the partner agency (OWWA, TESDA, DSWD, DOLE)\n4. Agency processes and updates the status\n5. You can track progress through our portal using your tracker number',
        'repatriation' => 'For repatriation assistance, OWWA provides support for OFWs needing to return to the Philippines. Services include:\n- Emergency repatriation assistance\n- Welfare support upon arrival\n- Reintegration programs\n- Family assistance\n\nPlease contact the DMW Case Manager to start the referral process.',
        'legal' => 'DMW provides legal assistance for OFWs including:\n- Legal counseling and advice\n- Assistance with employment contract issues\n- Documentation of employment concerns\n- Representation in labor disputes\n- Assistance with illegal recruitment cases',
        'tracker' => 'To look up your case using a tracker number, I can send a verification code to your registered email. Just tell me your tracker number and I\'ll start the verification process!',
        'verify' => 'You can verify your identity to access your case details. If you have a tracker number, just share it with me and I\'ll send a verification code to your registered email address.',
        'default' => "I'm not sure I understand your question. I can help you with:\n\n- **Agency info** — Details about OWWA, DMW, TESDA, DSWD, DOLE\n- **Services** — What services are available and their requirements\n- **Case tracking** — How to track your case status\n- **OFW support** — Repatriation, legal assistance, skills training\n- **Referrals** — How the inter-agency referral process works\n- **Documents** — Required documents for OFW assistance\n\nType \"help\" to see all options, or ask me a specific question!",
    ];

    public function message(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $userMessage = $request->input('message');

        // Try AI-first via agent() helper
        if (config('ai-chatbot.enabled', false)) {
            try {
                $guidePath = resource_path('guides/ofw-case-tracking.md');
                $guide = file_exists($guidePath) ? file_get_contents($guidePath) : '';

                $instructions = 'You are Bayani, a helpful assistant for the Bayanihan One Window system. Answer questions helpfully and accurately using the reference guide when relevant.';
                if ($guide) {
                    $instructions .= "\n\nHere is a reference guide you can use to answer questions:\n\n".$guide;
                }

                $response = agent(
                    instructions: $instructions,
                )->prompt(
                    prompt: $request->input('message'),
                    provider: config('ai-chatbot.provider', 'openai'),
                    model: config('ai-chatbot.model', ''),
                );

                return response()->json(['reply' => $response->text]);
            } catch (\Throwable $e) {
                Log::warning('Chatbot AI agent failed', [
                    'error' => $e->getMessage(),
                    'provider' => config('ai-chatbot.provider', 'unknown'),
                ]);

                return response()->json(['reply' => null, 'error' => 'AI service is currently unavailable. Please try again later.'], 503);
            }
        }

        // Fallback: keyword matching
        $message = strtolower(trim($userMessage));
        $response = $this->getResponse($message);

        return response()->json([
            'reply' => $response,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Keyword fallback
    // ──────────────────────────────────────────────

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
