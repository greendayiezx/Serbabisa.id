<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Task::query()->with(['category', 'mitra', 'customer'])->latest();

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isMitra()) {
            if ($request->query('status') === 'pending') {
                $query->where('status', 'pending')->whereNull('mitra_id');
            } else {
                $query->where('mitra_id', $user->id);
            }
        } elseif ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe' => ['required', Rule::in(['fixed', 'custom'])],
            'category_id' => ['nullable', 'exists:categories,id'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'lokasi_alamat' => ['required', 'string', 'max:255'],
            'lokasi_lat' => ['required', 'numeric'],
            'lokasi_lng' => ['required', 'numeric'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $task = Task::create([
            ...$validated,
            'customer_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json($task->load('category'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        return response()->json($task->load(['category', 'mitra', 'customer']));
    }

    /**
     * Lokasi unik yang paling sering dipakai pengguna (Tersimpan / Baru-Baru Ini).
     */
    public function recentLocations(Request $request)
    {
        $user = $request->user();

        $tasks = Task::query()
            ->where('customer_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['lokasi_alamat', 'lokasi_lat', 'lokasi_lng', 'created_at']);

        $places = collect();

        foreach ($tasks as $task) {
            $lat = (float) $task->lokasi_lat;
            $lng = (float) $task->lokasi_lng;
            $key = sprintf('%.5f,%.5f', $lat, $lng);

            if ($places->has($key)) {
                continue;
            }

            $places->put($key, [
                'id' => $key,
                'label' => $task->lokasi_alamat,
                'address' => $task->lokasi_alamat,
                'lat' => $lat,
                'lng' => $lng,
                'last_used_at' => $task->created_at?->toIso8601String(),
            ]);
        }

        return response()->json($places->values()->take(6));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'accepted', 'in_progress', 'completed', 'cancelled'])],
        ]);

        if (isset($validated['status'])) {
            $timestampField = match ($validated['status']) {
                'accepted' => 'accepted_at',
                'completed' => 'completed_at',
                'cancelled' => 'cancelled_at',
                default => null,
            };
            if ($timestampField) {
                $validated[$timestampField] = now();
            }
        }

        $task->update($validated);

        return response()->json($task->load(['category', 'mitra', 'customer']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(null, 204);
    }
}
