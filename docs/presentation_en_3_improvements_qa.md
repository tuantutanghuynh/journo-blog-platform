# Journo — Fullstack Project Presentation (English)
## Part 3: Improvements & Interview Q&A

> Continued from [Part 2 — Deep Technical Analysis](presentation_en_2_deep_dive.md)

---

## 4. IMPROVEMENT POINTS

### 4.1 Token in localStorage — XSS Vulnerability

**Problem:** `localStorage.setItem("token", token)` in `Login.jsx` and `Register.jsx`. Any JavaScript executing on the page — including scripts injected via XSS through unescaped user-generated content — can call `localStorage.getItem("token")` and steal the token.

**Fix:**
```javascript
// Instead of localStorage, use httpOnly cookies set by the server.
// Backend: return cookie in the response
return response()
    ->json(['user' => $user])
    ->cookie('token', $plainTextToken, 60*24*7, '/', null, true, true);
    //            value,  minutes=1week, path, domain, secure, httpOnly

// Frontend: no manual token management needed
const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
  withCredentials: true,  // browser sends httpOnly cookie automatically
});
// Remove the interceptor entirely — the browser handles it
```

**Why this is better:** `httpOnly` cookies are inaccessible to JavaScript. XSS attacks cannot read them. The browser sends them automatically on every same-site request.

---

### 4.2 No Protected Routes — Poor UX for Unauthenticated Users

**Problem:** `App.jsx` has no route guard. An unauthenticated user can navigate to `/create-post`, fill in an entire form, click submit, and only then discover they are not logged in (receiving a 401 from the server).

**Fix:**
```javascript
// src/components/PrivateRoute.jsx
import { Navigate } from "react-router-dom";

export default function PrivateRoute({ children }) {
  const isLoggedIn = Boolean(localStorage.getItem("token"));
  return isLoggedIn ? children : <Navigate to="/login" replace />;
  // 'replace' removes /create-post from history — back button goes to previous page
}

// App.jsx
<Route path="/create-post" element={
  <PrivateRoute><CreatePost /></PrivateRoute>
} />
<Route path="/posts/:id/edit" element={
  <PrivateRoute><EditPost /></PrivateRoute>
} />
```

**Why this is better:** Users are redirected immediately before wasting effort. Clean UX. The server-side check still acts as the authoritative guard (defense in depth).

---

### 4.3 Pagination UI Missing

**Problem:** `PostController::index()` returns `paginate(10)` with full pagination metadata (`current_page`, `last_page`, `total`). `Home.jsx` reads only `res.data.data` and ignores all of that metadata — users have no way to see or navigate to subsequent pages.

**Fix:**
```javascript
// Home.jsx
const [page, setPage]         = useState(1);
const [lastPage, setLastPage] = useState(1);

const fetchPosts = async (pageNum = 1) => {
  const res = await api.get(`/posts?page=${pageNum}`);
  setPosts(res.data.data);
  setLastPage(res.data.last_page);
};

// Render pagination controls
<div>
  <button disabled={page === 1} onClick={() => {
    setPage(p => p - 1);
    fetchPosts(page - 1);
  }}>Previous</button>

  <span>Page {page} of {lastPage}</span>

  <button disabled={page === lastPage} onClick={() => {
    setPage(p => p + 1);
    fetchPosts(page + 1);
  }}>Next</button>
</div>
```

**Why this is better:** The backend already handles pagination correctly. Connecting the UI to it costs very little and dramatically improves usability at scale.

---

### 4.4 Slug Duplicate Not Handled

**Problem:** `PostController::store()` generates `$post->slug = Str::slug($request->title)`. If two posts share the same title, the second `save()` call hits a database-level UNIQUE constraint violation — this surfaces as an unhandled 500 error instead of a meaningful 422 validation message.

**Fix:**
```php
// PostController::store()
$baseSlug = Str::slug($request->title);
$slug = $baseSlug;
$counter = 2;

while (Post::where('slug', $slug)->exists()) {
    $slug = $baseSlug . '-' . $counter++;
}
// "great-post" → "great-post-2" → "great-post-3"

$post->slug = $slug;
```

**Why this is better:** No more 500 errors. Users can create multiple posts with the same title without confusion.

---

### 4.5 No Rate Limiting on Login — Brute Force Risk

**Problem:** `POST /api/login` has no throttle. An automated script can attempt thousands of passwords against any email address without being blocked.

**Fix:**
```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
    // 5 attempts per 1 minute per IP address
    // Automatically returns 429 Too Many Requests when exceeded
    // Laravel includes this middleware out of the box — no package needed
```

**Why this is better:** Eliminates brute-force password guessing with two lines of code. The cost is negligible; the protection is real.

---

### 4.6 Login Returns 404 for Unknown Email — Information Leakage

**Problem:** When an email is not found, `AuthController::login()` returns HTTP 404 with message "Email not found". This tells an attacker definitively whether an email address exists in the system — enabling email enumeration attacks.

**Fix:**
```php
// AuthController::login()
if (!$user || !Hash::check($request->password, $user->password)) {
    return response()->json(['message' => 'Invalid credentials'], 401);
    // Same status, same message for both cases:
    //   - Email doesn't exist
    //   - Password is wrong
    // Attacker cannot determine which case applies
}
```

**Why this is better:** Email enumeration is closed. Security best practice: identical response for both failure modes.

---

### 4.7 No Error Boundary — Uncaught React Crashes

**Problem:** If any component throws a runtime error (e.g., trying to access a property of `null`), React 19 unmounts the entire component tree and shows a blank page — or in development, a full-screen error overlay.

**Fix:**
```javascript
// src/components/ErrorBoundary.jsx
import { Component } from "react";

export default class ErrorBoundary extends Component {
  state = { hasError: false, error: null };

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  render() {
    if (this.state.hasError) {
      return (
        <div>
          <h2>Something went wrong.</h2>
          <button onClick={() => this.setState({ hasError: false })}>
            Try again
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

// App.jsx — wrap the Routes
<ErrorBoundary>
  <Routes>...</Routes>
</ErrorBoundary>
```

**Why this is better:** Isolates crashes. One broken component does not take down the whole application. Graceful degradation is a production requirement.

---

### 4.8 No Test Coverage

**Problem:** The project has zero tests — no unit tests for controllers, no feature tests for API endpoints, no component tests for React pages.

**Fix — prioritized test order:**

```php
// 1. API Feature tests (highest value — test authorization logic)
// tests/Feature/PostAuthorizationTest.php
public function test_unauthenticated_request_is_rejected()
{
    $response = $this->postJson('/api/posts', ['title' => 'Test', 'content' => 'Body']);
    $response->assertStatus(401);
}

public function test_user_cannot_edit_another_users_post()
{
    $owner   = User::factory()->create();
    $intruder = User::factory()->create();
    $post    = Post::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($intruder)
                     ->putJson("/api/posts/{$post->id}", ['title' => 'Hijacked']);

    $response->assertStatus(403);
    $this->assertEquals('Original Title', $post->fresh()->title);
}
```

```javascript
// 2. React component tests
// src/pages/Home.test.jsx (Vitest + Testing Library)
it('shows loading state while fetching', () => {
  render(<Home />);
  expect(screen.getByText('Loading posts...')).toBeInTheDocument();
});
```

**Why this is better:** Authorization bugs are among the most dangerous in any system. A test suite that covers ownership checks would have caught the slug uniqueness gap too.

---

## 5. INTERVIEW Q&A

### Q1: Why did you choose Laravel Sanctum over writing your own JWT?

I chose Sanctum because it matches the specific requirements of a SPA backend: I need API tokens that can be individually revoked. With stateless JWT, once you issue a token there is no server-side record — logout is purely cosmetic on the client. The user removes the token from localStorage, but the token itself remains cryptographically valid until it expires. With Sanctum, `$request->user()->currentAccessToken()->delete()` removes the record from `personal_access_tokens` and the token is dead immediately. That is genuine logout.

The tradeoff I accepted is that every request requires a database lookup to verify the token. JWT verification needs no DB hit — it's purely mathematical. At this scale that difference doesn't matter. If I were building a service handling millions of concurrent users across many API servers, I'd reconsider and likely add a Redis cache layer in front of the token lookup, or switch to JWT with short expiry plus a refresh token pattern.

---

### Q2: How does Sanctum token authentication work at the code level?

When a user logs in, `AuthController::login()` calls `$user->createToken('auth_token')`. Sanctum generates a cryptographically random 40-character string, prepends the database record's ID to form a token like `"3|Kx9mN2pQrT..."`, then stores a SHA-256 hash of the token in the `personal_access_tokens` table. The plaintext token is returned to the client exactly once and never stored.

On subsequent requests, the frontend attaches the token as `Authorization: Bearer 3|Kx9mN2pQrT...`. Sanctum's middleware parses the `id` (3) and the token string, looks up record 3 in `personal_access_tokens`, hashes the incoming token with SHA-256, and compares it to the stored hash. If they match, Sanctum loads the associated user and binds it to `$request->user()`. If not, it throws an `AuthenticationException`, which `bootstrap/app.php` catches and converts to a JSON 401 response.

---

### Q3: Why is the token stored in localStorage, and what's the risk?

I stored it in localStorage because it's the simplest pattern for SPA development — no CSRF tokens, no `withCredentials`, no cookie configuration. The Axios interceptor reads it with `localStorage.getItem('token')` and attaches it as a Bearer header on every request.

The risk is XSS. If an attacker can inject JavaScript into the page — through unescaped user-generated content, a compromised third-party script, or any other vector — they can call `localStorage.getItem('token')` and exfiltrate the token. The fix is httpOnly cookies, which the browser sends automatically but JavaScript cannot read at all. I would make that change before exposing this to real users.

---

### Q4: How did you prevent N+1 query problems?

Anywhere I fetch a list of posts that includes related data, I use Eloquent's eager loading: `Post::with('author', 'category', 'tags')`. This generates four queries regardless of how many posts are returned: one for the posts themselves, one for all associated users, one for all associated categories, and one for all associated tags via the join table. Without eager loading, accessing `$post->author->name` inside a loop would fire one additional query per post — 10 posts, 10 extra queries; 100 posts, 100 extra queries.

I also chose to separate the comments from the post detail request — `PostDetail.jsx` makes two API calls. This lets me load comments with `Comment::with('user', 'replies.user')` in the dedicated comments endpoint, getting the threaded structure without over-complicating the post query.

---

### Q5: How does your authorization work? What stops user A from deleting user B's post?

I handle authorization explicitly in the controller, right after finding the resource. In `PostController::destroy()`, after `Post::find($id)` returns the post, I compare `$post->user_id` with `$request->user()->id`. The `$request->user()` value is populated by Sanctum from the verified token — it cannot be spoofed from the request body. If those two IDs don't match, I return 403 Forbidden immediately without touching the record.

The same check exists in `update()`. I apply this pattern consistently: find → check existence → check ownership → perform action. What I haven't done yet is extract this into a Laravel Policy class, which would give me a dedicated `PostPolicy::update()` method and cleaner controller code. That's the next improvement.

---

### Q6: What's wrong with using `PUT` instead of `PATCH` for your post update endpoint?

The HTTP semantics distinction is: `PUT` means "replace the entire resource with this payload" — the client sends a complete representation. `PATCH` means "apply these partial modifications" — the client sends only what changed. My `PostController::update()` method actually implements partial update logic: `if ($request->title) { $post->title = $request->title; }` — I only update fields that are present in the request body. That behavior matches `PATCH`, not `PUT`. I should change `Route::put('/posts/{id}', ...)` to `Route::patch(...)` and update the frontend `api.put(...)` call to `api.patch(...)`. It's a semantic inconsistency that would confuse anyone reading the API contract.

---

### Q7: If you scaled this to 10× the users, what would you change first?

The first bottleneck would be the database. I'd add composite indexes for the most common queries: `posts(status, published_at DESC)` for the home page feed, and `comments(post_id, is_approved, parent_id)` for the comment listing. Right now the only indexes are the ones Laravel adds for foreign keys and unique constraints.

Second, I'd add caching for public data. The post list, category list — these don't change per-user and could be served from Redis with a few minutes TTL instead of hitting MySQL on every page load.

Third, the Sanctum token lookup hits the database on every single API request. With high traffic, that's the fastest thing to cache: store the verified token → user mapping in Redis with a short TTL. Laravel has this built-in pattern.

Finally, I'd replace localStorage with httpOnly cookies, add rate limiting on auth endpoints, and set `sanctum.expiration` to something reasonable (say, 1 week) with a refresh token mechanism.

---

### Q8: What is the hardest technical problem you solved in this project?

The hardest part was understanding the Sanctum token format. The token returned to the client looks like `"3|Kx9mN2pQrT..."`, and I initially assumed the part after the pipe was already a hash. It's not — it's the raw random string. Sanctum hashes it before storing. When verifying, it takes the raw part from the incoming request, hashes it, and compares to the stored hash. I spent time confused about why certain tokens weren't validating until I traced through the Sanctum source code and understood the `id|plaintext` / `stored SHA256 hash` split.

The second challenge was debugging CORS. My Axios requests were failing silently, and I kept looking at the frontend for the problem. CORS is entirely server-side policy — the browser enforces it, but the server declares it via response headers. Once I understood that, configuring `config/cors.php` was straightforward.

---

### Q9: Are there any current bugs in the code you'd want to fix immediately?

Yes, two I consider pressing. First, the slug uniqueness issue: if two posts have the same title, `Str::slug()` produces the same slug for both. The second save hits the UNIQUE constraint on `posts.slug` and throws an unhandled database exception — the user sees a 500 error with no meaningful message. A simple while-loop that appends a counter to the slug fixes this entirely.

Second, the login endpoint returns 404 with the message "Email not found" when an email doesn't exist. This is information leakage — an attacker can enumerate valid email addresses by probing the endpoint. Both the "email not found" and "wrong password" cases should return 401 with the same generic message: "Invalid credentials."

---

### Q10: What would you do differently if you started this project again from scratch?

Three things immediately. First, I'd design auth state sharing from day one — a React Context that exposes `user`, `login()`, and `logout()` — instead of reading from `localStorage` in every component. That one decision would have eliminated the `window.location.href` hack and enabled proper PrivateRoute guards.

Second, I'd use React Query for all server state from the beginning. The loading/error/data pattern I wrote manually in every component is exactly what React Query automates — plus caching, background refetch, and request deduplication.

Third, I'd write the feature tests for authorization before writing the controllers. The tests for "user cannot edit others' posts" and "unauthenticated requests are rejected" are three-line tests that would have given me confidence in my ownership checks throughout development.

---

*End of English presentation series.*  
*See also:*  
*- [presentation_vi_1_overview_architecture.md](presentation_vi_1_overview_architecture.md) — Vietnamese version*  
*- [presentation_fr_1_overview_architecture.md](presentation_fr_1_overview_architecture.md) — French version*
