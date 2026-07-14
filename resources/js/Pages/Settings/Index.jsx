import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';

export default function Index({ settings }) {
    const { data, setData, post, processing, errors } = useForm({
        pexels_api_key: settings?.pexels_api_key || '',
        facebook_page_id: settings?.facebook_page_id || '',
        facebook_page_access_token: settings?.facebook_page_access_token || '',
        gemini_api_key: settings?.gemini_api_key || '',
        meta_graph_version: settings?.meta_graph_version || 'v25.0',
        facebook_publish_mode: settings?.facebook_publish_mode || 'fake',
        facebook_video_upload_mode: settings?.facebook_video_upload_mode || 'remote_url',
        facebook_video_max_mb: settings?.facebook_video_max_mb || '100',
        facebook_publish_as_reel: settings?.facebook_publish_as_reel || 'false',
        gemini_enabled: settings?.gemini_enabled || 'false',
        gemini_model: settings?.gemini_model || 'gemini-2.5-flash',
        gemini_caption_mode: settings?.gemini_caption_mode || 'template',
    });

    const [showFields, setShowFields] = useState({
        pexels_api_key: false,
        facebook_page_access_token: false,
        gemini_api_key: false,
    });

    const [validating, setValidating] = useState(false);

    const toggleShow = (field) => {
        setShowFields((prev) => ({ ...prev, [field]: !prev[field] }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/settings');
    };

    const handleValidateFacebook = () => {
        setValidating(true);
        router.post('/settings/facebook/validate', {}, {
            preserveScroll: true,
            onFinish: () => setValidating(false),
        });
    };

    return (
        <AppLayout title="Settings">
            <div className="mx-auto max-w-2xl space-y-6">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Pexels Section */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                        <h2 className="mb-4 text-lg font-semibold text-gray-800">🔍 Pexels API</h2>
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    PEXELS_API_KEY
                                </label>
                                <div className="relative">
                                    <input
                                        type={showFields.pexels_api_key ? 'text' : 'password'}
                                        value={data.pexels_api_key}
                                        onChange={(e) => setData('pexels_api_key', e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-16 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter your Pexels API key"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShow('pexels_api_key')}
                                        className="absolute inset-y-0 right-0 flex items-center px-3 text-xs font-medium text-gray-500 hover:text-gray-700"
                                    >
                                        {showFields.pexels_api_key ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                                {errors.pexels_api_key && (
                                    <p className="mt-1 text-xs text-red-600">{errors.pexels_api_key}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Facebook Publishing Section */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                        <h2 className="mb-4 text-lg font-semibold text-gray-800">📘 Facebook Publishing</h2>

                        {data.facebook_publish_mode === 'real' && (
                            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                ⚠️ <strong>Real mode</strong> will publish approved posts to your Facebook Page via Graph API.
                            </div>
                        )}

                        <div className="space-y-4">
                            {/* Page ID */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_PAGE_ID
                                </label>
                                <input
                                    type="text"
                                    value={data.facebook_page_id}
                                    onChange={(e) => setData('facebook_page_id', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="Enter your Facebook Page ID"
                                />
                            </div>

                            {/* Page Access Token */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_PAGE_ACCESS_TOKEN
                                </label>
                                <div className="relative">
                                    <input
                                        type={showFields.facebook_page_access_token ? 'text' : 'password'}
                                        value={data.facebook_page_access_token}
                                        onChange={(e) => setData('facebook_page_access_token', e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-16 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter your Page Access Token"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShow('facebook_page_access_token')}
                                        className="absolute inset-y-0 right-0 flex items-center px-3 text-xs font-medium text-gray-500 hover:text-gray-700"
                                    >
                                        {showFields.facebook_page_access_token ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                            </div>

                            {/* Meta Graph Version */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    META_GRAPH_VERSION
                                </label>
                                <input
                                    type="text"
                                    value={data.meta_graph_version}
                                    onChange={(e) => setData('meta_graph_version', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="v25.0"
                                />
                            </div>

                            {/* Publish Mode */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_PUBLISH_MODE
                                </label>
                                <select
                                    value={data.facebook_publish_mode}
                                    onChange={(e) => setData('facebook_publish_mode', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    <option value="fake">fake — No real Facebook API calls</option>
                                    <option value="real">real — Publish to Facebook via Graph API</option>
                                </select>
                            </div>

                            {/* Video Upload Mode */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_VIDEO_UPLOAD_MODE
                                </label>
                                <select
                                    value={data.facebook_video_upload_mode}
                                    onChange={(e) => setData('facebook_video_upload_mode', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    <option value="remote_url">remote_url — Facebook fetches the video from Pexels</option>
                                    <option value="local_download">local_download — Download and upload (Placeholder)</option>
                                </select>
                            </div>

                            {/* Video Max MB */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_VIDEO_MAX_MB
                                </label>
                                <input
                                    type="number"
                                    value={data.facebook_video_max_mb}
                                    onChange={(e) => setData('facebook_video_max_mb', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="100"
                                />
                            </div>

                            {/* Publish as Reel */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    FACEBOOK_PUBLISH_AS_REEL
                                </label>
                                <select
                                    value={data.facebook_publish_as_reel}
                                    onChange={(e) => setData('facebook_publish_as_reel', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    <option value="false">false — Publish videos as regular Page posts</option>
                                    <option value="true">true — Publish videos as Reels</option>
                                </select>
                            </div>

                            {/* Validate Button */}
                            <div>
                                <button
                                    type="button"
                                    onClick={handleValidateFacebook}
                                    disabled={validating}
                                    className="rounded-lg border border-blue-600 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 disabled:opacity-50"
                                >
                                    {validating ? 'Validating...' : '🔍 Validate Facebook Config'}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Gemini Section */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                        <h2 className="mb-4 text-lg font-semibold text-gray-800">🤖 Gemini AI</h2>
                        
                        <div className="space-y-4">
                            {/* Gemini Enabled */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    GEMINI_ENABLED
                                </label>
                                <select
                                    value={data.gemini_enabled}
                                    onChange={(e) => setData('gemini_enabled', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    <option value="false">false — Disable Gemini API calls</option>
                                    <option value="true">true — Enable Gemini AI features</option>
                                </select>
                            </div>

                            {/* Gemini API Key */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    GEMINI_API_KEY
                                </label>
                                <div className="relative">
                                    <input
                                        type={showFields.gemini_api_key ? 'text' : 'password'}
                                        value={data.gemini_api_key}
                                        onChange={(e) => setData('gemini_api_key', e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 pr-16 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter your Gemini API key"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShow('gemini_api_key')}
                                        className="absolute inset-y-0 right-0 flex items-center px-3 text-xs font-medium text-gray-500 hover:text-gray-700"
                                    >
                                        {showFields.gemini_api_key ? 'Hide' : 'Show'}
                                    </button>
                                </div>
                            </div>

                            {/* Gemini Model */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    GEMINI_MODEL
                                </label>
                                <input
                                    type="text"
                                    value={data.gemini_model}
                                    onChange={(e) => setData('gemini_model', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="gemini-2.5-flash"
                                />
                            </div>

                            {/* Gemini Caption Mode */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">
                                    GEMINI_CAPTION_MODE
                                </label>
                                <select
                                    value={data.gemini_caption_mode}
                                    onChange={(e) => setData('gemini_caption_mode', e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    <option value="template">template — Use local text templates</option>
                                    <option value="ai">ai — Use Gemini AI for captions</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Save All Button */}
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : '💾 Save All Settings'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
