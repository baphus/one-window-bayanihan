<?php

namespace Tests\Feature;

use App\Http\Requests\StoreIntakeRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreIntakeRequestTest extends TestCase
{
    public function test_ofw_must_select_at_least_one_valid_requested_assessment(): void
    {
        $request = new StoreIntakeRequest;
        $rules = $request->rules();

        $empty = Validator::make(['proposed_assessments' => []], $rules);
        $this->assertFalse($empty->passes());
        $this->assertArrayHasKey('proposed_assessments', $empty->errors()->messages());

        $selected = Validator::make(['proposed_assessments' => ['LEGAL_ASSISTANCE']], $rules);
        $this->assertArrayNotHasKey('proposed_assessments', $selected->errors()->messages());
        $this->assertArrayNotHasKey('proposed_assessments.0', $selected->errors()->messages());

        $invalid = Validator::make(['proposed_assessments' => ['NOT_AN_OPTION']], $rules);
        $this->assertArrayHasKey('proposed_assessments.0', $invalid->errors()->messages());
    }
}
