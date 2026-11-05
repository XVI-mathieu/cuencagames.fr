# Manifeste des layers, timeline map et placeholders

Tous les layers sont des **SVG dessinés par code**, entièrement originaux (aucun personnage, vaisseau,
logo, véhicule ni asset tiré d'un jeu ou d'une marque), sans texte, chargés par `<img alt="">`.
Ils sont faits pour être **remplacés tels quels** par des illustrations finales (mêmes noms, mêmes proportions).

## Contrat commun

| Règle | Valeur |
|---|---|
| Ratio maître | 16:9 (cadre visible 1600 × 900) |
| Bleed | 16 % tout autour sur les layers « scène » : viewBox 1856 × 1044, cadre visible décalé de (128, 72) |
| Caméra | frontale, point de fuite (928, 540) dans le viewBox, horizon du sol à y = 640 |
| Lumière | une lueur chaude `#ffb45a` (écran de la borne héros) ; lueur froide `#8fd8ff` aux portes du fond |
| Grade | fonds `#04050a → #0d1020`, traits `#10152c → #2a3360` (tokens du site : `--bg`, `--bg-2`, `--surface`, `--border`) |
| Cadrage CSS | `.cin-layer` : `left/top -8 %`, `116 % × 116 %`, `object-fit: cover`, `object-position: 50% 100 %` (ancre bas + centre) |
| Layout shift | `width`/`height` sur chaque `<img>` ; le stage est en `svh` |

## Manifeste

| N° | Fichier | Rôle | viewBox (px) | Ancre | Profondeur / mouvement |
|---|---|---|---|---|---|
| 00 | `layers/00-hangar-bg.svg` | Fond du hangar : plafond, charpente, mur du fond, portes entrouvertes à lueur froide. Opaque, plein cadre | 1856 × 1044 | bas + centre ; point de fuite (928, 540) | le plus loin : zoom +6 % (push) +3 % (pano), pointeur +6 px |
| 10 | `layers/10-far-rows.svg` | Lointain : rangées de bornes au fond. Haut transparent, même horizon | 1856 × 1044 | bas + centre ; pied des bornes à y 640–712 | zoom +16 % / +8 %, pointeur +9 px |
| 20 | `layers/20-midground.svg` | Midground : sol, bornes latérales, câbles, reflet chaud au sol | 1856 × 1044 | bas + centre ; sol feuilleté à y 610 | zoom +42 % / +20 %, pointeur −12 px |
| 30 | `layers/30-cabinet.svg` | Borne héros, caisson. Objet isolé avec marge pour le zoom, sans texte | 1000 × 1400 (5:7) | pied (500, 1340) ; centre d'écran (500, 475) = origine du zoom | zoom ×1 → ×3,2 (écran cadré à p .25) puis +70 %, pointeur −18 px |
| 31 | `layers/31-screen.svg` | Écran de la borne, séparé du caisson | 560 × 450 | **ancre du panneau A** : rect (220, 250)–(780, 700) dans le viewBox du 30, soit `left 22 % / top 17,857 % / 56 % × 32,143 %` du caisson | suit la borne ; opacité 0,4 → 1 (allumage .15–.22) |
| 40 | `layers/40-door-left.svg` | Battant gauche, occluder de premier plan, chant intérieur chaud | 600 × 1044 | bord intérieur (droit), `preserveAspectRatio xMaxYMid slice` | s'écarte de −45 % à −100 % de sa largeur, zoom +35 %, pointeur −24 px |
| 41 | `layers/41-door-right.svg` | Battant droit (miroir) | 600 × 1044 | bord intérieur (gauche), `xMinYMid slice` | idem, symétrique |
| 50 | `layers/50-vignette.svg` | Vignette de cadrage, centre transparent | 1856 × 1044 | centre | statique |

Overlays CSS (pas des fichiers) : `.cab-glow` (halo chaud autour de l'écran), `.cin-tint` (teinte `#0d1020`), `.cin-scrim` (voile de lisibilité du hero).
Grain et poussière : non fournis (optionnels), à ajouter dans la bande z 10–19 si souhaité.

## Bandes de z-index

| Bande | Contenu |
|---|---|
| 0–9 | monde : layers 00 → 41 |
| 10–19 | teinte (10), voile hero (11), vignette (12) |
| 20–29 | texte narratif : intro (22), panneaux A et B (24) |
| 30–39 | rail, filtres, contrôles (30) ; en-tête du site fixé à 35 |
| 40+ | bannière cookies du site, **inchangée** (z-index 50) |

## Timeline map

`p` = progression normalisée (0–1) sur la course virtuelle : **4 200 px** desktop, **2 800 px** mobile (≤ 700 px de large ou pointeur grossier).
Toutes les bornes vivent dans `CONFIG.timeline` de `cinema.js`.

| p | Événement |
|---|---|
| 0.00 – 0.03 | hero fixe : logo, eyebrow, « Mes Projets », accroche, compteurs, « Voir les jeux », « Se connecter », « Passer l'animation » |
| 0.03 – 0.12 | titre et intro sortent (opacité + 26 px) |
| 0.03 – 0.25 | push-in : tous les layers zooment à vitesses différentes, la borne glisse vers le centre et est cadrée par son écran |
| 0.15 – 0.25 | les battants s'écartent ; 0.15 – 0.22 la borne s'allume |
| 0.25 – 0.275 | panneau A « Jouer en un clic » entre, tient jusqu'à 0.35 |
| 0.35 – 0.40 | panneau A sort |
| 0.35 – 0.44 | la borne s'efface vers la caméra (zoom +70 %, opacité → 0) |
| 0.44 – 0.48 | panorama propre de la salle, dérive latérale lente (de 0.35 à 0.74) |
| 0.48 – 0.52 | panneau B « Fait maison, vérifiable » entre ; 0.48 – 0.58 la salle se floute et se teinte |
| 0.52 – 0.69 | B tient |
| 0.69 – 0.74 | B sort, la salle reprend sa netteté |
| 0.75 – 0.96 | le rail de jeux entre horizontalement (la salle s'assombrit et se floute légèrement pour la lisibilité) |
| 0.91 – 1.00 | filtres, compteur de résultats, précédent/suivant, position « Jeu x sur N » apparaissent ; état final interactif |

La timeline est une fonction pure de `p` (`cinema.compute(p)`) : même `p`, mêmes variables, en descendant comme en montant.

## Placeholders à remplacer par des illustrations de production

Les 8 fichiers de `layers/` sont des placeholders. Pour les remplacer : conserver le nom, le ratio et les ancres du tableau ci-dessus.

1. `00-hangar-bg` : opaque, plein cadre, point de fuite et horizon identiques.
2. `10-far-rows` : alpha propre, haut transparent, sans halo.
3. `20-midground` : sol + bornes latérales + câbles, alpha propre en haut.
4. `30-cabinet` : ancre bas et centre, écran en (220, 250)–(780, 700).
5. `31-screen` : 560 × 450, même centre d'écran, aucun texte (le texte du panneau A est en HTML).
6. `40-door-left` / `41-door-right` : bord intérieur chaud, `preserveAspectRatio` conservé.
7. `50-vignette` : centre transparent.

Pour des illustrations photo/raster : WebP/AVIF pour les grands layers, SVG/PNG si vectoriel ou alpha ; garder `width`/`height`.
