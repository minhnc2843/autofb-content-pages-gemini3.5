import { Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '../../Components/AppLayout';
import StatusBadge from '../../Components/StatusBadge';

export default function Index({ posts, month, year, topics, missingSlotsDates, filters }) {
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [topicIdFilter, setTopicIdFilter] = useState(filters?.topic_id || '');
    const [showGenerateModal, setShowGenerateModal] = useState(false);
    
    // Generator parameters
    const [generateDays, setGenerateDays] = useState('7');
    const [postsPerDay, setPostsPerDay] = useState('3');
    const [generating, setGenerating] = useState(false);

    // Apply active filters on dropdown updates
    useEffect(() => {
        router.get('/calendar', {
            month,
            year,
            status: statusFilter,
            topic_id: topicIdFilter
        }, { preserveState: true, preserveScroll: true });
    }, [statusFilter, topicIdFilter]);

    // Handle Month/Year navigation
    const navigateMonth = (direction) => {
        let newMonth = month + direction;
        let newYear = year;

        if (newMonth > 12) {
            newMonth = 1;
            newYear += 1;
        } else if (newMonth < 1) {
            newMonth = 12;
            newYear -= 1;
        }

        router.get('/calendar', {
            month: newMonth,
            year: newYear,
            status: statusFilter,
            topic_id: topicIdFilter
        });
    };

    // Calculate days in the current month
    const daysInMonth = new Date(year, month, 0).getDate();
    // Day of the week of the first day in month (0 = Sunday, 1 = Monday, etc.)
    const firstDayIndex = new Date(year, month - 1, 1).getDay();

    // Months names array
    const monthNames = [
        "January", "February", "March", "April", "May", "June", 
        "July", "August", "September", "October", "November", "December"
    ];

    // Generate Calendar Grid items
    const calendarDays = [];
    // Padding days before first of month
    for (let i = 0; i < firstDayIndex; i++) {
        calendarDays.push({ padding: true });
    }
    // Days of month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateString = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayPosts = posts.filter(post => post.scheduled_date === dateString);
        const hasWarning = missingSlotsDates.includes(dateString);
        
        calendarDays.push({
            day,
            dateString,
            posts: dayPosts,
            hasWarning
        });
    }

    const handleGenerateSubmit = (e) => {
        e.preventDefault();
        setGenerating(true);
        router.post('/calendar/generate', {
            days: generateDays,
            posts_per_day: postsPerDay
        }, {
            onSuccess: () => {
                setShowGenerateModal(false);
                setGenerating(false);
            },
            onFinish: () => setGenerating(false)
        });
    };

    return (
        <AppLayout title="Content Calendar">
            {/* Header controls */}
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                {/* Month Navigation */}
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => navigateMonth(-1)}
                        className="rounded-lg border border-gray-300 bg-white p-2 hover:bg-gray-50 transition cursor-pointer"
                    >
                        ◀
                    </button>
                    <h2 className="text-xl font-bold text-gray-800">
                        {monthNames[month - 1]} {year}
                    </h2>
                    <button
                        onClick={() => navigateMonth(1)}
                        className="rounded-lg border border-gray-300 bg-white p-2 hover:bg-gray-50 transition cursor-pointer"
                    >
                        ▶
                    </button>
                </div>

                {/* Filters & Actions */}
                <div className="flex flex-wrap items-center gap-3">
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-xs shadow-sm bg-white focus:outline-none"
                    >
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="published">Published</option>
                        <option value="failed">Failed</option>
                    </select>

                    <select
                        value={topicIdFilter}
                        onChange={(e) => setTopicIdFilter(e.target.value)}
                        className="rounded-lg border border-gray-300 px-3 py-2 text-xs shadow-sm bg-white focus:outline-none"
                    >
                        <option value="">All Topics</option>
                        {topics && topics.map(t => (
                            <option key={t.id} value={t.id}>{t.name}</option>
                        ))}
                    </select>

                    <button
                        onClick={() => setShowGenerateModal(true)}
                        className="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-xs font-semibold text-white shadow transition cursor-pointer"
                    >
                        ⚡ Generate Schedule
                    </button>
                </div>
            </div>

            {/* Status alerts banner */}
            <div className="mb-4 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600 flex flex-col sm:flex-row justify-between gap-2 shadow-xs">
                <span>⚠️ <strong>Draft posts</strong> will not auto-publish. Approve them before scheduled time.</span>
                <span>ℹ️ <strong>Approved posts</strong> will publish when the scheduled publish command runs.</span>
            </div>

            {/* Calendar Grid */}
            <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                {/* Week headers */}
                <div className="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-bold uppercase tracking-wider text-gray-500 py-3">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>

                {/* Day cells */}
                <div className="grid grid-cols-7 grid-rows-5 divide-x divide-y divide-gray-200">
                    {calendarDays.map((cell, index) => {
                        if (cell.padding) {
                            return <div key={`pad-${index}`} className="min-h-[140px] bg-gray-50/40"></div>;
                        }

                        return (
                            <div
                                key={cell.dateString}
                                className={`min-h-[140px] p-2 flex flex-col gap-1 transition ${
                                    cell.hasWarning ? 'bg-amber-50/20' : 'bg-white'
                                }`}
                            >
                                {/* Date label & Warnings */}
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-bold text-gray-600">{cell.day}</span>
                                    {cell.hasWarning && (
                                        <span 
                                            className="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800"
                                            title="Missing slots: This day has fewer than 3 posts scheduled (08:00, 13:00, 20:00)."
                                        >
                                            ⚠ Missing slots
                                        </span>
                                    )}
                                </div>

                                {/* Scheduled post list inside cells */}
                                <div className="flex-1 overflow-y-auto space-y-1.5 max-h-[100px]">
                                    {cell.posts.map(post => (
                                        <Link
                                            key={post.id}
                                            href={`/queue/${post.id}/edit`}
                                            className="block rounded-lg border border-gray-100 bg-gray-50 hover:bg-indigo-50 hover:border-indigo-100 p-1.5 transition text-left"
                                        >
                                            <div className="flex items-start gap-1.5">
                                                {post.thumbnail_url ? (
                                                    <img
                                                        src={post.thumbnail_url}
                                                        alt="Thumbnail"
                                                        className="h-6 w-6 rounded object-cover flex-shrink-0"
                                                    />
                                                ) : (
                                                    <span className="text-[10px] flex-shrink-0">📝</span>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-[10px] font-medium text-gray-700">
                                                        {post.caption || 'No caption'}
                                                    </p>
                                                    <div className="flex items-center justify-between gap-1 mt-0.5">
                                                        <span className="text-[8px] font-bold text-indigo-600 bg-indigo-50 px-1 rounded">
                                                            {post.scheduled_time}
                                                        </span>
                                                        <span className="text-[8px] text-gray-400 capitalize">
                                                            {post.status}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Generate Schedule Modal */}
            {showGenerateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-bold text-gray-800 mb-2">
                            ⚡ Auto-Generate Schedule
                        </h3>
                        <p className="text-xs text-gray-500 mb-4">
                            Generate draft posts scheduled at 08:00, 13:00, and 20:00 using media related to active topics. Duplicates will be avoided.
                        </p>
                        <form onSubmit={handleGenerateSubmit}>
                            <div className="mb-4">
                                <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Days to schedule</label>
                                <select
                                    value={generateDays}
                                    onChange={(e) => setGenerateDays(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none"
                                >
                                    <option value="7">Next 7 Days</option>
                                    <option value="14">Next 14 Days</option>
                                    <option value="30">Next 30 Days</option>
                                </select>
                            </div>

                            <div className="mb-4">
                                <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Posts per day</label>
                                <select
                                    value={postsPerDay}
                                    onChange={(e) => setPostsPerDay(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none"
                                >
                                    <option value="1">1 post/day</option>
                                    <option value="2">2 posts/day</option>
                                    <option value="3">3 posts/day (Default)</option>
                                </select>
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowGenerateModal(false)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer"
                                    disabled={generating}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-medium text-white shadow-sm flex items-center gap-1 cursor-pointer"
                                    disabled={generating}
                                >
                                    {generating ? 'Generating...' : 'Start Generating'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
