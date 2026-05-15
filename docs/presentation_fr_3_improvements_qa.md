# Journo — Présentation du Projet Fullstack (Français)
## Partie 3 : Améliorations & Q&R d'Entretien

> Suite de [Partie 2 — Analyse Technique Approfondie](presentation_fr_2_deep_dive.md)

---

## 4. POINTS D'AMÉLIORATION

### 4.1 Token dans localStorage — Vulnérabilité XSS

**Problème :** `localStorage.setItem("token", token)` dans `Login.jsx` et `Register.jsx`. Tout JavaScript s'exécutant sur la page — y compris des scripts injectés via XSS à travers du contenu utilisateur non échappé — peut appeler `localStorage.getItem("token")` et voler le token.

**Correction :**
```javascript
// Au lieu de localStorage, utiliser des cookies httpOnly définis par le serveur.
// Backend : retourner un cookie dans la réponse
return response()
    ->json(['user' => $user])
    ->cookie('token', $plainTextToken, 60*24*7, '/', null, true, true);
    //            valeur,  minutes=1semaine, path, domaine, secure, httpOnly

// Frontend : plus de gestion manuelle du token
const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
  withCredentials: true,  // le navigateur envoie le cookie httpOnly automatiquement
});
// Supprimer complètement l'interceptor
```

**Pourquoi c'est mieux :** Les cookies `httpOnly` sont inaccessibles au JavaScript. Les attaques XSS ne peuvent pas les lire. Le navigateur les envoie automatiquement à chaque requête same-site.

---

### 4.2 Pas de Routes Protégées — Mauvaise UX pour les Utilisateurs Non-Authentifiés

**Problème :** `App.jsx` n'a pas de garde de route. Un utilisateur non-authentifié peut naviguer vers `/create-post`, remplir tout un formulaire, cliquer sur Envoyer, et ne découvrir qu'il n'est pas connecté qu'à ce moment (en recevant un 401 du serveur).

**Correction :**
```javascript
// src/components/PrivateRoute.jsx
import { Navigate } from "react-router-dom";

export default function PrivateRoute({ children }) {
  const isLoggedIn = Boolean(localStorage.getItem("token"));
  return isLoggedIn ? children : <Navigate to="/login" replace />;
  // 'replace' supprime /create-post de l'historique — le bouton retour va à la page précédente
}

// App.jsx
<Route path="/create-post" element={
  <PrivateRoute><CreatePost /></PrivateRoute>
} />
```

**Pourquoi c'est mieux :** Les utilisateurs sont redirigés immédiatement avant de perdre du temps. UX claire. La vérification côté serveur reste le garde d'autorité (défense en profondeur).

---

### 4.3 Interface de Pagination Manquante

**Problème :** `PostController::index()` retourne `paginate(10)` avec des métadonnées de pagination complètes (`current_page`, `last_page`, `total`). `Home.jsx` ne lit que `res.data.data` et ignore toutes ces métadonnées — les utilisateurs n'ont aucun moyen de voir ou naviguer vers les pages suivantes.

**Correction :**
```javascript
const [page, setPage]         = useState(1);
const [lastPage, setLastPage] = useState(1);

const fetchPosts = async (pageNum = 1) => {
  const res = await api.get(`/posts?page=${pageNum}`);
  setPosts(res.data.data);
  setLastPage(res.data.last_page);
};

<div>
  <button disabled={page === 1} onClick={() => { setPage(p => p-1); fetchPosts(page-1); }}>
    Précédent
  </button>
  <span>Page {page} sur {lastPage}</span>
  <button disabled={page === lastPage} onClick={() => { setPage(p => p+1); fetchPosts(page+1); }}>
    Suivant
  </button>
</div>
```

**Pourquoi c'est mieux :** Le backend gère déjà correctement la pagination. Connecter l'UI coûte très peu et améliore considérablement l'utilisabilité à l'échelle.

---

### 4.4 Doublon de Slug Non Géré

**Problème :** `PostController::store()` génère `$post->slug = Str::slug($request->title)`. Si deux articles partagent le même titre, le second `save()` heurte une violation de contrainte UNIQUE au niveau de la base de données — cela se manifeste comme une erreur 500 non gérée au lieu d'un message 422 significatif.

**Correction :**
```php
$baseSlug = Str::slug($request->title);
$slug = $baseSlug;
$counter = 2;

while (Post::where('slug', $slug)->exists()) {
    $slug = $baseSlug . '-' . $counter++;
}
// "bon-article" → "bon-article-2" → "bon-article-3"

$post->slug = $slug;
```

**Pourquoi c'est mieux :** Plus d'erreurs 500. Les utilisateurs peuvent créer plusieurs articles avec le même titre sans confusion.

---

### 4.5 Pas de Limitation de Taux sur Login — Risque de Force Brute

**Problème :** `POST /api/login` n'a pas de throttle. Un script automatisé peut tenter des milliers de mots de passe contre n'importe quelle adresse email sans être bloqué.

**Correction :**
```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
    // 5 tentatives par 1 minute par adresse IP
    // Retourne automatiquement 429 Too Many Requests quand dépassé
    // Laravel inclut ce middleware nativement — aucun package nécessaire
```

**Pourquoi c'est mieux :** Élimine la devinette de mot de passe par force brute avec deux lignes de code.

---

### 4.6 Login Retourne 404 pour Email Inconnu — Fuite d'Information

**Problème :** Quand un email n'est pas trouvé, `AuthController::login()` retourne HTTP 404 avec le message "Email not found". Cela indique à un attaquant si une adresse email existe dans le système — permettant des attaques d'énumération d'emails.

**Correction :**
```php
if (!$user || !Hash::check($request->password, $user->password)) {
    return response()->json(['message' => 'Invalid credentials'], 401);
    // Même statut, même message pour les deux cas :
    //   - Email n'existe pas
    //   - Mot de passe incorrect
    // L'attaquant ne peut pas déterminer quel cas s'applique
}
```

**Pourquoi c'est mieux :** L'énumération d'emails est fermée. Bonne pratique de sécurité : réponse identique pour les deux modes d'échec.

---

### 4.7 Pas d'Error Boundary — Crashes React Non Capturés

**Problème :** Si un composant lève une erreur d'exécution (ex : accéder à une propriété de `null`), React 19 démonte toute l'arborescence et affiche une page blanche.

**Correction :**
```javascript
// src/components/ErrorBoundary.jsx
import { Component } from "react";

export default class ErrorBoundary extends Component {
  state = { hasError: false };

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  render() {
    if (this.state.hasError) {
      return (
        <div>
          <h2>Une erreur s'est produite.</h2>
          <button onClick={() => this.setState({ hasError: false })}>
            Réessayer
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

// App.jsx — envelopper les Routes
<ErrorBoundary>
  <Routes>...</Routes>
</ErrorBoundary>
```

**Pourquoi c'est mieux :** Isole les crashes. Un composant cassé ne fait pas tomber toute l'application.

---

### 4.8 Pas de Couverture de Tests

**Problème :** Le projet n'a aucun test — ni tests unitaires pour les contrôleurs, ni tests de fonctionnalités pour les endpoints API, ni tests de composants React.

**Correction — ordre de priorité :**

```php
// 1. Tests de fonctionnalités API (valeur la plus haute — teste la logique d'autorisation)
public function test_requete_non_authentifiee_est_rejetee()
{
    $response = $this->postJson('/api/posts', ['title' => 'Test', 'content' => 'Corps']);
    $response->assertStatus(401);
}

public function test_utilisateur_ne_peut_pas_modifier_article_dautrui()
{
    $proprietaire = User::factory()->create();
    $intrus       = User::factory()->create();
    $article      = Post::factory()->create(['user_id' => $proprietaire->id]);

    $response = $this->actingAs($intrus)
                     ->putJson("/api/posts/{$article->id}", ['title' => 'Piraté']);

    $response->assertStatus(403);
}
```

**Pourquoi c'est mieux :** Les bugs d'autorisation comptent parmi les plus dangereux. Une suite de tests couvrant les vérifications de propriété aurait aussi détecté le problème d'unicité des slugs.

---

## 5. Q&R D'ENTRETIEN

### Q1 : Pourquoi avez-vous choisi Laravel Sanctum plutôt que d'écrire votre propre JWT ?

J'ai choisi Sanctum parce que le projet est une SPA qui nécessite une authentification par token API, et Sanctum résout exactement ce problème. Sanctum stocke chaque token en tant qu'enregistrement dans la base de données — cela me permet de révoquer n'importe quel token individuel, ce qui constitue une vraie déconnexion. Avec un JWT stateless, une fois le token émis, le serveur n'en garde aucune trace ; la déconnexion est purement cosmétique côté client. Avec Sanctum, `$request->user()->currentAccessToken()->delete()` supprime l'enregistrement de `personal_access_tokens` et le token est mort immédiatement.

Le compromis que j'ai accepté : chaque requête nécessite un accès à la base de données pour vérifier le token. La vérification JWT ne nécessite aucun accès DB. À cette échelle, cela ne pose pas problème. Si je construisais un service gérant des millions d'utilisateurs simultanés, je reconsidérerais et ajouterais probablement une couche de cache Redis devant la recherche de token.

---

### Q2 : Comment fonctionne l'authentification Sanctum au niveau du code ?

Lors de la connexion, `AuthController::login()` appelle `$user->createToken('auth_token')`. Sanctum génère une chaîne aléatoire de 40 caractères (crypto-sûre), la préfixe avec l'ID de l'enregistrement en base pour former un token comme `"3|Kx9mN2pQrT..."`, puis stocke un hash SHA-256 du token dans la table `personal_access_tokens`. Le texte brut est retourné au client exactement une fois et n'est jamais stocké.

Sur les requêtes suivantes, le frontend attache le token comme `Authorization: Bearer 3|Kx9mN2pQrT...`. Le middleware Sanctum analyse l'ID (3) et la chaîne du token, recherche l'enregistrement 3 dans `personal_access_tokens`, hache le token entrant avec SHA-256, et compare au hash stocké. S'ils correspondent, Sanctum charge l'utilisateur associé et le lie à `$request->user()`. Sinon, il lance une `AuthenticationException` que `bootstrap/app.php` intercepte et convertit en réponse JSON 401.

---

### Q3 : Pourquoi le token est-il stocké dans localStorage, et quel est le risque ?

J'ai stocké le token dans localStorage parce que c'est le pattern le plus simple pour le développement SPA — pas de token CSRF, pas de `withCredentials`, pas de configuration de cookie. L'interceptor Axios le lit avec `localStorage.getItem('token')` et l'attache comme en-tête Bearer sur chaque requête.

Le risque est le XSS. Si un attaquant peut injecter du JavaScript dans la page — via du contenu utilisateur non échappé, un script tiers compromis, ou tout autre vecteur — il peut appeler `localStorage.getItem('token')` et exfiltrer le token. Les cookies httpOnly sont immunisés : le navigateur les envoie automatiquement mais JavaScript ne peut absolument pas les lire. Je ferais ce changement avant d'exposer ceci à de vrais utilisateurs.

---

### Q4 : Comment avez-vous évité les problèmes de requêtes N+1 ?

Partout où je récupère une liste d'articles incluant des données liées, j'utilise le eager loading d'Eloquent : `Post::with('author', 'category', 'tags')`. Cela génère quatre requêtes quel que soit le nombre d'articles retournés : une pour les articles eux-mêmes, une pour tous les utilisateurs associés, une pour toutes les catégories associées, et une pour tous les tags associés via la table de jointure.

Sans eager loading, accéder à `$post->author->name` dans une boucle déclencherait une requête supplémentaire par article — 10 articles, 10 requêtes supplémentaires ; 100 articles, 100 requêtes supplémentaires. C'est le problème N+1 classique.

---

### Q5 : Comment fonctionne votre autorisation ? Qu'est-ce qui empêche l'utilisateur A de supprimer l'article de l'utilisateur B ?

Je gère l'autorisation explicitement dans le contrôleur, juste après avoir trouvé la ressource. Dans `PostController::destroy()`, après que `Post::find($id)` retourne l'article, je compare `$post->user_id` avec `$request->user()->id`. La valeur `$request->user()` est peuplée par Sanctum depuis le token vérifié — elle ne peut pas être falsifiée depuis le body de la requête. Si ces deux IDs ne correspondent pas, je retourne 403 Forbidden immédiatement sans toucher l'enregistrement.

Le même contrôle existe dans `update()`. J'applique ce pattern de façon cohérente : trouver → vérifier l'existence → vérifier la propriété → effectuer l'action. Ce que je n'ai pas encore fait, c'est extraire cela dans une classe Laravel Policy, ce qui donnerait une méthode dédiée `PostPolicy::update()` et un code de contrôleur plus propre.

---

### Q6 : Qu'est-ce qui ne va pas avec l'utilisation de `PUT` au lieu de `PATCH` pour votre endpoint de mise à jour ?

La distinction sémantique HTTP est : `PUT` signifie "remplacer toute la ressource par cette charge utile" — le client envoie une représentation complète. `PATCH` signifie "appliquer ces modifications partielles" — le client envoie uniquement ce qui a changé. Ma méthode `PostController::update()` implémente en réalité une logique de mise à jour partielle : `if ($request->title) { $post->title = $request->title; }` — je ne mets à jour que les champs présents dans le body de la requête. Ce comportement correspond à `PATCH`, pas à `PUT`. Je devrais changer `Route::put('/posts/{id}', ...)` en `Route::patch(...)` et mettre à jour l'appel frontend correspondant. C'est une inconsistance sémantique qui confondrait quiconque lirait le contrat API.

---

### Q7 : Quels sont les bugs actuels dans le code que vous voudriez corriger immédiatement ?

Oui, deux que je considère urgents. Premièrement, le problème d'unicité des slugs : si deux articles ont le même titre, `Str::slug()` produit le même slug pour les deux. Le second `save()` heurte la contrainte UNIQUE sur `posts.slug` et lance une exception de base de données non gérée — l'utilisateur voit une erreur 500 sans message significatif. Une simple boucle while qui ajoute un compteur au slug corrige cela entièrement.

Deuxièmement, l'endpoint de connexion retourne 404 avec le message "Email not found" quand un email n'existe pas. C'est une fuite d'information — un attaquant peut énumérer les adresses email valides en sondant l'endpoint. Les deux cas — email inexistant et mot de passe incorrect — doivent retourner 401 avec le même message générique : "Invalid credentials".

---

### Q8 : Si vous deviez scaler ce projet à 10× les utilisateurs, que changeriez-vous en premier ?

Le premier goulot d'étranglement serait la base de données. J'ajouterais des index composites pour les requêtes les plus fréquentes : `posts(status, published_at DESC)` pour le flux de la page d'accueil, et `comments(post_id, is_approved, parent_id)` pour la liste des commentaires. Actuellement, les seuls index sont ceux que Laravel ajoute pour les clés étrangères et les contraintes uniques.

Ensuite, j'ajouterais du cache pour les données publiques. La liste des articles, la liste des catégories — celles-ci ne changent pas par utilisateur et pourraient être servies depuis Redis avec un TTL de quelques minutes au lieu d'interroger MySQL à chaque chargement de page.

Troisièmement, la recherche de token Sanctum frappe la base de données à chaque requête API. Avec un trafic élevé, c'est la chose la plus rapide à mettre en cache : stocker le mapping token vérifié → utilisateur dans Redis avec un court TTL. Laravel a ce pattern intégré.

Enfin, je remplacerais localStorage par des cookies httpOnly, j'ajouterais une limitation de taux sur les endpoints d'auth, et je définirais `sanctum.expiration` à quelque chose de raisonnable avec un mécanisme de refresh token.

---

### Q9 : Quelle a été la plus grande difficulté technique dans ce projet ?

La plus grande difficulté technique a été de comprendre le format du token Sanctum. Le token retourné au client ressemble à `"3|Kx9mN2pQrT..."`, et j'ai d'abord supposé que la partie après le pipe était déjà un hash. Ce n'est pas le cas — c'est la chaîne aléatoire brute. Sanctum la hache avant stockage. Lors de la vérification, il prend la partie brute de la requête entrante, la hache, et compare au hash stocké. J'ai passé du temps confus sur pourquoi certains tokens ne se validaient pas jusqu'à ce que je trace le code source de Sanctum et comprenne la division `id|plaintext` / `SHA256 hash stocké`.

La deuxième difficulté a été le débogage de CORS. Mes requêtes Axios échouaient silencieusement, et je cherchais le problème côté frontend. CORS est entièrement une politique côté serveur — le navigateur l'applique, mais le serveur la déclare via les en-têtes de réponse. Une fois que j'ai compris cela, configurer `config/cors.php` a été simple.

---

### Q10 : Que feriez-vous différemment si vous recommenciez ce projet de zéro ?

Trois choses immédiatement. Premièrement, je concevrais le partage de l'état d'auth dès le départ — un Context React qui expose `user`, `login()` et `logout()` — au lieu de lire depuis localStorage dans chaque composant. Cette seule décision aurait éliminé le hack `window.location.href` et permis de vrais gardes de routes protégées.

Deuxièmement, j'utiliserais React Query pour tout l'état serveur dès le début. Le pattern loading/error/data que j'ai écrit manuellement dans chaque composant est exactement ce que React Query automatise — plus le cache, le rafraîchissement en arrière-plan et la déduplication des requêtes.

Troisièmement, j'écrirais les tests de fonctionnalités pour l'autorisation avant d'écrire les contrôleurs. Les tests pour "l'utilisateur ne peut pas modifier les articles d'autrui" et "les requêtes non authentifiées sont rejetées" sont des tests de trois lignes qui m'auraient donné confiance dans mes vérifications de propriété tout au long du développement.

---

*Fin de la série de présentation en français.*  
*Voir aussi :*  
*- [presentation_vi_1_overview_architecture.md](presentation_vi_1_overview_architecture.md) — Version vietnamienne*  
*- [presentation_en_1_overview_architecture.md](presentation_en_1_overview_architecture.md) — Version anglaise*
