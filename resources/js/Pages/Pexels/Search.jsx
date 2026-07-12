import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import MediaCard from '../../Components/MediaCard';

export default function Search({ results, error }) {
    const { data, setData, post, processing } = useForm({
        keyword: '',
        media_type: 'photo',
    });

    const [creatingId, setCreatingId] = useState(null);

    const handleSearch = (e) => {
        e.preventDefault();
        post('/pexels/search', {
            preserveScroll: true,
        });
    };

    const handleCreateDraft = (media) => {
        setCreatingId(media.id);
        router.post('/pexels/create-draft', {
            pexels_id: media.id,
            type: media.type || 'photo',
            url: media.url,
            media_url: media.src?.original || media.src?.large || media.video_files?.[0]?.link || '',
            thumbnail_url: media.src?.medium || media.video_pictures?.[0]?.picture || media.image || '',
            photographer: media.photographer || media.user?.name || 'Unknown',
            width: media.width,
            height: media.height,
        }, {
            preserveScroll: true,
            onFinish: () => setCreatingId(null),
        });
    };

    return (
        <AppLayout title="Pexels Search">
            {/* Search form */}
            <div className="mb-8 rounded-xl bg-white p-6 shadow-sm">
                <form onSubmit={handleSearch} className="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <label className="mb-1 block text-sm font-medium text-gray-700">
                            Keyword
                        </label>
                        <input
                            type="text"
                            value={data.keyword}
                            onChange={(e) => setData('keyword', e.target.value)}
                            placeholder="Search for photos or videos..."
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div className="w-full sm:w-40">
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
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {processing ? (
                            <>
                                <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                Searching...
                            </>
                        ) : (
                            '🔍 Search'
                        )}
                    </button>
                </form>
            </div>

            {/* Error state */}
            {error && (
                <div className="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            {/* Results grid */}
            {results && results.length > 0 && (
                <div>
                    <p className="mb-4 text-sm text-gray-500">
                        Found {results.length} result{results.length !== 1 ? 's' : ''}
                    </p>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {results.map((media) => (
                            <MediaCard
                                key={media.id}
                                media={media}
                                onCreateDraft={handleCreateDraft}
                                creating={creatingId === media.id}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* Empty results */}
            {results && results.length === 0 && (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm">
                    <p className="text-gray-400">No results found. Try a different keyword.</p>
                </div>
            )}

            {/* Initial state */}
            {!results && !error && (
                <div className="rounded-xl bg-white py-12 text-center shadow-sm">
                    <p className="text-4xl">🔍</p>
                    <p className="mt-2 text-gray-400">
                        Enter a keyword above to search for Pexels media.
                    </p>
                </div>
            )}
        </AppLayout>
    );
}
