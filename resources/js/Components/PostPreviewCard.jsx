import StatusBadge from './StatusBadge';

export default function PostPreviewCard({ post }) {
    const caption = post.caption
        ? post.caption.length > 80
            ? post.caption.substring(0, 80) + '…'
            : post.caption
        : 'No caption';

    return (
        <div className="flex gap-3 rounded-lg bg-white p-3 shadow-sm">
            {/* Thumbnail */}
            {post.media_url && (
                <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md bg-gray-200">
                    <img
                        src={post.media_url}
                        alt="Post media"
                        className="h-full w-full object-cover"
                    />
                </div>
            )}

            {/* Info */}
            <div className="flex flex-1 flex-col justify-center overflow-hidden">
                <p className="truncate text-sm text-gray-800">{caption}</p>
                <div className="mt-1 flex items-center gap-2">
                    {post.scheduled_at && (
                        <span className="text-xs text-gray-500">
                            🕐 {new Date(post.scheduled_at).toLocaleString()}
                        </span>
                    )}
                    <StatusBadge status={post.status} />
                </div>
            </div>
        </div>
    );
}
