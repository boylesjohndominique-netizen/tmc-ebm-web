<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Equipment::with('category');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('availability') && $request->availability === 'available') {
            $query->where('available_quantity', '>', 0);
        }

        return response()->json([
            'equipment' => $query->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function show(Equipment $equipment)
    {
        return response()->json($equipment->load('category'));
    }

    public function media(string $filename)
    {
        $safe = basename($filename);
        $path = 'equipment-images/'.$safe;

        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path), [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        if (Storage::disk('public')->exists($path)) {
            return response()->file(Storage::disk('public')->path($path), [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        return response()->json(['message' => 'Equipment image not found.'], 404);
    }
}
