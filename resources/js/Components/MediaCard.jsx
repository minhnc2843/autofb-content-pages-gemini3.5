export default function MediaCard({ media, onCreateDraft, creating }) {
    const isVideo = media.type === 'video';
    const thumbnail = isVideo ? (media.video_pictures?.[0]?.picture || media.image) : media.src?.medium || media.src?.original;
    const dimensions = isVideo
        ? `${media.width}×${media.height}`
        : `${media.width}×${media.height}`;

    return (
        <div className="group overflow-hidden rounded-xl bg-white shadow-sm transition-shadow hover:shadow-md">
            {/* Thumbnail */}
            <div className="relative aspect-video overflow-hidden bg-gray-200">
                <img
                    src={thumbnail}
                    alt={media.alt || media.url || 'Pexels media'}
                    className="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
                {/* Type badge */}
                <span
                    className={`absolute top-2 right-2 rounded-full px-2 py-0.5 text-xs font-semibold uppercase text-white ${
                        isVideo ? 'bg-purple-600' : 'bg-blue-600'
                    }`}
                >
                    {isVideo ? '🎬 Video' : '📷 Photo'}
                </span>
            </div>

            {/* Info */}
            <div className="p-4">
                <p className="text-sm font-medium text-gray-800 truncate">
                    📸 {media.photographer || media.user?.name || 'Unknown'}
                </p>
                <p className="mt-1 text-xs text-gray-500">{dimensions}</p>

                <a
                    href={media.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1 inline-block text-xs text-indigo-600 hover:underline"
                >
                    View on Pexels ↗
                </a>

                <button
                    onClick={() => onCreateDraft(media)}
                    disabled={creating}
                    className="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {creating ? (
                        <>
                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Creating...
                        </>
                    ) : (
                        'Create Draft Post'
                    )}
                </button>
            </div>
        </div>
    );
}
