---
name: local-server
description: Start the Laravel development server
disable-model-invocation: true
---

Kill any process already running on port 8000, then start the Laravel development server in the background:

```bash
lsof -ti :8000 | xargs kill -9 2>/dev/null; php artisan serve &
```

Confirm the server is running and accessible at http://localhost:8000 (expect a 200 or 302 response).
