<?php

namespace App\Services;

use App\Models\SurveyForm;
use Illuminate\Support\Collection;

class SurveyFormService
{
    /**
     * Get all forms for an agency, ordered by created_at desc, with question count.
     */
    public function getFormsForAgency(string $agencyId): Collection
    {
        return SurveyForm::where('agency_id', $agencyId)
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a form with questions.
     */
    public function createForm(string $agencyId, array $data): SurveyForm
    {
        $form = SurveyForm::create([
            'agency_id' => $agencyId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'is_active' => false,
        ]);

        foreach ($data['questions'] as $question) {
            $form->questions()->create([
                'type' => $question['type'],
                'label' => $question['label'],
                'options' => $question['options'] ?? null,
                'is_required' => $question['is_required'] ?? true,
                'order' => $question['order'] ?? 0,
            ]);
        }

        return $form->load('questions');
    }

    /**
     * Update form title/description and sync questions (delete old, insert new).
     */
    public function updateForm(SurveyForm $form, array $data): SurveyForm
    {
        $form->update([
            'title' => $data['title'] ?? $form->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $form->description,
        ]);

        if (isset($data['questions'])) {
            $form->questions()->delete();

            foreach ($data['questions'] as $question) {
                $form->questions()->create([
                    'type' => $question['type'],
                    'label' => $question['label'],
                    'options' => $question['options'] ?? null,
                    'is_required' => $question['is_required'] ?? true,
                    'order' => $question['order'] ?? 0,
                ]);
            }
        }

        return $form->load('questions');
    }

    /**
     * Delete form (cascade handles questions).
     */
    public function deleteForm(SurveyForm $form): void
    {
        $form->delete();
    }

    /**
     * Deactivate all other forms for the agency, activate this one.
     */
    public function activateForm(SurveyForm $form): void
    {
        SurveyForm::where('agency_id', $form->agency_id)
            ->where('id', '!=', $form->id)
            ->update(['is_active' => false]);

        $form->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    /**
     * Get the currently active form with questions loaded.
     */
    public function getActiveFormForAgency(string $agencyId): ?SurveyForm
    {
        return SurveyForm::where('agency_id', $agencyId)
            ->where('is_active', true)
            ->with('questions')
            ->first();
    }
}
