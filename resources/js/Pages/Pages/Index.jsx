import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';

export default function Index({ pages }) {
    const handleToggleActive = (id) => {
        router.post(`/pages/${id}/toggle-active`, {}, {
            preserveScroll: true,
        });
    };

    const handleSetDefault = (id) => {
        router.post(`/pages/${id}/set-default`, {}, {
            preserveScroll: true,
        });
    };

    const handleValidateFacebook = (id) => {
        router.post(`/pages/${id}/validate-facebook`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="Pages Management">
            <div className="mb-6 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                    Manage profiles, schedules, and Facebook page credentials.
                </p>
                <Link
                    href="/pages/create"
                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    + Create Page
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                {pages && pages.length > 0 ? (
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Name
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Platform
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Publish Mode
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Active
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Default
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {pages.map((page, idx) => {
                                const isDefault = page.slug === 'default-facebook-page';
                                return (
                                    <tr
                                        key={page.id}
                                        className={idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}
                                    >
                                        <td className="px-6 py-4">
                                            <div className="text-sm font-medium text-gray-900 flex items-center gap-2">
                                                {page.name}
                                                {isDefault && (
                                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                        Default
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-xs text-gray-400">
                                                Page ID: {page.facebook_page_id || 'N/A'}
                                            </div>
                                            <div className="text-xs text-gray-400">
                                                Niche: {page.niche || 'N/A'} | Tone: {page.content_tone || 'N/A'}
                                            </div>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-sm capitalize text-gray-500">
                                            {page.platform}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    page.publish_mode === 'real'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                }`}
                                            >
                                                {page.publish_mode === 'real' ? 'Real' : 'Fake'}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4">
                                            <button
                                                onClick={() => handleToggleActive(page.id)}
                                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                                                    page.is_active ? 'bg-indigo-600' : 'bg-gray-300'
                                                }`}
                                            >
                                                <span
                                                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                                        page.is_active ? 'translate-x-6' : 'translate-x-1'
                                                    }`}
                                                />
                                            </button>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4">
                                            {!isDefault ? (
                                                <button
                                                    onClick={() => handleSetDefault(page.id)}
                                                    className="inline-flex items-center rounded bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                                >
                                                    Set Default
                                                </button>
                                            ) : (
                                                <span className="text-xs text-gray-400">Default Page</span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <button
                                                onClick={() => handleValidateFacebook(page.id)}
                                                className="mr-4 text-xs font-medium text-emerald-600 hover:text-emerald-800"
                                                title="Validate Facebook config"
                                            >
                                                Validate FB
                                            </button>
                                            <Link
                                                href={`/pages/${page.id}/edit`}
                                                className="font-medium text-indigo-600 hover:text-indigo-800"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                ) : (
                    <div className="px-6 py-12 text-center text-sm text-gray-400">
                        No pages configured yet. Create one to get started!
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
