# Backend Production-Ready: Hướng Dẫn Toàn Diện

> Tài liệu này tổng hợp tất cả các khía cạnh cần suy nghĩ khi xây dựng một backend chuyên nghiệp: bảo mật cao, có thể scale, dễ bảo trì.

---

## Mục Lục

1. [Kiến trúc & Design Pattern](#1-kiến-trúc--design-pattern)
2. [Cấu trúc thư mục chuẩn](#2-cấu-trúc-thư-mục-chuẩn)
3. [Database Design](#3-database-design)
4. [API Design](#4-api-design)
5. [Authentication & Authorization](#5-authentication--authorization)
6. [Bảo mật](#6-bảo-mật)
7. [Validation & Error Handling](#7-validation--error-handling)
8. [Logging & Monitoring](#8-logging--monitoring)
9. [Caching](#9-caching)
10. [Queue & Background Jobs](#10-queue--background-jobs)
11. [Testing](#11-testing)
12. [Performance & Scalability](#12-performance--scalability)
13. [DevOps & CI/CD](#13-devops--cicd)
14. [Documentation](#14-documentation)
15. [Checklist trước khi deploy](#15-checklist-trước-khi-deploy)

---

## 1. Kiến trúc & Design Pattern

### Layered Architecture (bắt buộc)

```
Request → Middleware → Controller → Service → Repository → Model → DB
                                       ↓
                                  Event/Job Queue
```

| Layer | Trách nhiệm | KHÔNG được làm |
|---|---|---|
| **Controller** | Nhận request, trả response, gọi Service | Chứa business logic, gọi trực tiếp DB |
| **Service** | Business logic, orchestrate các Repository | Gọi trực tiếp Model/DB query |
| **Repository** | Tất cả query DB, trừu tượng hóa data access | Chứa business logic |
| **Model** | Định nghĩa schema, relationships, mutators | Chứa logic phức tạp |

### Các Pattern quan trọng

**Repository Pattern**
```
Vì sao: Dễ swap DB (MySQL → MongoDB), dễ mock khi test, query tập trung một chỗ.
```

**Service Layer**
```
Vì sao: Business logic không bị lặp lại, tái sử dụng được giữa các Controller,
         dễ test độc lập với HTTP layer.
```

**DTO (Data Transfer Object)**
```
Vì sao: Kiểm soát chính xác data vào/ra, tránh mass assignment, 
         không expose cột nhạy cảm (password_hash, internal_flags).
```

**Observer / Event-Driven**
```
Vì sao: Tách side effects (gửi email, ghi log, update cache) ra khỏi core logic.
         Khi cần thêm side effect mới → không cần chạm vào Service.
```

**Strategy Pattern**
```
Dùng khi: Có nhiều cách thực hiện cùng một việc (thanh toán: VNPay, MoMo, Stripe).
           Thêm provider mới không cần sửa code cũ (Open/Closed Principle).
```

### SOLID trong thực tế
- **S** — Mỗi class một trách nhiệm. `UserService` không xử lý email.
- **O** — Thêm tính năng mới bằng cách extend, không sửa class cũ.
- **L** — Subclass phải thay thế được parent mà không vỡ behavior.
- **I** — Interface nhỏ, đặc thù. Không ép implement method không cần thiết.
- **D** — Inject dependency qua constructor, không `new` bên trong class.

---

## 2. Cấu Trúc Thư Mục Chuẩn

### Laravel (ví dụ cụ thể)

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── V1/                    ← versioning
│   │       │   ├── AuthController.php
│   │       │   └── PostController.php
│   │       └── V2/
│   ├── Middleware/
│   │   ├── Authenticate.php
│   │   ├── RateLimitMiddleware.php
│   │   └── EnsureEmailVerified.php
│   ├── Requests/                      ← Form Request (validation)
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   └── Post/
│   │       ├── StorePostRequest.php
│   │       └── UpdatePostRequest.php
│   └── Resources/                     ← API Resources (DTO out)
│       ├── UserResource.php
│       └── PostResource.php
│
├── Services/                          ← Business Logic
│   ├── AuthService.php
│   ├── PostService.php
│   └── NotificationService.php
│
├── Repositories/                      ← Data Access
│   ├── Contracts/                     ← Interfaces
│   │   ├── PostRepositoryInterface.php
│   │   └── UserRepositoryInterface.php
│   └── Eloquent/                      ← Implementation
│       ├── PostRepository.php
│       └── UserRepository.php
│
├── Models/
│   ├── User.php
│   └── Post.php
│
├── Events/                            ← Domain Events
│   └── PostPublished.php
│
├── Listeners/                         ← React to Events
│   ├── SendPublishNotification.php
│   └── InvalidatePostCache.php
│
├── Jobs/                              ← Background Jobs
│   ├── SendEmailJob.php
│   └── GenerateThumbnailJob.php
│
├── Exceptions/                        ← Custom Exceptions
│   ├── Handler.php
│   ├── NotFoundException.php
│   └── ForbiddenException.php
│
├── DTOs/                              ← Data Transfer Objects (optional)
│   └── CreatePostDTO.php
│
└── Enums/                             ← Constants có type
    ├── PostStatus.php
    └── UserRole.php
```

### Nguyên tắc đặt tên
- Controller: số nhiều, động từ hành động — `PostController`, `UserController`
- Service: chức năng cụ thể — `PostService`, `AuthService`
- Repository: tên Model + Repository — `PostRepository`
- Event: hành động đã xảy ra (quá khứ) — `PostPublished`, `UserRegistered`
- Job: công việc cần làm — `SendWelcomeEmail`, `ResizeImage`

---

## 3. Database Design

### Nguyên tắc schema
- **Chuẩn hóa 3NF** cho OLTP (transactional). Denormalize có chủ đích khi cần performance.
- **Đặt tên nhất quán**: `snake_case`, số nhiều cho bảng (`posts`, `users`), số ít cho cột.
- **UUID vs Auto-increment**: UUID cho bảo mật (không đoán được ID), auto-increment cho performance. Dùng ULID là best of both.
- **Soft Delete**: Thêm `deleted_at` thay vì xóa hẳn — cho phép recover data, audit trail.
- **Timestamps**: Luôn có `created_at`, `updated_at` ở mọi bảng.

### Migrations — quy tắc bắt buộc
```
- Mỗi migration chỉ làm một việc.
- Migration phải có cả up() và down() (rollback được).
- Không sửa migration đã chạy trên production — tạo migration mới.
- Không đặt logic phức tạp trong migration.
```

### Indexing
```sql
-- Index những cột hay WHERE, ORDER BY, JOIN
-- Index composite: thứ tự cột quan trọng (selectivity cao đặt trước)
CREATE INDEX idx_posts_user_status ON posts (user_id, status);
CREATE INDEX idx_posts_published_at ON posts (published_at DESC) WHERE status = 'published';

-- KHÔNG index cột có cardinality thấp (boolean, enum ít giá trị) một mình
-- KHÔNG over-index: mỗi index làm chậm INSERT/UPDATE
```

### Foreign Keys & Constraints
```
- Luôn khai báo FK với ON DELETE behavior rõ ràng (CASCADE, RESTRICT, SET NULL).
- Dùng CHECK constraints cho business rules đơn giản (age > 0).
- Unique constraints cho email, username, slug.
```

### Connection Pooling
```
Production luôn dùng connection pool (PgBouncer, RDS Proxy).
Không để ứng dụng mở connection trực tiếp không giới hạn.
```

### Read/Write Splitting
```
Read replica cho SELECT nặng.
Master chỉ nhận INSERT/UPDATE/DELETE.
Laravel: config database.php với read/write array.
```

---

## 4. API Design

### RESTful Conventions

```
GET    /api/v1/posts          → Danh sách
POST   /api/v1/posts          → Tạo mới
GET    /api/v1/posts/{id}     → Chi tiết
PUT    /api/v1/posts/{id}     → Update toàn bộ
PATCH  /api/v1/posts/{id}     → Update một phần
DELETE /api/v1/posts/{id}     → Xóa

GET    /api/v1/posts/{id}/comments    → Resource lồng nhau (tối đa 2 cấp)
```

### HTTP Status Codes — dùng đúng

```
200 OK              → GET/PUT/PATCH thành công
201 Created         → POST tạo mới thành công
204 No Content      → DELETE thành công (không có body)
400 Bad Request     → Validation lỗi, request malformed
401 Unauthorized    → Chưa đăng nhập / token không hợp lệ
403 Forbidden       → Đã đăng nhập nhưng không có quyền
404 Not Found       → Resource không tồn tại
409 Conflict        → Duplicate (email đã tồn tại)
422 Unprocessable   → Validation lỗi business rule
429 Too Many Req    → Rate limit
500 Internal Error  → Lỗi server (không expose detail ra ngoài)
```

### Response Format chuẩn

```json
// Success
{
  "success": true,
  "data": { ... },
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 10
  }
}

// Error
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email is required", "Must be valid email"],
    "password": ["Minimum 8 characters"]
  },
  "code": "VALIDATION_ERROR"  ← error code cho client xử lý
}
```

### API Versioning
```
URL-based: /api/v1/, /api/v2/  ← Rõ ràng, dễ debug (khuyên dùng)
Header-based: Accept: application/vnd.app.v1+json
```

### Pagination
```
Offset pagination:  ?page=2&per_page=20       ← Dễ implement, có vấn đề với large dataset
Cursor pagination:  ?cursor=eyJpZCI6MTAwfQ==  ← Tốt hơn cho real-time data, infinite scroll
Keyset pagination:  ?after_id=100             ← Performance tốt nhất cho large dataset
```

### Filtering & Sorting
```
GET /api/v1/posts?status=published&category=tech&sort=-created_at&search=keyword
     ↑ filter                                   ↑ sort (- là DESC)         ↑ search
```

---

## 5. Authentication & Authorization

### Authentication — Lựa chọn

**JWT (JSON Web Token)**
```
Ưu: Stateless, không cần DB lookup mỗi request, phù hợp microservices.
Nhược: Không thể revoke trước expiry (phải dùng blacklist).
Best practice:
  - Access token: 15 phút
  - Refresh token: 7-30 ngày, lưu trong DB, có thể revoke
  - Lưu access token trong memory (JS), refresh token trong httpOnly cookie
  - Rotate refresh token mỗi lần dùng (refresh token rotation)
```

**Session-based (Sanctum)**
```
Ưu: Dễ revoke, stateful, bảo mật hơn cho web app.
Nhược: Không phù hợp stateless/microservices, cần session storage.
```

**OAuth 2.0 / Social Login**
```
Dùng Passport (full OAuth server) hoặc Socialite (login với Google/Facebook).
```

### Authorization — Roles & Permissions

```
RBAC (Role-Based): User có Role, Role có Permission.
  → Đơn giản, phù hợp hầu hết dự án.
  
ABAC (Attribute-Based): Policy dựa trên attribute của user + resource + context.
  → Phức tạp hơn, linh hoạt hơn (dùng khi RBAC không đủ).

Ví dụ RBAC:
  Roles: admin, editor, author, reader
  Permissions: post.create, post.edit.own, post.edit.any, post.delete

Implement với Laravel Gates & Policies:
  - Gate::define() cho permission đơn giản
  - Policy class cho resource-based authorization
  - Spatie/laravel-permission cho RBAC phức tạp
```

### Multi-Factor Authentication (MFA)
```
TOTP (Google Authenticator): Thêm bảo mật, nên offer cho user.
Email OTP: Dùng cho verify email, reset password.
SMS OTP: Kém bảo mật hơn TOTP (SIM swap attack), nhưng UX tốt hơn.
```

---

## 6. Bảo Mật

### OWASP Top 10 — Phải xử lý

**A01 - Broken Access Control**
```
- Kiểm tra quyền ở mọi endpoint, không chỉ ở UI.
- Never trust client: luôn validate ownership server-side.
- Disable directory listing trên web server.
- Default deny: không có permission rõ ràng → không được phép.
```

**A02 - Cryptographic Failures**
```
- Không bao giờ lưu password plain text → dùng bcrypt/argon2.
- Không dùng MD5/SHA1 cho password.
- Encrypt sensitive data at rest (PII, payment info) → AES-256.
- TLS 1.2+ cho tất cả connections, HSTS header.
- Không log sensitive data (password, token, credit card).
```

**A03 - Injection**
```
SQL Injection:
  - Luôn dùng prepared statements / parameterized queries.
  - Với Eloquent: KHÔNG dùng whereRaw() với user input.
  - Dùng: ->where('column', $userInput) ← an toàn
  - Tránh: ->whereRaw("column = '$userInput'") ← nguy hiểm

Command Injection:
  - KHÔNG dùng exec(), shell_exec(), system() với user input.
  - Nếu bắt buộc: escapeshellarg() và whitelist commands.
```

**A07 - Authentication Failures**
```
- Rate limit login endpoint (5 lần/phút).
- Account lockout sau N lần sai (tạm thời, không vĩnh viễn → DoS).
- Secure password reset: token ngắn hạn (15 phút), single-use.
- Không reveal thông tin: "Email hoặc password không đúng" thay vì chỉ định cái nào sai.
- Log tất cả failed login attempts.
```

**A08 - Software & Data Integrity**
```
- Verify integrity của dependencies (composer.lock, package-lock.json).
- Không dùng unserialize() với user input.
- Validate file upload: MIME type thực (không chỉ extension), size limit, scan virus.
```

### Input Validation — Defense in Depth
```
Rule 1: Validate ở Controller/Request layer (format, type, required).
Rule 2: Sanitize trước khi lưu DB (strip tags cho rich text).
Rule 3: Escape khi output (XSS prevention).
Rule 4: Validate lại ở Service layer cho business rules.
```

### File Upload Security
```
- Whitelist MIME types, không blacklist.
- Dùng finfo_file() để detect MIME thực, không tin $_FILES['type'].
- Đổi tên file (random UUID), không giữ tên gốc.
- Lưu ngoài webroot, serve qua controller.
- Scan virus với ClamAV nếu cần.
- Giới hạn size (server + application level).
```

### Rate Limiting
```
Login: 5 req/phút/IP
API chung: 60 req/phút/user
Upload: 10 req/giờ/user
Search: 30 req/phút/user

Implement: Redis + sliding window algorithm.
Response headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After
```

### Security Headers (Web Server Config)
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=()
```

### CORS
```
- Whitelist cụ thể domain, không dùng * cho API có auth.
- Credentials: true chỉ khi cần và origin phải cụ thể.
- Limit allowed methods và headers.
```

### Secrets Management
```
- Không bao giờ commit .env vào git.
- Dùng biến môi trường cho tất cả secrets.
- Production: dùng secret manager (AWS Secrets Manager, HashiCorp Vault).
- Rotate credentials định kỳ.
- Mỗi environment (dev, staging, prod) có credentials riêng.
```

---

## 7. Validation & Error Handling

### Validation Strategy
```
Tầng 1 — HTTP Layer (Form Request):
  - Required/optional fields
  - Type checking (string, integer, email, url)
  - Format validation (regex, min/max length)
  - Database existence (exists:table,column)

Tầng 2 — Service Layer:
  - Business rules ("user chỉ được tạo tối đa 5 post/ngày")
  - Cross-field validation ("end_date phải sau start_date")
  - State machine validation ("chỉ publish khi có content")
```

### Custom Exceptions — Đặt tên rõ ràng
```php
NotFoundException          → 404
ForbiddenException         → 403
ValidationException        → 422
ConflictException          → 409 (duplicate resource)
PaymentFailedException     → 402
RateLimitExceededException → 429
ExternalServiceException   → 503 (third party down)
```

### Global Exception Handler
```
- Map exception class → HTTP status code.
- Log stack trace (server-side), trả về message chung (client-side).
- KHÔNG trả về stack trace, SQL error, file path ra production.
- Gửi alert khi có unexpected 500 error.
```

### Fail Fast vs Graceful Degradation
```
Fail Fast: Throw exception sớm khi data không hợp lệ.
           Tránh garbage in garbage out.

Graceful Degradation: Khi service phụ (email, search) down → 
                      vẫn phục vụ core functionality,
                      queue lại task để retry sau.
```

---

## 8. Logging & Monitoring

### Logging Levels — Dùng đúng
```
DEBUG   → Chi tiết khi debug, tắt trên production.
INFO    → Sự kiện bình thường (user login, post created).
WARNING → Có thể là vấn đề (retry attempt, slow query).
ERROR   → Lỗi có thể handle được (validation fail, 404).
CRITICAL→ Lỗi nghiêm trọng cần xử lý ngay (payment fail, DB down).
```

### Những gì phải log
```
✅ Request/Response (không log body chứa sensitive data)
✅ Authentication events (login, logout, failed attempts)
✅ Authorization failures
✅ Business events (order placed, payment processed)
✅ Errors và exceptions (với stack trace)
✅ External API calls (thời gian, status)
✅ Slow queries (> 1 giây)
✅ Background job status

❌ Passwords, tokens, credit card
❌ PII không cần thiết (tùy GDPR/PDPA requirements)
```

### Structured Logging
```json
{
  "level": "info",
  "message": "Post published",
  "context": {
    "user_id": 123,
    "post_id": 456,
    "ip": "1.2.3.4"
  },
  "timestamp": "2025-01-15T10:30:00Z",
  "request_id": "uuid-abc-123"   ← Trace request xuyên suốt
}
```

### Monitoring Stack
```
Logs:       ELK Stack (Elasticsearch + Logstash + Kibana) hoặc Loki + Grafana
Metrics:    Prometheus + Grafana
Errors:     Sentry (realtime error tracking với stack trace)
APM:        New Relic, Datadog, hoặc OpenTelemetry
Uptime:     Pingdom, UptimeRobot, hoặc Blackbox Exporter
```

### Alerting
```
Alert khi:
- Error rate > 1% trong 5 phút
- Response time P99 > 2 giây
- Queue depth > 1000 jobs
- DB connection pool > 80%
- Disk > 80%
- Failed login spike (brute force detection)
```

---

## 9. Caching

### Cache Levels

```
L1 — Application Cache (in-memory, per process):
     Route cache, config cache, view cache.
     Tốc độ cao nhất, không share giữa servers.

L2 — Shared Cache (Redis/Memcached):
     Session, user data, query results, API responses.
     Share giữa nhiều server, persistence tùy config.

L3 — HTTP Cache:
     CDN cache tĩnh assets.
     Browser cache với Cache-Control headers.

L4 — Database Query Cache:
     Tắt MySQL query cache (đã deprecated), thay bằng application cache.
```

### Cache Strategy

**Cache-Aside (Lazy Loading)**
```
1. Check cache → có thì trả về
2. Không có → query DB
3. Lưu vào cache với TTL
4. Trả về kết quả

Dùng cho: data đọc nhiều, không cần realtime.
```

**Write-Through**
```
1. Write vào cache
2. Write vào DB ngay sau đó

Dùng cho: data cần đọc ngay sau khi write, consistency quan trọng.
```

**Cache Invalidation**
```
TTL-based: Tự expire sau X giây (đơn giản, eventual consistency).
Event-based: Xóa cache ngay khi data thay đổi (consistency cao hơn, phức tạp hơn).
Tag-based: Cache tags để invalidate theo nhóm (Spatie/laravel-tags).
```

### Cache Key Convention
```
{prefix}:{model}:{identifier}:{variant}

Ví dụ:
app:post:123:detail
app:post:list:page:1:status:published
app:user:456:profile
```

### Vấn đề cần tránh
```
Cache Stampede: Nhiều request đồng thời hit DB khi cache expire.
→ Giải pháp: Cache lock (atomic), staggered TTL, background refresh.

Cache Poisoning: User input ảnh hưởng cache key chứa data của user khác.
→ Giải pháp: Luôn include user_id trong cache key cho user-specific data.

Stale Data: Cache chứa data cũ quá lâu.
→ Giải pháp: TTL hợp lý, event-based invalidation.
```

---

## 10. Queue & Background Jobs

### Khi nào dùng Queue
```
✅ Gửi email, SMS, push notification
✅ Xử lý ảnh (resize, watermark)
✅ Generate PDF, export Excel
✅ Gọi external API (có thể chậm/fail)
✅ Tính toán nặng (report, analytics)
✅ Webhook outgoing
✅ Scheduled cleanup tasks
```

### Queue Design
```
Ưu tiên theo queue name:
  critical  → payment, security alerts (process ngay)
  high      → email, notification
  default   → business operations
  low       → analytics, cleanup, report

Mỗi Job:
  - Idempotent: chạy lại nhiều lần phải cho kết quả giống nhau.
  - Timeout: đặt timeout rõ ràng, không để job chạy vô tận.
  - Retry: define số lần retry và backoff strategy (exponential backoff).
  - Failed jobs: lưu vào failed_jobs table, có thể retry thủ công.
```

### Scheduled Tasks
```
Tập trung cron vào một chỗ (Laravel Scheduler → một cron duy nhất trên server).
Log kết quả của mỗi scheduled task.
Alert khi scheduled task fail.
Dùng Mutex (onOneServer) để tránh chạy duplicate trên nhiều server.
```

---

## 11. Testing

### Testing Pyramid

```
        [E2E Tests]           ← Ít, chậm, test user flow
       [Integration Tests]    ← Vừa, test Service + DB
      [Unit Tests]            ← Nhiều, nhanh, test logic thuần
```

### Unit Tests — Service Layer
```
Mock Repository → test Service logic độc lập với DB.
Test từng method, từng nhánh if/else.
Fast feedback, chạy trong CI không cần DB.
```

### Integration Tests — Repository Layer
```
Dùng DB thực (in-memory SQLite hoặc test DB riêng).
Test query đúng, relationships đúng, constraint đúng.
Dùng database transaction, rollback sau mỗi test.
```

### Feature Tests (API Tests)
```
Test toàn bộ HTTP stack: route → middleware → controller → service → DB.
Test success cases, error cases, edge cases.
Test auth: endpoint cần auth phải trả 401 khi không có token.
```

### Testing Checklist
```
□ Happy path hoạt động
□ Invalid input → đúng error message
□ Unauthorized access → 401/403
□ Resource not found → 404
□ Business rule violation → 422
□ Edge cases (empty list, max length, special chars)
□ Concurrent requests (race conditions)
```

### Test Data
```
Factory: Tạo data ngẫu nhiên có cấu trúc.
Seeder: Data cố định cho specific scenarios.
Fixture: Static data cho complex cases.
Faker: Random data có thực tế (email, name, address).
```

---

## 12. Performance & Scalability

### Database Performance
```
N+1 Query:
  Vấn đề: Lấy 100 posts → 100 query để lấy author → 101 queries total.
  Giải pháp: Eager loading (with('author')), luôn review query count.

Query Optimization:
  - EXPLAIN ANALYZE trước khi deploy query nặng.
  - Index đúng cột.
  - Tránh SELECT *, chỉ lấy cột cần thiết.
  - Tránh function trên indexed column trong WHERE.
  - Dùng chunk() cho batch processing large dataset.
```

### Horizontal Scaling
```
Stateless Application:
  - Không lưu state trên server (session → Redis, file → S3).
  - Có thể chạy N instances phía sau load balancer.

Load Balancing:
  - Round-robin cho API servers.
  - Sticky session nếu cần (stateful app, nhưng cố tránh).
  - Health check endpoint: GET /health → 200 OK.

Database Scaling:
  - Vertical scaling (bigger instance) có giới hạn.
  - Read replicas cho read-heavy workload.
  - Sharding cho extreme scale (phức tạp, dùng khi thực sự cần).
  - Connection pooling luôn dùng.
```

### Async & Non-blocking
```
- Dùng Queue cho operations không cần kết quả ngay.
- Tránh sleep() trong request cycle.
- External API calls → timeout + retry + circuit breaker.
```

### Circuit Breaker Pattern
```
Khi service ngoài (payment, SMS) liên tục fail:
  CLOSED → gọi bình thường
  OPEN   → sau N failures, không gọi nữa, trả fallback ngay
  HALF-OPEN → sau timeout, thử lại một request

Lợi ích: Không để một service down kéo toàn bộ hệ thống down.
```

### Pagination — KHÔNG dùng OFFSET cho large table
```
-- Chậm vì phải đọc và skip 100,000 rows
SELECT * FROM posts ORDER BY id LIMIT 20 OFFSET 100000;

-- Nhanh hơn nhiều (keyset pagination)
SELECT * FROM posts WHERE id > 100000 ORDER BY id LIMIT 20;
```

---

## 13. DevOps & CI/CD

### Environments
```
local    → dev laptop, fake external services (mail: Mailpit, payment: sandbox)
dev      → shared dev server, auto-deploy từ develop branch
staging  → mirror của production, test trước khi release
production → real users, real data
```

### CI/CD Pipeline (mỗi PR)
```
1. Lint & Format check (Pint, ESLint)
2. Static analysis (PHPStan level 6+)
3. Unit tests
4. Integration tests
5. Security scan (composer audit, npm audit)
6. Build Docker image
7. Deploy to staging
8. Smoke tests trên staging
9. Manual approval → deploy production
```

### Docker
```dockerfile
# Multi-stage build → image nhỏ hơn
FROM php:8.3-fpm-alpine AS builder
# Install dependencies, build assets

FROM php:8.3-fpm-alpine AS production
# Chỉ copy artifacts cần thiết
# Chạy với non-root user
# KHÔNG install dev dependencies
```

### Infrastructure as Code
```
Dùng Terraform, Pulumi, hoặc AWS CDK.
Không cấu hình server thủ công → không reproducible, không audit trail.
```

### Zero-downtime Deployment
```
Blue-Green: Deploy lên environment mới → switch traffic → xóa cũ.
Rolling: Deploy từng instance một, luôn có instance cũ serving.
Canary: Deploy cho 5% traffic trước → monitor → tăng dần.

Database migration: Phải backward-compatible (additive only).
  - Thêm cột nullable mới → OK
  - Đổi tên cột → thêm cột mới + copy data + xóa cột cũ (3 deploys)
  - KHÔNG DROP COLUMN cùng lúc với code thay đổi
```

### Backup Strategy
```
Database: Daily full backup + continuous binlog/WAL.
File storage: Replication + versioning (S3 Versioning).
Test restore: Thực sự restore từ backup định kỳ (không chỉ backup).
Retention: 7 daily, 4 weekly, 12 monthly.
Off-site: Backup ở region/provider khác.
```

---

## 14. Documentation

### API Documentation
```
OpenAPI/Swagger: Chuẩn công nghiệp, generate client SDK được.
  → Dùng L5-Swagger (Laravel) hoặc Scribe.
  
Postman Collection: Dễ share với frontend team.
  → Export và commit vào repo.
```

### Code Documentation
```
Ưu tiên code tự giải thích (good naming) hơn comment.
Comment khi: WHY không rõ ràng từ code (workaround, business constraint ẩn).
KHÔNG comment: WHAT (code đã nói rõ rồi).
```

### README tối thiểu
```
□ Mô tả ngắn gọn dự án là gì
□ Yêu cầu môi trường (PHP 8.3, MySQL 8.0, Redis 7)
□ Setup local (từng bước, copy-paste được)
□ Chạy tests
□ Deploy
□ Biến môi trường quan trọng
□ Link tới API docs, ERD diagram
```

### ADR (Architecture Decision Records)
```
Ghi lại các quyết định kiến trúc quan trọng:
  - Vấn đề là gì
  - Các lựa chọn đã xem xét
  - Quyết định chọn gì và TẠI SAO
  - Hệ quả (trade-offs)

Lợi ích: 6 tháng sau không ai hỏi "Tại sao lại dùng X?"
```

---

## 15. Checklist Trước Khi Deploy Production

### Security
```
□ Tất cả secrets trong environment variables, không trong code
□ DEBUG=false, APP_ENV=production
□ HTTPS bắt buộc, HSTS enabled
□ CORS configured đúng
□ Rate limiting enabled
□ SQL injection: dùng parameterized queries toàn bộ
□ File upload validated (MIME type, size, rename)
□ Auth endpoints có rate limit
□ Sensitive data không logged
□ Dependencies không có known vulnerabilities (composer audit)
```

### Performance
```
□ Route cache, config cache, view cache enabled
□ N+1 queries đã fix (dùng telescope/debugbar kiểm tra)
□ Database indexes đúng chỗ
□ Connection pooling configured
□ Slow query logging enabled (> 1s)
□ CDN cho static assets
```

### Reliability
```
□ Health check endpoint hoạt động
□ Graceful shutdown configured
□ Queue workers có supervisor (auto-restart)
□ Failed job monitoring
□ Error alerting (Sentry)
□ Backup đang chạy và đã test restore
□ Rollback plan có sẵn
```

### Observability
```
□ Structured logging enabled
□ Request ID tracing
□ Metrics dashboard (response time, error rate, throughput)
□ Alerting rules configured
□ Runbook cho các alert phổ biến
```

### Process
```
□ Staging deploy thành công
□ Smoke tests pass trên staging
□ Database migration backward-compatible
□ API breaking changes? → version bump
□ Team được thông báo về deploy window
□ On-call person biết deploy đang xảy ra
```

---

## Tham Khảo & Tiếp Theo

### Đọc thêm
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [The Twelve-Factor App](https://12factor.net/)
- [Martin Fowler - Patterns of Enterprise Application Architecture](https://martinfowler.com/books/eaa.html)
- [System Design Primer](https://github.com/donnemartin/system-design-primer)

### Tools gợi ý (Laravel ecosystem)
```
Security:     spatie/laravel-permission, tymon/jwt-auth, laravel/sanctum
Caching:      predis/predis (Redis)
Queue:        Laravel Horizon (Redis queue monitoring)
Monitoring:   sentry/sentry-laravel, barryvdh/laravel-debugbar (dev only)
Testing:      PHPUnit, Pest, Laravel Dusk (E2E)
Code quality: laravel/pint, phpstan/phpstan, rector/rector
API Docs:     knuckleswtf/scribe, darkaonline/l5-swagger
```
