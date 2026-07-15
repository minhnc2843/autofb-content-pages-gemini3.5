<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Setting;

class PageContextService
{
    /**
     * Get the default page, creating it if it doesn't exist.
     */
    public function getDefaultPage(): Page
    {
        $page = Page::where('slug', 'default-facebook-page')->first();
        if ($page) {
            return $page;
        }

        $page = Page::first();
        if ($page) {
            return $page;
        }

        // Lazy create Default Page from global settings
        $fbPageId = Setting::getValue('FACEBOOK_PAGE_ID');
        $fbToken = Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN');
        $fbMode = Setting::getValue('FACEBOOK_PUBLISH_MODE', 'fake');

        $page = Page::create([
            'name' => 'Default Facebook Page',
            'slug' => 'default-facebook-page',
            'platform' => 'facebook',
            'facebook_page_id' => $fbPageId,
            'facebook_page_name' => 'Default Page',
            'access_token' => $fbToken,
            'publish_mode' => $fbMode ?: 'fake',
            'is_active' => true,
            'timezone' => config('app.timezone', 'Asia/Ho_Chi_Minh'),
            'language' => 'english',
        ]);

        $page->profile()->create([
            'preferred_media_types' => ['photo', 'video'],
            'content_mix' => ['photo' => 50, 'video' => 50, 'text' => 0],
            'posting_slots' => ['07:30', '12:30', '20:30'],
            'approval_mode' => 'manual',
        ]);

        return $page;
    }

    /**
     * Resolve page by ID, falling back to default page.
     */
    public function resolvePage(?int $pageId): Page
    {
        if ($pageId) {
            $page = Page::find($pageId);
            if ($page) {
                return $page;
            }
        }
        return $this->getDefaultPage();
    }

    /**
     * Get access credentials for a page, falling back to global settings.
     */
    public function getPageCredential(Page $page): array
    {
        $pageId = $page->facebook_page_id;
        $accessToken = $page->access_token;
        $publishMode = $page->publish_mode;

        if (empty($pageId)) {
            $pageId = Setting::getValue('FACEBOOK_PAGE_ID');
        }
        if (empty($accessToken)) {
            $accessToken = Setting::getValue('FACEBOOK_PAGE_ACCESS_TOKEN');
        }
        if (empty($publishMode)) {
            $publishMode = Setting::getValue('FACEBOOK_PUBLISH_MODE', 'fake');
        }

        return [
            'facebook_page_id' => $pageId,
            'access_token' => $accessToken,
            'publish_mode' => $publishMode,
        ];
    }
}
