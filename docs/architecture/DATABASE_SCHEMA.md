# Database Schema

## topics

```txt
id
name
keyword
language
media_type: photo | video | both
is_active
created_at
updated_at
```

## media_items

```txt
id
pexels_id
type: photo | video
url
thumbnail_url
width
height
duration nullable
photographer
photographer_url
pexels_url
raw_json
created_at
updated_at
```

## posts_queue

```txt
id
topic_id
media_item_id
caption
scheduled_at
status: draft | approved | published_fake | published | failed
facebook_post_id nullable
error_message nullable
created_at
updated_at
```

## settings

```txt
id
key
value
is_secret
created_at
updated_at
```

## ai_analyses

```txt
id
target_type: page | post | media | topic
target_id
provider: gemini
score nullable
result_json nullable
raw_response nullable
created_at
updated_at
```

## page_audits

```txt
id
page_id nullable
score
brand_score
content_score
cta_score
consistency_score
suggestions_json
created_at
updated_at
```
