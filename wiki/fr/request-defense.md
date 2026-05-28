# Défense des requêtes entrantes

## Pourquoi tu en aurais besoin — un scénario concret

Un attaquant découvre ton endpoint `/api/upload`. Il envoie :

```
POST /api/upload HTTP/1.1
Content-Length: 2147483648
Content-Type: application/octet-stream

<2 Go de déchets>
```

Ton worker PHP-FPM commence à recevoir le corps. **Avant même que ton code applicatif tourne**, le body parser (le `BodyParsingMiddleware` de Slim, le buffering de `php://input` de PHP, ou ton propre `json_decode( $request->getBody() )`) essaie de matérialiser le payload de 2 Go en mémoire. Au moment où ta validation prend le relais pour rejeter l'upload, ton worker a consommé 2 Go de RAM et a déclenché le OOM killer. Ton process meurt. Ton superviseur le relance. L'attaquant envoie une autre requête. Répète jusqu'à ce que ton serveur tombe.

**Avec `enforceMaxBodySize()` appelé AVANT tout parsing du body**, la requête est rejetée sur la seule base de l'en-tête `Content-Length` — pas d'allocation mémoire, pas de parsing, pas de streaming. Coût : une lecture d'en-tête + une comparaison d'entiers.

```
HTTP/1.1 413 Payload Too Large
```

L'attaquant reçoit un rejet propre, ton worker reste en bonne santé. La défense côté PHP complète les garde-fous en amont (nginx `client_max_body_size`, PHP `post_max_size` / `upload_max_filesize`) — avoir les trois couches empêche qu'une seule mauvaise configuration soit porteuse à elle seule.

Cette page couvre aussi une défense soeur, [`enforceTrustedHosts()`](#enforcetrustedhosts), contre les attaques Host Header.

---

`oihana/php-middleware` fournit des helpers de défense pré-parsing qui rejettent les requêtes manifestement mauvaises avant que l'application ait à les gérer.

## `enforceMaxBodySize()`

```php
namespace oihana\middleware\helpers\body ;

function enforceMaxBodySize( ServerRequestInterface $request , int $maxBytes ) : bool ;
```

Retourne `true` quand le body tient dans la limite (ou que sa longueur est inconnue), `false` quand il dépasse la limite ou porte un `Content-Length` malformé.

### Comportement

| En-tête `Content-Length` | Retourne |
| :--- | :--- |
| Absent (streaming / chunked) | `true` — ne peut pas vérifier, défère aux garde-fous amont |
| `0` à `$maxBytes` | `true` |
| `> $maxBytes` | `false` |
| Négatif (`-1`) | `false` (strict — `ctype_digit` rejette le signe) |
| Non-numérique (`abc`) | `false` |
| Avec `+` en tête, décimales, ou tout autre caractère non-chiffre | `false` |

**Défaut défensif sur entrée malformée.** Si le helper ne peut pas faire confiance à la longueur déclarée, la requête est rejetée. Mieux vaut bouncer une requête légitime bizarre qu'autoriser une bombe payload sous un en-tête invérifiable.

### Utilisation

```php
use oihana\enums\http\HttpStatusCode ;
use function oihana\middleware\helpers\body\enforceMaxBodySize ;

// Rejette tout body de plus de 10 Mio avant parsing.
if ( !enforceMaxBodySize( $request , 10 * 1024 * 1024 ) )
{
    return $responseFactory->createResponse( HttpStatusCode::PAYLOAD_TOO_LARGE ) ;
}

// Safe à parser — le body fait au plus 10 Mio.
$parsed = json_decode( (string) $request->getBody() , true ) ;
```

### Où ça s'insère dans la stack de défense

L'application de la limite de body côté PHP est **une couche dans un dispositif de défense en profondeur** — pas un remplacement des limites amont :

| Couche | Configure | Rôle |
| :--- | :--- | :--- |
| **nginx / Apache** | `client_max_body_size`, `LimitRequestBody` | Rejet au bord, avant que la requête atteigne PHP-FPM. |
| **PHP** | `post_max_size`, `upload_max_filesize` | Borne le traitement de body du runtime PHP. |
| **`enforceMaxBodySize()`** | Limite par endpoint | Limite plus serrée, spécifique à la route, posée par le code applicatif. |

Un endpoint de login peut plafonner à 4 Ko ; un upload d'avatar à 5 Mo ; un upload vidéo à 200 Mo. Les garde-fous amont posent un plafond global ; le helper pose la réalité par route.

## `enforceTrustedHosts()`

```php
namespace oihana\middleware\helpers\host ;

function enforceTrustedHosts( ServerRequestInterface $request , array $trustedHosts ) : bool ;
```

Défense sœur contre les **attaques Host Header** — la classe d'attaques où un attaquant forge l'en-tête `Host:` pour faire générer à ton app des URLs pointant vers son domaine (emails de reset de mot de passe empoisonnés) ou pour contourner le routing virtual-host.

### L'attaque concrète

Ton app envoie des emails de reset de mot de passe contenant :

```php
$resetLink = $request->getUri()->getScheme()
           . '://' . $request->getHeaderLine( 'Host' )
           . '/reset/' . $token ;
```

Un attaquant demande un reset pour le compte de quelqu'un d'autre, mais avec `Host: attacker.com` dans sa requête. Ton app génère `https://attacker.com/reset/<vrai-token>` et l'envoie par mail à la victime. La victime clique. Le token fuit à l'attaquant. Compte compromis.

Avec `enforceTrustedHosts()`, les requêtes portant un Host qui n'est pas sur ton allowlist sont rejetées avant que tout handler ne tourne :

```php
use function oihana\middleware\helpers\host\enforceTrustedHosts ;

if ( !enforceTrustedHosts( $request , [
    'example.com' ,
    '*.example.com' ,
    'admin.internal' ,
] ) )
{
    return $responseFactory->createResponse( 400 ) ;
}
```

### Règles de matching

Per RFC 9110 §7.2 — `Host` est insensible à la casse.

| Entrée de l'allowlist | Matche |
| :--- | :--- |
| `example.com` | Match exact : `Host: example.com` ou `Host: example.com:8080` (port strippé). |
| `*.example.com` | N'importe quel sous-domaine : `api.example.com`, `staging.api.example.com`. **Ne matche PAS l'apex `example.com`** — le lister explicitement pour l'accepter. |
| `*.*.example.com` | **Rejeté comme invalide** — les wildcards imbriqués n'ont pas de sémantique standard. |
| `api.*.com` | **Rejeté comme invalide** — les wildcards en milieu de chaîne n'ont pas de sémantique standard. |

### Matrice de comportement

| Condition | Retourne |
| :--- | :--- |
| Allowlist vide | `true` (no-op : guard désactivé, PAS bloquer-tout) |
| En-tête `Host` manquant | `false` (HTTP/1.1 exige Host) |
| `Host` malformé (multiple colons non bracketés, bracket IPv6 non fermé) | `false` (défensif) |
| Host matche une entrée de l'allowlist | `true` |
| Host ne matche aucune entrée | `false` |

Le comportement **allowlist vide = no-op** est un filet de sécurité intentionnel. Un déploiement mal configuré qui câble le middleware mais oublie de remplir l'allowlist verrouillerait sinon tous les utilisateurs dehors — le no-op échoue ouvert au lieu, ce qui est le bon compromis pour une config manquante (tu le remarqueras ; tu ne remarquerais pas forcément une défense qui marche mais qui est contournée).

### Où ça s'insère dans la stack de défense

| Couche | Configure | Rôle |
| :--- | :--- | :--- |
| **Blocs `server_name` nginx / Apache** | Routing par vhost | Rejet au bord, avant que la requête atteigne PHP-FPM. |
| **`enforceTrustedHosts()`** | Allowlist côté app | Défense en profondeur au cas où le reverse-proxy serait mal configuré ou absent. |

Si ton reverse-proxy fait déjà du matching `server_name` strict et ne forward jamais des hosts inconnus, le helper est redondant en production. Il garde son intérêt en développement (où on ne fait typiquement pas tourner un reverse-proxy) et comme fallback si la config proxy dérive.

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [RFC 9110 §15.5.14](https://www.rfc-editor.org/rfc/rfc9110#status.413) — sémantique `413 Payload Too Large`.
- [`client_max_body_size`](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) nginx — configuration sœur amont.
