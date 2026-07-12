export default function StatusBadge({ status }) {
    const colorMap = {
        draft: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-blue-100 text-blue-800',
        published: 'bg-emerald-100 text-emerald-800',
        published_fake: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };

    const labelMap = {
        draft: 'Draft',
        approved: 'Approved',
        published: '✅ Published',
        published_fake: 'Published (Fake)',
        failed: 'Failed',
    };

    const classes = colorMap[status] || 'bg-gray-100 text-gray-800';
    const label = labelMap[status] || status;

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`}>
            {label}
        </span>
    );
}
