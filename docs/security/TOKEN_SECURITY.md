# Token Security

## Nguyên tắc

- Không hard-code token/API key.
- Không commit `.env`.
- Không đưa token vào React props nếu không cần.
- Không log token.
- Không hiển thị token đầy đủ trên UI.

## Facebook token

- Chỉ dùng cho Page mà người dùng quản lý.
- Cần lưu ngày hết hạn nếu có.
- Cần có cảnh báo token sắp hết hạn.
- Khi lỗi token, không retry vô hạn.
