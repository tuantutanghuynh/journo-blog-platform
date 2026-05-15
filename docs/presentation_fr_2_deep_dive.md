# Journo — Présentation du Projet Fullstack (Français)
## Partie 2 : Analyse Technique Approfondie

> Suite de [Partie 1 — Vue d'ensemble & Architecture](presentation_fr_1_overview_architecture.md)

---

## 3. ANALYSE APPROFONDIE DES COMPOSANTS

### 3.1 FLUX D'AUTHENTIFICATION — Laravel Sanctum

#### POURQUOI : Sanctum plutôt qu'un JWT maison ?

J'ai choisi Laravel Sanctum parce que le projet est une SPA qui nécessite une authentification par token API, et Sanctum résout exactement ce problème. Sanctum stocke chaque token en tant qu'enregistrement dans la table `personal_access_tokens`. Cela me permet de révoquer n'importe quel token — ce qui constitue une vraie déconnexion.

Avec un JWT stateless, une fois le token émis, le serveur n'en garde aucune trace. La déconnexion devient une illusion côté client : on supprime le token du localStorage, mais le token lui-même reste cryptographiquement valide jusqu'à son expiration. Avec Sanctum, appeler `$request->user()->currentAccessToken()->delete()` invalide le token immédiatement côté serveur.

Le compromis accepté : chaque requête nécessite une recherche en base de données pour vérifier le token. La vérification JWT ne nécessite aucun accès DB — c'est purement mathématique. À cette échelle, cette différence ne pose pas problème.

#### COMMENT : Cycle de vie du token Sanctum

```php
// AuthController.php — register()
$user->password = bcrypt($request->password);
// bcrypt() = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
// .env: BCRYPT_ROUNDS=12 → 2^12 = 4096 itérations
// Chaque hash inclut un sel aléatoire intégré dans la chaîne de sortie

$token = $user->createToken('auth_token')->plainTextToken;
// createToken() :
//   1. Génère une chaîne aléatoire de 40 caractères (crypto-safe)
//   2. Préfixe avec l'ID de l'enregistrement : "3|Kx9mN2pQrT..."
//   3. Hash SHA-256 du token → stocké dans personal_access_tokens
//   4. Retourne le texte brut (affiché une seule fois, jamais stocké)
```

```php
// AuthController.php — login()
$passwordIsCorrect = Hash::check($request->password, $user->password);
// Hash::check() :
//   - Extrait le sel intégré dans le hash bcrypt stocké
//   - Re-hache l'entrée en texte brut avec ce même sel
//   - Comparaison en temps constant → pas de timing attack
```

```php
// AuthController.php — logout()
$request->user()->currentAccessToken()->delete();
// currentAccessToken() = le token spécifique utilisé dans CETTE requête
// delete() supprime l'enregistrement de personal_access_tokens
// → Les autres tokens de cet utilisateur restent valides (logout par appareil)
```

```javascript
// frontend/src/api/axios.js — Interceptor d'Attachement du Token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  // Lecture fraîche depuis localStorage à chaque requête
  // (pas mis en cache dans une variable — évite les lectures obsolètes après déconnexion)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
    // Format Bearer RFC 6750
    // Le middleware Sanctum lit : en-tête Authorization → supprime "Bearer " → vérifie le token
  }
  return config;
  // DOIT retourner config — ne rien retourner bloque la requête
});
```

**Vérification du token côté backend :**
```
En-tête : Authorization: Bearer 3|Kx9mN2pQrT...
          │
          │ Sanctum analyse : id = 3, token = Kx9mN2pQrT...
          ▼
SELECT * FROM personal_access_tokens WHERE id = 3
          │
          │ SHA-256(Kx9mN2pQrT...) vs token_hash stocké
          ▼
Correspondance → charge User(tokenable_id) → lie à $request->user()
Pas de corresp. → AuthenticationException
              → bootstrap/app.php intercepte → JSON { message: 'Unauthenticated.' } 401
```

#### COMPROMIS

| | Sanctum (token DB) | JWT Stateless |
|---|---|---|
| Vraie déconnexion | Oui (suppression enregistrement) | Non (attendre expiration) |
| Révocation par appareil | Oui | Non |
| Accès DB par requête | Oui | Non |
| Idéal pour | SPA, mobile où le logout importe | Microservices, haute charge |

---

### 3.2 STOCKAGE DU TOKEN — localStorage vs Cookie httpOnly

#### COMMENT : Implémentation actuelle

```javascript
// Après login — stocker le token
localStorage.setItem("token", token);

// Navbar.jsx — dériver l'état d'authentification
const token = localStorage.getItem("token");
const isLoggedIn = token !== null;

// Déconnexion
localStorage.removeItem("token");
window.location.href = "/login";
```

#### POURQUOI : Justification honnête et risque connu

J'ai choisi localStorage pour sa simplicité lors du développement : pas de token CSRF, pas de `withCredentials`, pas de configuration de cookie same-site. L'interceptor Axios le lit de façon synchrone et l'attache.

Le risque connu : tout JavaScript s'exécutant sur la page peut appeler `localStorage.getItem('token')`. Si une vulnérabilité XSS existe — même juste du contenu utilisateur affiché sans échappement — le script d'un attaquant peut voler le token.

Les cookies httpOnly sont immunisés : le navigateur envoie le cookie automatiquement, mais JavaScript ne peut pas le lire. Le prix est `credentials: true` côté CORS, la protection CSRF, et une configuration de développement local plus complexe.

**Mon évaluation honnête :** localStorage est acceptable pour un projet d'apprentissage. Il doit être remplacé avant toute mise en production.

---

### 3.3 GESTION D'ÉTAT — État Local Uniquement (Pas de Context, Pas de Redux)

#### POURQUOI : Décision délibérée d'éviter Context API et Redux

Chaque page de Journo gère ses propres données avec `useState` et `useEffect`. J'ai pris la décision délibérée de ne pas introduire Context API ou Redux, et voici le raisonnement :

- **État d'auth :** Le token vit dans localStorage. Tout composant qui a besoin de savoir si l'utilisateur est connecté appelle `localStorage.getItem('token')` directement. Il n'y a rien à partager.
- **Données des articles :** Consommées par une seule page à la fois. Aucune raison de les élever à l'état global.
- **Données de formulaire :** Totalement locales à chaque composant de formulaire. `useState` est l'outil approprié.

Ajouter Redux à ce projet signifierait écrire des actions, des reducers, un store et des selectors pour des données déjà accessibles depuis un seul `localStorage.getItem`. C'est du sur-engineering.

#### COMMENT : Pattern des 3 états utilisé de façon cohérente

```javascript
// Home.jsx — pattern utilisé de façon cohérente dans toutes les pages
const [posts, setPosts]     = useState([]);    // les données
const [loading, setLoading] = useState(true);  // indicateur de chargement
const [error, setError]     = useState(null);  // message d'erreur

const fetchPosts = async () => {
  setLoading(true);   // afficher le spinner
  setError(null);     // effacer les erreurs précédentes

  try {
    const res = await api.get("/posts");
    setPosts(res.data.data);
    // res.data = objet pagination complet de Laravel
    // res.data.data = le vrai tableau des articles
  } catch (err) {
    setError("Failed to load posts");  // message pour l'utilisateur
  } finally {
    setLoading(false);  // toujours exécuté — succès ou échec
  }
};
```

#### COMPROMIS : Ce que cette approche manque

Sans React Query ou SWR, je n'ai pas :
- **Cache côté client** — revenir à Home re-fetche tous les articles
- **Revalidation en arrière-plan** — les données deviennent obsolètes sans rafraîchissement automatique
- **Déduplication** — deux composants qui requêtent le même endpoint font deux requêtes
- **Mises à jour optimistes** — après la création d'un article, je redirige plutôt que d'insérer dans la liste locale

---

### 3.4 ROUTING — React Router DOM v7

#### COMMENT : Déclaration des routes

```javascript
// App.jsx — toutes les routes en un seul endroit
export default function App() {
  return (
    <BrowserRouter>
      <Navbar />
      <Routes>
        <Route path="/"               element={<Home />} />
        <Route path="/login"          element={<Login />} />
        <Route path="/register"       element={<Register />} />
        <Route path="/posts/:id"      element={<PostDetail />} />
        <Route path="/create-post"    element={<CreatePost />} />
        <Route path="/posts/:id/edit" element={<EditPost />} />
      </Routes>
    </BrowserRouter>
  );
}
```

**Gap de conception important :** Il n'y a pas de routes protégées côté frontend. `CreatePost`, `EditPost` sont accessibles sans authentification — le backend rejettera la soumission avec 401, mais l'utilisateur aura perdu du temps à remplir le formulaire. La correction est un composant `PrivateRoute`.

#### COMMENT : Navigation après mutations

```javascript
// J'utilise window.location.href au lieu de useNavigate()
window.location.href = "/";
// Déclenche un rechargement complet de la page.
// POURQUOI : La Navbar lit le token depuis localStorage au montage.
//   useNavigate() ne fait que changer le composant de route —
//   la Navbar reste montée et ne relit pas localStorage.
//   Un rechargement complet re-monte tout → la Navbar voit le nouveau token.
// COÛT : Perd la fluidité SPA. Correction : partager l'état d'auth via Context.
```

---

### 3.5 CONCEPTION DE L'API — Conventions RESTful

#### COMMENT : Nommage des endpoints et méthodes HTTP

```
GET    /api/posts                → index()   — récupérer la collection (idempotent)
GET    /api/posts/{id}           → show()    — récupérer une ressource
POST   /api/posts                → store()   — créer une nouvelle ressource
PUT    /api/posts/{id}           → update()  — mettre à jour la ressource
DELETE /api/posts/{id}           → destroy() — supprimer la ressource

POST   /api/register             — créer un utilisateur
POST   /api/login                — obtenir un token (action, pas une ressource)
POST   /api/logout               — révoquer un token

GET    /api/posts/{id}/comments  — ressource imbriquée
POST   /api/posts/{id}/comments  — créer un commentaire pour un article
```

**Inconsistance honnête :** J'utilise `PUT` pour les mises à jour d'articles mais implémente une logique de mise à jour partielle. La méthode HTTP correcte pour les mises à jour partielles est `PATCH`. C'est une inconsistance sémantique à corriger.

#### COMMENT : Formats de réponse

```php
return response()->json($post, 201);     // 201 Created
return response()->json($posts, 200);    // 200 OK
return response()->json(['message' => '...'], 404);   // 404 Not Found
return response()->json(['message' => '...'], 403);   // 403 Forbidden
// 401 : géré par bootstrap/app.php → { message: 'Unauthenticated.' }
// 422 : géré automatiquement par $request->validate()
```

---

### 3.6 COUCHE BASE DE DONNÉES — Eloquent ORM

#### POURQUOI : Eloquent plutôt que SQL brut

J'ai choisi Eloquent pour trois raisons : les prepared statements PDO automatiques préviennent l'injection SQL sans effort supplémentaire ; l'API des relations rend les jointures complexes lisibles ; et les migrations donnent un schéma versionné et reproductible.

#### COMMENT : Prévenir les Requêtes N+1

```php
// PostController::index() — CORRECT
$posts = Post::with('author', 'category', 'tags')
    ->where('status', 'published')
    ->orderBy('published_at', 'desc')
    ->paginate(10);

// with('author', 'category', 'tags') = Eager Loading
// Exécute 4 requêtes au total (pas N+1) :
//   Requête 1 : SELECT * FROM posts WHERE status='published' ... LIMIT 10
//   Requête 2 : SELECT * FROM users WHERE id IN (1,2,5,...)
//   Requête 3 : SELECT * FROM categories WHERE id IN (3,7,...)
//   Requête 4 : SELECT tags.*, post_tag.post_id FROM tags JOIN post_tag ...
```

**Sans eager loading — le piège N+1 :**
```php
// INCORRECT — déclenche N requêtes supplémentaires
$posts = Post::where('status', 'published')->paginate(10);
foreach ($posts as $post) {
    echo $post->author->name;    // +1 requête par article
    echo $post->category->name;  // +1 requête par article
}
// Total : 1 + 10 + 10 = 21 requêtes pour 10 articles
```

#### COMMENT : Définitions des Relations

```php
// Post.php
public function author()
{
    return $this->belongsTo(User::class, 'user_id');
    // Alias 'author' au lieu de 'user' — plus clair dans le contexte blog
}

public function tags()
{
    return $this->belongsToMany(Tag::class, 'post_tag');
    // Laravel gère la JOIN automatiquement
}
```

```php
// Comment.php — auto-référencement
public function replies()
{
    return $this->hasMany(Comment::class, 'parent_id');
    // Un commentaire a plusieurs réponses partageant le même parent_id
}
```

#### COMMENT : Vérification d'Autorisation dans le Contrôleur

```php
// PostController::update() et destroy()
$post = Post::find($id);

if (!$post) {
    return response()->json(['message' => 'Post not found'], 404);
}

if ($post->user_id !== $request->user()->id) {
    return response()->json(['message' => 'Forbidden'], 403);
    // $request->user()->id provient de Sanctum — ne peut pas être falsifié
    // $post->user_id provient de la base de données
    // Différence = authentifié mais pas propriétaire
}
```

---

### 3.7 SÉCURITÉ

#### 3.7.1 Hachage des Mots de Passe avec Bcrypt

```php
// Inscription : hacher le mot de passe avant stockage
$user->password = bcrypt($request->password);
// .env: BCRYPT_ROUNDS=12 → facteur de coût 12 → 2^12 = 4096 itérations
// Chaque hash inclut un sel aléatoire unique

// Connexion : vérifier sans connaître le mot de passe original
Hash::check($request->password, $user->password);
// Extrait le sel du hash stocké, re-hache l'entrée, comparaison en temps constant
```

**Pourquoi pas MD5 ou SHA-256 ?**
MD5 et SHA-256 sont conçus pour la vitesse — un GPU moderne peut calculer des milliards de hashes SHA-256 par seconde, rendant les attaques par force brute réalistes. Bcrypt est intentionnellement lent, avec un facteur de coût ajustable. À mesure que le matériel accélère, on augmente les rounds pour maintenir la résistance.

#### 3.7.2 Prévention de l'Injection SQL

```php
// Toutes les requêtes Eloquent utilisent des prepared statements PDO
$user = User::where('email', $request->email)->first();
// SQL réel : SELECT * FROM users WHERE email = ? [liaison : valeur email]
// La valeur n'est JAMAIS concaténée directement dans la chaîne SQL
```

#### 3.7.3 CORS

Le serveur de dev frontend tourne sur le port 5173 (Vite) ; le backend sur le port 8000. Sans en-têtes CORS, la politique same-origin du navigateur bloque toutes les requêtes cross-origin.

La configuration CORS par défaut de Laravel (via `config/cors.php`) autorise toutes les origines en développement. Pour la production, cela doit être restreint :

```php
// config/cors.php — production
'allowed_origins' => ['https://journo.com'],
// Jamais ['*'] en production
```

CORS est appliqué par le navigateur, pas le serveur. Le serveur déclare sa politique via les en-têtes `Access-Control-Allow-Origin`. Le client Axios ne configure pas CORS.

---

### 3.8 PATTERNS DE CONCEPTION DANS LE CODE

#### Pattern 1 : Active Record (Eloquent)

**3 signes dans le code :**
1. L'objet model sait se sauvegarder : `$post->save()`
2. Le model connaît ses propres relations : `$post->author`, `$post->comments`
3. Le model transporte à la fois données et comportement

```php
$post = new Post();
$post->title = $request->title;
$post->save();  // Le model exécute sa propre requête INSERT
```

**POURQUOI :** Développement rapide, code lisible.  
**COMPROMIS :** Le model porte à la fois logique d'accès aux données et logique métier — viole SRP si on ajoute des règles complexes.

#### Pattern 2 : Middleware Chain

**3 signes dans le code :**
1. `Route::middleware('auth:sanctum')->group(...)` enveloppe des groupes de routes entiers
2. Le middleware s'exécute avant le contrôleur — les contrôleurs ne vérifient pas l'auth
3. `bootstrap/app.php` ajoute un middleware de gestion globale des exceptions

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store']);
    // PostController::store() n'a pas de vérification d'auth —
    // le middleware garantit déjà que $request->user() est renseigné
});
```

**POURQUOI :** Responsabilité unique — le middleware gère les préoccupations transversales.  
**COMPROMIS :** Dépendance implicite — si on teste un contrôleur directement, la chaîne middleware est contournée.

#### Pattern 3 : Intercepteur (Axios)

**3 signes dans le code :**
1. La logique d'attachement du token vit en exactement un endroit : `api/axios.js`
2. Toutes les pages importent la même instance `api` — aucune ne sait rien des tokens
3. Changer le schéma d'auth nécessite de modifier un seul fichier

```javascript
// api/axios.js — configuré une seule fois
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
```

**POURQUOI :** DRY — ne pas répéter la logique de token dans chaque composant.  
**COMPROMIS :** Comportement caché — un développeur regardant `api.get('/posts')` ne voit pas l'attachement du token sans lire `axios.js`.

#### Pattern 4 : Repository (implicite, via Eloquent)

**3 signes dans le code :**
1. Les contrôleurs n'écrivent jamais de SQL brut — ils appellent uniquement des méthodes de Model
2. Si le moteur de base de données change, seuls les Models ont besoin de changer
3. Les patterns d'accès aux données sont définis dans une seule couche

**POURQUOI :** Séparation des préoccupations.  
**COMPROMIS :** Les models Eloquent mélangent accès aux données et logique métier. Un vrai pattern Repository aurait une classe séparée.

---

### 3.9 DÉCISIONS DE CONCEPTION NON-ÉVIDENTES

#### 1. `window.location.href` au lieu de `useNavigate()`

Après une connexion réussie, j'appelle `window.location.href = "/"` au lieu du `navigate("/")` de React Router. La raison : le composant `Navbar` lit `localStorage.getItem('token')` au montage pour décider quels liens afficher. Une navigation React Router ne démonte pas et ne remonte pas `Navbar` — elle reste montée avec l'ancienne valeur `isLoggedIn = false`. Un rechargement complet force `Navbar` à se re-monter et à relire le nouveau token.

C'est un symptôme de l'absence d'état d'auth partagé. La correction appropriée est un Context React.

#### 2. `user_id` vient de `$request->user()`, pas du body de la requête

```php
$post->user_id = $request->user()->id;
// PAS : $post->user_id = $request->user_id;
```

Si je lisais `user_id` depuis le body de la requête, un client pourrait envoyer n'importe quel `user_id` et créer des articles attribués à d'autres utilisateurs. `$request->user()` est peuplé exclusivement par Sanctum depuis le token vérifié — il ne peut pas être manipulé depuis l'extérieur.

#### 3. `is_approved = true` codé en dur dans CommentController

```php
$comment->is_approved = true;  // codé en dur
```

Le schéma a été conçu pour une future modération (le champ existe, la valeur par défaut est `false`). Le contrôleur le remplace immédiatement à `true` pour que les commentaires apparaissent sans approbation admin — comportement correct pour un projet d'apprentissage, mais autoriserait le spam en production.

#### 4. Commentaires récupérés séparément de l'article

```javascript
// PostDetail.jsx — deux requêtes indépendantes
useEffect(() => {
  fetchPost();      // GET /posts/:id
  fetchComments();  // GET /posts/:id/comments
}, []);
```

Le backend pourrait inclure les commentaires dans la réponse de l'article. J'ai choisi deux requêtes pour garder l'endpoint des commentaires flexible — il peut être appelé indépendamment, supporte la pagination ultérieurement, et retourne la structure imbriquée séparément.

---

*Suite : [Partie 3 — Améliorations & Q&R d'Entretien](presentation_fr_3_improvements_qa.md)*
