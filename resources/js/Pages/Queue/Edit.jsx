import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Edit({ post }) {
    const { data, setData, put, processing, errors } = useForm({
        caption: post.caption || '',
        scheduled_at: post.scheduled_at
            ? post.scheduled_at.replace(' ', 'T').substring(0, 16)
            : '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/queue/${post.id}`);
    };

    return (
        <AppLayout title="Edit Post">
            <div className="mx-auto max-w-3xl">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    {/* Status display */}
                    <div className="mb-6 flex items-center gap-3">
                        <span className="text-sm font-medium text-gray-500">Current Status:</span>
                        <StatusBadge status={post.status} />
                    </div>

                    {/* Media preview */}
                    {(post.thumbnail_url || post.media_url) && (
                        <div className="mb-6">
                            <label className="mb-2 block text-sm font-medium text-gray-700">
                                Media Preview
                            </label>
                            <div className="w-full max-w-md overflow-hidden rounded-lg bg-gray-100">
                                <img
                                    src={post.thumbnail_url || post.media_url}
                                    alt="Post media"
                                    className="w-full object-contain"
                                />
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
                        <div className="flex items-center gap-3 border-t border-gray-100 pt-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Save Changes'}
                            </button>
                            <Link
                                href="/queue"
                                className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
