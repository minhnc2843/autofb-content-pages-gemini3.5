import AppLayout from '../Components/AppLayout';
import StatusBadge from '../Components/StatusBadge';

export default function Dashboard({ stats, recentPosts }) {
    const statCards = [
        {
            label: 'Draft Posts',
            count: stats?.draft ?? 0,
            icon: '📝',
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            text: 'text-yellow-700',
        },
        {
            label: 'Approved Posts',
            count: stats?.approved ?? 0,
            icon: '✅',
            bg: 'bg-blue-50',
            border: 'border-blue-200',
            text: 'text-blue-700',
        },
        {
            label: 'Published (Fake)',
            count: stats?.published_fake ?? 0,
            icon: '🚀',
            bg: 'bg-green-50',
            border: 'border-green-200',
            text: 'text-green-700',
        },
        {
            label: 'Failed Posts',
            count: stats?.failed ?? 0,
            icon: '❌',
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-700',
        },
    ];

    return (
        <AppLayout title="Dashboard">
            {/* Stat cards */}
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {statCards.map((card) => (
                    <div
                        key={card.label}
                        className={`rounded-xl border bg-white p-6 shadow-sm ${card.border}`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-3xl">{card.icon}</span>
                            <span className={`text-3xl font-bold ${card.text}`}>
                                {card.count}
                            </span>
                        </div>
                        <p className="mt-2 text-sm font-medium text-gray-600">{card.label}</p>
                    </div>
                ))}
            </div>

            {/* Recent posts table */}
            <div className="mt-8">
                <h2 className="mb-4 text-lg font-semibold text-gray-800">
                    Recent Scheduled Posts
                </h2>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                    {recentPosts && recentPosts.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        Caption
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
                                        className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}
                                    >
                                        <td className="max-w-xs truncate px-6 py-4 text-sm text-gray-700">
                                            {post.caption || 'No caption'}
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
