# Journo — Personal Blog Platform

A full-stack personal blog application where users can write, publish, and share articles. Built from scratch as a hands-on learning project, covering everything from business analysis and UX/UI design to API development and frontend implementation.

---

## Purpose

This project is primarily a **learning playground**. The goals are:

- Practice **React + TypeScript** (frontend) — components, routing, state management, API integration
- Practice **PHP Laravel** (backend) — REST API, authentication, ORM, migrations
- Follow a realistic full-stack workflow: BA → Design → Development → Git → Deployment
- Incrementally add more advanced and challenging features over time
- In the future, rewrite or extend the backend using **Java Spring Boot** as a second backend implementation

This is not a production product — it is intentionally built step by step to build muscle memory across the stack.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | React 18 + Vite + **TypeScript** |
| Backend (current) | PHP 8.2 + Laravel 12 + Sanctum |
| Backend (planned) | Java Spring Boot |
| Database | MySQL (XAMPP) |
| Auth | Laravel Sanctum (token-based) |
| API style | REST / JSON |

---

## Core Features (v1)

### Guest (unauthenticated)
- Browse paginated list of published posts
- Read full post detail
- Search posts by keyword

### Authenticated User
- Register / Login / Logout
- Create, edit, and delete own posts
- View personal post dashboard

---

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/register` | No | Register a new account |
| POST | `/api/login` | No | Login → returns token |
| POST | `/api/logout` | Yes | Logout |
| GET | `/api/me` | Yes | Get current user info |
| GET | `/api/posts` | No | List published posts (paginated) |
| GET | `/api/posts/{id}` | No | Get post detail |
| POST | `/api/posts` | Yes | Create a new post |
| PUT | `/api/posts/{id}` | Yes | Update post (owner only) |
| DELETE | `/api/posts/{id}` | Yes | Delete post (owner only) |
| GET | `/api/categories` | No | List all categories |
| GET | `/api/categories/{id}` | No | Get category detail |
| GET | `/api/categories/{id}/posts` | No | Get posts in a category |
| GET | `/api/posts/{id}/comments` | No | Get comments of a post |
| POST | `/api/posts/{id}/comments` | Yes | Add a comment |
| DELETE | `/api/comments/{id}` | Yes | Delete comment (owner only) |

---

## Project Structure

```
journo/
├── backend/        ← Laravel project (serves on port 8000)
├── frontend/       ← React + Vite + TypeScript (serves on port 5173)
├── doc/            ← Guides, ERD, wireframes, implementation docs
└── database/       ← SQL schema and migration files
```

---

## Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8.2+, MySQL)
- Node.js 18+
- Composer

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Backend will be available at `http://localhost:8000`.

### Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

Frontend will be available at `http://localhost:5173`.

---

## Roadmap

### v1 — Foundation (current)
- [x] Project setup and documentation
- [x] Database schema design (11 tables)
- [x] Eloquent Models + Relationships (8 models)
- [x] Migrations + Seeders
- [x] Laravel REST API with Sanctum auth
- [x] AuthController — register, login, logout, me
- [x] PostController — CRUD posts
- [x] CategoryController — list, detail, posts by category
- [x] CommentController — list, add, delete comments
- [x] React + Vite frontend (SPA)
- [x] Connect frontend to backend (Axios)
- [x] Home page — list all published posts
- [x] Login page — authenticate and store token
- [x] Register page — create new account
- [ ] Post Detail page — view single post + comments
- [ ] Navbar — navigation between pages
- [ ] Logout functionality
- [ ] Create Post page (authenticated users only)

### v2 — Extended Features (planned)
- [ ] Rich text / Markdown editor for posts
- [ ] Image upload and media management
- [ ] Post likes / reactions
- [ ] User profile pages
- [ ] Follow / unfollow users
- [ ] Admin dashboard

### v3 — Advanced (future)
- [ ] Rewrite backend in **Java Spring Boot**
- [ ] Full-text search with Elasticsearch
- [ ] Real-time notifications
- [ ] OAuth (Google / GitHub login)
- [ ] Deployment to cloud (Railway, Render, or VPS)

---

## Learning Focus

Each phase of this project targets a specific skill set:

| Phase | Focus |
|---|---|
| Business Analysis | User stories, requirements, use cases |
| UX/UI Design | Wireframes, design system |
| Backend | Laravel MVC, Eloquent ORM, REST API design, token auth |
| Frontend | React hooks, TypeScript types, Axios, React Router, context API |
| Project Management | Git workflow, branching, GitHub Projects |
| Deployment | Environment config, build pipeline |

---

## License

MIT
