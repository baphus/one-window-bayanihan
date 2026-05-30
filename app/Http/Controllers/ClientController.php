<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePictureRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Services\AuditLogFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::with(['caseFile' => function ($q) {
            $q->with('referrals.agency');
        }])->orderBy('created_at', 'desc');

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
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(string $id)
    {
        $client = Client::with([
            'caseFile' => function ($q) {
                $q->with(['referrals.agency', 'referrals.milestones', 'user']);
            },
            'addresses',
            'employments',
        ])->findOrFail($id);

        $auditLogs = AuditLog::with('user')
            ->where(function ($q) use ($client) {
                // Direct client changes
                // Direct client changes (from AuditObserver on Client model)
                $q->whereIn('module', ['clients'])->where('entity_id', $client->id);

                // Case file changes if client has case (from AuditObserver + CaseService)
                if ($client->caseFile) {
                    $q->orWhere(function ($q2) use ($client) {
                        $q2->whereIn('module', ['CASE', 'cases', 'case_files'])->where('entity_id', $client->caseFile->id);
                    });

                    // Referral changes (from AuditObserver + ReferralService)
                    $referralIds = $client->caseFile->referrals->pluck('id');
                    if ($referralIds->isNotEmpty()) {
                        $q->orWhere(function ($q2) use ($referralIds) {
                            $q2->whereIn('module', ['REFERRAL', 'referrals'])->whereIn('entity_id', $referralIds);
                        });

                        // Milestone changes (from ReferralService)
                        $milestoneIds = $client->caseFile->referrals->flatMap->milestones->pluck('id');
                        if ($milestoneIds->isNotEmpty()) {
                            $q->orWhere(function ($q2) use ($milestoneIds) {
                                $q2->whereIn('module', ['MILESTONE', 'milestones'])->whereIn('entity_id', $milestoneIds);
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

        $file = $request->file('profile_picture');
        $filename = 'client-'.$client->id.'-'.time().'-'.str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT).'.'.$file->extension();
        $path = $file->storeAs('profile-pictures', $filename, 'public');

        $client->avatar_url = $path;
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture updated successfully.');
    }

    public function destroyAvatar(string $id)
    {
        $client = Client::findOrFail($id);

        if ($client->avatar_url) {
            Storage::disk('public')->delete($client->avatar_url);
        }

        $client->avatar_url = null;
        $client->save();

        return redirect()->route('clients.show', $client)->with('success', 'Profile picture removed successfully.');
    }
}
