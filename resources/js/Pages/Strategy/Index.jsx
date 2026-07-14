import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';

export default function Index({ strategy, topics, geminiEnabled }) {
    const [generating, setGenerating] = useState(false);

    // Categories colors mapping
    const categoryColors = {
        educational: 'bg-blue-100 text-blue-800 border-blue-200',
        spiritual: 'bg-purple-100 text-purple-800 border-purple-200',
        funny: 'bg-amber-100 text-amber-800 border-amber-200',
        funny_pet: 'bg-amber-100 text-amber-800 border-amber-200',
        question: 'bg-teal-100 text-teal-800 border-teal-200',
        story: 'bg-orange-100 text-orange-800 border-orange-200',
        viral_hook: 'bg-rose-100 text-rose-800 border-rose-200',
        soft_cta: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    };

    const handleGenerate = () => {
        setGenerating(true);
        router.post('/strategy/generate', {}, {
            preserveScroll: true,
            onFinish: () => setGenerating(false),
        });
    };

    return (
        <AppLayout title="AI Content Strategy Engine">
            {/* Header Strategy Overview */}
            <div className="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 to-white p-6 shadow-sm mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="space-y-1">
                    <div className="flex items-center gap-3">
                        <span className="text-3xl">🎯</span>
                        <h2 className="text-xl font-bold text-gray-800">
                            {strategy?.strategy_title || 'Weekly Content Strategy'}
                        </h2>
                    </div>
                    <p className="text-sm text-gray-600 leading-relaxed max-w-2xl">
                        {strategy?.overview || 'Generate your custom weekly content strategy outline using Gemini AI based on your configured active topics.'}
                    </p>
                </div>
                
                <div>
                    {geminiEnabled ? (
                        <button
                            onClick={handleGenerate}
                            disabled={generating}
                            className="w-full md:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-5 py-2.5 shadow-sm transition disabled:opacity-50 cursor-pointer"
                        >
                            {generating ? '⏳ Generating...' : '🎯 Generate New Strategy'}
                        </button>
                    ) : (
                        <div className="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700 max-w-xs">
                            ⚠️ <strong>Gemini Disabled:</strong> Enable Gemini in Settings to generate new strategy plans.
                        </div>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                {/* Daily Plan List */}
                <div className="lg:col-span-2 space-y-6">
                    <h3 className="text-md font-bold text-gray-800 flex items-center gap-1.5 mb-4">
                        📅 7-Day Editorial Roadmap
                    </h3>

                    {strategy?.daily_plan && strategy.daily_plan.length > 0 ? (
                        <div className="space-y-4">
                            {strategy.daily_plan.map((item, idx) => {
                                const catColor = categoryColors[item.category] || 'bg-gray-100 text-gray-800 border-gray-200';
                                return (
                                    <div 
                                        key={idx}
                                        className="rounded-xl border border-gray-150 bg-white p-5 shadow-xs transition hover:shadow-sm"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
                                            <span className="text-sm font-bold text-gray-800 bg-gray-50 px-2.5 py-1 rounded-md border border-gray-100">
                                                {item.day}
                                            </span>
                                            <span className={`text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border ${catColor}`}>
                                                {item.category.replace('_', ' ')}
                                            </span>
                                        </div>

                                        <h4 className="text-sm font-bold text-gray-800 mb-1">
                                            Focus: {item.focus}
                                        </h4>
                                        <p className="text-xs text-gray-500 leading-relaxed bg-gray-50/50 p-3 rounded-lg border border-gray-100">
                                            💡 <strong>Suggested Outline:</strong> {item.prompt_suggestion}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="text-center py-12 bg-white rounded-xl border border-gray-150 text-gray-400 text-sm">
                            No strategy roadmap generated yet. Click the generate button to start.
                        </div>
                    )}
                </div>

                {/* Category distribution sidebar */}
                <div className="lg:col-span-1 space-y-6">
                    {/* Category Distribution Chart */}
                    <div className="rounded-xl border border-gray-150 bg-white p-5 shadow-xs">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">
                            📈 Content Category Mix
                        </h3>
                        {strategy?.category_distribution && Object.keys(strategy.category_distribution).length > 0 ? (
                            <div className="space-y-3.5">
                                {Object.entries(strategy.category_distribution).map(([cat, count]) => {
                                    const percent = Math.min(100, Math.round((count / 7) * 100));
                                    return (
                                        <div key={cat} className="space-y-1">
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="capitalize font-semibold text-gray-700">{cat.replace('_', ' ')}</span>
                                                <span className="font-bold text-gray-500">{count} post(s) ({percent}%)</span>
                                            </div>
                                            <div className="w-full bg-gray-100 rounded-full h-2">
                                                <div 
                                                    className="bg-indigo-600 h-2 rounded-full" 
                                                    style={{ width: `${percent}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-6 text-xs text-gray-400">
                                No distribution statistics available.
                            </div>
                        )}
                    </div>

                    {/* Active Topics Summary */}
                    <div className="rounded-xl border border-gray-150 bg-white p-5 shadow-xs">
                        <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">
                            📝 Input Active Topics
                        </h3>
                        {topics && topics.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {topics.map(t => (
                                    <span key={t.id} className="text-xs bg-gray-50 border border-gray-150 rounded-lg px-2.5 py-1 text-gray-600 font-medium">
                                        🏷️ {t.name}
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <div className="text-xs text-gray-400 italic">No active topics configured.</div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
