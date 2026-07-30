<?php

namespace App\Services\Chatbot;

use App\Models\Agency;

/**
 * Builds the quick-suggestion replies shown in the chatbot welcome card.
 * Suggestions are role-aware: OFW/guest users see public-facing help,
 * while ADMIN, CASE_MANAGER, and AGENCY users see internal help relevant
 * to their workflow.
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
        return match ($role) {
            'ADMIN' => $this->getAdminSuggestions(),
            'CASE_MANAGER' => $this->getCaseManagerSuggestions(),
            'AGENCY' => $this->getAgencySuggestions(),
            default => $this->getOfwSuggestions(),
        };
    }

    // ── OFW / Guest ───────────────────────────────────────────────────────

    private function getOfwSuggestions(): array
    {
        return [
            'Check my case status' => [
                'reply' => "To check your case status, you'll need your **Tracker Number** and the **email address** you used when submitting your request. Visit our tracking portal, enter both, and you'll receive a one-time passcode (OTP) via email to verify your identity. Once verified, your case details and current status will be displayed.\n\nIf you don't have your tracker number, check your email confirmation or contact the DMW Region VII office for assistance.",
                'actions' => [['label' => 'Go to Tracking Portal', 'url' => '/track', 'icon' => 'track']],
            ],
            'OWWA contact number' => $this->buildOwwaReply(),
            'Lost my tracker number' => [
                'reply' => "If you've lost your tracker number:\n\n1. **Check your email inbox** for the automated confirmation message you received when you submitted your request. The subject line will mention \"One Window Bayanihan — Case Confirmation\".\n2. If you have a printed **acknowledgment receipt**, the tracker number is printed there.\n3. If neither works, **contact the DMW Region VII office** directly. Provide your full name, date of submission, and the email you used so they can locate your case.",
            ],
            'OTP not working' => [
                'reply' => "Here are some tips if your OTP isn't working:\n\n1. **Check your Spam/Junk folder** — automated emails sometimes end up there.\n2. **Wait 1-2 minutes** — delivery delays happen occasionally.\n3. **Click \"Resend OTP\"** — you can request a new code anytime. The previous one will expire immediately.\n4. **OTPs expire after 5 minutes** — if yours expired, just request a new one.\n5. **Double-check your email** — look for typos like \"gmial.com\" instead of \"gmail.com\".\n\nIf it's still not arriving, add the sender domain to your email whitelist and try again.",
            ],
            'DMW legal assistance' => $this->buildDmwReply(),
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
            'System settings' => [
                'reply' => "The **System Settings** page lets you configure:\n\n- **AI Chatbot** — enable/disable, set the provider and model.\n- **Turnstile** — bot protection for public forms.\n- **General** — app name, assistant name, and other display settings.\n\nChanges take effect immediately for new sessions.",
                'actions' => [['label' => 'System Settings', 'url' => '/admin/system', 'icon' => 'settings']],
            ],
            'Case categories & statuses' => [
                'reply' => "You can manage case categories and statuses from the **Admin** section:\n\n- **Case Categories** — add, edit, or deactivate categories that case managers use when creating cases.\n- **Case Issues** — manage the specific issue types within each category.\n- **Statuses** — define the workflow statuses cases move through.\n\nThese settings directly affect what case managers see when filing cases.",
            ],
            'Data export' => [
                'reply' => "You can export case data to Excel from the **Reports** page. The export runs asynchronously:\n\n1. Go to **Reports → Export**.\n2. Select your filters (date range, status, agency).\n3. Click **Export to Excel** — the file generates in the background.\n4. You'll receive a notification when it's ready to download.\n\nLarge exports may take a few minutes depending on the data volume.",
            ],
            'Audit logs' => [
                'reply' => "The **Audit Log** records every significant action in the system:\n\n- Case create/update/delete/restore/purge\n- User login/logout and MFA events\n- Referral changes\n- System setting modifications\n\nYou can filter by user, action type, or date range. Entries cannot be modified or deleted — they are a permanent record.",
                'actions' => [['label' => 'View Audit Logs', 'url' => '/admin/audit-logs', 'icon' => 'history']],
            ],
        ];
    }

    // ── Case Manager ──────────────────────────────────────────────────────

    private function getCaseManagerSuggestions(): array
    {
        return [
            'How to create a case' => [
                'reply' => "To create a new case:\n\n1. Go to **Cases → Create** from the sidebar.\n2. Select the **client** (or create a new one).\n3. Choose the **category** and **issue type**.\n4. Fill in the **case summary** and supporting details.\n5. Click **Save** to create a draft, or **Submit** to publish it immediately.\n\nDrafts can be edited and reviewed before publishing.",
                'actions' => [['label' => 'Create Case', 'url' => '/cases/create', 'icon' => 'add_circle']],
            ],
            'Adding milestones' => [
                'reply' => "Milestones track progress on a case. To add one:\n\n1. Open the case you're working on.\n2. Scroll to the **Milestones** section.\n3. Click **Add Milestone** and fill in the title, description, and status.\n4. Milestones can be marked as **In Progress**, **Completed**, or **Blocked**.\n\nMilestones help track case progress and are visible to case managers and admins.",
            ],
            'Creating a referral' => [
                'reply' => "To create a referral to a partner agency:\n\n1. Open the case.\n2. Click **Create Referral** in the case actions.\n3. Select the **partner agency** — the system shows which agencies handle which services.\n4. Choose the **service** and add a message explaining what you need.\n5. Submit — the agency focal will be notified.\n\nYou can track the referral status and add comments from the case view.",
            ],
            'Case statuses explained' => [
                'reply' => "Here are the case statuses and what they mean:\n\n- **Draft** — case is being prepared, not yet published.\n- **Published** — case is active and visible to assigned agencies.\n- **Under Review** — agency is reviewing the case.\n- **In Progress** — active work on the case.\n- **Resolved** — case has been addressed.\n- **Closed** — case is finalized and archived.\n\nYou can update the status from the case detail page.",
            ],
            'Managing drafts' => [
                'reply' => "Draft cases are saved but not yet published to agencies. You can:\n\n- **Edit** — update any field before publishing.\n- **Review** — check all details are complete and accurate.\n- **Submit** — publish the case, making it visible to the relevant agency.\n- **Delete** — remove the draft if it's no longer needed.\n\nDrafts are only visible to you and other case managers.",
            ],
        ];
    }

    // ── Agency Focal ──────────────────────────────────────────────────────

    private function getAgencySuggestions(): array
    {
        return [
            'Managing my referrals' => [
                'reply' => "Referrals from case managers appear in your **Referral** dashboard. You can:\n\n- **View** all pending, in-progress, and completed referrals.\n- **Accept or decline** referrals with a reason.\n- **Add comments** to communicate with the referring case manager.\n- **Mark as completed** once the case is handled.\n\nTimely responses help maintain good inter-agency relationships.",
            ],
            'Agency services profile' => [
                'reply' => "Your agency services profile defines what your agency offers. Keep it updated:\n\n- Go to **Agency Profile** to view your current services.\n- Request changes through the system admin if new services need to be added.\n- Ensure your **contact information** is current so case managers can reach you.\n\nAn accurate profile helps case managers route referrals to the right agency.",
            ],
            'Overdue referrals' => [
                'reply' => "Referrals that haven't been responded to within the expected timeframe are flagged as **overdue**:\n\n- Check the **Overdue Referrals** section on your dashboard.\n- Respond promptly to avoid escalation.\n- If you need more time, add a comment explaining the delay.\n\nRegular overdue flags may affect your agency's feedback score.",
            ],
            'Feedback dashboard' => [
                'reply' => "Your **Feedback Dashboard** shows how case managers rate your agency's referral handling:\n\n- **Response time** — how quickly you respond to referrals.\n- **Resolution rate** — percentage of referrals successfully completed.\n- **Case manager ratings** — qualitative feedback on your service.\n\nUse this data to identify areas for improvement.",
            ],
            'Referral documents' => [
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
