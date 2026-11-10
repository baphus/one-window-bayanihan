<?php

namespace App\Http\Requests;

class StoreIntakeRequest extends StoreCaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOfw() ?? false;
    }
}
