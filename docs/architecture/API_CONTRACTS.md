# Internal API Contracts

## Routes gợi ý

```txt
GET    /dashboard
GET    /topics
POST   /topics
PUT    /topics/{topic}
DELETE /topics/{topic}

GET    /pexels
POST   /pexels/search
POST   /pexels/create-draft

GET    /queue
GET    /queue/{post}/edit
PUT    /queue/{post}
POST   /queue/{post}/approve
POST   /queue/{post}/unapprove
DELETE /queue/{post}

GET    /settings
PUT    /settings

POST   /ai/posts/{post}/score
POST   /ai/page-audit
```

## Service Contracts

### PexelsService

```php
searchPhotos(string $keyword, int $perPage = 10): array
searchVideos(string $keyword, int $perPage = 10): array
searchBoth(string $keyword, int $perPage = 10): array
```

### CaptionService

```php
generate(array $topic, array $media, string $language): string
```

### FacebookPageService

```php
publishTextPost(array $post): array
publishPhotoPost(array $post): array
publishVideoPost(array $post): array
```
