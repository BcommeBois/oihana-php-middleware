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

Cette page couvre aussi une défense soeur, [`enforceTrustedHosts()`](#enforcetrustedhosts) (livrée plus tard dans la v0.7), contre les attaques Host Header.

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

## `enforceTrustedHosts()` — livré plus tard dans la v0.7

Helper sœur contre les attaques Host Header (cache poisoning, password-reset poisoning). La documentation atterrira dans cette même page quand le helper sera livré (Lot C).

## Voir aussi

- [Démarrage](getting-started.md) — câblage middleware PSR-15 général.
- [RFC 9110 §15.5.14](https://www.rfc-editor.org/rfc/rfc9110#status.413) — sémantique `413 Payload Too Large`.
- [`client_max_body_size`](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) nginx — configuration sœur amont.
