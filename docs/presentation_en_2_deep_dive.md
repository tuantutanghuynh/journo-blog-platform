# Journo — Fullstack Project Presentation (English)
## Part 2: Deep Technical Analysis

> Continued from [Part 1 — Overview & Architecture](presentation_en_1_overview_architecture.md)

---

## 3. DEEP COMPONENT ANALYSIS

### 3.1 AUTH FLOW — Laravel Sanctum Token Authentication

#### WHY: Why Sanctum instead of hand-rolling JWT?

I chose Laravel Sanctum because the project is a SPA that needs API token authentication, and Sanctum solves exactly that problem. Sanctum stores every token as a record in the `personal_access_tokens` database table. This means I can revoke any individual token — which is what real logout means.

With stateless JWT, once a token is issued the server has no record of it. Logout becomes a client-side illusion: you delete the token from localStorage, but the token itself is still cryptographically valid until it expires. With Sanctum, calling `$request->user()->currentAccessToken()->delete()` invalidates the token server-side immediately.

The tradeoff I accepted: every request requires a database lookup to verify the token. JWT needs no DB hit for verification. At this scale that's fine; at microservice scale I'd reconsider.

#### HOW: Sanctum token lifecycle step by step

```php
// AuthController.php — register()
$user->password = bcrypt($request->password);
// bcrypt() = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
// .env: BCRYPT_ROUNDS=12 → 2^12 = 4096 iterations
// Each hash includes a random salt embedded in the output string

$token = $user->createToken('auth_token')->plainTextToken;
// createToken():
//   1. Generate cryptographically random 40-character string
//   2. Prepend the record's database ID: "3|Kx9mN2pQrT..."
//   3. SHA-256 hash the token → store hash in personal_access_tokens
//   4. Return plaintext (shown exactly once — never stored plaintext)

return response()->json(['user' => $user, 'token' => $token], 201);
```

```php
// AuthController.php — login()
$user = User::where('email', $request->email)->first();

$passwordIsCorrect = Hash::check($request->password, $user->password);
// Hash::check():
//   - Extracts the salt embedded in the stored bcrypt hash
//   - Re-hashes the input plaintext with that same salt
//   - Constant-time comparison → no timing attack vulnerability

$token = $user->createToken('auth_token')->plainTextToken;
// Same flow as register — creates a NEW token record
// User can have multiple active tokens (multiple devices)
```

```php
// AuthController.php — logout()
$request->user()->currentAccessToken()->delete();
// currentAccessToken() = the specific token used in THIS request
// delete() removes the record from personal_access_tokens
// → All other tokens for this user remain valid (device-specific logout)
```

```javascript
// frontend/src/api/axios.js — Token Attachment Interceptor
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  // Read fresh from localStorage on every request
  // (not cached in a variable — avoids stale reads after logout)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
    // RFC 6750 Bearer token format
    // Sanctum middleware reads: Authorization header → strips "Bearer " → verifies token
  }
  return config;
  // MUST return config — returning nothing blocks the request
});
```

**Token verification on the backend:**
```
Header: Authorization: Bearer 3|Kx9mN2pQrT...
         │
         │ Sanctum parses: id = 3, token = Kx9mN2pQrT...
         ▼
SELECT * FROM personal_access_tokens WHERE id = 3
         │
         │ SHA-256(Kx9mN2pQrT...) vs stored token_hash
         ▼
Match found → load User(tokenable_id) → bind to $request->user()
No match   → AuthenticationException
           → bootstrap/app.php catches → JSON { message: 'Unauthenticated.' } 401
```

#### TRADEOFF

| | Sanctum (DB-backed token) | Stateless JWT |
|---|---|---|
| Real logout | Yes (delete record) | No (wait for expiry) |
| Per-device revoke | Yes | No |
| DB hit per request | Yes | No |
| Token introspection | Yes (can see active tokens) | No |
| Best for | SPAs, mobile where logout matters | Microservices, high-scale |

---

### 3.2 TOKEN STORAGE — localStorage vs httpOnly Cookie

#### HOW: Current implementation

```javascript
// After login — store token
localStorage.setItem("token", token);

// Navbar.jsx — derive auth state
const token = localStorage.getItem("token");
const isLoggedIn = token !== null;

// Logout — remove token
localStorage.removeItem("token");
window.location.href = "/login";  // full reload to re-render Navbar
```

#### WHY: The honest rationale and the known risk

I chose localStorage because it's the simplest pattern for SPA development: no CSRF tokens, no `withCredentials`, no same-site cookie configuration. The Axios interceptor reads it synchronously and attaches it. Done.

The known risk: any JavaScript running on the page can call `localStorage.getItem('token')`. If an XSS vulnerability exists — even just unescaped user-generated content rendered as HTML — an attacker's script can steal the token.

httpOnly cookies are immune to this: the browser sends the cookie automatically, JavaScript cannot read it. The cost is CORS `credentials: true` on both sides, CSRF protection (SameSite header or CSRF token), and more complex local development setup.

**My honest assessment:** localStorage is acceptable for a learning project. It should be replaced before any real users interact with the system.

---

### 3.3 STATE MANAGEMENT — Local State Only (No Context, No Redux)

#### WHY: Why I deliberately avoided Context API and Redux

Every page in Journo manages its own data with `useState` and `useEffect`. I made a deliberate decision not to introduce Context API or Redux, and here is the reasoning:

- **Auth state:** The token lives in localStorage. Any component that needs to know if the user is logged in calls `localStorage.getItem('token')` directly. There is nothing to share.
- **Post data:** Only consumed by one page at a time. No reason to lift it to global state.
- **Form data:** Completely local to each form component. `useState` is the right tool.

Adding Redux to this project would mean writing actions, reducers, a store, and selectors for data that is already accessible from a single `localStorage.getItem` call. That is over-engineering.

#### HOW: The consistent 3-state pattern

```javascript
// Home.jsx — the pattern used consistently across all data-fetching pages
const [posts, setPosts]     = useState([]);    // the data
const [loading, setLoading] = useState(true);  // loading indicator
const [error, setError]     = useState(null);  // error message

const fetchPosts = async () => {
  setLoading(true);   // show spinner
  setError(null);     // clear previous errors

  try {
    const res = await api.get("/posts");
    setPosts(res.data.data);
    // res.data = full Laravel pagination object
    // res.data.data = the actual array of posts
    // (Laravel paginate() wraps items in a 'data' key)
  } catch (err) {
    setError("Failed to load posts");  // user-facing message, not raw error
  } finally {
    setLoading(false);  // always runs — success or failure
  }
};

// Rendering pattern:
if (loading) return <p>Loading posts...</p>;
if (error)   return <p style={{ color: "red" }}>{error}</p>;
// reaching here means: data is ready, render it
```

#### TRADEOFF: What this approach misses

Without React Query or SWR, I have no:
- **Client-side cache** — navigating back to Home re-fetches all posts
- **Background revalidation** — data goes stale with no automatic refresh
- **Deduplication** — if two components request the same endpoint simultaneously, two requests fire
- **Optimistic updates** — after creating a post, I redirect rather than inserting into the local list

These are the improvements I would make first if this were a production application.

---

### 3.4 ROUTING — React Router DOM v7

#### HOW: Route declarations

```javascript
// App.jsx — all routes in one place
export default function App() {
  return (
    <BrowserRouter>
      <Navbar />   {/* renders on every route */}
      <Routes>
        <Route path="/"              element={<Home />} />
        <Route path="/login"         element={<Login />} />
        <Route path="/register"      element={<Register />} />
        <Route path="/posts/:id"     element={<PostDetail />} />
        <Route path="/create-post"   element={<CreatePost />} />
        <Route path="/posts/:id/edit" element={<EditPost />} />
      </Routes>
    </BrowserRouter>
  );
}
```

**Important design gap:** There are no protected routes at the frontend level. `CreatePost`, `EditPost` are accessible without authentication — the backend will reject the submission with 401, but the user has already wasted time filling in the form. The fix is a `PrivateRoute` component.

#### HOW: Reading URL params

```javascript
// PostDetail.jsx, EditPost.jsx
import { useParams } from "react-router-dom";

const { id } = useParams();
// Route /posts/:id → useParams() returns { id: "42" }
// Note: id is a STRING, not a number
// Eloquent's find() accepts string IDs (casts automatically)
```

#### HOW: Navigation after mutations

```javascript
// I use window.location.href instead of useNavigate()
window.location.href = "/";
// Triggers a full page reload.
// WHY: The Navbar reads token from localStorage on mount.
//   useNavigate() would only swap the route component —
//   Navbar stays mounted and does not re-read localStorage.
//   A full reload re-mounts everything → Navbar sees the new token.
// COST: Loses SPA seamlessness. Fix: share auth state via Context.
```

---

### 3.5 API DESIGN — RESTful Conventions

#### HOW: Endpoint naming and HTTP methods

```
GET    /api/posts                → index()   — retrieve collection (idempotent)
GET    /api/posts/{id}           → show()    — retrieve single resource
POST   /api/posts                → store()   — create new resource
PUT    /api/posts/{id}           → update()  — update resource
DELETE /api/posts/{id}           → destroy() — delete resource

POST   /api/register             — create a user (action on a collection)
POST   /api/login                — obtain a token (action, not a resource)
POST   /api/logout               — revoke a token

GET    /api/posts/{id}/comments  — nested resource: comments of a post
POST   /api/posts/{id}/comments  — create comment for a post
DELETE /api/comments/{id}        — delete a specific comment
```

**Honest inconsistency:** I use `PUT` for post updates but implement partial update logic (`if ($request->title) { $post->title = $request->title; }`). The correct HTTP method for partial updates is `PATCH`. This should be fixed.

#### HOW: Response format patterns

```php
// 201 Created — resource creation
return response()->json($post, 201);

// 200 OK — retrieval (status code 200 is Laravel default, can omit)
return response()->json($posts, 200);

// 404 Not Found
return response()->json(['message' => 'Post not found'], 404);

// 403 Forbidden
return response()->json(['message' => 'You do not have permission...'], 403);

// 401 Unauthenticated — from bootstrap/app.php exception handler
return response()->json(['message' => 'Unauthenticated.'], 401);

// 422 Unprocessable — from $request->validate() failure (automatic)
// Laravel auto-returns: { message: "...", errors: { field: ["rule msg"] } }
```

**Laravel pagination JSON structure** (consumed by frontend):
```json
{
  "current_page": 1,
  "data": [ /* array of posts */ ],
  "last_page": 3,
  "per_page": 10,
  "total": 25,
  "next_page_url": "http://127.0.0.1:8000/api/posts?page=2",
  "prev_page_url": null
}
```

---

### 3.6 DATABASE LAYER — Eloquent ORM

#### WHY: Eloquent over raw SQL

I chose Eloquent for three reasons: automatic PDO prepared statements prevent SQL injection without extra effort; the relationship API makes complex joins readable; and migrations give me version-controlled, reproducible schema.

The cost is some performance overhead and the N+1 trap — which is why eager loading is non-negotiable on any list endpoint.

#### HOW: Preventing N+1 Queries

```php
// PostController::index() — CORRECT
$posts = Post::with('author', 'category', 'tags')
    ->where('status', 'published')
    ->orderBy('published_at', 'desc')
    ->paginate(10);

// with('author', 'category', 'tags') = Eager Loading
// Executes 4 queries total (not N+1):
//   Query 1: SELECT * FROM posts WHERE status='published' ... LIMIT 10
//   Query 2: SELECT * FROM users WHERE id IN (1,2,5,...)
//   Query 3: SELECT * FROM categories WHERE id IN (3,7,...)
//   Query 4: SELECT tags.*, post_tag.post_id FROM tags
//            JOIN post_tag ON ... WHERE post_tag.post_id IN (...)
```

**Without eager loading — the N+1 trap:**
```php
// WRONG — this fires 10 extra queries for author, 10 for category
$posts = Post::where('status', 'published')->paginate(10);
foreach ($posts as $post) {
    echo $post->author->name;    // +1 query per post
    echo $post->category->name;  // +1 query per post
}
// Total: 1 + 10 + 10 = 21 queries for 10 posts
// Scale to 100 posts: 201 queries
```

#### HOW: Relationship Definitions

```php
// Post.php
public function author()
{
    return $this->belongsTo(User::class, 'user_id');
    // 'user_id' = explicit FK name
    // Aliased 'author' instead of 'user' — semantically clearer in blog context
}

public function tags()
{
    return $this->belongsToMany(Tag::class, 'post_tag');
    // Laravel handles the JOIN automatically
    // post_tag.post_id, post_tag.tag_id matched by convention
}
```

```php
// Comment.php — self-referencing
public function replies()
{
    return $this->hasMany(Comment::class, 'parent_id');
    // A comment has many replies that share the same parent_id
}

public function parent()
{
    return $this->belongsTo(Comment::class, 'parent_id');
    // Inverse: a reply belongs to a parent comment
}
```

#### HOW: Authorization in Controller

```php
// PostController::update() and destroy() — consistent pattern
$post = Post::find($id);

if (!$post) {
    return response()->json(['message' => 'Post not found'], 404);
}

if ($post->user_id !== $request->user()->id) {
    return response()->json(['message' => 'Forbidden'], 403);
    // $request->user()->id comes from Sanctum — cannot be spoofed
    // $post->user_id comes from the database
    // Mismatch means: authenticated but not the owner
}
```

---

### 3.7 SECURITY

#### 3.7.1 Password Hashing with Bcrypt

```php
// Register: hash password before storing
$user->password = bcrypt($request->password);
// bcrypt() wraps PHP's password_hash($pass, PASSWORD_BCRYPT)
// .env: BCRYPT_ROUNDS=12 → cost factor 12 → 2^12 = 4096 iterations
// Each hash includes a unique random salt embedded in the output
// Example output: "$2y$12$..." (algorithm, cost, salt+hash combined)

// Login: verify without knowing the original password
Hash::check($request->password, $user->password);
// Extracts the salt from the stored hash
// Re-hashes the input with that salt
// Constant-time comparison (prevents timing attacks)
```

**Why not MD5 or SHA-256?**
MD5 and SHA-256 are general-purpose hash functions designed for speed. A modern GPU can compute billions of SHA-256 hashes per second — meaning a brute-force or rainbow table attack is feasible. Bcrypt is designed specifically to be slow and to have an adjustable cost factor. As hardware gets faster, you increase the rounds to keep pace.

#### 3.7.2 SQL Injection Prevention

```php
// All Eloquent queries use PDO prepared statements internally
$user = User::where('email', $request->email)->first();
// Actual SQL: SELECT * FROM users WHERE email = ? [binding: email value]
// The email value is NEVER concatenated into the SQL string
// Even if email = "' OR '1'='1" → treated as a literal string, not SQL

// Laravel validation adds another layer:
$request->validate(['email' => 'required|email']);
// Rejects malformed input before it reaches the query layer
```

#### 3.7.3 CORS

The frontend dev server runs on port 5173 (Vite); the backend runs on port 8000 (artisan serve). Without CORS headers, the browser's same-origin policy blocks all cross-origin requests.

Laravel's default CORS config (via `config/cors.php`) allows all origins in development. For production, this must be restricted:

```php
// config/cors.php — production
'allowed_origins' => ['https://journo.com'],
// Not ['*'] — never allow all origins in production
```

CORS is enforced by the browser, not the server. The server just advertises its policy via `Access-Control-Allow-Origin` response headers. The Axios client does not configure CORS — it simply sends requests and the browser enforces the policy.

---

### 3.8 DESIGN PATTERNS IN THE CODE

#### Pattern 1: Active Record (Eloquent)

**3 signs in the code:**
1. The model object knows how to save itself: `$post->save()`
2. The model knows its own relationships: `$post->author`, `$post->comments`
3. The model carries both data and behavior

```php
$post = new Post();
$post->title = $request->title;
$post->save();  // Model issues its own INSERT query
// No separate DAO class needed
```

**WHY:** Rapid development, readable code, no boilerplate DAO layer.  
**TRADEOFF:** Model carries both data and persistence logic — violates SRP if you add complex business rules. Fix: introduce Service classes.

#### Pattern 2: Middleware Chain

**3 signs in the code:**
1. `Route::middleware('auth:sanctum')->group(...)` wraps entire route groups
2. The middleware runs before the controller — controllers do not re-check auth
3. `bootstrap/app.php` adds global exception-handling middleware

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    // PostController::store() has no auth check —
    // the middleware already guarantees $request->user() is populated
});
```

**WHY:** Single Responsibility — middleware handles cross-cutting concerns (auth), controllers handle business logic.  
**TRADEOFF:** Implicit dependency — if you test a controller directly, the middleware chain is bypassed. Must use `actingAs()` in tests.

#### Pattern 3: Interceptor (Axios)

**3 signs in the code:**
1. Token-attachment logic lives in exactly one place: `api/axios.js`
2. All pages import the same `api` instance — none of them know about tokens
3. Changing the auth scheme requires editing one file, not every page

```javascript
// api/axios.js — set up once
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// All pages just import `api` — they get the interceptor for free
// Home.jsx:       api.get("/posts")       ← Bearer attached automatically
// CreatePost.jsx: api.post("/posts", ...) ← Bearer attached automatically
```

**WHY:** DRY — don't repeat token logic in every component.  
**TRADEOFF:** Hidden behavior — a developer looking at `api.get('/posts')` doesn't see the token attachment without reading `axios.js`.

#### Pattern 4: Repository (implicit, via Eloquent)

**3 signs in the code:**
1. Controllers never write raw SQL — only call Model methods
2. If the database engine changes, only Models need to change
3. Data access patterns (eager loading, filtering) are defined in one layer

**WHY:** Separation of concerns — controllers don't know how data is stored.  
**TRADEOFF:** Eloquent models conflate data access and business logic. True Repository pattern would have a separate class.

---

### 3.9 NON-OBVIOUS DESIGN DECISIONS

#### 1. `window.location.href` instead of `useNavigate()`

After a successful login, I call `window.location.href = "/"` instead of React Router's `navigate("/")`. The reason: the `Navbar` component reads `localStorage.getItem('token')` on mount to decide which links to show. A React Router navigation does not unmount and remount `Navbar` — it stays mounted with the old `isLoggedIn = false` value. A full page reload forces `Navbar` to re-mount and re-read the new token.

This is a symptom of not having shared auth state. The proper fix is a React Context that holds the auth state and provides a `login()` / `logout()` function that components call to trigger a re-render.

#### 2. `user_id` comes from `$request->user()`, not the request body

```php
$post->user_id = $request->user()->id;
// NOT: $post->user_id = $request->user_id;
```

If I read `user_id` from the request body, a client could send any `user_id` they want and create posts attributed to other users. `$request->user()` is populated exclusively by Sanctum from the verified token — it cannot be manipulated from the outside.

#### 3. `is_approved = true` hardcoded in CommentController

```php
$comment->is_approved = true;  // hardcoded
```

The schema designed for future moderation (the field exists, default is `false`). The controller overrides it to `true` immediately so comments appear without requiring admin review — which is the right behavior for a learning project, but would allow spam in production. The fix is an admin queue that sets `is_approved` after review.

#### 4. Comments fetched separately from the post

```javascript
// PostDetail.jsx — two independent requests
useEffect(() => {
  fetchPost();      // GET /posts/:id
  fetchComments();  // GET /posts/:id/comments
}, []);
```

The backend could include comments in the post response with `Post::with('comments.user')`. I chose two requests to keep the comment endpoint flexible — it can be called independently, supports pagination later, and returns comments with their nested replies structure separate from the post data.

---

*Continue to: [Part 3 — Improvements & Interview Q&A](presentation_en_3_improvements_qa.md)*
