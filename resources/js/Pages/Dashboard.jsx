import AppLayout from '../Components/AppLayout';
import StatusBadge from '../Components/StatusBadge';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard({ stats, recentPosts, insights, audit, extraStats }) {
    const [isSyncing, setIsSyncing] = useState(false);
    const [isAuditing, setIsAuditing] = useState(false);

    const handleSyncInsights = () => {
        setIsSyncing(true);
        router.post('/insights/sync', {}, {
            onFinish: () => setIsSyncing(false)
        });
    };

    const handleRunAudit = () => {
        setIsAuditing(true);
        router.post('/insights/audit', {}, {
            onFinish: () => setIsAuditing(false)
        });
    };

    const statCards = [
        {
            label: 'Draft Posts',
            count: stats?.draft ?? 0,
            icon: '📝',
            bg: 'bg-amber-50/40',
            border: 'border-amber-100',
            text: 'text-amber-700',
        },
        {
            label: 'Approved Posts',
            count: stats?.approved ?? 0,
            icon: '✅',
            bg: 'bg-indigo-50/40',
            border: 'border-indigo-100',
            text: 'text-indigo-700',
        },
        {
            label: 'Published (Fake)',
            count: stats?.published_fake ?? 0,
            icon: '🚀',
            bg: 'bg-emerald-50/40',
            border: 'border-emerald-100',
            text: 'text-emerald-700',
        },
        {
            label: 'Failed Posts',
            count: stats?.failed ?? 0,
            icon: '❌',
            bg: 'bg-rose-50/40',
            border: 'border-rose-100',
            text: 'text-rose-700',
        },
    ];

    const latestInsight = insights && insights.length > 0 ? insights[insights.length - 1] : null;

    return (
        <AppLayout title="Dashboard">
            {/* Stat cards */}
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {statCards.map((card) => (
                    <div
                        key={card.label}
                        className={`rounded-xl border bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md ${card.border} ${card.bg}`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-3xl">{card.icon}</span>
                            <span className={`text-3xl font-bold ${card.text}`}>
                                {card.count}
                            </span>
                        </div>
                        <p className="mt-2 text-sm font-semibold text-gray-600">{card.label}</p>
                    </div>
                ))}
            </div>

            {/* Phase 3.5 Metrics Panel */}
            <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-3">
                {/* Scheduled today */}
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm flex items-center gap-4">
                    <span className="text-3xl p-3 bg-blue-50 text-blue-600 rounded-xl">📅</span>
                    <div>
                        <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Scheduled Today</span>
                        <span className="text-2xl font-bold text-gray-800">{extraStats?.scheduled_today ?? 0} posts</span>
                    </div>
                </div>

                {/* Missing slots next 7 days */}
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm flex items-center gap-4">
                    <span className="text-3xl p-3 bg-amber-50 text-amber-600 rounded-xl">⚠️</span>
                    <div>
                        <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Missing Slots (Next 7d)</span>
                        <span className="text-2xl font-bold text-gray-800">{extraStats?.missing_slots_7_days ?? 0} days</span>
                    </div>
                </div>

                {/* 7-day Coverage progress */}
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div className="flex justify-between items-center mb-2">
                        <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">7-Day Coverage Score</span>
                        <span className="text-sm font-bold text-indigo-600">{extraStats?.coverage_percent ?? 0}%</span>
                    </div>
                    <div className="w-full bg-gray-100 rounded-full h-3">
                        <div 
                            className="bg-indigo-600 h-3 rounded-full transition-all duration-500" 
                            style={{ width: `${extraStats?.coverage_percent ?? 0}%` }}
                        ></div>
                    </div>
                    <span className="text-[10px] text-gray-400 block mt-2">Target: 3 posts/day (21 total slots scheduled over 7 days)</span>
                </div>
            </div>

            {/* Quick Actions Shortcuts */}
            <div className="mt-6 rounded-xl border border-indigo-100 bg-indigo-50/30 p-5 shadow-sm">
                <h3 className="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-3">⚡ Quick Actions</h3>
                <div className="flex flex-wrap gap-3">
                    <Link href="/pexels" className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 shadow-sm transition">
                        🔍 Find Pexels Media
                    </Link>
                    <Link href="/queue" className="inline-flex items-center gap-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold px-4 py-2 shadow-sm transition">
                        📋 View Queue
                    </Link>
                    <Link href="/calendar" className="inline-flex items-center gap-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold px-4 py-2 shadow-sm transition">
                        🗓 Open Calendar
                    </Link>
                    <Link href="/settings" className="inline-flex items-center gap-2 rounded-lg bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold px-4 py-2 shadow-sm transition">
                        ⚙️ Edit Settings
                    </Link>
                </div>
            </div>

            {/* Upcoming Schedules & Failed Post Alerts */}
            <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Next scheduled posts */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
                        ⏰ Upcoming Publications
                    </h2>
                    {extraStats?.next_scheduled_posts && extraStats.next_scheduled_posts.length > 0 ? (
                        <div className="space-y-4">
                            {extraStats.next_scheduled_posts.map(post => (
                                <Link 
                                    key={post.id} 
                                    href={`/queue/${post.id}/edit`}
                                    className="flex items-center gap-3 p-3 rounded-lg border border-gray-50 bg-gray-50/50 hover:bg-indigo-50/40 hover:border-indigo-100 transition group text-left"
                                >
                                    {post.thumbnail_url ? (
                                        <img src={post.thumbnail_url} className="h-10 w-10 object-cover rounded-md flex-shrink-0" />
                                    ) : (
                                        <div className="h-10 w-10 flex items-center justify-center bg-gray-100 rounded-md text-sm">📝</div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-semibold text-gray-700 group-hover:text-indigo-800">{post.caption || 'No caption'}</p>
                                        <span className="text-[10px] text-gray-400 mt-1 block">Scheduled: {post.scheduled_at}</span>
                                    </div>
                                    <span className="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full font-bold uppercase">{post.topic_name}</span>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center text-xs text-gray-400 py-6">No upcoming approved posts scheduled.</div>
                    )}
                </div>

                {/* Failed posts needing attention */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
                        ⚠ Failed Posts (Need Attention)
                    </h2>
                    {extraStats?.failed_posts && extraStats.failed_posts.length > 0 ? (
                        <div className="space-y-4">
                            {extraStats.failed_posts.map(post => (
                                <Link 
                                    key={post.id} 
                                    href={`/queue/${post.id}/edit`}
                                    className="flex items-center gap-3 p-3 rounded-lg border border-rose-50 bg-rose-50/20 hover:bg-rose-50/40 hover:border-rose-100 transition group text-left"
                                >
                                    {post.thumbnail_url ? (
                                        <img src={post.thumbnail_url} className="h-10 w-10 object-cover rounded-md flex-shrink-0" />
                                    ) : (
                                        <div className="h-10 w-10 flex items-center justify-center bg-gray-100 rounded-md text-sm">📝</div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-semibold text-rose-800">{post.caption || 'No caption'}</p>
                                        <span className="text-[10px] text-rose-500 mt-1 block truncate">Error: {post.error_message || 'Unknown error'}</span>
                                    </div>
                                    <span className="text-[10px] bg-rose-100 text-rose-800 px-2.5 py-0.5 rounded-full font-bold uppercase">Fix ➔</span>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center text-xs text-gray-400 py-6">Great! No failed posts found.</div>
                    )}
                </div>
            </div>

            {/* Page Insights & AI Audit Section */}
            <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
                {/* Insights Panel */}
                <div className="lg:col-span-1 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            📊 Page Insights
                        </h2>
                        <button
                            onClick={handleSyncInsights}
                            disabled={isSyncing}
                            className="text-xs px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition disabled:bg-gray-400 cursor-pointer"
                        >
                            {isSyncing ? 'Syncing...' : '🔄 Sync Insights'}
                        </button>
                    </div>

                    {insights && insights.length > 0 ? (
                        <div className="space-y-4">
                            <div className="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span className="text-xs font-semibold text-gray-400 uppercase">Total Page Followers</span>
                                <p className="text-2xl font-bold text-gray-800 mt-1">
                                    {(latestInsight?.followers ?? 0).toLocaleString()}
                                </p>
                            </div>
                            <div className="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span className="text-xs font-semibold text-gray-400 uppercase">Recent Impressions (7d)</span>
                                <p className="text-2xl font-bold text-gray-800 mt-1">
                                    {insights.reduce((acc, curr) => acc + curr.impressions, 0).toLocaleString()}
                                </p>
                            </div>
                            <div className="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span className="text-xs font-semibold text-gray-400 uppercase">Recent Post Engagements (7d)</span>
                                <p className="text-2xl font-bold text-gray-800 mt-1">
                                    {insights.reduce((acc, curr) => acc + curr.engagements, 0).toLocaleString()}
                                </p>
                            </div>
                            <p className="text-xs text-gray-400 italic text-right">
                                Last synced: {latestInsight ? new Date(latestInsight.date).toLocaleDateString() : 'Never'}
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-12 text-center text-gray-400">
                            <span className="text-4xl mb-2">📉</span>
                            <p className="text-sm">No insights synchronized yet.</p>
                            <button
                                onClick={handleSyncInsights}
                                disabled={isSyncing}
                                className="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 transition disabled:bg-gray-400 cursor-pointer"
                            >
                                {isSyncing ? 'Syncing...' : 'Sync Insights Now'}
                            </button>
                        </div>
                    )}
                </div>

                {/* AI Audit Panel */}
                <div className="lg:col-span-2 rounded-xl border border-gray-100 bg-white p-6 shadow-sm flex flex-col">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            🤖 AI Page Audit
                        </h2>
                        <button
                            onClick={handleRunAudit}
                            disabled={isAuditing}
                            className="text-xs px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition disabled:bg-gray-400 cursor-pointer"
                        >
                            {isAuditing ? 'Analyzing...' : '⚡ Run Page Audit'}
                        </button>
                    </div>

                    {audit ? (
                        <div className="flex-1 grid grid-cols-1 md:grid-cols-4 gap-6">
                            {/* Score Display */}
                            <div className="md:col-span-1 flex flex-col items-center justify-center p-4 rounded-xl bg-gradient-to-br from-purple-50 to-indigo-50 border border-indigo-100 text-center">
                                <span className="text-xs font-semibold text-indigo-500 uppercase tracking-wider mb-2">Page Score</span>
                                <div className={`relative flex items-center justify-center w-24 h-24 rounded-full border-4 ${
                                    audit.score >= 80 ? 'border-emerald-400 text-emerald-600 bg-emerald-50' : 
                                    audit.score >= 50 ? 'border-amber-400 text-amber-600 bg-amber-50' : 
                                    'border-rose-400 text-rose-600 bg-rose-50'
                                }`}>
                                    <span className="text-3xl font-extrabold">{audit.score}</span>
                                </div>
                                <span className="text-xs text-gray-400 mt-3">Audit run:<br/>{audit.created_at}</span>
                            </div>

                            {/* Strengths & Suggestions */}
                            <div className="md:col-span-3 space-y-4">
                                <div>
                                    <span className="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1.5">🌟 Key Strengths</span>
                                    <ul className="list-disc pl-4 space-y-1 text-sm text-gray-700">
                                        {audit.strengths && audit.strengths.map((str, idx) => (
                                            <li key={idx}>{str}</li>
                                        ))}
                                    </ul>
                                </div>

                                <div>
                                    <span className="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1.5">⚠️ Weaknesses</span>
                                    <ul className="list-disc pl-4 space-y-1 text-sm text-gray-700">
                                        {audit.weaknesses && audit.weaknesses.map((weak, idx) => (
                                            <li key={idx}>{weak}</li>
                                        ))}
                                    </ul>
                                </div>

                                <div>
                                    <span className="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1.5">💡 AI Recommendations</span>
                                    <ul className="list-disc pl-4 space-y-1 text-sm text-gray-700">
                                        {audit.suggestions && audit.suggestions.map((sug, idx) => (
                                            <li key={idx} className="text-indigo-700 font-medium">{sug}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center py-12 text-center text-gray-400">
                            <span className="text-4xl mb-2">🧠</span>
                            <p className="text-sm">No page audit report generated yet.</p>
                            <button
                                onClick={handleRunAudit}
                                disabled={isAuditing}
                                className="mt-4 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:bg-gray-400 cursor-pointer"
                            >
                                {isAuditing ? 'Analyzing...' : 'Run First Audit'}
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* Recent posts table */}
            <div className="mt-8">
                <h2 className="mb-4 text-lg font-semibold text-gray-800">
                    Recent Scheduled Posts
                </h2>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
                    {recentPosts && recentPosts.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Caption
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Type
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Scheduled At
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {recentPosts.map((post, idx) => (
                                    <tr
                                        key={post.id}
                                        className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}
                                    >
                                        <td className="max-w-xs truncate px-6 py-4 text-sm text-gray-700">
                                            {post.caption || 'No caption'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500 capitalize">
                                            {post.media_type || 'Text'}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {post.scheduled_at
                                                ? new Date(post.scheduled_at).toLocaleString()
                                                : '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <StatusBadge status={post.status} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="px-6 py-12 text-center text-sm text-gray-400">
                            No scheduled posts yet. Search for media on Pexels to get started!
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
