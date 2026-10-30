<?php

namespace App\Services\Chatbot;

use App\Models\Agency;

/**
 * Builds frequently asked questions shown in the chatbot welcome card.
 * FAQ questions focus on public OFW help for every user.
 *
 * Service-specific replies pull live data from the database so they stay
 * accurate without a re-index.
 */
class ChatbotSuggestionService
{
    /**
     * Return the full suggestion map for the given role.
     *
     * @param  string|null  $role  User role (OFW, CASE_MANAGER, ADMIN, AGENCY) or null for guest/OFW.
     * @return array<string, array{reply: string, actions?: list<array{label: string, url: string, icon: string}>}>
     */
    public function getSuggestions(?string $role = null): array
    {
        return $this->getOfwSuggestions();
    }

    // ── OFW / Guest ───────────────────────────────────────────────────────

    private function getOfwSuggestions(): array
    {
        return [
            'How do I request assistance?' => [
                'reply' => 'First, create an account and confirm your email. Sign in, then choose “File a new case” under My Cases. A DMW Case Manager will review your request before it becomes active.',
            ],
            'How do I check my case status?' => [
                'reply' => 'Go to **/track**, enter your **OWBAP-** tracker number and registered email, then enter the email OTP.',
            ],
            'What do I need to track my case?' => [
                'reply' => 'You need your **OWBAP-** tracker number, registered email, and the OTP sent to that email.',
            ],
            'What if I lost my tracker number?' => [
                'reply' => 'Check your confirmation email or acknowledgment receipt. If you still cannot find it, contact DMW Region VII.',
            ],
            'Why did I not receive my OTP?' => [
                'reply' => 'Check your Spam or Junk folder, confirm your email, and request a new OTP. OTPs are sent by email only.',
            ],
            'What can I see when tracking my case?' => [
                'reply' => 'You can see your public status, milestones, and agency progress. Private staff notes are not shown.',
            ],
        ];
    }

    // ── Admin ─────────────────────────────────────────────────────────────

    private function getAdminSuggestions(): array
    {
        return [
            'User management' => [
                'reply' => "You can manage users from the **Admin → Users** page. From there you can:\n\n- **Invite new users** by clicking the \"Invite User\" button and selecting their role.\n- **Edit user roles** — click a user's name to open their profile, then change their role (CASE_MANAGER, AGENCY, ADMIN).\n- **Deactivate users** — remove access without deleting their data.\n\nFor security, admin accounts require MFA enrollment.",
                'actions' => [['label' => 'Manage Users', 'url' => '/admin/users', 'icon' => 'people']],
            ],
            'How do I configure the AI chatbot?' => [
                'reply' => "The **System Settings** page lets you configure:\n\n- **AI Chatbot** — enable/disable, set the provider and model.\n- **Turnstile** — bot protection for public forms.\n- **General** — app name, assistant name, and other display settings.\n\nChanges take effect immediately for new sessions.",
                'actions' => [['label' => 'System Settings', 'url' => '/admin/system', 'icon' => 'settings']],
            ],
            'What do the case statuses mean?' => [
                'reply' => "You can manage case categories and statuses from the **Admin** section:\n\n- **Case Categories** — add, edit, or deactivate categories that case managers use when creating cases.\n- **Case Issues** — manage the specific issue types within each category.\n- **Statuses** — define the workflow statuses cases move through.\n\nThese settings directly affect what case managers see when filing cases.",
            ],
            'How do I export case data to Excel?' => [
                'reply' => "You can export case data to Excel from the **Reports** page. The export runs asynchronously:\n\n1. Go to **Reports → Export**.\n2. Select your filters (date range, status, agency).\n3. Click **Export to Excel** — the file generates in the background.\n4. You'll receive a notification when it's ready to download.\n\nLarge exports may take a few minutes depending on the data volume.",
            ],
            'How do I use the audit log?' => [
                'reply' => "The **Audit Log** records every significant action in the system:\n\n- Case create/update/delete/restore/purge\n- User login/logout and MFA events\n- Referral changes\n- System setting modifications\n\nYou can filter by user, action type, or date range. Entries cannot be modified or deleted — they are a permanent record.",
                'actions' => [['label' => 'View Audit Logs', 'url' => '/admin/audit-logs', 'icon' => 'history']],
            ],
        ];
    }

    // ── Case Manager ──────────────────────────────────────────────────────

    private function getCaseManagerSuggestions(): array
    {
        return [
            'How do I create a case?' => [
                'reply' => "To create a new case:\n\n1. Go to **Cases → Create** from the sidebar.\n2. Select the **client** (or create a new one).\n3. Choose the **category** and **issue type**.\n4. Fill in the **case summary** and supporting details.\n5. Click **Submit**. The case is created as **Pending Review** and appears in the review queue.\n\nOnce a supervisor approves it, the case becomes **Open** and goes live.",
                'actions' => [['label' => 'Create Case', 'url' => '/cases/create', 'icon' => 'add_circle']],
            ],
            'How do I add milestones to a referral?' => [
                'reply' => "Milestones track progress on a case. To add one:\n\n1. Open the case you're working on.\n2. Scroll to the **Milestones** section.\n3. Click **Add Milestone** and fill in the title, description, and status.\n4. Milestones can be marked as **In Progress**, **Completed**, or **Blocked**.\n\nMilestones help track case progress and are visible to case managers and admins.",
            ],
            'How do I create a referral?' => [
                'reply' => "To create a referral to a partner agency:\n\n1. Open the case.\n2. Click **Create Referral** in the case actions.\n3. Select the **partner agency** — the system shows which agencies handle which services.\n4. Choose the **service** and add a message explaining what you need.\n5. Submit — the agency focal will be notified.\n\nYou can track the referral status and add comments from the case view.",
            ],
            'What do the case statuses mean?' => [
                'reply' => "Here are the case statuses and what they mean:\n\n- **Pending Review** — newly submitted, waiting for approval.\n- **Open** — approved and active; referrals can be made.\n- **Closed** — the case has been resolved and finalized.\n- **Archived** — a closed case moved out of the active list.\n\nYou can update the status from the case detail page.",
            ],
            'Moving a case through review' => [
                'reply' => "Every new case starts as **Pending Review**.\n\n- A supervisor opens **Cases → Review Pending** to see the queue.\n- **Approve** moves the case to **Open** and makes it a live case of record.\n- **Reject** removes it from the queue.\n\nOnce a case is **Open**, close it from the case detail page. Closing it is what allows it to be archived later.",
            ],
        ];
    }

    // ── Agency Focal ──────────────────────────────────────────────────────

    private function getAgencySuggestions(): array
    {
        return [
            'How do I manage my referrals?' => [
                'reply' => "Referrals from case managers appear in your **Referral** dashboard. You can:\n\n- **View** all pending, in-progress, and completed referrals.\n- **Accept or decline** referrals with a reason.\n- **Add comments** to communicate with the referring case manager.\n- **Mark as completed** once the case is handled.\n\nTimely responses help maintain good inter-agency relationships.",
            ],
            'How do I manage my agency services profile?' => [
                'reply' => "Your agency services profile defines what your agency offers. Keep it updated:\n\n- Go to **Agency Profile** to view your current services.\n- Request changes through the system admin if new services need to be added.\n- Ensure your **contact information** is current so case managers can reach you.\n\nAn accurate profile helps case managers route referrals to the right agency.",
            ],
            'How do I handle overdue referrals?' => [
                'reply' => "Referrals that haven't been responded to within the expected timeframe are flagged as **overdue**:\n\n- Check the **Overdue Referrals** section on your dashboard.\n- Respond promptly to avoid escalation.\n- If you need more time, add a comment explaining the delay.\n\nRegular overdue flags may affect your agency's feedback score.",
            ],
            'How do I read my agency feedback?' => [
                'reply' => "Your **Feedback Dashboard** shows how case managers rate your agency's referral handling:\n\n- **Response time** — how quickly you respond to referrals.\n- **Resolution rate** — percentage of referrals successfully completed.\n- **Case manager ratings** — qualitative feedback on your service.\n\nUse this data to identify areas for improvement.",
            ],
            'Which documents are needed for referrals?' => [
                'reply' => "When handling referrals, you may need to upload supporting documents:\n\n- **Compliance documents** — required certifications or permits.\n- **Case reports** — status updates or resolution summaries.\n- **Supporting evidence** — photos, forms, or correspondence.\n\nUpload files directly from the referral detail page. Accepted formats include PDF, DOC, JPG, and PNG.",
            ],
        ];
    }

    // ── Shared helpers ────────────────────────────────────────────────────

    private function buildOwwaReply(): array
    {
        $agency = Agency::where(function ($q) {
            $q->where('name', 'like', '%OWWA%')
                ->orWhere('short', 'like', '%OWWA%');
        })
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->with(['services' => fn ($q) => $q->where('is_deleted', false)])
            ->first();

        if (! $agency) {
            return ['reply' => 'OWWA (Overseas Workers Welfare Administration) provides welfare assistance and support services for OFWs. Please visit their website at owwa.gov.ph for more information.'];
        }

        $lines = ["**{$agency->name}**"];

        if ($agency->contact_info) {
            $lines[] = '';
            foreach (explode("\r\n", $agency->contact_info) as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $lines[] = "- {$trimmed}";
                }
            }
        }

        $services = $agency->services->pluck('name')->filter()->values();
        if ($services->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '- **Services:** '.$services->implode(', ');
        }

        $lines[] = '';
        $lines[] = 'They assist with welfare support, emergency repatriation, and family assistance programs for OFWs.';

        return ['reply' => implode("\n", $lines)];
    }

    private function buildDmwReply(): array
    {
        $agency = Agency::where(function ($q) {
            $q->where('name', 'like', '%DMW%')
                ->orWhere('short', 'like', '%DMW%');
        })
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->with(['services' => fn ($q) => $q->where('is_deleted', false)])
            ->first();

        if (! $agency) {
            return ['reply' => 'DMW (Department of Migrant Workers) provides assistance for OFWs. Please visit dmw.gov.ph or call 1348 for more information.'];
        }

        $lines = ["**{$agency->name}** provides legal assistance for OFWs including:\n"];

        $services = $agency->services->pluck('name')->filter()->values();
        if ($services->isNotEmpty()) {
            foreach ($services as $service) {
                $lines[] = "- {$service}";
            }
        } else {
            $lines[] = '- Legal counseling and advice';
            $lines[] = '- Assistance with employment contract issues';
            $lines[] = '- Representation in labor disputes';
        }

        $lines[] = '';
        if ($agency->contact_info) {
            foreach (explode("\r\n", $agency->contact_info) as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    if (str_starts_with(strtolower($trimmed), 'tel:') || str_starts_with(strtolower($trimmed), 'email:')) {
                        $lines[] = "- **{$trimmed}**";
                    } else {
                        $lines[] = "- {$trimmed}";
                    }
                }
            }
        }

        $lines[] = '';
        $lines[] = 'To start a case, visit the DMW office or file through the One Window Bayanihan system.';

        return ['reply' => implode("\n", $lines)];
    }
}
