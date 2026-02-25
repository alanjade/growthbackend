<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // AI CHAT  POST /api/support/chat
    // Body: { messages: [{role, content}], context?: string }
    // ─────────────────────────────────────────────────────────────────────────
    public function chat(Request $request)
    {
        $request->validate([
            'messages'        => 'required|array|min:1',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        $systemPrompt = <<<PROMPT
You are a friendly, knowledgeable support assistant for Sproutvest — a Nigerian real estate investment platform where users buy units of land, manage their portfolio, make deposits/withdrawals, and track transactions.

User context:
- Name: {$user->name}
- Email: {$user->email}
- Role: {$user->role}

You help with:
- How to deposit funds (Paystack)
- How to buy/sell land units
- KYC verification process and status
- Transaction PIN setup and reset
- Wallet balance and withdrawals
- Portfolio and investment returns
- Account and profile settings
- General platform navigation

Rules:
- Be concise, warm and professional
- If you cannot resolve an issue, recommend the user submit a support ticket
- Never reveal internal system details, API keys, or other users' information
- For financial disputes or account security issues, always escalate to a ticket
- Format responses in plain text, no markdown headers
PROMPT;

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 600,
            'system'     => $systemPrompt,
            'messages'   => $request->messages,
        ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'AI service temporarily unavailable. Please try again or submit a ticket.',
            ], 503);
        }

        $reply = $response->json('content.0.text', 'Sorry, I could not generate a response.');

        return response()->json([
            'success' => true,
            'data'    => ['reply' => $reply],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIST TICKETS  GET /api/support/tickets
    // ─────────────────────────────────────────────────────────────────────────
    public function indexTickets(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->select('id', 'reference', 'subject', 'category', 'status', 'priority', 'created_at', 'updated_at')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE TICKET  POST /api/support/tickets
    // Body: { subject, category, message, priority?, attachment? }
    // ─────────────────────────────────────────────────────────────────────────
    public function storeTicket(Request $request)
    {
        $request->validate([
            'subject'    => 'required|string|max:150',
            'category'   => 'required|in:account,payment,kyc,investment,withdrawal,other',
            'message'    => 'required|string|max:3000',
            'priority'   => 'in:low,normal,high',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,mp4,webm',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('support-attachments', 'public');
        }

        $ticket = SupportTicket::create([
            'user_id'   => $request->user()->id,
            'reference' => 'TKT-' . strtoupper(Str::random(8)),
            'subject'   => $request->subject,
            'category'  => $request->category,
            'status'    => 'open',
            'priority'  => $request->priority ?? 'normal',
        ]);

        SupportMessage::create([
            'ticket_id'      => $ticket->id,
            'sender_type'    => 'user',
            'sender_id'      => $request->user()->id,
            'body'           => $request->message,
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data'    => $ticket->load('messages'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW TICKET  GET /api/support/tickets/{ticket}
    // ─────────────────────────────────────────────────────────────────────────
    public function showTicket(Request $request, SupportTicket $ticket)
    {
        // Ensure user owns this ticket
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $ticket->load('messages'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPLY TO TICKET  POST /api/support/tickets/{ticket}/reply
    // Body: { message, attachment? }
    // ─────────────────────────────────────────────────────────────────────────
    public function replyTicket(Request $request, SupportTicket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is closed. Please open a new one.',
            ], 422);
        }

        $request->validate([
            'message'    => 'required|string|max:3000',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')
                ->store('support-attachments', 'public');
        }

        $msg = SupportMessage::create([
            'ticket_id'      => $ticket->id,
            'sender_type'    => 'user',
            'sender_id'      => $request->user()->id,
            'body'           => $request->message,
            'attachment_path' => $attachmentPath,
        ]);

        // Re-open if it was pending-user-reply
        if ($ticket->status === 'waiting') {
            $ticket->update(['status' => 'open']);
        }
        $ticket->touch();

        return response()->json([
            'success' => true,
            'message' => 'Reply sent.',
            'data'    => $msg,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FAQs  GET /api/support/faqs
    // ─────────────────────────────────────────────────────────────────────────
    public function faqs()
    {
        $faqs = Cache::remember('support_faqs', 3600, fn () =>
            Faq::where('is_active', true)
                ->orderBy('sort_order')
                ->select('id', 'category', 'question', 'answer')
                ->get()
                ->groupBy('category')
        );

        return response()->json(['success' => true, 'data' => $faqs]);
    }
}