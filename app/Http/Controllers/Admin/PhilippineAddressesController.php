<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhilippineAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class PhilippineAddressesController extends Controller
{
    public function index(Request $request)
    {
        $query = PhilippineAddress::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('parent_code')) {
            $query->where('parent_code', $request->parent_code);
        }

        $addresses = $query->orderBy('type')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'code' => $item->code,
                'name' => $item->name,
                'parent_code' => $item->parent_code,
            ]);

        $counts = PhilippineAddress::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->pluck('count', 'type');

        return Inertia::render('Admin/PhilippineAddresses/Index', [
            'addresses' => $addresses,
            'counts' => $counts,
            'filters' => $request->only(['type', 'search', 'parent_code']),
        ]);
    }

    public function sync()
    {
        Artisan::call('philippine-addresses:sync');

        return back()->with('success', 'Philippine addresses synced successfully from PSGC API.');
    }
}
