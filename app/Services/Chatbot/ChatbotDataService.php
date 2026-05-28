<?php

namespace App\Services\Chatbot;

use App\Models\Agency;
use App\Models\CaseStatus;
use App\Models\Service;
use App\Models\ServiceRequirement;
use Illuminate\Support\Collection;

/**
 * Provides structured data queries for the AI chatbot.
 *
 * These methods expose only public/reference information (agency details,
 * service descriptions, requirements, case status definitions) — never
 * private case or client PII data.
 */
class ChatbotDataService
{
    /**
     * Search agencies by name or description.
     *
     * @return Collection<int, array{id: string, name: string, short: string, slug: string, description: string, contact_info: string|null}>
     */
    public function searchAgencies(string $query, int $limit = 5): Collection
    {
        return Agency::query()
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($query).'%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($query).'%'])
                    ->orWhereRaw('LOWER(short) LIKE ?', ['%'.mb_strtolower($query).'%']);
            })
            ->take($limit)
            ->get()
            ->map(fn (Agency $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'short' => $a->short,
                'slug' => $a->slug,
                'description' => $a->description,
                'contact_info' => $a->contact_info,
            ]);
    }

    /**
     * Get services offered by a specific agency.
     *
     * @return Collection<int, array{id: string, name: string, description: string, processing_days: int|null}>
     */
    public function getAgencyServices(string $agencyId): Collection
    {
        return Service::query()
            ->where('agcy_id', $agencyId)
            ->where('is_deleted', false)
            ->get()
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'processing_days' => $s->processing_days,
            ]);
    }

    /**
     * Get document requirements for a specific service.
     *
     * @return Collection<int, array{name: string, description: string, is_required: bool}>
     */
    public function getServiceRequirements(string $serviceId): Collection
    {
        return ServiceRequirement::query()
            ->where('service_id', $serviceId)
            ->where('is_deleted', false)
            ->get()
            ->map(fn (ServiceRequirement $r) => [
                'name' => $r->name,
                'description' => $r->description,
                'is_required' => $r->is_required,
            ]);
    }

    /**
     * Search services by name or description across all agencies.
     *
     * @return Collection<int, array{id: string, name: string, description: string, processing_days: int|null, agency_name: string}>
     */
    public function searchServices(string $query, int $limit = 5): Collection
    {
        return Service::query()
            ->where('is_deleted', false)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($query).'%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($query).'%']);
            })
            ->with('agency')
            ->take($limit)
            ->get()
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'processing_days' => $s->processing_days,
                'agency_name' => $s->agency?->name ?? '',
                'agency_id' => $s->agcy_id,
            ]);
    }

    /**
     * Get all active case status definitions.
     *
     * @return Collection<int, array{name: string, slug: string, type: string, color: string}>
     */
    public function getCaseStatuses(): Collection
    {
        return CaseStatus::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (CaseStatus $cs) => [
                'name' => $cs->name,
                'slug' => $cs->slug,
                'type' => $cs->type,
                'color' => $cs->color,
            ]);
    }
}
