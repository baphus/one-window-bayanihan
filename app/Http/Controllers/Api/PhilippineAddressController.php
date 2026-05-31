<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhilippineAddressService;
use Illuminate\Http\Request;

class PhilippineAddressController extends Controller
{
    public function __construct(
        private readonly PhilippineAddressService $addressService,
    ) {}

    public function regions()
    {
        return response()->json($this->addressService->getRegions());
    }

    public function provinces(Request $request)
    {
        return response()->json(
            $this->addressService->getProvinces($request->query('region'))
        );
    }

    public function cities(Request $request)
    {
        return response()->json(
            $this->addressService->getCities($request->query('province'))
        );
    }

    public function barangays(Request $request)
    {
        return response()->json(
            $this->addressService->getBarangays($request->query('city'))
        );
    }
}
