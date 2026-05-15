# Journo — Fullstack Project Presentation (English)
## Part 1: Project Overview & System Architecture

> First-person voice — for fullstack technical interviews.  
> Author: TuanTu | Stack: React 19 + Laravel 12 + MySQL

---

## 1. PROJECT OVERVIEW

### 1.1 What does the system do and for whom?

Journo is a fullstack blog application that lets users register, log in, and write or share posts with a community. Readers can browse articles, leave comments, and interact with content; authors have full control over their own posts.

The problem I set out to solve was building a complete end-to-end content platform — from user authentication and permission-based CRUD to a threaded comment system — using a strictly API-first architecture where the frontend and backend are fully decoupled and communicate only through a REST JSON API.

### 1.2 Full Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Frontend | React | 19.2.5 |
| Frontend Router | React Router DOM | 7.14.2 |
| Frontend HTTP | Axios | 1.15.2 |
| Frontend Build | Vite | 8.0 |
| Backend Framework | Laravel | 12.x |
| Backend Runtime | PHP | 8.2 |
| Authentication | Laravel Sanctum | 4.0 |
| Database | MySQL | 8.x (via XAMPP) |
| Dev Server | `php artisan serve` | port 8000 |

### 1.3 Core Features

1. **Register / Login** — token-based authentication via Laravel Sanctum
2. **Browse posts** — offset-based pagination, published posts only
3. **Post detail** — full content, author info, category, threaded comments
4. **Create post** — form with title, excerpt, content, status (draft/published)
5. **Edit post** — only the author may edit (server-enforced authorization)
6. **Delete post** — with confirmation dialog, server-enforced authorization
7. **Comment system** — post comments when logged in, threaded reply support
8. **Contextual navigation** — Navbar shows/hides actions based on login state

**Infrastructure designed but not yet wired to UI:**
- Post like system
- User follow system
- Media / file upload management
- Hierarchical categories (parent/child)

### 1.4 What Makes This Different from a Plain CRUD App

1. **API-first architecture** — frontend and backend are fully independent; the backend never renders HTML; all communication is REST JSON
2. **Revocable token auth with Sanctum** — every token is a database record in `personal_access_tokens` and can be individually revoked; this is fundamentally different from stateless JWT
3. **Server-side authorization** — the controller checks `post->user_id === request->user()->id` before any mutation, preventing user A from editing user B's content
4. **Eager loading to prevent N+1** — `Post::with('author', 'category', 'tags')` loads all relations in 4 queries instead of N+1
5. **Threaded comments** — `comments.parent_id` self-reference supports nested replies
6. **Production-ready schema** — foreign keys with explicit cascade rules, unique indexes, ENUM types for status, composite primary keys on junction tables

---

## 2. SYSTEM ARCHITECTURE

### 2.1 Full System Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        BROWSER (User)                           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                   React Application                     │   │
│  │                                                         │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │   │
│  │  │  Navbar  │  │  Pages   │  │   Axios HTTP Client  │  │   │
│  │  │          │  │ Home     │  │                      │  │   │
│  │  │ reads    │  │ Login    │  │  baseURL: :8000/api  │  │   │
│  │  │ token    │  │ Register │  │                      │  │   │
│  │  │ from     │  │ PostDtl  │  │  interceptor: auto   │  │   │
│  │  │localStorage│ CreatePst│  │  attach Bearer token │  │   │
│  │  │          │  │ EditPost │  │                      │  │   │
│  │  └──────────┘  └────┬─────┘  └──────────┬───────────┘  │   │
│  │                     │                    │              │   │
│  │              useState/useEffect          │              │   │
│  │              (local state per page)      │              │   │
│  └─────────────────────────────────────────┼──────────────┘   │
│                                            │                   │
│                    localStorage            │ HTTP Requests      │
│               ┌──────────────┐            │ (JSON + Bearer)    │
│               │  "token": "" │            │                   │
│               └──────────────┘            │                   │
└────────────────────────────────────────────┼───────────────────┘
                                             │
                              ═══════════════╪═══════════════
                                   NETWORK (HTTP/REST)
                              ═══════════════╪═══════════════
                                             │
┌────────────────────────────────────────────┼───────────────────┐
│                  Laravel 12 Backend         │                   │
│                                             ▼                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                  bootstrap/app.php                       │  │
│  │   withRouting → api.php | withExceptions → JSON errors   │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │               routes/api.php (Router Layer)              │  │
│  │                                                          │  │
│  │  Public:     POST /register, POST /login                 │  │
│  │              GET /posts, GET /posts/{id}                 │  │
│  │              GET /categories, GET /posts/{id}/comments   │  │
│  │                                                          │  │
│  │  Protected:  middleware('auth:sanctum') {                │  │
│  │    POST /logout, GET /me                                 │  │
│  │    POST/PUT/DELETE /posts                                │  │
│  │    POST/DELETE /comments                                 │  │
│  │  }                                                       │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │            Controllers (Api namespace)                   │  │
│  │                                                          │  │
│  │  AuthController    PostController    CommentController   │  │
│  │  CategoryController                                      │  │
│  │                                                          │  │
│  │  Validate → Authorize → Call Model → Return JSON         │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              Eloquent Models (ORM Layer)                 │  │
│  │                                                          │  │
│  │  User   Post   Comment   Category   Tag                  │  │
│  │  Like   Follow   Media                                   │  │
│  │                                                          │  │
│  │  Relationships: hasMany, belongsTo, belongsToMany        │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
└──────────────────────────────┼──────────────────────────────────┘
                               │
┌──────────────────────────────┼──────────────────────────────────┐
│              MySQL Database   │                                  │
│                              ▼                                  │
│  users  posts  categories  tags  post_tag                       │
│  comments  likes  follows  media                                │
│  personal_access_tokens  (Sanctum table)                        │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Backend Layer Diagram

```
Incoming HTTP Request
        │
        ▼
┌─────────────────────────────────────────┐
│         Sanctum Middleware              │
│  (auth:sanctum)                         │
│  - Read Bearer token from header        │
│  - Hash token → look up in DB           │
│  - Valid → bind user to $request        │
│  - Invalid → 401 JSON (from app.php)    │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│         Controller Layer                │
│  - Validate request input               │
│  - Authorization check (owner match)    │
│  - Call Eloquent Models                 │
│  - Return response()->json(...)         │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│         Eloquent ORM Layer              │
│  - Model::find(), Model::where()        │
│  - with() eager loading                 │
│  - Relationship traversal              │
│  - save(), delete()                     │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│         MySQL Database                  │
│  - PDO prepared statements (via Laravel)│
│  - Foreign key enforcement              │
│  - Cascade deletes                      │
└─────────────────────────────────────────┘
```

### 2.3 Key File Role Map

| File | Role | Why separated |
|---|---|---|
| `frontend/src/api/axios.js` | Creates Axios instance with token interceptor | One shared instance; token logic written once |
| `frontend/src/App.jsx` | Declares all application routes | Single place to see the full URL structure |
| `frontend/src/components/NavBar.jsx` | Navigation bar, reads token from localStorage | Shared component; decoupled from page state |
| `backend/routes/api.php` | Defines all API endpoints, public vs protected groups | Routing separate from logic; full API surface visible at a glance |
| `backend/app/Http/Controllers/Api/AuthController.php` | register / login / logout / me | Auth logic isolated from business logic |
| `backend/app/Http/Controllers/Api/PostController.php` | Post CRUD with authorization | Single Responsibility: one controller per resource |
| `backend/app/Models/Post.php` | Eloquent model, relationship definitions | Model knows how to connect to related tables |
| `backend/bootstrap/app.php` | App config, custom exception handler | Centralizes AuthenticationException → JSON 401 response |
| `backend/database/migrations/*.php` | Schema as code | Version-controlled schema, reproducible across environments |

### 2.4 End-to-End Data Flows

#### Flow 1: Authentication (Login → Token → Protected Request)

```
[User enters email/password in Login.jsx]
        │
        │ handleSubmit() — event.preventDefault()
        ▼
[api.post('/login', { email, password })]
        │
        │ Interceptor: no token attached yet
        ▼
[POST http://127.0.0.1:8000/api/login]
        │
        ▼
[routes/api.php] → Route::post('/login', [AuthController::class, 'login'])
        │
        ▼
[AuthController::login()]
  │  User::where('email', $email)->first()
  │  → not found: return 404
  │
  │  Hash::check($password, $user->password)
  │  → bcrypt verify: extract salt from stored hash,
  │    re-hash plaintext, compare
  │  → mismatch: return 401
  │
  │  $user->createToken('auth_token')->plainTextToken
  │  → Sanctum generates random 40-char token
  │  → SHA-256 hash stored in personal_access_tokens
  │  → Returns plaintext: format "id|randomString"
        │
        ▼
[Response JSON: { user: {...}, token: "3|Kx9mN2..." }]
        │
        ▼
[Login.jsx receives token]
  localStorage.setItem("token", token)
  window.location.href = "/"  ← full reload (not navigate())
        │
        ▼
[Next request — e.g.: api.post('/posts', {...})]
        │
        ▼
[Axios interceptor runs]
  const token = localStorage.getItem("token")
  config.headers.Authorization = `Bearer ${token}`
        │
        ▼
[POST /api/posts with header: Authorization: Bearer 3|Kx9mN2...]
        │
        ▼
[Sanctum middleware 'auth:sanctum']
  - Strip token from header
  - Hash it → look up in personal_access_tokens
  - Match found → bind user to $request
  - No match → AuthenticationException
    → bootstrap/app.php catches → JSON 401
        │
        ▼
[PostController::store() runs with $request->user() populated]
```

#### Flow 2: Create Post (Business Flow)

```
[User fills form in CreatePost.jsx]
  title, excerpt, content, status (draft/published)
        │
        │ handleSubmit() — setLoading(true), setError(null)
        ▼
[api.post('/posts', { title, content, excerpt, status })]
        │
        │ interceptor attaches Bearer token
        ▼
[Sanctum verifies token → $request->user() = authenticated User]
        │
        ▼
[PostController::store()]
  │
  │ $request->validate([...])
  │   'title' required|string|max:255
  │   'content' required|string
  │   → failure: auto 422 with error messages
  │
  │ $post = new Post()
  │ $post->user_id = $request->user()->id  ← from auth, NOT request body
  │ $post->slug    = Str::slug($request->title)  ← auto URL-friendly slug
  │ $post->status  = $request->status ?? 'draft'
  │
  │ if ($post->status === 'published') {
  │     $post->published_at = now()
  │ }
  │
  │ $post->save()    ← INSERT INTO posts ...
  │
        ▼
[Response JSON: post object, HTTP 201 Created]
        │
        ▼
[CreatePost.jsx: window.location.href = "/"]
        │
        ▼
[Home.jsx fetchPosts()]
  api.get('/posts')
  → PostController::index()
  → Post::with('author','category','tags')
      ->where('status','published')
      ->orderBy('published_at','desc')
      ->paginate(10)
  → returns paginated JSON
        │
        ▼
[Home.jsx renders post list]
```

### 2.5 Database Schema

```sql
-- Table 1: users
CREATE TABLE users (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(255) NOT NULL,
  email             VARCHAR(255) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password          VARCHAR(255) NOT NULL,  -- bcrypt hash
  remember_token    VARCHAR(100) NULL,
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL
);

-- Table 2: categories (self-referencing hierarchy)
CREATE TABLE categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) NOT NULL UNIQUE,
  description TEXT NULL,
  color       VARCHAR(7) DEFAULT '#000000',
  parent_id   BIGINT UNSIGNED NULL,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL
);

-- Table 3: posts (core content table)
CREATE TABLE posts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  category_id  BIGINT UNSIGNED NULL,
  title        VARCHAR(255) NOT NULL,
  slug         VARCHAR(255) NOT NULL UNIQUE,
  excerpt      TEXT NULL,
  content      LONGTEXT NOT NULL,
  cover_image  VARCHAR(255) NULL,
  status       ENUM('draft','published','archived') DEFAULT 'draft',
  published_at TIMESTAMP NULL,
  view_count   INT UNSIGNED DEFAULT 0,
  reading_time SMALLINT UNSIGNED NULL,
  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL
);

-- Table 4: tags
CREATE TABLE tags (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  slug       VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

-- Table 5: post_tag (N-N junction)
CREATE TABLE post_tag (
  post_id BIGINT UNSIGNED NOT NULL,
  tag_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),  -- composite PK prevents duplicates
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
);

-- Table 6: comments (threaded with parent_id)
CREATE TABLE comments (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id     BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  parent_id   BIGINT UNSIGNED NULL,  -- NULL = top-level
  content     TEXT NOT NULL,
  is_approved BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (post_id)   REFERENCES posts(id)    ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL
);

-- Table 7: likes (one per user per post)
CREATE TABLE likes (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  post_id    BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY unique_like (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

-- Table 8: follows (social graph)
CREATE TABLE follows (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  follower_id  BIGINT UNSIGNED NOT NULL,
  following_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY unique_follow (follower_id, following_id),
  FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL
);

-- Table 9: media
CREATE TABLE media (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id    BIGINT UNSIGNED NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  file_name  VARCHAR(255)  NOT NULL,
  file_path  VARCHAR(255)  NOT NULL,
  file_type  VARCHAR(50)   NOT NULL,
  file_size  BIGINT UNSIGNED NOT NULL,
  mime_type  VARCHAR(100)  NOT NULL,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
```

**Relationship summary:**
- `users` → `posts`: 1-N (one user writes many posts)
- `users` → `comments`: 1-N
- `posts` → `comments`: 1-N (delete post → cascade delete comments)
- `posts` ↔ `tags`: N-N (via `post_tag`)
- `categories` → `categories`: self-referencing 1-N
- `users` ↔ `users` (follows): self-referencing N-N

---

*Continue to: [Part 2 — Deep Technical Analysis](presentation_en_2_deep_dive.md)*
