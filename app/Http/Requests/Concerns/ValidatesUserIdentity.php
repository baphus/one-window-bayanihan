<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * One ruleset for the identity columns shared by every role, so the OFW
 * settings page, the invite form, and admin create/edit cannot drift apart.
 */
trait ValidatesUserIdentity
{
    /**
     * @param  bool  $partial  Update surfaces may omit the fields; the profile
     *                         forms always post them.
     * @return array<string, array<int, mixed>>
     */
    protected function identityRules(bool $requireDemographics = false, bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'first_name' => [...$sometimes, 'required', 'string', 'max:255'],
            'middle_name' => [...$sometimes, 'nullable', 'string', 'max:255'],
            'last_name' => [...$sometimes, 'required', 'string', 'max:255'],
            'suffix' => [...$sometimes, 'nullable', 'string', 'max:50'],
            // OFW demographics drive age-based vulnerability assessment;
            // staff birth date and sex are optional.
            'date_of_birth' => [...$sometimes, $requireDemographics ? 'required' : 'nullable', 'date_format:Y-m-d', 'before:today'],
            'sex' => [...$sometimes, $requireDemographics ? 'required' : 'nullable', Rule::in(['MALE', 'FEMALE'])],
        ];
    }
}
