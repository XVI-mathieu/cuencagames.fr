# « L'Arsenal » — scène cinématique de l'accueil

Expérience 2.5D pilotée par le scroll, superposée à l'accueil existant en amélioration progressive.
Aucune dépendance. Aucun traceur. Aucune requête vers `/telecharger/{id}` ni `/jouer/{id}` (les liens sont clonés tels quels : un téléchargement ou une partie ne se déclenche que sur un vrai clic).

## Lancer en local

La scène a été développée contre un **instantané** de la page d'accueil en ligne (`gaming/index.html` dans `Desktop/site gaming`, avec ses CSS, polices et logo copiés). Ces copies ne servent qu'au banc de test local. Avec le vrai projet PHP : `demarrer.bat` puis `http://localhost:8000/?cinema=1` (PHP n'est pas installé sur la machine où j'ai travaillé, je n'ai donc pas pu lancer le site PHP lui-même).

```bash
python -m http.server 8077 --bind 127.0.0.1
```

Puis ouvrir `http://127.0.0.1:8077/gaming/index.html?cinema=1`.
`?cinema=force` (banc de test uniquement, présent dans l'instantané, pas dans le site PHP) ignore `prefers-reduced-motion`. Pour tester la scène sur le site PHP avec cette option activée dans l'OS, désactivez-la le temps du test.
En console : `cinema.go(0.58)` saute à une progression, `cinema.compute(p)` renvoie toutes les variables d'une frame.

## Intégration dans ce projet (déjà faite)

Fichiers ajoutés : `public/assets/cinema/` (`cinema.js`, `cinema.css`, `layers/*.svg`) et ce dossier `docs-cinema/`.
Fichiers modifiés (copies d'origine dans `_sauvegarde-avant-cinema/`, à supprimer avant la mise en ligne si vous voulez) :

| Fichier | Changement |
|---|---|
| `app/Views/home/index.php` | une ligne : `$cinema = true;` |
| `app/Views/layout.php` | dans le `<head>`, un bloc conditionnel (si `$cinema` et mode ≠ `off`) : petit script de garde, `cinema.css`, `cinema.js` |
| `app/config.php` | une clé : `'cinema_mode' => 'test'` |

Aucun contrôleur, route, modèle, session, CSRF ni compteur de téléchargements n'est touché.

**Mode** (`app/config.php`, clé `cinema_mode`) :
- `'test'` (défaut) : la scène ne s'active que sur `/?cinema=1` ; tous les visiteurs voient l'accueil actuel ;
- `'on'` : la scène s'active pour tous les visiteurs ;
- `'off'` : rien n'est chargé.

Dans tous les cas la scène reste inactive avec `prefers-reduced-motion`, sur un écran de moins de 560 px de haut, quand le catalogue est vide, ou quand le serveur affiche un message flash (le message reste alors visible sur l'accueil classique). Les chemins des assets sont déduits de l'adresse de `cinema.js`, donc le site peut vivre à la racine (`demarrer.bat`) ou dans `/gaming/`.

**Mise en ligne** : envoyer par FTP `public/assets/cinema/`, `app/Views/layout.php`, `app/Views/home/index.php` et `app/config.php`.

## Fichiers

| Fichier | Rôle |
|---|---|
| `cinema.js` | `CONFIG` (timeline, course de scroll, lissage, couleurs de teinte), helpers `clamp/lerp/smoothstep/rangeProgress/segmentInOut`, lecture des données serveur, construction du DOM, `compute(p)` (pure), boucle rAF, rail |
| `cinema.css` | tokens, stage sticky, transforms finaux des layers, panneaux, rail, responsive |
| `layers/*.svg` | 8 layers placeholders (voir `MANIFEST.md`) |
| `MANIFEST.md` | manifeste d'assets, bandes de z-index, timeline map, liste des placeholders |

## Titre et mise en scène

- **Titre** : l'intro affiche « CUENCA GAMES » (`CONFIG.copy.title` dans `cinema.js` ; vide = titre rendu par le serveur). L'en-tête du site et le `<title>` de la page restent « Mes Projets » : ils viennent du serveur, que je n'ai pas modifié.
- **Ouverture** : bandes de cinéma qui se retirent, titre qui entre mot à mot avec un éclat de lumière qui le traverse, puis compteurs et boutons.
- **Pendant le scroll** : profondeur de champ (la salle se défocalise quand la borne est cadrée), allumage de la borne avec une petite surcharge de lumière, éclat chaud quand elle passe la caméra, texte des panneaux qui entre ligne par ligne.
- **Ambiance** : scintillement discret de l'écran, grain de pellicule, grains de poussière. Tout est en pause hors écran et désactivé en mode `cin-lite` ; rien de tout cela n'existe en reduced-motion (la scène n'est pas active).

## Choix techniques

- **Données** : stats, chips (Tout / Jeux / Projets + compteurs) et cartes sont lus dans le DOM rendu par le serveur (`.hero .stats`, `#chips`, `#projects .card`) puis clonés. « Jouer » n'existe que si la carte serveur le propose (Destruction Derby n'a que son .exe). Rien n'est codé en dur. Le compteur « N jeux sur M se lancent dans le navigateur » du panneau A est dérivé des cartes.
- **Filtres** : annonce du nombre de résultats en `aria-live` polite ; zéro résultat (« Projets : 0 ») affiche un état vide avec un bouton « Voir tout ».
- **Rail** : flèches précédent/suivant désactivées aux extrémités, drag souris, swipe natif, flèches/Home/End au clavier, position « Jeu x sur N » en `aria-live` polite, cibles ≥ 44 px. Pas de groupe mis à l'échelle, donc pas de compensation d'échelle nécessaire.
- **Mouvement** : le JS écrit des variables CSS, le CSS possède les transforms. Playhead et pointeur lissés séparément, la boucle s'arrête à convergence et ne tourne pas hors écran (`IntersectionObserver`). Parallaxe pointeur 6 à 24 px, désactivée pour les pointeurs grossiers. Flou seulement pendant le panneau B et le rail, remplacé par la teinte sur appareils modestes (`cin-lite`).
- **Reduced motion** : la scène ne s'active pas ; on garde l'accueil actuel (en-tête, compteurs, filtres, grille).
- **Viewport court** (< 560 px de haut : zoom navigateur 200 % sur un écran standard, paysage mobile) : accueil classique, car le contenu de la scène ne tiendrait pas.
- **Mobile** : « Jouer » devient l'action principale, le .exe passe en secondaire (le site actuel affiche déjà les deux sur toutes les plateformes ; seule la hiérarchie change). Scroll virtuel 2 800 px, parallaxe coupée.

## Points d'attention pour le propriétaire du site

1. **Texte du panneau B** : la page `/gaming/securite` est une simulation de sensibilisation (faux site de « jeux gratuits »). Elle ne parle ni de mentions légales ni de RGPD. Le panneau B reprend donc fidèlement ce qu'elle dit sur les .exe (programme qui peut tout faire sur l'ordinateur, ne télécharger que depuis le site officiel, virustotal.com en cas de doute) et ajoute une ligne tirée du bandeau cookies et du pied de page du site (cookies techniques uniquement, liens légales/RGPD/cookies en pied de page). Le lien mène vers `/gaming/securite`.
2. **Panneau A** : libellé tiré de la fiche projet (« Directement dans le navigateur, rien à installer… Sans compte : 10 minutes de jeu. Créez un compte gratuit pour jouer sans limite. »), sans « n'importe quel jeu » puisque tous les jeux ne sont pas jouables dans le navigateur. À valider avant de figer.
3. **Propriété intellectuelle** : les layers et textes de la scène sont originaux. Le catalogue affiche des noms de jeux qui évoquent des franchises existantes (par exemple « Star Wars Commander », « Rocket League », « F1 2026 »). Ils sont repris tels que le site les affiche, mais c'est un risque de marque qui existe déjà sur la page actuelle.
4. **Palette** : le site est cyan/magenta néon. La scène garde ses tokens (fonds, surfaces, bordures, Orbitron/Rajdhani, biseaux, vert « Jouer ») mais n'utilise qu'une lueur chaude `#ffb45a` pour la lumière et les CTA ; le cyan reste pour les focus.

## Résultats de vérification

Faits (navigateur intégré, serveur statique local, instantané de la page réelle) :

| Contrôle | Résultat |
|---|---|
| 1280×720, p = 0, .18, .27, .44, .58, .74, .90, 1 | inspectés : pas de trou ni de bord non peint, pas de collision de textes, pas d'inversion de z-order, borne cadrée, panneaux A et B jamais visibles ensemble |
| 390×844 (mobile) | p = 0, .27, .58, 1 inspectés ; aucun débordement horizontal (`scrollWidth` = 390) ; en-tête compact sur une ligne ; « Jouer » mis en avant sur les cartes |
| 768×1024 (tablette) | p = 0, .27, 1 inspectés ; pas de débordement horizontal |
| 1024×768, 1440×900 | chargés sans erreur, pas de débordement horizontal (1024 : `scrollWidth` 1009 pour 1024) ; **non inspectés visuellement à chaque point** |
| Zoom 200 % | simulé par 640×360 (= 1280×720 à 200 %) : bascule sur l'accueil classique, tout reste lisible ; 960×560 (= 1920×1120 à 200 %) : scène complète, rail entièrement visible |
| Timeline | `compute(p)` : mêmes valeurs en descendant, en montant et après des sauts ; saut maximal 3,75 % d'une variable par pas de 0,001 de p (aucun saut d'opacité) ; A et B ne se recouvrent jamais |
| Rail | filtre « Projets » → état vide + « 0 résultat » + précédent/suivant désactivés ; deux clics sur « suivant » → « Jeu 3 sur 9 » ; touche Fin → « Jeu 9 sur 9 » et « suivant » désactivé |
| « Voir les jeux » | mène à p = 1 (4 200 px) |
| Réseau | aucune requête vers `/telecharger/` ni `/jouer/` pendant le chargement ou la navigation ; aucun `prefetch`/`prerender` ; un seul fichier par layer, ~32 Ko de SVG |
| Poids | `cinema.js` 34 Ko (10 Ko gzip), `cinema.css` 16 Ko (5 Ko gzip), SVG 31 Ko (5 Ko gzip) |
| Console | aucune erreur ; en mode reduced-motion (réglage de cette machine) l'accueil classique s'affiche, console vide |
| Flou | le panneau B s'affiche sur la salle floutée (vérifié avec `cin-lite` retiré) ; la machine de test déclare ≤ 4 cœurs et passait donc en mode `cin-lite` (teinte seule, sans flou) |
| Bandeau cookies | s'affiche au-dessus de la scène (z-index 50), cliquable (`elementFromPoint` = bandeau) ; l'intro réserve 96 px en bas pour qu'il ne masque pas « Voir les jeux » |
| Navigation clavier | éléments hors vue rendus `inert` (3 groupes au repos) ; seuls hero et « Passer l'animation » sont tabulables au départ |

**Non vérifiés ici** (le panneau de prévisualisation se met en pause quand il est masqué, ce qui fige `requestAnimationFrame`) :

- stabilité des frames et repaints coûteux : à mesurer dans Chrome DevTools (Performance, scroll sur toute la course) ;
- parcours complet au clavier avec lecteur d'écran, et ordre exact de Tab au clavier réel (les touches ont été simulées en partie) ;
- test sur un vrai téléphone (swipe, barre d'URL dynamique : le stage utilise `svh`) ;
- Jouer, .exe, fiches projet, Sécurité, connexion et cookies : **non touchés** (liens clonés sans modification, routes et serveur intacts) mais je n'ai pas cliqué sur « Jouer » ni « .exe » pour ne pas déclencher une session ou un téléchargement comptés ; le bandeau cookies de l'instantané reste au-dessus (z-index 50) et utilisable.
