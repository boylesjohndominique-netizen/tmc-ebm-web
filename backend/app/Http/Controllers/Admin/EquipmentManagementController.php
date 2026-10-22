<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEquipmentRequest;
use App\Models\BorrowingItem;
use App\Models\Equipment;
use Illuminate\Support\Facades\Storage;

class EquipmentManagementController extends Controller
{
    public function store(SaveEquipmentRequest $request)
    {
        $data = $request->validated();
        unset($data['gallery'], $data['remove_image'], $data['existing_images'], $data['clear_gallery']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('equipment-images', 'local');
        }

        $galleryPaths = [];
        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $file) {
                $galleryPaths[] = $file->store('equipment-images', 'local');
            }
        }
        $data['images'] = $galleryPaths;
        $data['available_quantity'] = $data['quantity'];
        $data['status'] = $data['quantity'] > 0 ? 'Available' : 'Unavailable';

        return response()->json(Equipment::create($data)->load('category'), 201);
    }

    public function show(Equipment $equipment)
    {
        $items = BorrowingItem::with('borrowing.user')
            ->where('equipment_id', $equipment->id)
            ->whereHas('borrowing', fn ($q) => $q->whereIn('status', ['Pending', 'Approved']))
            ->latest()->get();

        return response()->json([
            'equipment' => $equipment->load('category'),
            'active_borrowings' => $items->map(fn ($i) => [
                'borrowing_request_id' => $i->borrowing_request_id,
                'borrower' => trim("{$i->borrowing?->user?->first_name} {$i->borrowing?->user?->last_name}"),
                'quantity' => $i->quantity,
                'status' => $i->borrowing?->status,
                'expected_return_date' => $i->borrowing?->expected_return_date,
            ]),
        ]);
    }

    public function update(SaveEquipmentRequest $request, Equipment $equipment)
    {
        $data = $request->validated();

        unset($data['remove_image'], $data['existing_images'], $data['clear_gallery'], $data['gallery']);

        $borrowed = $equipment->quantity - $equipment->available_quantity;
        if ($data['quantity'] < $borrowed) {
            return response()->json(['message' => 'Quantity cannot be lower than currently borrowed quantity.'], 422);
        }

        // Main profile image update / removal
        if ($request->hasFile('image')) {
            $this->deleteStoredFile($equipment->image);
            $data['image'] = $request->file('image')->store('equipment-images', 'local');
        } elseif ($request->boolean('remove_image')) {
            $this->deleteStoredFile($equipment->image);
            $data['image'] = null;
        }

        // Gallery / description images update
        $currentGallery = is_array($equipment->images) ? $equipment->images : [];
        if ($request->has('existing_images') || $request->boolean('clear_gallery') || $request->hasFile('gallery')) {
            $keep = $request->boolean('clear_gallery')
                ? []
                : ($request->has('existing_images') ? (array) $request->input('existing_images', []) : $currentGallery);

            $keptPaths = [];
            foreach ($currentGallery as $oldPath) {
                if (in_array($oldPath, $keep, true)) {
                    $keptPaths[] = $oldPath;
                } else {
                    $this->deleteStoredFile($oldPath);
                }
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    if (count($keptPaths) >= 10) {
                        break;
                    }
                    $keptPaths[] = $file->store('equipment-images', 'local');
                }
            }

            $data['images'] = array_values($keptPaths);
        }

        $data['available_quantity'] = $data['quantity'] - $borrowed;
        $data['status'] = $data['available_quantity'] > 0 ? 'Available' : 'Unavailable';
        $equipment->update($data);

        return response()->json($equipment->fresh()->load('category'));
    }

    public function destroy(Equipment $equipment)
    {
        $activeBorrowings = BorrowingItem::where('equipment_id', $equipment->id)
            ->whereHas('borrowing', fn ($q) => $q->whereIn('status', ['Pending', 'Approved']))
            ->count();

        if ($activeBorrowings > 0) {
            return response()->json([
                'message' => "Cannot delete: {$equipment->name} is involved in {$activeBorrowings} pending/approved borrowing(s). Resolve them first.",
            ], 422);
        }

        $this->deleteStoredFile($equipment->image);
        foreach ((array) ($equipment->images ?? []) as $path) {
            $this->deleteStoredFile($path);
        }

        $equipment->delete();

        return response()->json(['message' => 'Equipment deleted.']);
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
