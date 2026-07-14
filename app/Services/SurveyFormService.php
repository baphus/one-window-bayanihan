<?php

namespace App\Services;

use App\Models\SurveyForm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($agencyId, $data) {
            $form = SurveyForm::create([
                'agency_id' => $agencyId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => false,
            ]);

            $this->replaceQuestions($form, $data['questions']);

            return $form->load('questions');
        });
    }

    /**
     * Update form title/description and sync questions (delete old, insert new).
     */
    public function updateForm(SurveyForm $form, array $data): SurveyForm
    {
        return DB::transaction(function () use ($form, $data) {
            $form = SurveyForm::query()->lockForUpdate()->findOrFail($form->id);
            $this->ensureNoInvitations($form);
            $form->update([
                'title' => $data['title'] ?? $form->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $form->description,
            ]);
            if (isset($data['questions'])) {
                $form->questions()->delete();
                $this->replaceQuestions($form, $data['questions']);
            }

            return $form->load('questions');
        });
    }

    /**
     * Delete form (cascade handles questions).
     */
    public function deleteForm(SurveyForm $form): void
    {
        DB::transaction(function () use ($form) {
            $form = SurveyForm::query()->lockForUpdate()->findOrFail($form->id);
            $this->ensureNoInvitations($form);
            $form->delete();
        });
    }

    /**
     * Deactivate all other forms for the agency, activate this one.
     */
    public function activateForm(SurveyForm $form): void
    {
        DB::transaction(function () use ($form) {
            $forms = SurveyForm::query()
                ->where('agency_id', $form->agency_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedForm = $forms->firstWhere('id', $form->id);

            if (! $lockedForm) {
                abort(404);
            }

            $forms->each(function (SurveyForm $agencyForm) use ($lockedForm): void {
                if ($agencyForm->id !== $lockedForm->id) {
                    $agencyForm->update(['is_active' => false]);
                }
            });
            $lockedForm->update(['is_active' => true, 'activated_at' => now()]);
        });
    }

    public function assertEditable(SurveyForm $form): void
    {
        DB::transaction(function () use ($form) {
            $this->ensureNoInvitations(SurveyForm::query()->lockForUpdate()->findOrFail($form->id));
        });
    }

    private function ensureNoInvitations(SurveyForm $form): void
    {
        if ($form->invitations()->exists()) {
            abort(409, 'This survey form is immutable because invitations exist. Create a replacement form instead.');
        }
    }

    private function replaceQuestions(SurveyForm $form, array $questions): void
    {
        foreach ($questions as $question) {
            $form->questions()->create([
                'type' => $question['type'],
                'label' => $question['label'],
                'options' => $question['options'] ?? null,
                'is_required' => $question['is_required'] ?? true,
                'order' => $question['order'] ?? 0,
            ]);
        }
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
