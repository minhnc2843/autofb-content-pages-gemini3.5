# Service Boundaries

## Controller không được làm

- Không gọi trực tiếp Pexels API trong controller.
- Không tự tạo caption logic dài trong controller.
- Không xử lý Facebook token trong controller.
- Không nhét toàn bộ business logic vào React page.

## Controller chỉ nên làm

- Validate request.
- Gọi service.
- Redirect/render Inertia page.
- Trả flash message.

## Service làm

- Gọi API ngoài.
- Xử lý lỗi.
- Mapping dữ liệu.
- Business rules.

## Command làm

- Job theo lịch.
- Generate daily posts.
- Publish due posts.
- Log rõ ràng.
