<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Settings\SettingGroup;
use App\Settings\SettingRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * System settings, driven entirely by SettingRegistry.
 *
 * The page, the validation rules and the audit entries are all derived from the
 * registry, so adding a setting never means touching this controller. That was
 * deliberate: the previous version hand-wrote a field, its rule and its audit
 * call per setting, which is how two settings came to exist that nothing read.
 */
class SystemSettingsController extends Controller
{
    /**
     * Environment variables the page names but never lets an admin edit.
     *
     * Shown so nobody hunts for a field that will never exist. Secrets stay in
     * the environment by policy — see docs/SYSTEM_SETTINGS_V1.md.
     *
     * @var array<int, array{key: string, env: string, note: string}>
     */
    private const READ_ONLY = [
        [
            'key' => 'contact_email',
            'env' => 'CONTACT_RECIPIENT_EMAIL',
            'note' => 'Where messages from the public contact form are delivered.',
        ],
    ];

    public function index()
    {
        return Inertia::render('SystemSettings/Index', [
            'groups' => $this->groups(),
            'readOnly' => $this->readOnlyValues(),
            'mfa' => $this->mfaStatus(),
            'chatbot_last_reindexed_at' => SystemSetting::getValue('chatbot_last_reindexed_at'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate(SettingRegistry::validationRules());

        $changed = [];
        $rejected = [];

        // Validate the whole request before writing anything. Previously the
        // loop wrote ordinary settings as it went, then returned an error for a
        // confirm-required key — so a mixed save persisted changes AND wrote no
        // audit row, leaving the settings different from what the log says.
        foreach (SettingRegistry::all() as $key => $setting) {
            if (! array_key_exists($key, $validated) || $validated[$key] === null) {
                continue;
            }

            // High-impact settings need the confirmation flag the page sends
            // once the admin has read the consequence. Refusing the write is
            // safer than trusting the UI alone.
            if ($setting->confirmRequired && ! $request->boolean('confirmed')) {
                $rejected[] = $key;
            }
        }

        if ($rejected !== []) {
            return back()->withErrors([
                'confirmed' => 'Some settings need confirmation before they can be changed: '.implode(', ', $rejected).'. Nothing was saved.',
            ]);
        }

        foreach (SettingRegistry::all() as $key => $setting) {
            // Absent means "leave it alone". A cleared field arrives as null
            // (ConvertEmptyStringsToNull) and must never be written as the
            // type's zero value — a 0-day retention flips a cutoff to "now".
            if (! array_key_exists($key, $validated) || $validated[$key] === null) {
                continue;
            }

            $previous = SystemSetting::getValue($key, $setting->default);
            $value = $setting->cast($validated[$key]);

            if ((string) $previous === $setting->serialize($value)) {
                continue;
            }

            SystemSetting::setValue($key, $value);

            $changed[$key] = ['old' => $setting->cast($previous), 'new' => $value];
        }

        if ($changed !== []) {
            // One row per save, carrying every key that moved. The old→new
            // values are what make a bad change reversible by hand.
            AuditLogger::log(AuditAction::UPDATE)
                ->byId(auth()->id())
                ->module(AuditModule::SYSTEM_SETTING)
                ->context(
                    array_map(fn (array $change) => $change['old'], $changed),
                    array_map(fn (array $change) => $change['new'], $changed),
                )
                ->description('Updated system settings: '.implode(', ', array_keys($changed)))
                ->write();
        }

        return back()->with('success', $changed === [] ? 'No changes.' : 'Settings updated successfully.');
    }

    /**
     * Refresh the parsed chatbot helpdesk content cache.
     *
     * Runs synchronously — the corpus is small (~300 sections) and completes
     * in under 30 seconds. Returns JSON for the frontend polling handler.
     */
    public function reindexChatbot(): JsonResponse
    {
        try {
            $exitCode = Artisan::call('chatbot:index');

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Helpdesk cache refresh failed. Check the server logs for details.',
                ], 500);
            }

            SystemSetting::setValue('chatbot_last_reindexed_at', now()->toIso8601String());

            AuditLogger::log(AuditAction::UPDATE)
                ->byId(auth()->id())
                ->module(AuditModule::SYSTEM_SETTING)
                ->description('Chatbot helpdesk content cache refreshed (reindexed)')
                ->write();

            return response()->json([
                'success' => true,
                'message' => 'Chatbot helpdesk content refreshed successfully.',
                'last_reindexed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot helpdesk cache refresh failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Helpdesk cache refresh failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Settings by group, in registry order. Empty groups are omitted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groups(): array
    {
        $groups = [];

        foreach (SettingGroup::ORDER as $group) {
            $settings = SettingRegistry::inGroup($group);

            if ($settings === []) {
                continue;
            }

            $groups[] = [
                'key' => $group,
                'label' => SettingGroup::label($group),
                'description' => SettingGroup::description($group),
                'settings' => array_map(fn ($setting) => $setting->toArray() + [
                    'value' => SettingRegistry::value($setting->key),
                    'source' => SettingRegistry::source($setting->key),
                ], $settings),
            ];
        }

        return $groups;
    }

    /**
     * Read-only values shown with the environment variable that controls them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readOnlyValues(): array
    {
        return array_map(fn (array $item) => $item + [
            'value' => (string) config('mail.contact_recipient', config('mail.from.address', '')),
        ], self::READ_ONLY);
    }

    /**
     * The real MFA state, replacing a toggle that claimed authority over a
     * policy the code makes unconditional in production.
     *
     * @return array<string, mixed>
     */
    private function mfaStatus(): array
    {
        $isProduction = app()->isProduction();

        return [
            'enforced' => $isProduction || (bool) config('mfa.enrollment_enforcement_enabled'),
            'unconditional' => $isProduction,
            'env' => $isProduction ? null : 'MFA_ENROLLMENT_ENFORCEMENT_ENABLED',
            'note' => $isProduction
                ? 'Enforced for every role in production and cannot be switched off here.'
                : 'Enforced for: '.implode(', ', (array) config('mfa.enrollment_enforced_roles', [])).'. Controlled by the deployment, not this page.',
        ];
    }
}
