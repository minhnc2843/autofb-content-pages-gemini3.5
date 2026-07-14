import { Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Edit({ post }) {
    const [approving, setApproving] = useState(false);
    const [publishing, setPublishing] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        caption: post.caption || '',
        scheduled_at: post.scheduled_at
            ? post.scheduled_at.replace(' ', 'T').substring(0, 16)
            : '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/queue/${post.id}`, { preserveScroll: true });
    };

    const handleSaveAndApprove = (e) => {
        e.preventDefault();
        router.put(`/queue/${post.id}`, {
            caption: data.caption,
            scheduled_at: data.scheduled_at,
            approve_after_save: true
        }, {
            preserveScroll: true,
        });
    };

    const handleApproveOnly = () => {
        setApproving(true);
        router.patch(`/queue/${post.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => setApproving(false)
        });
    };

    const handleUnapprove = () => {
        setApproving(true);
        router.patch(`/queue/${post.id}/unapprove`, {}, {
            preserveScroll: true,
            onFinish: () => setApproving(false)
        });
    };

    const handlePublishNow = () => {
        if (confirm("Are you sure you want to publish this post immediately to Facebook?")) {
            setPublishing(true);
            router.post(`/queue/${post.id}/publish-now`, {}, {
                preserveScroll: true,
                onFinish: () => setPublishing(false)
            });
        }
    };

    return (
        <AppLayout title="Edit Post">
            <div className="mx-auto max-w-3xl">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    {/* Status display */}
                    <div className="mb-6 flex flex-col gap-2 rounded-lg bg-gray-50 p-4 border border-gray-200">
                        <div className="flex items-center gap-3">
                            <span className="text-sm font-medium text-gray-500">Current Status:</span>
                            <StatusBadge status={post.status} />
                        </div>
                        {post.status === 'draft' && (
                            <p className="text-xs text-amber-600 font-medium mt-1">
                                ⚠️ This post will not publish until approved.
                            </p>
                        )}
                        {post.status === 'approved' && (
                            <p className="text-xs text-blue-600 font-medium mt-1">
                                ℹ️ This post can publish when scheduled command runs, or you can publish now.
                            </p>
                        )}
                    </div>

                    {/* Media preview */}
                    {(post.thumbnail_url || post.media_url) && (
                        <div className="mb-6">
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Media Preview ({post.media_type || 'photo'})
                            </label>
                            <div className="w-full max-w-md overflow-hidden rounded-lg bg-gray-100 p-2 border border-gray-200">
                                {post.media_type === 'video' && post.media_url ? (
                                    <div className="space-y-2">
                                        <video
                                            src={post.media_url}
                                            poster={post.thumbnail_url}
                                            controls
                                            className="w-full rounded-lg max-h-[300px]"
                                        />
                                        <a
                                            href={post.media_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="block text-center text-xs font-semibold text-indigo-600 hover:underline"
                                        >
                                            🎬 Open Direct Video Link ↗
                                        </a>
                                    </div>
                                ) : (
                                    <img
                                        src={post.thumbnail_url || post.media_url}
                                        alt="Post media"
                                        className="w-full object-contain max-h-[400px] rounded-lg"
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Caption */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Caption
                            </label>
                            <textarea
                                value={data.caption}
                                onChange={(e) => setData('caption', e.target.value)}
                                rows={6}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="Write your post caption..."
                            />
                            {errors.caption && (
                                <p className="mt-1 text-xs text-red-600">{errors.caption}</p>
                            )}
                        </div>

                        {/* Scheduled At */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Scheduled At
                            </label>
                            <input
                                type="datetime-local"
                                value={data.scheduled_at}
                                onChange={(e) => setData('scheduled_at', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:w-auto"
                            />
                            {errors.scheduled_at && (
                                <p className="mt-1 text-xs text-red-600">{errors.scheduled_at}</p>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                            {/* Published / Published Fake: view only */}
                            {(post.status === 'published' || post.status === 'published_fake') ? (
                                <Link
                                    href="/queue"
                                    className="rounded-lg border border-gray-300 bg-gray-50 px-5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
                                >
                                    Back to Queue
                                </Link>
                            ) : (
                                <>
                                    {/* Standard Save Changes */}
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50 cursor-pointer"
                                    >
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </button>

                                    {/* Draft status actions */}
                                    {post.status === 'draft' && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={handleSaveAndApprove}
                                                className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 cursor-pointer"
                                            >
                                                Save & Approve
                                            </button>
                                            <button
                                                type="button"
                                                disabled={approving}
                                                onClick={handleApproveOnly}
                                                className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                                            >
                                                {approving ? 'Approving...' : 'Approve Now'}
                                            </button>
                                        </>
                                    )}

                                    {/* Approved status actions */}
                                    {post.status === 'approved' && (
                                        <>
                                            <button
                                                type="button"
                                                disabled={publishing}
                                                onClick={handlePublishNow}
                                                className="rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50 cursor-pointer"
                                            >
                                                {publishing ? 'Publishing...' : '🚀 Publish Now'}
                                            </button>
                                            <button
                                                type="button"
                                                disabled={approving}
                                                onClick={handleUnapprove}
                                                className="rounded-lg bg-yellow-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-yellow-700 disabled:opacity-50 cursor-pointer"
                                            >
                                                {approving ? 'Unapproving...' : 'Unapprove'}
                                            </button>
                                        </>
                                    )}

                                    {/* Failed status actions */}
                                    {post.status === 'failed' && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={handleSaveAndApprove}
                                                className="rounded-lg bg-teal-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-teal-700 cursor-pointer"
                                            >
                                                Save & Retry as Approved
                                            </button>
                                            <button
                                                type="button"
                                                disabled={approving}
                                                onClick={handleApproveOnly}
                                                className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50 cursor-pointer"
                                            >
                                                {approving ? 'Approving...' : 'Approve Now'}
                                            </button>
                                        </>
                                    )}

                                    <Link
                                        href="/queue"
                                        className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                    >
                                        Cancel
                                    </Link>
                                </>
                            )}
                        </div>
                    </form>

                    {/* Status History Timeline */}
                    {post.status_history && post.status_history.length > 0 && (
                        <div className="mt-8 border-t border-gray-200 pt-6">
                            <h3 className="mb-4 text-xs font-bold text-gray-500 uppercase tracking-wider">
                                ⏳ Status Transition History
                            </h3>
                            <div className="flow-root">
                                <ul className="-mb-8">
                                    {post.status_history.map((log, idx) => (
                                        <li key={idx}>
                                            <div className="relative pb-6">
                                                {idx !== post.status_history.length - 1 ? (
                                                    <span
                                                        className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                                        aria-hidden="true"
                                                    />
                                                ) : null}
                                                <div className="relative flex space-x-3">
                                                    <div>
                                                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 border border-indigo-100 ring-8 ring-white text-xs">
                                                            🔄
                                                        </span>
                                                    </div>
                                                    <div className="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                        <div>
                                                            <p className="text-sm text-gray-600">
                                                                Transitioned from <span className="font-semibold text-gray-800 capitalize">{log.from_status.replace('_', ' ')}</span> to{' '}
                                                                <span className="font-semibold text-indigo-700 capitalize">{log.to_status.replace('_', ' ')}</span>
                                                            </p>
                                                            <p className="text-xs text-gray-400">
                                                                Operator: <span className="font-medium text-gray-600">{log.changed_by}</span>
                                                            </p>
                                                        </div>
                                                        <div className="whitespace-nowrap text-right text-xs text-gray-400">
                                                            <span>{log.created_at}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
