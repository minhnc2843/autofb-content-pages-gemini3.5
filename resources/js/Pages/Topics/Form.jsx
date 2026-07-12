import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';

export default function Form({ topic }) {
    const isEditing = !!topic;

    const { data, setData, post, put, processing, errors } = useForm({
        name: topic?.name || '',
        keyword: topic?.keyword || '',
        language: topic?.language || 'english',
        media_type: topic?.media_type || 'photo',
        is_active: topic?.is_active ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/topics/${topic.id}`);
        } else {
            post('/topics');
        }
    };

    return (
        <AppLayout title={isEditing ? 'Edit Topic' : 'Create Topic'}>
            <div className="mx-auto max-w-2xl">
                <div className="rounded-xl bg-white p-6 shadow-sm">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Name */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Name
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="e.g. Travel Photography"
                            />
                            {errors.name && (
                                <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                            )}
                        </div>

                        {/* Keyword */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Keyword
                            </label>
                            <input
                                type="text"
                                value={data.keyword}
                                onChange={(e) => setData('keyword', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                placeholder="e.g. nature landscape"
                            />
                            {errors.keyword && (
                                <p className="mt-1 text-xs text-red-600">{errors.keyword}</p>
                            )}
                        </div>

                        {/* Language */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Language
                            </label>
                            <select
                                value={data.language}
                                onChange={(e) => setData('language', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="english">English</option>
                                <option value="thai">Thai</option>
                                <option value="lao">Lao</option>
                                <option value="khmer">Khmer</option>
                                <option value="vietnamese">Vietnamese</option>
                            </select>
                            {errors.language && (
                                <p className="mt-1 text-xs text-red-600">{errors.language}</p>
                            )}
                        </div>

                        {/* Media Type */}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Media Type
                            </label>
                            <select
                                value={data.media_type}
                                onChange={(e) => setData('media_type', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option value="photo">Photo</option>
                                <option value="video">Video</option>
                                <option value="both">Both</option>
                            </select>
                            {errors.media_type && (
                                <p className="mt-1 text-xs text-red-600">{errors.media_type}</p>
                            )}
                        </div>

                        {/* Is Active */}
                        <div className="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">
                                Active
                            </label>
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-3 pt-4 border-t border-gray-100">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : isEditing ? 'Update Topic' : 'Create Topic'}
                            </button>
                            <Link
                                href="/topics"
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
