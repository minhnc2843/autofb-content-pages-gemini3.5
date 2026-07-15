import { useForm, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../../Components/AppLayout';

export default function Index({ sessions, pages, activeSession, messages, pendingTask }) {
    const [newMessage, setNewMessage] = useState('');
    const [selectedPageId, setSelectedPageId] = useState('');
    const messagesEndRef = useRef(null);

    const { data, setData, post, processing, reset } = useForm({
        message: '',
        session_id: activeSession?.id || '',
        page_id: '',
    });

    useEffect(() => {
        if (messagesEndRef.current) {
            messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages]);

    const handleSendMessage = (e) => {
        e.preventDefault();
        if (!data.message.trim()) return;

        post('/assistant/message', {
            onSuccess: () => {
                setData('message', '');
            }
        });
    };

    const handleNewSession = (e) => {
        e.preventDefault();
        router.post('/assistant/message', {
            message: 'Hello, I want to start a new assistant session.',
            page_id: selectedPageId || null,
        });
    };

    const handleConfirmTask = (taskId) => {
        router.post(`/assistant/tasks/${taskId}/confirm`);
    };

    const handleCancelTask = (taskId) => {
        router.post(`/assistant/tasks/${taskId}/cancel`);
    };

    return (
        <AppLayout title="AI Assistant / Command Center">
            <div className="flex h-[calc(100vh-140px)] gap-6 overflow-hidden">
                
                {/* Session Sidebar (Left) */}
                <div className="flex w-64 flex-col rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                    <h3 className="text-sm font-bold text-gray-900 mb-3">Chat Sessions</h3>
                    
                    {/* New session selector */}
                    <form onSubmit={handleNewSession} className="space-y-2 mb-4 border-b border-gray-100 pb-4">
                        <select
                            value={selectedPageId}
                            onChange={(e) => setSelectedPageId(e.target.value)}
                            className="block w-full rounded-md border-0 py-1 px-2 text-xs text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                        >
                            <option value="">Select Target Page (Optional)</option>
                            {pages.map(page => (
                                <option key={page.id} value={page.id}>{page.name}</option>
                            ))}
                        </select>
                        <button
                            type="submit"
                            className="flex w-full justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                            + New Session
                        </button>
                    </form>

                    <div className="flex-1 overflow-y-auto space-y-1">
                        {sessions.map(s => (
                            <Link
                                key={s.id}
                                href={`/assistant?session_id=${s.id}`}
                                className={`block rounded-lg px-3 py-2 text-xs font-medium transition-colors ${
                                    activeSession?.id === s.id
                                        ? 'bg-indigo-50 text-indigo-700'
                                        : 'text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                <div className="truncate font-semibold">{s.title || `Chat #${s.id}`}</div>
                                <div className="text-[10px] text-gray-400 mt-0.5">
                                    {new Date(s.updated_at).toLocaleString()}
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Message Panel (Center) */}
                <div className="flex flex-1 flex-col rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    {activeSession ? (
                        <>
                            {/* Panel Header */}
                            <div className="bg-gray-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <div>
                                    <h2 className="text-sm font-bold text-gray-900">{activeSession.title}</h2>
                                    {activeSession.page && (
                                        <p className="text-[10px] text-gray-500">Bound to: {activeSession.page.name}</p>
                                    )}
                                </div>
                                <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800">
                                    Active
                                </span>
                            </div>

                            {/* Messages Container */}
                            <div className="flex-1 overflow-y-auto p-6 space-y-4">
                                {messages.map((msg) => {
                                    const isUser = msg.role === 'user';
                                    const hasSecrets = msg.metadata?.has_redacted_secrets;
                                    return (
                                        <div
                                            key={msg.id}
                                            className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}
                                        >
                                            <div className={`max-w-lg rounded-xl px-4 py-2.5 shadow-sm text-sm ${
                                                isUser
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}>
                                                {/* Text Content */}
                                                <div className="whitespace-pre-line leading-relaxed">
                                                    {msg.content}
                                                </div>

                                                {/* Warning Redacted Secrets badge */}
                                                {isUser && hasSecrets && (
                                                    <div className="mt-2 flex items-center gap-1.5 rounded bg-amber-500 px-2 py-1 text-[10px] font-semibold text-white">
                                                        <span>🛡️</span> A secret was detected and securely redacted before AI processing.
                                                    </div>
                                                )}
                                                
                                                <div className={`text-[9px] mt-1 text-right ${isUser ? 'text-indigo-200' : 'text-gray-400'}`}>
                                                    {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Message Input form */}
                            <form onSubmit={handleSendMessage} className="border-t border-gray-100 p-4 bg-gray-50 flex gap-2">
                                <input
                                    type="text"
                                    value={data.message}
                                    onChange={e => setData('message', e.target.value)}
                                    placeholder="Ask Assistant: 'Create content plan...', 'Update token...', 'Phân tích page Phật giáo...'"
                                    className="flex-1 rounded-lg border-0 py-2 px-3 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600"
                                />
                                <button
                                    type="submit"
                                    disabled={processing || !data.message.trim()}
                                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    Send
                                </button>
                            </form>
                        </>
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center text-gray-400 p-8 text-center">
                            <span className="text-4xl mb-2">🤖</span>
                            <h3 className="font-semibold text-sm">Select or Start a Chat Session</h3>
                            <p className="text-xs text-gray-500 mt-1 max-w-sm">
                                Talk with Gemini Assistant to build plans, analyze page metrics, or update Access Tokens securely.
                            </p>
                        </div>
                    )}
                </div>

                {/* Task Preview Panel (Right) */}
                <div className="flex w-80 flex-col rounded-xl bg-white p-4 shadow-sm border border-gray-100 overflow-y-auto">
                    <h3 className="text-sm font-bold text-gray-900 mb-3">AI Tasks & Plans</h3>

                    {pendingTask ? (
                        <div className="rounded-lg bg-gray-50 p-4 border border-gray-200 space-y-4">
                            <div>
                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${
                                    pendingTask.status === 'awaiting_confirmation'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-blue-100 text-blue-800'
                                }`}>
                                    {pendingTask.status === 'awaiting_confirmation' ? 'Awaiting Confirmation' : 'Running...'}
                                </span>
                                <h4 className="font-bold text-gray-800 text-xs mt-2 uppercase tracking-wide">
                                    Type: {pendingTask.type.replace(/_/g, ' ')}
                                </h4>
                            </div>

                            {/* Plan details */}
                            {pendingTask.plan_json?.steps && (
                                <div className="space-y-2">
                                    <h5 className="text-[10px] font-bold text-gray-500 uppercase">Plan Steps</h5>
                                    <ul className="text-xs text-gray-700 space-y-1.5 list-disc pl-4">
                                        {pendingTask.plan_json.steps.map((step, idx) => (
                                            <li key={idx}>{step}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {pendingTask.plan_json?.parameters && (
                                <div className="space-y-2 bg-white p-2 rounded border border-gray-100 text-xs">
                                    <h5 className="text-[10px] font-bold text-gray-500 uppercase mb-1">Parameters</h5>
                                    {Object.entries(pendingTask.plan_json.parameters).map(([k, v]) => (
                                        <div key={k} className="flex justify-between py-0.5 border-b border-gray-50 last:border-0">
                                            <span className="text-gray-400 font-mono text-[10px]">{k}</span>
                                            <span className="text-gray-800 font-medium">{typeof v === 'object' ? JSON.stringify(v) : String(v)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {pendingTask.status === 'awaiting_confirmation' && (
                                <div className="flex gap-2 pt-2">
                                    <button
                                        onClick={() => handleConfirmTask(pendingTask.id)}
                                        className="flex-1 justify-center rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-green-500"
                                    >
                                        Confirm Plan
                                    </button>
                                    <button
                                        onClick={() => handleCancelTask(pendingTask.id)}
                                        className="rounded-lg bg-white border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center text-xs text-gray-400 py-12">
                            No active tasks pending confirmation. Ask the AI assistant to perform actions to queue plans.
                        </div>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
