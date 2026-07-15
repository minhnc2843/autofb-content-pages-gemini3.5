<?php

namespace App\Http\Controllers;

use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiTask;
use App\Models\Page;
use App\Services\Security\SecretRedactionService;
use App\Services\AI\AIAssistantService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssistantController extends Controller
{
    protected SecretRedactionService $redactionService;
    protected AIAssistantService $assistantService;

    public function __construct(SecretRedactionService $redactionService, AIAssistantService $assistantService)
    {
        $this->redactionService = $redactionService;
        $this->assistantService = $assistantService;
    }

    public function index(Request $request)
    {
        $sessions = AiChatSession::orderBy('updated_at', 'desc')->get();
        $pages = Page::where('is_active', true)->get();

        $activeSessionId = $request->query('session_id');
        $activeSession = null;
        $messages = [];
        $pendingTask = null;

        if ($activeSessionId) {
            $activeSession = AiChatSession::with('page')->find($activeSessionId);
            if ($activeSession) {
                $messages = AiChatMessage::where('session_id', $activeSession->id)
                    ->orderBy('created_at', 'asc')
                    ->get();
                $pendingTask = AiTask::where('chat_session_id', $activeSession->id)
                    ->whereIn('status', ['awaiting_confirmation', 'running'])
                    ->first();
            }
        }

        return Inertia::render('Assistant/Index', [
            'sessions' => $sessions,
            'pages' => $pages,
            'activeSession' => $activeSession,
            'messages' => $messages,
            'pendingTask' => $pendingTask,
        ]);
    }

    public function message(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|integer|exists:ai_chat_sessions,id',
            'page_id' => 'nullable|integer|exists:pages,id',
        ]);

        $sessionId = $request->input('session_id');
        $pageId = $request->input('page_id');

        // Resolve or create session
        if ($sessionId) {
            $session = AiChatSession::findOrFail($sessionId);
        } else {
            $page = $pageId ? Page::find($pageId) : null;
            $title = $page ? "Chat with " . $page->name : "General AI Chat";
            $session = AiChatSession::create([
                'page_id' => $pageId,
                'title' => $title,
                'status' => 'active',
            ]);
        }

        $rawMessage = $request->input('message');

        // Redact and save secrets
        $redactionResult = $this->redactionService->extractAndSaveSecrets($rawMessage, $session->id);
        $redactedText = $redactionResult['redacted_text'];
        $pendingSecretIds = $redactionResult['pending_secret_ids'];

        // Save user message to database
        AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $redactedText,
            'metadata' => [
                'has_redacted_secrets' => count($pendingSecretIds) > 0,
                'pending_secret_ids' => $pendingSecretIds,
            ],
        ]);

        // Process message through AI assistant service
        try {
            $responseMessage = $this->assistantService->processMessage($redactedText, $session, $pendingSecretIds);
        } catch (\Exception $e) {
            $responseMessage = "Sorry, I encountered an error while processing your request: " . $e->getMessage();
            AiChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $responseMessage,
            ]);
        }

        // Update session timestamp
        $session->touch();

        return redirect()->route('assistant', ['session_id' => $session->id])
            ->with('success', 'Message sent.');
    }

    public function confirmTask(AiTask $task)
    {
        $task->update(['status' => 'running']);

        try {
            $result = $this->assistantService->executeConfirmedTask($task);
            if ($result['success']) {
                $task->update([
                    'status' => 'completed',
                    'result_json' => $result,
                    'error_message' => null,
                ]);

                // Post a message in the chat that the task was completed
                if ($task->chat_session_id) {
                    AiChatMessage::create([
                        'session_id' => $task->chat_session_id,
                        'role' => 'assistant',
                        'content' => "✅ Task '{$task->type}' has been completed successfully! Result: " . ($result['message'] ?? 'Done.'),
                    ]);
                }

                return redirect()->back()->with('success', 'Task executed successfully: ' . ($result['message'] ?? ''));
            } else {
                $task->update([
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'Execution failed.',
                ]);

                if ($task->chat_session_id) {
                    AiChatMessage::create([
                        'session_id' => $task->chat_session_id,
                        'role' => 'assistant',
                        'content' => "❌ Task '{$task->type}' failed: " . ($result['message'] ?? 'Execution failed.'),
                    ]);
                }

                return redirect()->back()->with('error', 'Task execution failed: ' . ($result['message'] ?? ''));
            }
        } catch (\Exception $e) {
            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($task->chat_session_id) {
                AiChatMessage::create([
                    'session_id' => $task->chat_session_id,
                    'role' => 'assistant',
                    'content' => "❌ Task '{$task->type}' crashed: " . $e->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', 'Task execution crashed: ' . $e->getMessage());
        }
    }

    public function cancelTask(AiTask $task)
    {
        $task->update(['status' => 'cancelled']);

        if ($task->chat_session_id) {
            AiChatMessage::create([
                'session_id' => $task->chat_session_id,
                'role' => 'assistant',
                'content' => "🚫 Task '{$task->type}' has been cancelled by the user.",
            ]);
        }

        return redirect()->back()->with('success', 'Task cancelled.');
    }
}
