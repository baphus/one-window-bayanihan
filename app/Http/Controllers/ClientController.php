<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePictureRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Services\AuditLogFormatter;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $clients = Client::with(['caseFile' => function ($q) {
            $q->with('referrals.agency');
        }])->orderBy('created_at', 'desc');

        // Role-based scoping
        if ($user && ! $user->isAdmin()) {
            if ($user->isCaseManager()) {
                $clients->whereHas('caseFile', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } elseif ($user->isAgency() && $user->agcy_id) {
                $clients->whereHas('caseFile.referrals', function ($q) use ($user) {
                    $q->where('agcy_id', $user->agcy_id);
                });
            }
        }

        if (! empty($request->search)) {
            $search = $request->search;
            $clients->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhereHas('caseFile', function ($q) use ($search) {
                        $q->where('case_number', 'like', "%{$search}%");
                    });
            });
        }

        return Inertia::render('Client/Index', [
            'clients' => $clients->paginate(15),
            'filters' => (object) $request->only(['search']),
        ]);
    }

    public function show(string $id, Request $request)
    {
        $client = Client::with([
            'caseFile' => function ($q) {
                $q->with(['referrals.agency', 'referrals.milestones', 'user']);
            },
            'addresses',
            'employments',
        ])->findOrFail($id);

        $this->authorizeClientAccess($client, $request->user());

        $auditLogs = AuditLog::with('user')
            ->where(function ($q) use ($client) {
                // Direct client changes
                // Direct client changes (from AuditObserver on Client model)
                $q->whereIn('module', ['clients', 'client'])->where('entity_id', $client->id);

                // Case file changes if client has case (from AuditObserver + CaseService)
                if ($client->caseFile) {
                    $q->orWhere(function ($q2) use ($client) {
                        $q2->whereIn('module', ['CASE', 'cases', 'case_files', 'case'])->where('entity_id', $client->caseFile->id);
                    });

                    // Referral changes (from AuditObserver + ReferralService)
                    $referralIds = $client->caseFile->referrals->pluck('id');
                    if ($referralIds->isNotEmpty()) {
                        $q->orWhere(function ($q2) use ($referralIds) {
                            $q2->whereIn('module', ['REFERRAL', 'referrals', 'referral'])->whereIn('entity_id', $referralIds);
                        });

                        // Milestone changes (from ReferralService)
                        $milestoneIds = $client->caseFile->referrals->flatMap->milestones->pluck('id');
                        if ($milestoneIds->isNotEmpty()) {
                            $q->orWhere(function ($q2) use ($milestoneIds) {
                                $q2->whereIn('module', ['MILESTONE', 'milestones', 'milestone'])->whereIn('entity_id', $milestoneIds);
                            });
                        }
                    }
                }
            })
            ->orderBy('timestamp', 'desc')
            ->limit(20)
            ->get();

        $formattedLogs = $auditLogs->map(fn ($log) => app(AuditLogFormatter::class)->formatForDisplay($log));

        return Inertia::render('Client/Show', [
            'client' => $client,
            'auditLogs' => $formattedLogs,
        ]);
    }

    public function storeAvatar(ProfilePictureRequest $request, string $id)
    {
        $client = Client::findOrFail($id);
        $this->authorizeClientAccess($client, $request->user());

        $file = $request->file('profile_picture');
        $filename = 'client-'.$client->id.'-'.time().'-'.str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT).'.'.$file->guessExtension();
        $path = $file->storeAs('profile-pictures', $filename, 'private');

        $client->avatar_url = $path;
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture updated successfully.');
    }

    public function destroyAvatar(string $id, Request $request)
    {
        $client = Client::findOrFail($id);
        $this->authorizeClientAccess($client, $request->user());

        if ($client->avatar_url) {
            Storage::disk('private')->delete($client->avatar_url);
        }

        $client->avatar_url = null;
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture removed successfully.');
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $queries = new DataExportQueries;
        $clients = $queries->getClients($user);
        $columnMap = ColumnMaps::getMap('clients');
        $filename = 'clients-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Clients', $columnMap, $clients, $filename);
    }

    /**
     * Authorize that the current user can access this client record.
     * ADMIN: all. CASE_MANAGER: client must have a case they own.
     * AGENCY: client must have a case referred to their agency.
     * Returns 404 on mismatch (no 403 — don't reveal existence).
     */
    private function authorizeClientAccess(Client $client, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isCaseManager()) {
            $hasAccess = $client->caseFiles()
                ->where('user_id', $user->id)
                ->exists();

            if (! $hasAccess) {
                abort(404, 'Client not found.');
            }

            return;
        }

        if ($user->isAgency() && $user->agcy_id) {
            $hasAccess = $client->caseFiles()
                ->whereHas('referrals', function ($q) use ($user) {
                    $q->where('agcy_id', $user->agcy_id);
                })
                ->exists();

            if (! $hasAccess) {
                abort(404, 'Client not found.');
            }

            return;
        }

        // Unknown role — deny
        abort(404, 'Client not found.');
    }
}
