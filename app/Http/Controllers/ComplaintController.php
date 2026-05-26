<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    /**
     * GET /api/complaints/mine — شكاوى المستخدم الحالي
     */
    public function mine(Request $request)
    {
        $complaints = Complaint::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'subject'        => $c->subject,
                'message'        => $c->message,
                'status'         => $c->status,
                'admin_response' => $c->admin_response,
                'resolved_at'    => $c->resolved_at?->toIso8601String(),
                'created_at'     => $c->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $complaints,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'role'    => $request->user()->role,
            'subject' => $request->subject,
            'message' => $request->message,
            'status'  => 'pending',
        ]);

        return response()->json([
            'message' => 'Complaint submitted successfully',
            'complaint' => $complaint,
        ], 201);
    }
}
