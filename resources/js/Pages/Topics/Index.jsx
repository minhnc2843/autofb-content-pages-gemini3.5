import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';

export default function Index({ topics }) {
    const handleToggle = (id) => {
        router.patch(`/topics/${id}/toggle`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (id, name) => {
        if (confirm(`Are you sure you want to delete the topic "${name}"?`)) {
            router.delete(`/topics/${id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout title="Topics">
            <div className="mb-6 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                    Manage your content topics and keywords.
                </p>
                <Link
                    href="/topics/create"
                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    + Create Topic
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                {topics && topics.length > 0 ? (
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Name
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Keyword
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Language
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Media Type
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {topics.map((topic, idx) => (
                                <tr
                                    key={topic.id}
                                    className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}
                                >
                                    <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        {topic.name}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {topic.keyword}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm capitalize text-gray-500">
                                        {topic.language}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm capitalize text-gray-500">
                                        {topic.media_type}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4">
                                        <button
                                            onClick={() => handleToggle(topic.id)}
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                                topic.is_active ? 'bg-indigo-600' : 'bg-gray-300'
                                            }`}
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                    topic.is_active ? 'translate-x-6' : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <Link
                                            href={`/topics/${topic.id}/edit`}
                                            className="font-medium text-indigo-600 hover:text-indigo-800"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            onClick={() => handleDelete(topic.id, topic.name)}
                                            className="ml-4 font-medium text-red-600 hover:text-red-800"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-400">
                        No topics yet. Create one to start planning content!
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
