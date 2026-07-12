# Deployment Notes

Phase 1 chỉ chạy local.

Khi deploy production cần:

- HTTPS.
- Database production.
- Queue/scheduler chạy ổn.
- Log rotation.
- Backup database.
- Token/API key không commit lên Git.
- `.env` riêng production.
