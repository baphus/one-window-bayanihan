<?php

namespace App\Services;

use App\Models\CaseFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaseDuplicateCheckService
{
    public function forCase(CaseFile $case, array $assessments): array
    {
        $assessments = array_values(array_diff($assessments, ['OTHER']));
        if ($assessments === []) {
            return ['available' => true, 'matches' => []];
        }

        try {
            $namesUsable = $this->normalize($case->first_name) !== '' && $this->normalize($case->last_name) !== '' && $case->date_of_birth !== null;
            $accountUsable = $case->ofw_user_id && $case->client_type === CaseFile::CLIENT_TYPE_OFW;
            if (! $namesUsable && ! $accountUsable) {
                return ['available' => true, 'matches' => []];
            }

            $query = CaseFile::query()->where('status', 'OPEN')->where('is_deleted', false)->whereKeyNot($case->getKey())
                ->where(function ($query) use ($case, $namesUsable) {
                    if ($namesUsable) {
                        $query->where(function ($query) use ($case) {
                            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                                $query->whereRaw("LOWER(BTRIM(REGEXP_REPLACE(COALESCE($field, ''), '\\s+', ' ', 'g'))) = LOWER(?)", [$this->normalize($case->$field)]);
                            }
                            $query->whereNotNull('date_of_birth');
                        });
                    }
                    if ($case->ofw_user_id && $case->client_type === CaseFile::CLIENT_TYPE_OFW) {
                        $query->orWhere(function ($query) use ($case) {
                            $query->where('ofw_user_id', $case->ofw_user_id)->where('client_type', CaseFile::CLIENT_TYPE_OFW);
                        });
                    }
                });

            $matches = [];
            foreach ($query->lazyById(100) as $candidate) {
                $concerns = $candidate->confirmed_assessments ?: $candidate->proposed_assessments ?: [];
                $shared = array_values(array_intersect($assessments, array_diff($concerns, ['OTHER'])));
                if ($shared === []) {
                    continue;
                }
                $reasons = [];
                if ($case->client_type === CaseFile::CLIENT_TYPE_OFW && $candidate->client_type === CaseFile::CLIENT_TYPE_OFW
                    && $case->ofw_user_id && $candidate->ofw_user_id === $case->ofw_user_id) {
                    $reasons[] = 'same_ofw_account';
                }
                if ($namesUsable && $this->sameIdentity($case, $candidate)) {
                    $reasons[] = 'same_name_and_birth_date';
                }
                if ($reasons !== []) {
                    $matches[] = ['id' => $candidate->id, 'case_number' => $candidate->case_number,
                        'opened_at' => $candidate->reviewed_at?->toISOString(), 'shared_assessments' => $shared, 'reasons' => $reasons];
                }
            }

            return ['available' => true, 'matches' => $matches];
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Case duplicate check unavailable', ['case_id' => $case->id]);

            return ['available' => false, 'matches' => []];
        }
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
    }

    private function sameIdentity(CaseFile $a, CaseFile $b): bool
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if ($this->normalize($a->$field) !== $this->normalize($b->$field)) {
                return false;
            }
        }

        return $a->date_of_birth !== null && $b->date_of_birth !== null
            && $a->date_of_birth->toDateString() === $b->date_of_birth->toDateString();
    }
}
