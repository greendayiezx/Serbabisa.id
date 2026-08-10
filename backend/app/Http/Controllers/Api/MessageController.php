<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Task $task)
    {
        return response()->json(
            $task->messages()->with('sender:id,name')->orderBy('created_at')->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'isi' => ['required', 'string'],
        ]);

        $message = $task->messages()->create([
            ...$validated,
            'sender_id' => $request->user()->id,
        ]);

        return response()->json($message->load('sender:id,name'), 201);
    }
}
