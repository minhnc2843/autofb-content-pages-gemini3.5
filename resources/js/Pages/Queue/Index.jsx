import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Index({ posts, publishMode }) {
    const [publishingId, setPublishingId] = useState(null);
    const [showPublishModal, setShowPublishModal] = useState(false);
    const [selectedPost, setSelectedPost] = useState(null);

    const handleApprove = (id) => {
        router.patch(`/queue/${id}/approve`, {}, { preserveScroll: true });
    };

    const handleUnapprove = (id) => {
        router.patch(`/queue/${id}/unapprove`, {}, { preserveScroll: true });
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this post?')) {
            router.delete(`/queue/${id}`, { preserveScroll: true });
        }
    };

    const openPublishModal = (post) => {
        setSelectedPost(post);
        setShowPublishModal(true);
    };

    const handlePublishNow = () => {
        if (!selectedPost) return;
        setPublishingId(selectedPost.id);
        setShowPublishModal(false);
        router.post(`/queue/${selectedPost.id}/publish-now`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setPublishingId(null);
                setSelectedPost(null);
            },
        });
    };

    return (
        <AppLayout title="Post Queue">
            {/* Publish Mode Indicator */}
            <div className={`mb-4 rounded-lg border px-4 py-2 text-sm ${
                publishMode === 'real'
                    ? 'border-red-200 bg-red-50 text-red-700'
                    : 'border-blue-200 bg-blue-50 text-blue-700'
            }`}>
                <strong>Publish Mode:</strong>{' '}
                {publishMode === 'real' ? (
                    <>🔴 REAL — Posts will be published to Facebook via Graph API</>
                ) : (
                    <>🔵 FAKE — Posts will be fake-published (no Facebook API call)</>
                )}
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                {posts && posts.length > 0 ? (
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Media
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Caption
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Scheduled At
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
                            {posts.map((post, idx) => (
                                <tr
                                    key={post.id}
                                    className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}
                                >
                                    <td className="px-6 py-4">
                                        {post.thumbnail_url ? (
                                            <div className="h-12 w-12 overflow-hidden rounded-md bg-gray-200">
                                                <img
                                                    src={post.thumbnail_url}
                                                    alt="Media"
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex h-12 w-12 items-center justify-center rounded-md bg-gray-100 text-gray-400">
                                                📝
                                            </div>
                                        )}
                                    </td>
                                    <td className="max-w-xs px-6 py-4">
                                        <p className="truncate text-sm text-gray-700">
                                            {post.caption || 'No caption'}
                                        </p>
                                        {post.facebook_post_id && (
                                            <p className="mt-1 text-xs text-green-600">
                                                FB ID: {post.facebook_post_id}
                                            </p>
                                        )}
                                        {post.error_message && (
                                            <p className="mt-1 truncate text-xs text-red-500" title={post.error_message}>
                                                ⚠ {post.error_message}
                                            </p>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {post.scheduled_at
                                            ? new Date(post.scheduled_at).toLocaleString()
                                            : '—'}
                                    </td>
                                    <td className="px-6 py-4">
                                        <StatusBadge status={post.status} />
                                    </td>
                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        {/* Draft actions */}
                                        {post.status === 'draft' && (
                                            <>
                                                <Link
                                                    href={`/queue/${post.id}/edit`}
                                                    className="font-medium text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => handleApprove(post.id)}
                                                    className="ml-3 font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(post.id)}
                                                    className="ml-3 font-medium text-red-600 hover:text-red-800"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}

                                        {/* Approved actions */}
                                        {post.status === 'approved' && (
                                            <>
                                                <Link
                                                    href={`/queue/${post.id}/edit`}
                                                    className="font-medium text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => openPublishModal(post)}
                                                    disabled={publishingId === post.id}
                                                    className="ml-3 font-medium text-green-600 hover:text-green-800 disabled:opacity-50"
                                                >
                                                    {publishingId === post.id ? 'Publishing...' : '🚀 Publish Now'}
                                                </button>
                                                <button
                                                    onClick={() => handleUnapprove(post.id)}
                                                    className="ml-3 font-medium text-yellow-600 hover:text-yellow-800"
                                                >
                                                    Unapprove
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(post.id)}
                                                    className="ml-3 font-medium text-red-600 hover:text-red-800"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}

                                        {/* Published / Published fake - view only */}
                                        {(post.status === 'published_fake' || post.status === 'published') && (
                                            <span className="text-xs italic text-gray-400">
                                                {post.status === 'published' ? '✅ Published' : 'Fake published'}
                                            </span>
                                        )}

                                        {/* Failed actions */}
                                        {post.status === 'failed' && (
                                            <>
                                                <Link
                                                    href={`/queue/${post.id}/edit`}
                                                    className="font-medium text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Edit
                                                </Link>
                                                <button
                                                    onClick={() => handleDelete(post.id)}
                                                    className="ml-3 font-medium text-red-600 hover:text-red-800"
                                                >
                                                    Delete
                                                </button>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-400">
                        No posts in the queue. Search Pexels to create draft posts!
                    </div>
                )}
            </div>

            {/* Publish Confirmation Modal */}
            {showPublishModal && selectedPost && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-gray-800">
                            Confirm Publish
                        </h3>
                        <div className={`mt-3 rounded-lg border px-3 py-2 text-sm ${
                            publishMode === 'real'
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-blue-200 bg-blue-50 text-blue-700'
                        }`}>
                            {publishMode === 'real' ? (
                                <>🔴 <strong>REAL MODE</strong> — This will publish to your Facebook Page via Graph API.</>
                            ) : (
                                <>🔵 <strong>FAKE MODE</strong> — This will fake-publish (no Facebook API call).</>
                            )}
                        </div>
                        <p className="mt-3 text-sm text-gray-600">
                            Post #{selectedPost.id}: {selectedPost.caption?.substring(0, 80)}...
                        </p>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => { setShowPublishModal(false); setSelectedPost(null); }}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handlePublishNow}
                                className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${
                                    publishMode === 'real'
                                        ? 'bg-red-600 hover:bg-red-700'
                                        : 'bg-green-600 hover:bg-green-700'
                                }`}
                            >
                                {publishMode === 'real' ? '🚀 Publish to Facebook' : '✅ Fake Publish'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
