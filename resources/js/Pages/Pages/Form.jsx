import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '../../Components/AppLayout';

export default function Form({ page, presets }) {
    const isEdit = !!page;
    const [selectedPreset, setSelectedPreset] = useState('custom');

    const presetConfigs = {
        nature_healing: {
            niche: 'Nature & Relaxation',
            content_tone: 'calm, peaceful, healing',
            language: 'english',
            description: 'Calm, peaceful, and healing natural environments.',
            audience: 'People seeking relaxation, meditation, and peace.',
            content_goals: 'Publish beautiful nature photos and videos to help people de-stress.',
            avoid_topics: 'Superstition, commercial calls, politics.',
            preferred_media_types: ['photo', 'video'],
            photo_ratio: 30,
            video_ratio: 70,
            text_ratio: 0,
            posting_slots_str: '07:30, 12:30, 20:30',
            approval_mode: 'manual',
            auto_approve_min_score: 85,
            max_posts_per_day: 3,
            hashtag_policy: 'short, emotional, no spam hashtags',
            language_policy: 'english',
        },
        buddhist_teaching: {
            niche: 'Buddhism & Philosophy',
            content_tone: 'respectful, peaceful, reflective',
            language: 'english',
            description: 'Buddhist teachings and inspirational texts.',
            audience: 'Practitioners, philosophical seekers, and mindful individuals.',
            content_goals: 'Provide reflective thoughts, teachings, and reminders on inner peace.',
            avoid_topics: 'Superstition, luck/money promises, engagement bait.',
            preferred_media_types: ['photo', 'video', 'text'],
            photo_ratio: 60,
            video_ratio: 30,
            text_ratio: 10,
            posting_slots_str: '06:00, 12:30, 20:00',
            approval_mode: 'manual',
            auto_approve_min_score: 85,
            max_posts_per_day: 3,
            hashtag_policy: 'short, reflective hashtags',
            language_policy: 'english',
        },
        animals: {
            niche: 'Cute & Funny Animals',
            content_tone: 'cute, fun, heartwarming',
            language: 'english',
            description: 'Cuteness overload with funny animal reels and sweet pet images.',
            audience: 'Animal enthusiasts, pet owners, general public.',
            content_goals: 'Amuse and delight people with animal videos.',
            avoid_topics: 'Animal cruelty, political topics, heavy text.',
            preferred_media_types: ['photo', 'video'],
            photo_ratio: 20,
            video_ratio: 80,
            text_ratio: 0,
            posting_slots_str: '09:00, 15:00, 21:00',
            approval_mode: 'manual',
            auto_approve_min_score: 85,
            max_posts_per_day: 3,
            hashtag_policy: 'short hook, question, simple CTA',
            language_policy: 'english',
        }
    };

    const { data, setData, post, put, errors, processing } = useForm({
        name: page?.name || '',
        platform: page?.platform || 'facebook',
        facebook_page_id: page?.facebook_page_id || '',
        facebook_page_name: page?.facebook_page_name || '',
        facebook_page_link: page?.facebook_page_link || '',
        access_token: page?.access_token_masked || '',
        publish_mode: page?.publish_mode || 'fake',
        is_active: page?.is_active !== false,
        timezone: page?.timezone || 'Asia/Ho_Chi_Minh',
        language: page?.language || 'english',
        niche: page?.niche || '',
        content_tone: page?.content_tone || '',
        notes: page?.notes || '',

        // Profile fields
        description: page?.profile?.description || '',
        audience: page?.profile?.audience || '',
        content_goals: page?.profile?.content_goals || '',
        avoid_topics: page?.profile?.avoid_topics || '',
        preferred_media_types: page?.profile?.preferred_media_types || ['photo', 'video'],
        photo_ratio: page?.profile?.content_mix?.photo ?? 50,
        video_ratio: page?.profile?.content_mix?.video ?? 50,
        text_ratio: page?.profile?.content_mix?.text ?? 0,
        posting_slots_str: page?.profile?.posting_slots ? page.profile.posting_slots.join(', ') : '07:30, 12:30, 20:30',
        approval_mode: page?.profile?.approval_mode || 'manual',
        auto_approve_min_score: page?.profile?.auto_approve_min_score ?? 85,
        max_posts_per_day: page?.profile?.max_posts_per_day ?? 3,
        hashtag_policy: page?.profile?.hashtag_policy || '',
        language_policy: page?.profile?.language_policy || '',
        preset: 'custom',
    });

    const handlePresetChange = (preset) => {
        setSelectedPreset(preset);
        setData(prev => ({
            ...prev,
            preset: preset
        }));

        if (preset !== 'custom' && presetConfigs[preset]) {
            const config = presetConfigs[preset];
            Object.keys(config).forEach(key => {
                setData(prev => ({
                    ...prev,
                    [key]: config[key]
                }));
            });
        }
    };

    const toggleMediaType = (type) => {
        const current = [...data.preferred_media_types];
        const idx = current.indexOf(type);
        if (idx > -1) {
            current.splice(idx, 1);
        } else {
            current.push(type);
        }
        setData('preferred_media_types', current);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Format posting slots and content mix before sending
        const slotsArray = data.posting_slots_str
            .split(',')
            .map(s => s.trim())
            .filter(s => s.length > 0);

        const contentMix = {
            photo: parseInt(data.photo_ratio),
            video: parseInt(data.video_ratio),
            text: parseInt(data.text_ratio),
        };

        const submissionData = {
            ...data,
            posting_slots: slotsArray,
            content_mix: contentMix,
        };

        if (isEdit) {
            put(`/pages/${page.id}`, submissionData);
        } else {
            post('/pages', submissionData);
        }
    };

    return (
        <AppLayout title={isEdit ? `Edit Page: ${page.name}` : 'Create New Page'}>
            <form onSubmit={handleSubmit} className="space-y-8 bg-white p-6 rounded-xl shadow-sm max-w-4xl">
                {/* Basic Section */}
                <div>
                    <h3 className="text-base font-semibold leading-7 text-gray-900">Basic Information</h3>
                    <p className="mt-1 text-sm leading-6 text-gray-600">Enter general page configurations.</p>
                    
                    {!isEdit && (
                        <div className="mt-4">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Preset Type</label>
                            <select
                                value={selectedPreset}
                                onChange={(e) => handlePresetChange(e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm"
                            >
                                <option value="custom">Custom (No Preset)</option>
                                <option value="nature_healing">Nature Healing</option>
                                <option value="buddhist_teaching">Buddhist Teaching</option>
                                <option value="animals">Animals</option>
                            </select>
                        </div>
                    )}

                    <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6">
                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Page Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>

                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Facebook Page ID</label>
                            <input
                                type="text"
                                value={data.facebook_page_id}
                                onChange={e => setData('facebook_page_id', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                            {errors.facebook_page_id && <p className="mt-1 text-xs text-red-500">{errors.facebook_page_id}</p>}
                        </div>

                        <div className="sm:col-span-6">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Facebook Page Access Token</label>
                            <input
                                type="password"
                                value={data.access_token}
                                onChange={e => setData('access_token', e.target.value)}
                                placeholder={isEdit ? '••••••••••••••••' : 'Enter access token...'}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                            {errors.access_token && <p className="mt-1 text-xs text-red-500">{errors.access_token}</p>}
                        </div>

                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Publish Mode</label>
                            <select
                                value={data.publish_mode}
                                onChange={e => setData('publish_mode', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            >
                                <option value="fake">Fake Mode</option>
                                <option value="real">Real Mode</option>
                            </select>
                        </div>

                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Timezone</label>
                            <input
                                type="text"
                                value={data.timezone}
                                onChange={e => setData('timezone', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Language</label>
                            <input
                                type="text"
                                value={data.language}
                                onChange={e => setData('language', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>
                    </div>
                </div>

                {/* Profile Section */}
                <div className="border-t border-gray-900/10 pt-8">
                    <h3 className="text-base font-semibold leading-7 text-gray-900">Content Profile</h3>
                    <p className="mt-1 text-sm leading-6 text-gray-600">Establish the theme, tone, mix, and scheduling slots for AI planning.</p>

                    <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6">
                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Niche</label>
                            <input
                                type="text"
                                value={data.niche}
                                onChange={e => setData('niche', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Content Tone</label>
                            <input
                                type="text"
                                value={data.content_tone}
                                onChange={e => setData('content_tone', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-6">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Description</label>
                            <textarea
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                rows={2}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-6">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Target Audience</label>
                            <textarea
                                value={data.audience}
                                onChange={e => setData('audience', e.target.value)}
                                rows={2}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Avoid Topics</label>
                            <input
                                type="text"
                                value={data.avoid_topics}
                                onChange={e => setData('avoid_topics', e.target.value)}
                                placeholder="superstition, clickbait, etc."
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-3">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Approval Mode</label>
                            <select
                                value={data.approval_mode}
                                onChange={e => setData('approval_mode', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            >
                                <option value="manual">Manual Approve Required</option>
                                <option value="semi_auto">Semi-Auto (Auto Approve high score)</option>
                                <option value="full_auto">Full-Auto (Autopilot publish)</option>
                            </select>
                        </div>

                        {/* Content Mix Ratios */}
                        <div className="sm:col-span-6">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Content Mix Ratios (%)</label>
                            <div className="mt-2 grid grid-cols-3 gap-4">
                                <div>
                                    <span className="text-xs text-gray-500">Photo %</span>
                                    <input
                                        type="number"
                                        value={data.photo_ratio}
                                        onChange={e => setData('photo_ratio', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <span className="text-xs text-gray-500">Video %</span>
                                    <input
                                        type="number"
                                        value={data.video_ratio}
                                        onChange={e => setData('video_ratio', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                                    />
                                </div>
                                <div>
                                    <span className="text-xs text-gray-500">Text %</span>
                                    <input
                                        type="number"
                                        value={data.text_ratio}
                                        onChange={e => setData('text_ratio', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Posting Slots */}
                        <div className="sm:col-span-4">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Posting Slots (comma separated)</label>
                            <input
                                type="text"
                                value={data.posting_slots_str}
                                onChange={e => setData('posting_slots_str', e.target.value)}
                                placeholder="08:00, 13:00, 20:00"
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium leading-6 text-gray-900">Max Posts/Day</label>
                            <input
                                type="number"
                                value={data.max_posts_per_day}
                                onChange={e => setData('max_posts_per_day', e.target.value)}
                                className="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 sm:text-sm"
                            />
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-end gap-x-4 border-t border-gray-900/10 pt-6">
                    <Link
                        href="/pages"
                        className="text-sm font-semibold leading-6 text-gray-900 hover:text-gray-700"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                    >
                        {processing ? 'Saving...' : 'Save Page'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
