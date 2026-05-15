# Journo — Présentation du Projet Fullstack (Français)
## Partie 1 : Vue d'ensemble & Architecture du Système

> Style à la première personne — pour entretiens techniques fullstack.  
> Auteur : TuanTu | Stack : React 19 + Laravel 12 + MySQL

---

## 1. VUE D'ENSEMBLE DU PROJET

### 1.1 Que fait le système et pour qui ?

Journo est une application de blog fullstack qui permet aux utilisateurs de s'inscrire, de se connecter, d'écrire et de partager des articles avec une communauté. Les lecteurs peuvent parcourir les articles, laisser des commentaires et interagir avec le contenu ; les auteurs ont le contrôle total sur leurs propres publications.

Le problème que j'ai voulu résoudre était de construire une plateforme de contenu complète — de l'authentification des utilisateurs et du CRUD basé sur les permissions jusqu'à un système de commentaires imbriqués — en utilisant une architecture strictement API-first où le frontend et le backend sont totalement découplés et communiquent uniquement via une API REST JSON.

### 1.2 Stack Technologique Complet

| Couche | Technologie | Version |
|---|---|---|
| Frontend | React | 19.2.5 |
| Router Frontend | React Router DOM | 7.14.2 |
| HTTP Frontend | Axios | 1.15.2 |
| Build Frontend | Vite | 8.0 |
| Framework Backend | Laravel | 12.x |
| Runtime Backend | PHP | 8.2 |
| Authentification | Laravel Sanctum | 4.0 |
| Base de données | MySQL | 8.x (via XAMPP) |
| Serveur de dev | `php artisan serve` | port 8000 |

### 1.3 Fonctionnalités Principales

1. **Inscription / Connexion** — authentification par token via Laravel Sanctum
2. **Parcourir les articles** — pagination offset-based, articles publiés uniquement
3. **Détail d'un article** — contenu complet, auteur, catégorie, commentaires imbriqués
4. **Créer un article** — formulaire avec titre, extrait, contenu, statut (brouillon/publié)
5. **Modifier un article** — seul l'auteur peut modifier (autorisation côté serveur)
6. **Supprimer un article** — avec dialogue de confirmation, autorisation côté serveur
7. **Système de commentaires** — poster des commentaires si connecté, support des réponses imbriquées
8. **Navigation contextuelle** — la Navbar affiche/cache les actions selon l'état de connexion

**Infrastructure conçue mais sans UI :**
- Système de « likes » sur les articles
- Système de « follow » entre utilisateurs
- Gestion des médias / téléversement de fichiers
- Catégories hiérarchiques (parent/enfant)

### 1.4 Ce Qui Distingue Ce Projet d'une Simple Application CRUD

1. **Architecture API-first** — frontend et backend totalement indépendants ; le backend ne génère jamais de HTML ; toute communication passe par JSON REST
2. **Tokens révocables avec Sanctum** — chaque token est un enregistrement dans `personal_access_tokens` et peut être révoqué individuellement
3. **Autorisation côté serveur** — le contrôleur vérifie `post->user_id === request->user()->id` avant toute mutation
4. **Eager loading pour prévenir le N+1** — `Post::with('author', 'category', 'tags')` charge toutes les relations en 4 requêtes au lieu de N+1
5. **Commentaires imbriqués** — `comments.parent_id` auto-référencé supporte les réponses
6. **Schéma de base de données production-ready** — clés étrangères avec règles de cascade, index uniques, types ENUM, clés primaires composites

---

## 2. ARCHITECTURE DU SYSTÈME

### 2.1 Diagramme Général

```
┌─────────────────────────────────────────────────────────────────┐
│                      NAVIGATEUR (Utilisateur)                   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Application React                          │   │
│  │                                                         │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │   │
│  │  │  Navbar  │  │  Pages   │  │  Client HTTP Axios   │  │   │
│  │  │          │  │ Home     │  │                      │  │   │
│  │  │ lit le   │  │ Login    │  │  baseURL: :8000/api  │  │   │
│  │  │ token    │  │ Register │  │                      │  │   │
│  │  │ depuis   │  │ PostDtl  │  │  interceptor: ajout  │  │   │
│  │  │localStorage│ CréerArt│  │  automatique du token│  │   │
│  │  │          │  │ EditArt  │  │                      │  │   │
│  │  └──────────┘  └────┬─────┘  └──────────┬───────────┘  │   │
│  │                     │                    │              │   │
│  │             useState/useEffect           │              │   │
│  │             (état local par page)        │              │   │
│  └─────────────────────────────────────────┼──────────────┘   │
│                                            │                   │
│                   localStorage             │ Requêtes HTTP      │
│               ┌──────────────┐            │ (JSON + Bearer)    │
│               │  "token": "" │            │                   │
│               └──────────────┘            │                   │
└────────────────────────────────────────────┼───────────────────┘
                                             │
                         ════════════════════╪════════════════════
                                  RÉSEAU (HTTP/REST)
                         ════════════════════╪════════════════════
                                             │
┌────────────────────────────────────────────┼───────────────────┐
│              Backend Laravel 12            │                   │
│                                             ▼                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                bootstrap/app.php                         │  │
│  │  withRouting → api.php | withExceptions → erreurs JSON   │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           routes/api.php (Couche Router)                 │  │
│  │                                                          │  │
│  │  Public:    POST /register, POST /login                  │  │
│  │             GET /posts, GET /posts/{id}                  │  │
│  │             GET /categories, GET /posts/{id}/comments    │  │
│  │                                                          │  │
│  │  Protégé:   middleware('auth:sanctum') {                 │  │
│  │    POST /logout, GET /me                                 │  │
│  │    POST/PUT/DELETE /posts                                │  │
│  │    POST/DELETE /comments                                 │  │
│  │  }                                                       │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │          Contrôleurs (namespace Api)                     │  │
│  │                                                          │  │
│  │  AuthController  PostController  CommentController       │  │
│  │  CategoryController                                      │  │
│  │                                                          │  │
│  │  Valider → Autoriser → Appeler Model → Retourner JSON    │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
│                              ▼                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │           Models Eloquent (Couche ORM)                   │  │
│  │                                                          │  │
│  │  User  Post  Comment  Category  Tag                      │  │
│  │  Like  Follow  Media                                     │  │
│  │                                                          │  │
│  │  Relations: hasMany, belongsTo, belongsToMany            │  │
│  └───────────────────────────┬──────────────────────────────┘  │
│                              │                                  │
└──────────────────────────────┼──────────────────────────────────┘
                               │
┌──────────────────────────────┼──────────────────────────────────┐
│            Base de données MySQL │                              │
│                              ▼                                  │
│  users  posts  categories  tags  post_tag                       │
│  comments  likes  follows  media                                │
│  personal_access_tokens  (table Sanctum)                        │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Diagramme des Couches Backend

```
Requête HTTP entrante
        │
        ▼
┌─────────────────────────────────────────┐
│       Middleware Sanctum                │
│  (auth:sanctum)                         │
│  - Lire le token Bearer depuis l'en-tête│
│  - Hacher le token → recherche en DB    │
│  - Valide → lier l'user à $request      │
│  - Invalide → JSON 401 (via app.php)    │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│       Couche Contrôleur                 │
│  - Valider les données d'entrée         │
│  - Vérifier l'autorisation (owner)      │
│  - Appeler les Models Eloquent          │
│  - Retourner response()->json(...)      │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│       Couche ORM Eloquent               │
│  - Model::find(), Model::where()        │
│  - with() eager loading                 │
│  - Traversal des relations             │
│  - save(), delete()                     │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│       Base de données MySQL             │
│  - PDO prepared statements (via Laravel)│
│  - Application des clés étrangères      │
│  - Suppressions en cascade              │
└─────────────────────────────────────────┘
```

### 2.3 Tableau des Rôles des Fichiers Importants

| Fichier | Rôle | Pourquoi séparé |
|---|---|---|
| `frontend/src/api/axios.js` | Crée l'instance Axios avec l'interceptor de token | Une seule instance partagée ; logique de token écrite une fois |
| `frontend/src/App.jsx` | Déclare toutes les routes de l'application | Un seul endroit pour voir la structure complète des URL |
| `frontend/src/components/NavBar.jsx` | Barre de navigation, lit le token depuis localStorage | Composant partagé ; découplé de l'état des pages |
| `backend/routes/api.php` | Définit tous les endpoints API, groupes public/protégé | Routing séparé de la logique ; surface API visible en un coup d'œil |
| `backend/app/Http/Controllers/Api/AuthController.php` | register / login / logout / me | Logique d'auth isolée de la logique métier |
| `backend/app/Http/Controllers/Api/PostController.php` | CRUD des articles avec autorisation | Responsabilité unique : un contrôleur par ressource |
| `backend/app/Models/Post.php` | Model Eloquent, définitions des relations | Le model sait comment se connecter aux tables liées |
| `backend/bootstrap/app.php` | Config de l'app, gestionnaire d'exceptions personnalisé | Centralise AuthenticationException → JSON 401 |
| `backend/database/migrations/*.php` | Schéma en code | Schéma versionné, reproductible sur tous les environnements |

### 2.4 Flux de Données de Bout en Bout

#### Flux 1 : Authentification (Login → Token → Requête Protégée)

```
[L'utilisateur saisit email/mot de passe dans Login.jsx]
        │
        │ handleSubmit() — event.preventDefault()
        ▼
[api.post('/login', { email, password })]
        │
        │ Interceptor : pas de token attaché (pas encore)
        ▼
[POST http://127.0.0.1:8000/api/login]
        │
        ▼
[routes/api.php] → Route::post('/login', [AuthController::class, 'login'])
        │
        ▼
[AuthController::login()]
  │  User::where('email', $email)->first()
  │  → non trouvé : retourne 404
  │
  │  Hash::check($password, $user->password)
  │  → vérification bcrypt : extrait le sel du hash stocké,
  │    re-hache l'entrée, compare
  │  → différence : retourne 401
  │
  │  $user->createToken('auth_token')->plainTextToken
  │  → Sanctum génère un token aléatoire de 40 caractères
  │  → Hash SHA-256 stocké dans personal_access_tokens
  │  → Retourne le texte brut : format "id|chaîneAléatoire"
        │
        ▼
[Réponse JSON : { user: {...}, token: "3|Kx9mN2..." }]
        │
        ▼
[Login.jsx reçoit le token]
  localStorage.setItem("token", token)
  window.location.href = "/"  ← rechargement complet (pas navigate())
        │
        ▼
[Requête suivante — ex : api.post('/posts', {...})]
        │
        ▼
[L'interceptor Axios s'exécute]
  const token = localStorage.getItem("token")
  config.headers.Authorization = `Bearer ${token}`
        │
        ▼
[POST /api/posts avec en-tête : Authorization: Bearer 3|Kx9mN2...]
        │
        ▼
[Middleware Sanctum 'auth:sanctum']
  - Extrait le token de l'en-tête
  - Le hache → recherche dans personal_access_tokens
  - Trouvé → lie l'user à $request
  - Non trouvé → AuthenticationException
    → bootstrap/app.php intercepte → JSON 401
        │
        ▼
[PostController::store() s'exécute avec $request->user() renseigné]
```

#### Flux 2 : Créer un Article (Flux Métier Principal)

```
[L'utilisateur remplit le formulaire dans CreatePost.jsx]
  titre, extrait, contenu, statut (brouillon/publié)
        │
        │ handleSubmit() — setLoading(true), setError(null)
        ▼
[api.post('/posts', { title, content, excerpt, status })]
        │
        │ l'interceptor attache le token Bearer
        ▼
[Sanctum vérifie le token → $request->user() = User authentifié]
        │
        ▼
[PostController::store()]
  │
  │ $request->validate([...])
  │   'title' required|string|max:255
  │   'content' required|string
  │   → échec : 422 automatique avec messages d'erreur
  │
  │ $post = new Post()
  │ $post->user_id = $request->user()->id  ← de l'auth, PAS du body
  │ $post->slug    = Str::slug($request->title)  ← slug URL-friendly auto
  │ $post->status  = $request->status ?? 'draft'
  │
  │ if ($post->status === 'published') {
  │     $post->published_at = now()
  │ }
  │
  │ $post->save()    ← INSERT INTO posts ...
  │
        ▼
[Réponse JSON : objet post, HTTP 201 Created]
        │
        ▼
[CreatePost.jsx : window.location.href = "/"]
        │
        ▼
[Home.jsx fetchPosts()]
  api.get('/posts')
  → PostController::index()
  → Post::with('author','category','tags')
      ->where('status','published')
      ->orderBy('published_at','desc')
      ->paginate(10)
  → retourne JSON paginé
        │
        ▼
[Home.jsx affiche la liste des articles]
```

### 2.5 Schéma de la Base de Données

```sql
-- Table 1 : users
CREATE TABLE users (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(255) NOT NULL,
  email             VARCHAR(255) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password          VARCHAR(255) NOT NULL,  -- hash bcrypt
  remember_token    VARCHAR(100) NULL,
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL
);

-- Table 2 : categories (hiérarchie auto-référencée)
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

-- Table 3 : posts (table de contenu principal)
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

-- Table 4 : tags
-- Table 5 : post_tag (junction N-N, PRIMARY KEY composite)
-- Table 6 : comments (parent_id auto-référencé pour l'imbrication)
-- Table 7 : likes (UNIQUE KEY sur user_id + post_id)
-- Table 8 : follows (graphe social, UNIQUE KEY sur follower + following)
-- Table 9 : media (fichiers uploadés)
-- Table Sanctum : personal_access_tokens (tokens d'API hachés)
```

**Résumé des relations :**
- `users` → `posts` : 1-N
- `posts` ↔ `tags` : N-N (via `post_tag`)
- `posts` → `comments` : 1-N (cascade delete)
- `comments` → `comments` : auto-référencé (replies)
- `users` ↔ `users` (follows) : N-N auto-référencé

---

*Suite : [Partie 2 — Analyse Technique Approfondie](presentation_fr_2_deep_dive.md)*
