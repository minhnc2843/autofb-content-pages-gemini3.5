<?php

namespace App\Services;

class CaptionService
{
    /**
     * Template-based caption generation with multi-language support.
     */
    public function generate(array $topic, array $media, string $language = 'english'): string
    {
        $language = strtolower($language);
        $templates = $this->getTemplates();

        if (!isset($templates[$language])) {
            $language = 'english'; // fallback
        }

        $template = $templates[$language];
        $keyword = $topic['keyword'] ?? $topic['name'] ?? 'nature';
        $photographer = $media['photographer'] ?? 'Unknown';
        $mediaType = $media['type'] ?? 'photo';

        $hook = $this->pickRandom($template['hooks']);
        $body = $this->pickRandom($template['bodies']);
        $cta = $this->pickRandom($template['ctas']);
        $hashtags = $this->generateHashtags($keyword, $language);

        // Replace placeholders
        $hook = str_replace(['{keyword}', '{photographer}'], [$keyword, $photographer], $hook);
        $body = str_replace(['{keyword}', '{photographer}', '{media_type}'], [$keyword, $photographer, $mediaType], $body);
        $cta = str_replace(['{keyword}'], [$keyword], $cta);

        return trim("{$hook}\n\n{$body}\n\n{$cta}\n\n{$hashtags}");
    }

    /**
     * Get caption templates for all supported languages.
     */
    protected function getTemplates(): array
    {
        return [
            'english' => [
                'hooks' => [
                    "✨ Wait for this moment about {keyword}...",
                    "🔥 You won't believe this {keyword} shot!",
                    "📸 Stunning {keyword} captured perfectly!",
                    "💫 This {keyword} moment is everything!",
                    "🌟 The beauty of {keyword} never gets old!",
                ],
                'bodies' => [
                    "Nature has its own way of telling stories. This beautiful {keyword} scene reminds us to slow down and appreciate the world around us. 📷 Credit: {photographer}",
                    "Sometimes the best moments are the ones we don't plan. This incredible {keyword} capture by {photographer} speaks volumes.",
                    "Every {media_type} tells a story. This {keyword} moment captured by {photographer} is truly mesmerizing.",
                ],
                'ctas' => [
                    "👉 Follow for more amazing {keyword} content!",
                    "💬 What do you think? Drop a comment below!",
                    "❤️ Double tap if you love {keyword}!",
                    "🔔 Turn on notifications to never miss a post!",
                ],
            ],
            'vietnamese' => [
                'hooks' => [
                    "✨ Khoảnh khắc tuyệt đẹp về {keyword}...",
                    "🔥 Bạn sẽ không tin nổi bức ảnh {keyword} này!",
                    "📸 {keyword} đẹp lung linh được ghi lại hoàn hảo!",
                    "💫 Khoảnh khắc {keyword} này là tất cả!",
                    "🌟 Vẻ đẹp của {keyword} không bao giờ cũ!",
                ],
                'bodies' => [
                    "Thiên nhiên có cách kể chuyện riêng của nó. Cảnh {keyword} tuyệt đẹp này nhắc nhở chúng ta hãy chậm lại và trân trọng thế giới xung quanh. 📷 Nguồn: {photographer}",
                    "Đôi khi những khoảnh khắc đẹp nhất là những khoảnh khắc không hề lên kế hoạch. Bức ảnh {keyword} tuyệt vời này bởi {photographer} nói lên rất nhiều điều.",
                    "Mỗi {media_type} đều kể một câu chuyện. Khoảnh khắc {keyword} này được chụp bởi {photographer} thực sự mê hoặc.",
                ],
                'ctas' => [
                    "👉 Theo dõi để xem thêm nội dung {keyword} tuyệt vời!",
                    "💬 Bạn nghĩ sao? Bình luận bên dưới nhé!",
                    "❤️ Nhấn thích nếu bạn yêu {keyword}!",
                    "🔔 Bật thông báo để không bỏ lỡ bài viết nào!",
                ],
            ],
            'thai' => [
                'hooks' => [
                    "✨ ช่วงเวลาที่สวยงามเกี่ยวกับ {keyword}...",
                    "🔥 คุณจะไม่เชื่อภาพ {keyword} นี้!",
                    "📸 {keyword} ที่สวยงามถูกจับภาพอย่างสมบูรณ์แบบ!",
                    "💫 ช่วงเวลา {keyword} นี้คือทุกอย่าง!",
                    "🌟 ความงามของ {keyword} ไม่เคยเก่า!",
                ],
                'bodies' => [
                    "ธรรมชาติมีวิธีเล่าเรื่องของตัวเอง ฉาก {keyword} ที่สวยงามนี้เตือนเราให้ช้าลงและชื่นชมโลกรอบตัว 📷 เครดิต: {photographer}",
                    "บางครั้งช่วงเวลาที่ดีที่สุดคือช่วงเวลาที่เราไม่ได้วางแผน ภาพ {keyword} ที่เหลือเชื่อนี้โดย {photographer} สื่อความหมายได้มากมาย",
                    "ทุก {media_type} เล่าเรื่อง ช่วงเวลา {keyword} นี้ที่จับภาพโดย {photographer} น่าหลงใหลอย่างแท้จริง",
                ],
                'ctas' => [
                    "👉 ติดตามเพื่อดูเนื้อหา {keyword} ที่น่าทึ่งเพิ่มเติม!",
                    "💬 คุณคิดอย่างไร? แสดงความคิดเห็นด้านล่าง!",
                    "❤️ กดไลค์ถ้าคุณรัก {keyword}!",
                    "🔔 เปิดการแจ้งเตือนเพื่อไม่พลาดโพสต์!",
                ],
            ],
            'lao' => [
                'hooks' => [
                    "✨ ຊ່ວງເວລາທີ່ສວຍງາມກ່ຽວກັບ {keyword}...",
                    "🔥 ທ່ານຈະບໍ່ເຊື່ອພາບ {keyword} ນີ້!",
                    "📸 {keyword} ທີ່ສວຍງາມຖືກບັນທຶກຢ່າງສົມບູນແບບ!",
                    "💫 ຊ່ວງເວລາ {keyword} ນີ້ຄືທຸກຢ່າງ!",
                ],
                'bodies' => [
                    "ທຳມະຊາດມີວິທີເລົ່າເລື່ອງຂອງຕົນເອງ ສາກ {keyword} ທີ່ສວຍງາມນີ້ເຕືອນພວກເຮົາໃຫ້ຊ້າລົງ ແລະ ຊື່ນຊົມໂລກອ້ອมຮອບ 📷 ແຫຼ່ງ: {photographer}",
                    "ບາງເທື່ອຊ່ວງເວລາທີ່ດີທີ່ສຸດຄືຊ່ວງເວລາທີ່ພວກເຮົາບໍ່ໄດ້ວາງແຜນ ພາບ {keyword} ທີ່ເຫຼືອເຊື່ອນີ້ໂດຍ {photographer}",
                ],
                'ctas' => [
                    "👉 ຕິດຕາມເພື່ອເບິ່ງເນື້ອຫາ {keyword} ເພີ່ມເຕີມ!",
                    "💬 ທ່ານຄິດແນວໃດ? ຄອມເມັ້ນຂ້າງລຸ່ມ!",
                    "❤️ ກົດໄລຄ໌ຖ້າທ່ານຮັກ {keyword}!",
                ],
            ],
            'khmer' => [
                'hooks' => [
                    "✨ ពេលវេលាដ៏ស្រស់ស្អាតអំពី {keyword}...",
                    "🔥 អ្នកនឹងមិនជឿរូបភាព {keyword} នេះទេ!",
                    "📸 {keyword} ដ៏ស្រស់ស្អាតត្រូវបានថតយ៉ាងល្អឥតខ្ចោះ!",
                    "💫 ពេលវេលា {keyword} នេះគឺជាអ្វីៗទាំងអស់!",
                ],
                'bodies' => [
                    "ធម្មជាតិមានវិធីផ្ទាល់ខ្លួនក្នុងការប្រាប់រឿង ទិដ្ឋភាព {keyword} ដ៏ស្រស់ស្អាតនេះរំលឹកយើងឱ្យយឺតចុះ និងពេញចិត្តពិភពលោកជុំវិញ 📷 ប្រភព: {photographer}",
                    "ពេលខ្លះពេលវេលាដ៏ល្អបំផុតគឺជាពេលវេលាដែលយើងមិនបានគ្រោង រូបភាព {keyword} ដ៏អស្ចារ្យនេះដោយ {photographer}",
                ],
                'ctas' => [
                    "👉 តាមដានដើម្បីមើលមាតិកា {keyword} បន្ថែម!",
                    "💬 តើអ្នកគិតយ៉ាងម៉េច? បញ្ចេញមតិខាងក្រោម!",
                    "❤️ ចុចចូលចិត្តប្រសិនបើអ្នកស្រលាញ់ {keyword}!",
                ],
            ],
        ];
    }

    /**
     * Generate hashtags based on keyword and language.
     */
    protected function generateHashtags(string $keyword, string $language): string
    {
        $keyword = strtolower(str_replace(' ', '', $keyword));
        $base = ["#{$keyword}", "#pexels", "#beautiful", "#amazing"];

        $langTags = [
            'english' => ['#photography', '#nature', '#instagood', '#photooftheday'],
            'vietnamese' => ['#nhiepanhdep', '#thiennhien', '#vietnam', '#anhdem'],
            'thai' => ['#ถ่ายรูปสวย', '#ธรรมชาติ', '#ไทย', '#สวยงาม'],
            'lao' => ['#ລາວ', '#ສວຍງາມ', '#ທຳມະຊາດ'],
            'khmer' => ['#កម្ពុជា', '#ស្អាត', '#ធម្មជាតិ'],
        ];

        $tags = array_merge($base, $langTags[$language] ?? $langTags['english']);
        return implode(' ', array_slice($tags, 0, 8));
    }

    /**
     * Generate caption using Gemini AI with preset styles.
     */
    public function generateWithAi(array $topic, array $media, string $language = 'english', string $preset = 'creative'): string
    {
        $geminiService = new \App\Services\AI\GeminiService();
        
        $keyword = $topic['keyword'] ?? $topic['name'] ?? 'nature';
        $photographer = $media['photographer'] ?? 'Unknown';
        $mediaType = $media['type'] ?? 'photo';
        
        $presetPrompts = [
            'creative' => 'Write a highly engaging, creative, and storytelling caption with relevant emojis and hashtags.',
            'professional' => 'Write a formal, polite, informative, and professional caption. Avoid excessive emojis, use minimal tags.',
            'short' => 'Write a concise, simple, and punchy caption under 2 sentences.',
            'promotional' => 'Write a promotional caption featuring a clear Call to Action (CTA) encouraging users to comment, share, or follow.',
        ];
        
        $promptInstruction = $presetPrompts[$preset] ?? $presetPrompts['creative'];
        
        $prompt = "You are a senior social media manager.
Task: Write a Facebook page caption in " . strtoupper($language) . " language.
Topic keyword/theme: '{$keyword}'
Media info: A {$mediaType} taken by photographer '{$photographer}' (Please credit this photographer in the caption).
Style requirement: {$promptInstruction}
Do NOT wrap the caption in quotes. Return ONLY the caption text itself.";

        try {
            $result = $geminiService->generateText($prompt);
            if (isset($result['text']) && !empty($result['text'])) {
                return trim($result['text']);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Gemini caption generation failed, falling back to local template: " . $e->getMessage());
        }
        
        return $this->generate($topic, $media, $language);
    }

    /**
     * Pick a random item from an array.
     */
    protected function pickRandom(array $items): string
    {
        return $items[array_rand($items)];
    }
}
