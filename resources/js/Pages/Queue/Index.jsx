import { Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '../../Components/AppLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Index({ posts, publishMode, filters, topics }) {
    // Selection state for batch actions
    const [selectedIds, setSelectedIds] = useState([]);
    const [showRescheduleModal, setShowRescheduleModal] = useState(false);
    const [batchRescheduleDate, setBatchRescheduleDate] = useState('');

    const [publishingId, setPublishingId] = useState(null);
    const [showPublishModal, setShowPublishModal] = useState(false);
    const [selectedPost, setSelectedPost] = useState(null);
    
    // AI scoring states
    const [analyzingId, setAnalyzingId] = useState(null);
    const [showAiModal, setShowAiModal] = useState(false);
    const [selectedAiAnalysis, setSelectedAiAnalysis] = useState(null);

    // Filters local states
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [mediaTypeFilter, setMediaTypeFilter] = useState(filters?.media_type || '');
    const [topicIdFilter, setTopicIdFilter] = useState(filters?.topic_id || '');
    const [dateFromFilter, setDateFromFilter] = useState(filters?.date_from || '');
    const [dateToFilter, setDateToFilter] = useState(filters?.date_to || '');
    const [searchFilter, setSearchFilter] = useState(filters?.search || '');
    const [sortFilter, setSortFilter] = useState(filters?.sort || 'created_at_desc');

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

    const handleAnalyze = (id) => {
        setAnalyzingId(id);
        router.post(`/queue/${id}/analyze`, {}, {
            preserveScroll: true,
            onFinish: () => setAnalyzingId(null),
        });
    };

    const openAiModal = (post) => {
        setSelectedAiAnalysis({
            id: post.id,
            caption: post.caption,
            ...post.ai_analysis
        });
        setShowAiModal(true);
    };

    // Filter handlers
    const applyFilters = () => {
        router.get('/queue', {
            status: statusFilter,
            media_type: mediaTypeFilter,
            topic_id: topicIdFilter,
            date_from: dateFromFilter,
            date_to: dateToFilter,
            search: searchFilter,
            sort: sortFilter
        }, { 
            preserveState: true,
            preserveScroll: true
        });
    };

    const clearFilters = () => {
        setStatusFilter('');
        setMediaTypeFilter('');
        setTopicIdFilter('');
        setDateFromFilter('');
        setDateToFilter('');
        setSearchFilter('');
        setSortFilter('created_at_desc');
        router.get('/queue', {}, { preserveScroll: true });
    };

    // Auto apply filters on dropdown updates
    useEffect(() => {
        applyFilters();
    }, [statusFilter, mediaTypeFilter, topicIdFilter, sortFilter]);

    // Checkbox handlers
    const toggleAll = () => {
        if (selectedIds.length === posts.data.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(posts.data.map(p => p.id));
        }
    };

    const toggleOne = (id) => {
        setSelectedIds(prev =>
            prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
        );
    };

    // Batch action trigger
    const triggerBatchAction = (action, extraPayload = {}) => {
        if (selectedIds.length === 0) return;

        if (action === 'delete') {
            if (!confirm(`Are you sure you want to delete these ${selectedIds.length} draft/failed posts?`)) {
                return;
            }
        }

        router.post('/queue/batch', {
            ids: selectedIds,
            action,
            ...extraPayload
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedIds([]);
                setShowRescheduleModal(false);
                setBatchRescheduleDate('');
            }
        });
    };

    const handleBatchReschedule = (e) => {
        e.preventDefault();
        triggerBatchAction('reschedule', { scheduled_at: batchRescheduleDate });
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

            {/* Advanced Filters Panel */}
            <div className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 className="mb-4 text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                    🔍 Filter & Search Options
                </h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Search Caption */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Search Caption</label>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={searchFilter}
                                onChange={(e) => setSearchFilter(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                placeholder="Search post text..."
                                className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                            />
                            <button
                                onClick={applyFilters}
                                className="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 text-xs text-white font-medium"
                            >
                                Go
                            </button>
                        </div>
                    </div>

                    {/* Status filter */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Status</label>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="published">Published (Real)</option>
                            <option value="published_fake">Published (Fake)</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>

                    {/* Media Type filter */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Media Type</label>
                        <select
                            value={mediaTypeFilter}
                            onChange={(e) => setMediaTypeFilter(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">All Types</option>
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="text">Text only</option>
                        </select>
                    </div>

                    {/* Topic filter */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Topic</label>
                        <select
                            value={topicIdFilter}
                            onChange={(e) => setTopicIdFilter(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">All Topics</option>
                            {topics && topics.map(t => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Date range from */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">From Date</label>
                        <input
                            type="date"
                            value={dateFromFilter}
                            onChange={(e) => setDateFromFilter(e.target.value)}
                            onBlur={applyFilters}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    {/* Date range to */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">To Date</label>
                        <input
                            type="date"
                            value={dateToFilter}
                            onChange={(e) => setDateToFilter(e.target.value)}
                            onBlur={applyFilters}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    {/* Sort by */}
                    <div>
                        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Sort By</label>
                        <select
                            value={sortFilter}
                            onChange={(e) => setSortFilter(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="created_at_desc">Created: Newest First</option>
                            <option value="created_at_asc">Created: Oldest First</option>
                            <option value="scheduled_at_asc">Schedule: Soonest First</option>
                            <option value="scheduled_at_desc">Schedule: Latest First</option>
                        </select>
                    </div>

                    {/* Reset button */}
                    <div className="flex items-end">
                        <button
                            onClick={clearFilters}
                            className="w-full rounded-lg border border-gray-300 bg-gray-50 hover:bg-gray-100 px-3 py-1.5 text-xs text-gray-700 font-semibold transition"
                        >
                            Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            {/* Queue Table */}
            <div className="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-200 mb-20">
                {posts && posts.data && posts.data.length > 0 ? (
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left w-10">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.length === posts.data.length && posts.data.length > 0}
                                        onChange={toggleAll}
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
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
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    AI Score
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {posts.data.map((post, idx) => {
                                const isChecked = selectedIds.includes(post.id);
                                return (
                                    <tr
                                        key={post.id}
                                        className={`${isChecked ? 'bg-indigo-50/30' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}`}
                                    >
                                        <td className="px-6 py-4">
                                            <input
                                                type="checkbox"
                                                checked={isChecked}
                                                onChange={() => toggleOne(post.id)}
                                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                            />
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                {post.thumbnail_url ? (
                                                    <div className="relative h-12 w-12 flex-shrink-0 overflow-hidden rounded-md bg-gray-200">
                                                        <img
                                                            src={post.thumbnail_url}
                                                            alt="Media"
                                                            className="h-full w-full object-cover"
                                                        />
                                                        {post.media_type === 'video' && (
                                                            <span className="absolute bottom-0 right-0 rounded bg-purple-600 px-1 text-[9px] font-bold text-white">
                                                                🎬
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-400">
                                                        📝
                                                    </div>
                                                )}
                                                <div className="flex flex-col gap-0.5">
                                                    <span className={`inline-block w-max rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase ${
                                                        post.media_type === 'video' 
                                                            ? 'bg-purple-100 text-purple-800' 
                                                            : post.media_type === 'photo' 
                                                                ? 'bg-blue-100 text-blue-800' 
                                                                : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {post.media_type || 'text'}
                                                    </span>
                                                    {post.media_type === 'video' && post.media_url && (
                                                        <a
                                                            href={post.media_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="block text-xs text-indigo-600 hover:underline"
                                                        >
                                                            Play Video ↗
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="max-w-xs px-6 py-4">
                                            <p className="truncate text-sm text-gray-700" title={post.caption}>
                                                {post.caption || 'No caption'}
                                            </p>
                                            <div className="flex items-center gap-2 mt-1">
                                                <span className="text-[10px] bg-gray-100 text-gray-500 rounded px-1">{post.topic_name}</span>
                                                {post.facebook_post_id && (
                                                    <span className="text-[10px] bg-green-50 text-green-700 rounded px-1 font-medium">
                                                        FB ID: {post.facebook_post_id}
                                                    </span>
                                                )}
                                            </div>
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
                                        <td className="px-6 py-4">
                                            {post.ai_analysis ? (
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        onClick={() => openAiModal(post)}
                                                        className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-sm transition-transform hover:scale-105 border ${
                                                            post.ai_analysis.score >= 80
                                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                                                : post.ai_analysis.score >= 50
                                                                    ? 'bg-amber-100 text-amber-800 border-amber-300'
                                                                    : 'bg-rose-100 text-rose-800 border-rose-300'
                                                        }`}
                                                        title="Click to view details"
                                                    >
                                                        {post.ai_analysis.score}
                                                    </button>
                                                    <button
                                                        onClick={() => handleAnalyze(post.id)}
                                                        disabled={analyzingId === post.id}
                                                        className="text-xs text-gray-400 hover:text-indigo-600 disabled:opacity-50"
                                                        title="Re-analyze post"
                                                    >
                                                        {analyzingId === post.id ? '⏳' : '🔄'}
                                                    </button>
                                                </div>
                                            ) : (
                                                <button
                                                    onClick={() => handleAnalyze(post.id)}
                                                    disabled={analyzingId === post.id}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 border border-indigo-200 transition-colors hover:bg-indigo-100 disabled:opacity-50 cursor-pointer"
                                                >
                                                    {analyzingId === post.id ? 'Scoring...' : '🧠 Score'}
                                                </button>
                                            )}
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
                                                        className="ml-3 font-medium text-blue-600 hover:text-blue-800 cursor-pointer"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(post.id)}
                                                        className="ml-3 font-medium text-red-600 hover:text-red-800 cursor-pointer"
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
                                                        className="ml-3 font-medium text-green-600 hover:text-green-800 disabled:opacity-50 cursor-pointer"
                                                    >
                                                        {publishingId === post.id ? 'Publishing...' : '🚀 Publish Now'}
                                                    </button>
                                                    <button
                                                        onClick={() => handleUnapprove(post.id)}
                                                        className="ml-3 font-medium text-yellow-600 hover:text-yellow-800 cursor-pointer"
                                                    >
                                                        Unapprove
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(post.id)}
                                                        className="ml-3 font-medium text-red-600 hover:text-red-800 cursor-pointer"
                                                    >
                                                        Delete
                                                    </button>
                                                </>
                                            )}

                                            {/* Published / Published fake */}
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
                                                        className="ml-3 font-medium text-red-600 hover:text-red-800 cursor-pointer"
                                                    >
                                                        Delete
                                                    </button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-400">
                        No posts found matching the filters.
                    </div>
                )}
            </div>

            {/* Pagination Links */}
            {posts && posts.links && posts.links.length > 3 && (
                <div className="mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-xl shadow-sm border">
                    <div className="flex flex-1 justify-between sm:hidden">
                        {posts.prev_page_url ? (
                            <Link
                                href={posts.prev_page_url}
                                className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                        ) : (
                            <span className="relative inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400">
                                Previous
                            </span>
                        )}
                        {posts.next_page_url ? (
                            <Link
                                href={posts.next_page_url}
                                className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        ) : (
                            <span className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400">
                                Next
                            </span>
                        )}
                    </div>
                    <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm text-gray-700">
                                Showing <span className="font-medium">{posts.from || 0}</span> to <span className="font-medium">{posts.to || 0}</span> of{' '}
                                <span className="font-medium">{posts.total}</span> results
                            </p>
                        </div>
                        <div>
                            <nav className="isolate inline-flex -space-x-px rounded-md shadow-xs" aria-label="Pagination">
                                {posts.links.map((link, linkIdx) => {
                                    const label = link.label
                                        .replace('&laquo;', '«')
                                        .replace('&raquo;', '»')
                                        .replace('Previous', '« Previous')
                                        .replace('Next', 'Next »');

                                    if (!link.url) {
                                        return (
                                            <span
                                                key={linkIdx}
                                                className="relative inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-400 ring-1 ring-inset ring-gray-300 bg-gray-50"
                                            >
                                                {label}
                                            </span>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={linkIdx}
                                            href={link.url}
                                            className={`relative inline-flex items-center px-3 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:outline-offset-0 ${
                                                link.active
                                                    ? 'z-10 bg-indigo-600 text-white focus-visible:outline-2 focus-visible:outline-indigo-600'
                                                    : 'text-gray-900 hover:bg-gray-50'
                                            }`}
                                        >
                                            {label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                    </div>
                </div>
            )}

            {/* Sticky Batch Actions Toolbar */}
            {selectedIds.length > 0 && (
                <div className="fixed bottom-6 left-1/2 z-40 w-full max-w-4xl -translate-x-1/2 px-4">
                    <div className="flex items-center justify-between rounded-2xl bg-gray-900 px-6 py-4 shadow-xl border border-gray-800 text-white">
                        <div className="flex items-center gap-3">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold">
                                {selectedIds.length}
                            </span>
                            <span className="text-sm font-medium text-gray-300">posts selected</span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                onClick={() => triggerBatchAction('approve')}
                                className="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Approve Selected
                            </button>
                            <button
                                onClick={() => triggerBatchAction('unapprove')}
                                className="rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Unapprove Selected
                            </button>
                            <button
                                onClick={() => triggerBatchAction('draft')}
                                className="rounded-lg bg-gray-700 hover:bg-gray-600 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Move to Draft
                            </button>
                            <button
                                onClick={() => setShowRescheduleModal(true)}
                                className="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Reschedule
                            </button>
                            <button
                                onClick={() => triggerBatchAction('retry')}
                                className="rounded-lg bg-teal-600 hover:bg-teal-700 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Retry Failed
                            </button>
                            <button
                                onClick={() => triggerBatchAction('delete')}
                                className="rounded-lg bg-rose-600 hover:bg-rose-700 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Delete Drafts
                            </button>
                            <button
                                onClick={() => setSelectedIds([])}
                                className="rounded-lg border border-gray-700 hover:bg-gray-800 px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Batch Reschedule Modal */}
            {showRescheduleModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-semibold text-gray-800 mb-2">
                            🗓 Reschedule Selected Posts
                        </h3>
                        <p className="text-xs text-gray-500 mb-4">
                            This will update the scheduled publication date and time for all {selectedIds.length} selected posts.
                        </p>
                        <form onSubmit={handleBatchReschedule}>
                            <input
                                type="datetime-local"
                                value={batchRescheduleDate}
                                onChange={(e) => setBatchRescheduleDate(e.target.value)}
                                required
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none mb-4"
                            />
                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => { setShowRescheduleModal(false); setBatchRescheduleDate(''); }}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-medium text-white cursor-pointer"
                                >
                                    Save Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Single Publish Confirmation Modal */}
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
                        
                        {selectedPost.media_type === 'video' && (
                            <div className="mt-3 rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-xs text-purple-700">
                                🎬 <strong>Video Post:</strong> This will publish a video to your Facebook Page if real mode is enabled.
                            </div>
                        )}

                        <p className="mt-3 text-sm text-gray-600">
                            Post #{selectedPost.id}: {selectedPost.caption?.substring(0, 80)}...
                        </p>
                        <div className="mt-5 flex justify-end gap-3">
                            <button
                                onClick={() => { setShowPublishModal(false); setSelectedPost(null); }}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handlePublishNow}
                                className={`rounded-lg px-4 py-2 text-sm font-medium text-white cursor-pointer ${
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

            {/* AI Analysis Details Modal */}
            {showAiModal && selectedAiAnalysis && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl max-h-[85vh] overflow-y-auto">
                        <div className="flex items-center justify-between border-b pb-3">
                            <h3 className="text-lg font-bold text-gray-800">
                                🧠 AI Content Analysis
                            </h3>
                            <button
                                onClick={() => { setShowAiModal(false); setSelectedAiAnalysis(null); }}
                                className="text-gray-400 hover:text-gray-600 text-lg font-bold cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>
                        
                        <div className="mt-4 flex items-center gap-4">
                            <div className={`flex h-16 w-16 items-center justify-center rounded-full text-2xl font-black shadow ${
                                selectedAiAnalysis.score >= 80
                                    ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                                    : selectedAiAnalysis.score >= 50
                                        ? 'bg-amber-100 text-amber-800 border border-amber-300'
                                        : 'bg-rose-100 text-rose-800 border border-rose-300'
                            }`}>
                                {selectedAiAnalysis.score}
                            </div>
                            <div>
                                <h4 className="font-semibold text-gray-700">Optimization Score</h4>
                                <p className="text-xs text-gray-500">
                                    Based on social media best practices, formatting, and engagement hooks.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 space-y-4">
                            {/* Caption Preview */}
                            <div className="rounded-lg bg-gray-50 p-3">
                                <h4 className="text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    Caption Preview
                                </h4>
                                <p className="mt-1 text-xs text-gray-700 whitespace-pre-wrap">
                                    {selectedAiAnalysis.caption}
                                </p>
                            </div>

                            {/* Strengths */}
                            <div>
                                <h4 className="text-sm font-bold text-emerald-700 flex items-center gap-1">
                                    ✅ Strengths
                                </h4>
                                <ul className="mt-1 list-inside list-disc text-xs text-gray-600 space-y-1">
                                    {selectedAiAnalysis.strengths && selectedAiAnalysis.strengths.length > 0 ? (
                                        selectedAiAnalysis.strengths.map((str, i) => <li key={i}>{str}</li>)
                                    ) : (
                                        <li>Strong formatting and credit present.</li>
                                    )}
                                </ul>
                            </div>

                            {/* Weaknesses */}
                            <div>
                                <h4 className="text-sm font-bold text-rose-700 flex items-center gap-1">
                                    ❌ Weaknesses
                                </h4>
                                <ul className="mt-1 list-inside list-disc text-xs text-gray-600 space-y-1">
                                    {selectedAiAnalysis.weaknesses && selectedAiAnalysis.weaknesses.length > 0 ? (
                                        selectedAiAnalysis.weaknesses.map((weak, i) => <li key={i}>{weak}</li>)
                                    ) : (
                                        <li>No major weaknesses detected.</li>
                                    )}
                                </ul>
                            </div>

                            {/* Suggestions */}
                            <div>
                                <h4 className="text-sm font-bold text-indigo-700 flex items-center gap-1">
                                    💡 Suggestions for Improvement
                                </h4>
                                <ul className="mt-1 list-inside list-disc text-xs text-gray-600 space-y-1">
                                    {selectedAiAnalysis.suggestions && selectedAiAnalysis.suggestions.length > 0 ? (
                                        selectedAiAnalysis.suggestions.map((sug, i) => <li key={i}>{sug}</li>)
                                    ) : (
                                        <li>Try adding a question at the end to prompt comments.</li>
                                    )}
                                </ul>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3 border-t pt-3">
                            <button
                                onClick={() => {
                                    handleAnalyze(selectedAiAnalysis.id);
                                    setShowAiModal(false);
                                }}
                                className="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 cursor-pointer"
                            >
                                🔄 Re-analyze Post
                            </button>
                            <button
                                onClick={() => { setShowAiModal(false); setSelectedAiAnalysis(null); }}
                                className="rounded-lg bg-gray-800 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-700 cursor-pointer"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
