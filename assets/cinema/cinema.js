/* =========================================================
   « L'Arsenal » — moteur de scroll cinématique (aucune dépendance)
   Prérequis : <html class="cinema-on"> (snippet de <head>), cinema.css chargé.
   Le serveur reste la source de vérité : stats, filtres et cartes sont lus dans le HTML
   déjà rendu (.stats, #chips, #projects) puis clonés dans la grille.
   Ce script ne fait AUCUNE requête vers /telecharger/ ni /jouer/ : les liens sont clonés tels quels.
   ========================================================= */
(function () {
    'use strict';

    var html = document.documentElement;
    if (!html.classList.contains('cinema-on')) { return; }
    var script = document.currentScript;
    var BASE = script && script.src ? script.src.replace(/[^\/]*$/, '') : '/assets/cinema/';   // dossier de cinema.js, avec « / » final
    var LAYERS = BASE + 'layers/';
    // mode fixe : reduced-motion ou viewport court → même décor et même titre, mais sans scroll animé ; le catalogue classique suit
    var STATIC = html.classList.contains('cinema-static');
    function bail() { html.classList.remove('cinema-on', 'cinema-static'); }    // rend la main à la page classique

    // #region 1. Configuration — seul endroit à toucher pour retimer ou rééchelonner
    var CONFIG = {
        run: { desktop: 4200, mobile: 2800 },            // px de scroll virtuel
        mobileQuery: '(max-width: 700px), (pointer: coarse)',
        smoothing: 0.14,                                  // part de l'écart rattrapée par frame à 60 Hz
        pointerSmoothing: 0.09,
        parallax: true,                                   // coupé pour pointeur grossier (voir CSS)
        // p = progression normalisée 0..1 ; [début, fin]
        timeline: {
            introOut:  [0.03, 0.12],      // titre + intro sortent (le texte a fini de bouger à .18)
            push:      [0.03, 0.25],      // push-in : cadre l'écran de la borne à .25
            doors:     [0.15, 0.25],      // les battants s'écartent
            cabOn:     [0.15, 0.22],      // la borne s'allume
            panelAIn:  [0.25, 0.275],
            panelAOut: [0.35, 0.40],
            cabOut:    [0.35, 0.44],      // la borne s'efface vers la caméra
            pano:      [0.35, 0.74],      // dérive lente de la salle (panorama propre dès .44)
            panelBIn:  [0.48, 0.52],
            panelBOut: [0.69, 0.74],
            blurIn:    [0.48, 0.58],
            blurOut:   [0.69, 0.74],
            railIn:    [0.75, 0.96],      // panneau de fin « Choisis ton jeu » ; le catalogue en grille suit sous la scène
        },
        minHeight: 560,                   // en dessous (zoom 200 %, paysage mobile) : page classique, tout reste utilisable
        doorStart: 0.45,                  // ouverture initiale des battants (0 fermé, 1 hors cadre)
        scale: { bg: [0.06, 0.03], far: [0.16, 0.08], mid: [0.42, 0.20] },   // [push, pano]
        panPx: 0.03,                      // dérive latérale max, part de la largeur du stage
        cabEndExtra: 0.7,                 // zoom supplémentaire pendant que la borne passe la caméra
        dofPx: 2.4,                       // profondeur de champ : la salle se défocalise quand la borne est cadrée
        flashMax: 0.8,                    // éclat chaud quand la borne passe la caméra
        motes: 26,                        // grains de poussière en suspension
        blurPx: 5, railBlurPx: 3, tintMax: 0.5, railTint: 0.92, baseTint: 0.1,
        parallaxEase: 1,
        copy: {
            title: 'Cuenca Games',          // titre de la scène ; vide = titre rendu par le serveur
            skip: 'Passer l’animation',
            seeGames: 'Voir les jeux'
        }
    };
    var T = CONFIG.timeline;

    // #endregion

    // #region 2. Helpers mathématiques
    /** Borne la valeur v entre a et b. */
    function clamp(v, a, b) { return v < a ? a : v > b ? b : v; }
    /** Interpolation linéaire de a vers b (t = 0 donne a, t = 1 donne b). */
    function lerp(a, b, t) { return a + (b - a) * t; }
    /** Progression douce (courbe en S) de 0 à 1 quand v va de a à b. */
    function smoothstep(a, b, v) { var t = clamp((v - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); }
    /** Progression linéaire (0 à 1) de p dans l'intervalle r = [début, fin]. */
    function rangeProgress(r, p) { return clamp((p - r[0]) / (r[1] - r[0]), 0, 1); }
    /** Progression douce (0 à 1) de p dans l'intervalle r. */
    function ease(r, p) { return smoothstep(r[0], r[1], p); }
    // 0 → 1 → 0 : entrée, maintien, sortie
    function segmentInOut(inR, outR, p) { return ease(inR, p) * (1 - ease(outR, p)); }
    /** Arrondi à 4 décimales (évite des valeurs CSS interminables). */
    function r4(v) { return Math.round(v * 10000) / 10000; }

    // #endregion

    // #region 3. Lecture des données serveur (le DOM déjà rendu est la source)
    /** Texte d'un élément, espaces normalisés (chaîne vide si l'élément n'existe pas). */
    function text(el) { return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; }

    /** Lit dans la page déjà rendue par le serveur : titre, statistiques, filtres, cartes, logo et liens du menu. */
    function readData() {
        var srcMain = document.querySelector('main.main');
        var hero = document.querySelector('.hero');
        var data = { srcMain: srcMain };
        data.eyebrow = text(document.querySelector('.hero .eyebrow'));
        data.title = text(document.querySelector('.hero h1'));
        data.lede = text(document.querySelector('.hero > p'));
        data.stats = [].map.call(document.querySelectorAll('.hero .stats .stat'), function (s) {
            return { value: text(s.querySelector('strong')), label: text(s.querySelector('span')) };
        });
        data.chips = [].map.call(document.querySelectorAll('#chips .chip'), function (c) {
            var label = '';
            for (var n = c.firstChild; n; n = n.nextSibling) { if (n.nodeType === 3 && n.textContent.trim()) { label = n.textContent.trim(); break; } }
            return { filter: c.dataset.filter, label: label, count: text(c.querySelector('span')) };
        });
        data.cards = [].slice.call(document.querySelectorAll('#projects .card'));
        var logo = document.querySelector('.brand-logo');
        data.logo = logo ? logo.getAttribute('src') : BASE + '../images/logo.png';
        // lien de compte : celui que le serveur affiche dans l'en-tête (Se connecter, ou l'état connecté)
        var brand = document.querySelector('.site-header .brand');
        var homeHref = brand ? brand.getAttribute('href') : '/';
        var links = [].slice.call(document.querySelectorAll('.site-header .nav-links a'));
        var acct = links.filter(function (a) { return /connexion/.test(a.getAttribute('href')); })[0] ||
                   links.filter(function (a) { return !/securite/.test(a.getAttribute('href')) && a.getAttribute('href') !== homeHref; })[0];
        data.account = acct ? { href: acct.getAttribute('href'), label: text(acct) } : null;
        var sec = links.filter(function (a) { return /securite/.test(a.getAttribute('href')); })[0];
        data.securityHref = sec ? sec.getAttribute('href') : homeHref.replace(/\/?$/, '/') + 'securite';
        data.hero = hero;
        return data;
    }

    // #endregion

    // #region 4. Construction du DOM (tout le contenu textuel est en HTML sémantique)
    /** Crée un élément HTML : balise, classe CSS, attributs et enfants (textes ou éléments). */
    function el(tag, cls, attrs, children) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (attrs) { Object.keys(attrs).forEach(function (k) { if (attrs[k] !== null && attrs[k] !== undefined) { e.setAttribute(k, attrs[k]); } }); }
        (children || []).forEach(function (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
        return e;
    }
    // titre en mots séparés : le texte reste un seul <h1> lisible, les mots entrent l'un après l'autre
    function titleEl(str) {
        var h1 = el('h1', 'cin-title');
        str.split(' ').forEach(function (w, i, a) {
            h1.appendChild(el('span', 'cin-word', { style: '--i:' + i }, [w]));
            if (i < a.length - 1) { h1.appendChild(document.createTextNode(' ')); }
        });
        return h1;
    }
    /** Crée une image de décor (calque SVG de la scène). */
    function layerImg(cls, file, w, h) {
        var img = el('img', 'cin-layer ' + cls, { src: LAYERS + file, alt: '', width: w, height: h, decoding: 'async', draggable: 'false' });
        return img;
    }

    /** Construit toute la scène (décor, borne, panneaux, catalogue) et retourne les éléments utiles au rendu. */
    function build(data) {
        var section = el('section', 'cinematic-scroll', { 'aria-label': 'Arsenal — présentation et catalogue' });
        var stage = el('div', 'cinema-stage');

        /* monde */
        var world = el('div', 'cin-world', { 'aria-hidden': 'true' });
        var depth = el('div', 'cin-depth');
        var cab = el('div', 'cin-cab');
        var cabBody = el('img', 'cab-body', { src: LAYERS + '30-cabinet.svg', alt: '', width: 1000, height: 1400, decoding: 'async', draggable: 'false' });
        var cabScr = el('img', 'cab-screen', { src: LAYERS + '31-screen.svg', alt: '', width: 560, height: 450, decoding: 'async', draggable: 'false' });
        cab.appendChild(el('div', 'cab-glow'));
        cab.appendChild(cabBody); cab.appendChild(cabScr);
        cab.appendChild(el('div', 'cab-flicker', null, [el('i')]));
        var doorL = el('img', 'cin-layer l-doorL', { src: LAYERS + '40-door-left.svg', alt: '', width: 600, height: 1044, decoding: 'async', draggable: 'false' });
        var doorR = el('img', 'cin-layer l-doorR', { src: LAYERS + '41-door-right.svg', alt: '', width: 600, height: 1044, decoding: 'async', draggable: 'false' });
        depth.appendChild(layerImg('l-bg', '00-hangar-bg.svg', 1856, 1044));
        depth.appendChild(layerImg('l-far', '10-far-rows.svg', 1856, 1044));
        depth.appendChild(layerImg('l-mid', '20-midground.svg', 1856, 1044));
        depth.appendChild(cab); depth.appendChild(doorL); depth.appendChild(doorR);
        world.appendChild(depth);
        var tint = el('div', 'cin-tint');
        var scrim = el('div', 'cin-scrim');
        var vignette = el('img', 'cin-layer cin-vignette', { src: LAYERS + '50-vignette.svg', alt: '', width: 1856, height: 1044, decoding: 'async' });
        vignette.className = 'cin-vignette';
        world.appendChild(tint); world.appendChild(scrim); world.appendChild(vignette);
        var motes = el('div', 'cin-motes');
        for (var m = 0; m < CONFIG.motes; m++) {            // pseudo-aléatoire déterministe : même ciel à chaque chargement
            var rx = ((m * 7919) % 100) / 100, ry = ((m * 104729) % 100) / 100, rs = ((m * 1299709) % 100) / 100;
            motes.appendChild(el('i', null, { style: '--x:' + (rx * 100).toFixed(1) + '%;--y:' + (20 + ry * 75).toFixed(1) + '%;--s:' + (1.5 + rs * 2.5).toFixed(1) + 'px;--d:' + (9 + rs * 11).toFixed(1) + 's;--w:' + (-(rx * 14)).toFixed(1) + 's;--dx:' + (((m % 5) - 2) * 14) + 'px' }));
        }
        world.appendChild(el('div', 'cin-flash'));
        world.appendChild(motes);
        world.appendChild(el('div', 'cin-grain'));
        world.appendChild(el('div', 'cin-bars'));
        stage.appendChild(world);

        /* intro (hero) */
        var statEls = data.stats.map(function (s) {
            var strong = el('strong', null, { 'aria-hidden': 'true' }, [s.value]);
            strong.setAttribute('data-final', s.value);
            return el('div', 'stat', { role: 'group', 'aria-label': s.value + ' ' + s.label }, [strong, el('span', null, { 'aria-hidden': 'true' }, [s.label])]);
        });
        var seeGames = el('a', 'btn btn-primary btn-lg', { href: '#cin-rail' }, [CONFIG.copy.seeGames]);
        var cta = el('div', 'cin-cta', null, [seeGames]);
        if (data.account) { cta.appendChild(el('a', 'btn btn-lg', { href: data.account.href }, [data.account.label])); }
        var skip = el('a', 'cin-skip', { href: '#cin-rail' }, [CONFIG.copy.skip]);
        cta.appendChild(skip);
        var intro = el('div', 'cin-intro', null, [el('div', 'cin-intro-inner', null, [
            el('img', 'cin-logo', { src: data.logo, alt: '', width: 64, height: 64 }),
            el('span', 'eyebrow', null, [data.eyebrow]),
            titleEl(CONFIG.copy.title || data.title),
            el('p', 'cin-lede', null, [data.lede]),
            el('div', 'stats', null, statEls),
            cta
        ])]);
        stage.appendChild(intro);

        /* panneau A */
        var playable = data.cards.filter(function (c) { return c.querySelector('a.btn-play'); }).length;
        var panelA = el('section', 'cin-panel cin-a', { 'aria-labelledby': 'cin-a-title' }, [
            el('p', 'cin-kicker', null, ['01 // Jouer']),
            el('h2', null, { id: 'cin-a-title' }, ['Jouer en un clic']),
            el('p', null, null, ['Lance un jeu directement dans ton navigateur : rien à installer, 10 minutes de jeu sans compte. Ou télècharge le .exe.']),
            el('p', 'cin-note', null, [playable + ' jeu' + (playable > 1 ? 'x' : '') + ' sur ' + data.cards.length + ' se lance' + (playable > 1 ? 'nt' : '') + ' dans le navigateur. Un compte gratuit permet de jouer sans limite.'])
        ]);
        stage.appendChild(panelA);

        /* panneau B — reprend la page Sécurité (règle du .exe, VirusTotal) et le bandeau cookies */
        var secLink = el('a', 'btn', { href: data.securityHref }, ['Tester mes réflexes → Sécurité']);
        var panelB = el('section', 'cin-panel cin-b', { 'aria-labelledby': 'cin-b-title' }, [
            el('p', 'cin-kicker', null, ['02 // Sécurité']),
            el('h2', null, { id: 'cin-b-title' }, ['Fait maison, vérifiable']),
            el('ul', null, null, [
                el('li', null, null, ['Un .exe est un programme : une fois lancé, il peut faire tout ce que toi tu peux faire sur l’ordinateur.']),
                el('li', null, null, ['La règle : ne télécharge un .exe que depuis le site officiel du logiciel. En cas de doute, passe le fichier sur virustotal.com avant de l’ouvrir.']),
                el('li', null, null, ['Ce site n’utilise que des cookies techniques indispensables : ni suivi, ni publicité. Mentions légales, confidentialité (RGPD) et cookies sont en pied de page.'])
            ]),
            secLink
        ]);
        stage.appendChild(panelB);

        /* panneau de fin : le stage se termine sur ce titre, la grille des jeux suit en flux normal */
        var n = data.cards.length;
        var panelC = el('section', 'cin-panel cin-c', { 'aria-labelledby': 'cin-c-title' }, [
            el('p', 'cin-kicker', null, ['03 // Arsenal']),
            el('h2', null, { id: 'cin-c-title' }, ['Choisis ton jeu']),
            el('p', null, null, [n + (n > 1 ? ' jeux et applications' : ' jeu') + ', \u00e0 lancer dans le navigateur ou \u00e0 t\u00e9l\u00e9charger.']),
            el('a', 'btn btn-primary btn-lg', { href: '#cin-rail' }, ['Voir la liste \u2193'])
        ]);
        stage.appendChild(panelC);

        /* catalogue : filtres, recherche et grille de 3 colonnes (cartes clonées depuis le HTML du serveur) */
        var catalog = el('section', 'cin-catalog', { id: 'cin-rail', tabindex: '-1', 'aria-labelledby': 'cin-cat-title' });
        var chipEls = data.chips.map(function (c) {
            return el('button', 'chip' + (c.filter === 'all' ? ' is-active' : ''), { type: 'button', 'data-filter': c.filter, 'aria-pressed': c.filter === 'all' ? 'true' : 'false' }, [c.label, el('span', null, null, [c.count])]);
        });
        var chips = el('div', 'chips', { role: 'group', 'aria-label': 'Filtrer par cat\u00e9gorie' }, chipEls);
        var search = el('input', null, { type: 'search', placeholder: 'Rechercher\u2026', 'aria-label': 'Rechercher un projet' });
        var count = el('span', 'cin-count', { 'aria-live': 'polite', 'aria-atomic': 'true' });
        var tools = el('div', 'cin-tools', null, [chips, el('div', 'cin-search', null, [search]), count]);
        var head = el('div', 'cin-cat-head', null, [el('h2', 'cin-cat-title', { id: 'cin-cat-title' }, ['Arsenal // Choisis ton jeu']), tools]);

        var grid = el('ul', 'cin-grid', { 'aria-label': 'Jeux et projets' });
        var items = data.cards.map(function (card) {
            var clone = card.cloneNode(true);
            var li = el('li', 'cin-item');
            li.appendChild(clone);
            li.setAttribute('data-category', card.dataset.category || '');
            li._search = (card.dataset.search || '').toLowerCase();
            clone.removeAttribute('data-search');
            grid.appendChild(li);
            return li;
        });
        var empty = el('div', 'cin-empty', null, [el('p'), el('button', 'btn btn-sm', { type: 'button' }, ['Voir tout'])]);
        empty.hidden = true;
        catalog.appendChild(head); catalog.appendChild(grid); catalog.appendChild(empty);

        section.appendChild(stage);
        var main = el('main', 'cinema-main', { id: 'main' });
        main.appendChild(section);
        main.appendChild(catalog);

        return {
            main: main, section: section, stage: stage, world: world, depth: depth, cab: cab, cabScr: cabScr,
            intro: intro, panelA: panelA, panelB: panelB, panelC: panelC, catalog: catalog, grid: grid,
            items: items, chips: chipEls, search: search, count: count,
            empty: empty, seeGames: seeGames, skip: skip, statEls: statEls, images: [].slice.call(world.querySelectorAll('img')).concat([cabBody, cabScr])
        };
    }

    // #endregion

    // #region 5. État, mesure (géométrie en cache), interactions
    var data = readData();
    if (!data.srcMain || !data.cards.length || (!STATIC && data.srcMain.querySelector('.alert'))) { bail(); return; }   // pas de catalogue, ou un message du serveur à lire
    var ui = build(data);
    var mqMobile = window.matchMedia(CONFIG.mobileQuery);
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    var header = document.querySelector('.site-header');
    var state = { target: 0, play: 0, mx: 0, my: 0, tmx: 0, tmy: 0, raf: 0, last: 0, visible: true, live: {}, snap: false };
    var geo = { top: 0, travel: 1, stageW: 1, stageH: 1, hdr: 70, isMobile: false, cab: null };

    // lite : appareils modestes → teinte/opacité plutôt que flou
    if ((navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4) || (navigator.deviceMemory && navigator.deviceMemory <= 2)) { html.classList.add('cin-lite'); }

    /** Mesure la géométrie de la scène (tailles, distance de défilement, position de la borne) et la publie en variables CSS. */
    function measure() {
        geo.isMobile = mqMobile.matches;
        var run = geo.isMobile ? CONFIG.run.mobile : CONFIG.run.desktop;
        geo.hdr = header ? header.offsetHeight : 70;
        ui.section.style.setProperty('--run', run + 'px');
        ui.section.style.setProperty('--hdr', geo.hdr + 'px');
        geo.stageW = ui.stage.clientWidth;
        geo.stageH = ui.stage.clientHeight;
        geo.top = ui.section.getBoundingClientRect().top + window.pageYOffset;
        geo.travel = Math.max(1, ui.section.offsetHeight - ui.stage.offsetHeight);

        // Compensation de coordonnées : la borne est positionnée par CSS, on lit sa boîte de mise en page (hors transform)
        // pour savoir où translater l'écran afin qu'il finisse centré dans la zone libre sous l'en-tête.
        var cw = ui.cab.offsetWidth, ch = ui.cab.offsetHeight;
        var cx = ui.cab.offsetLeft + cw / 2, cy0 = ui.cab.offsetTop + ch * 0.3393;
        var scrW0 = cw * 0.56;
        var freeTop = geo.hdr, freeH = geo.stageH - freeTop;
        var targetCy = freeTop + freeH * 0.5;
        var targetW = Math.min(geo.stageW - 32, 660, (freeH * 0.62) * (560 / 450));
        geo.cab = {
            dxStart: geo.isMobile ? 0 : geo.stageW * 0.15,
            dyEnd: targetCy - cy0,
            sEnd: targetW / scrW0,
            scrW: targetW, scrH: targetW * 450 / 560, cx: geo.stageW / 2, cy: targetCy, cxNat: cx
        };
        ui.stage.style.setProperty('--scr-w', r4(geo.cab.scrW) + 'px');
        ui.stage.style.setProperty('--scr-h', r4(geo.cab.scrH) + 'px');
        ui.stage.style.setProperty('--scr-cy', r4(geo.cab.cy) + 'px');
        ui.stage.style.setProperty('--hdr', geo.hdr + 'px');
    }

    /** Progression cible (0 à 1) selon la position de défilement de la page. */
    function readTarget() { return clamp((window.pageYOffset - geo.top) / geo.travel, 0, 1); }

    /** Demande un rendu à la prochaine image (une seule demande à la fois, rien si la scène est hors écran). */
    function request() {
        if (state.raf || !state.visible) { return; }
        state.raf = window.requestAnimationFrame(frame);
    }

    // #endregion

    // #region 6. Calcul de la timeline : fonction pure de p → variables CSS (réversible, déterministe)
    function compute(p) {
        var push = ease(T.push, p);
        var pano = ease(T.pano, p);
        var doors = ease(T.doors, p);
        var cabOut = ease(T.cabOut, p);
        var S = CONFIG.scale, c = geo.cab;
        var introE = rangeProgress(T.introOut, p);
        var tintB = Math.max(segmentInOut(T.blurIn, T.blurOut, p), 0);
        var railE = ease(T.railIn, p);
        var cabS = Math.pow(c.sEnd, push) * (1 + CONFIG.cabEndExtra * cabOut);
        var pan = -geo.stageW * CONFIG.panPx * pano;
        var tint = Math.max(CONFIG.baseTint * (1 - tintB) + CONFIG.tintMax * tintB, CONFIG.baseTint + (CONFIG.railTint - CONFIG.baseTint) * railE);
        var a = segmentInOut(T.panelAIn, T.panelAOut, p);
        var b = segmentInOut(T.panelBIn, T.panelBOut, p);
        var onE = ease(T.cabOn, p);
        var dof = ease([0.12, 0.2], p) * (1 - ease([0.38, 0.46], p));
        var flash = segmentInOut([0.34, 0.39], [0.39, 0.46], p);
        return {
            '--dof': r4(CONFIG.dofPx * dof) + 'px',
            '--flash': r4(CONFIG.flashMax * flash),
            '--motes': r4(1 - ease([0.74, 0.92], p)),
            '--intro': r4(1 - ease(T.introOut, p)),
            '--intro-y': r4(-26 * introE) + 'px',
            '--scrim': r4(1 - ease([T.introOut[0], T.introOut[1] + 0.06], p)),
            '--s-bg': r4(1 + S.bg[0] * push + S.bg[1] * pano),
            '--s-far': r4(1 + S.far[0] * push + S.far[1] * pano),
            '--s-mid': r4(1 + S.mid[0] * push + S.mid[1] * pano),
            '--pan': r4(pan) + 'px',
            '--door': r4(lerp(CONFIG.doorStart, 1, doors)),
            '--door-s': r4(1 + 0.35 * push),
            '--cab-dx': r4(lerp(c.dxStart, 0, push)) + 'px',
            '--cab-dy': r4(lerp(0, c.dyEnd, push)) + 'px',
            '--cab-s': r4(cabS),
            '--cab-o': r4(1 - ease([T.cabOut[0] + 0.02, T.cabOut[1]], p)),
            '--scr-on': r4(lerp(0.4, 1, onE)),
            '--glow': r4(0.35 + 0.5 * onE + 0.15 * Math.sin(Math.PI * rangeProgress(T.cabOn, p))),   // petite surcharge à l'allumage
            '--a': r4(a), '--a-y': r4(lerp(18, 0, ease(T.panelAIn, p)) - 14 * ease(T.panelAOut, p)) + 'px',
            '--b': r4(b), '--b-y': r4(lerp(18, 0, ease(T.panelBIn, p)) - 14 * ease(T.panelBOut, p)) + 'px',
            '--tint-a': r4(tint),
            '--blur': r4((CONFIG.blurPx * tintB + CONFIG.railBlurPx * railE) * (geo.isMobile ? 0.6 : 1)) + 'px',
            '--rail': r4(railE), '--c': r4(railE), '--c-y': r4(lerp(18, 0, railE)) + 'px',
            _p: p, _dof: dof, _blur: Math.max(tintB, railE), _a: a, _b: b, _intro: 1 - ease(T.introOut, p), _rail: railE, _cab: 1 - cabOut
        };
    }

    // bascule is-live / inert seulement au franchissement d'un seuil
    function setLive(key, node, on, useInert) {
        if (state.live[key] === on) { return; }
        state.live[key] = on;
        node.classList.toggle('is-live', on);
        if (useInert) { if (on) { node.removeAttribute('inert'); node.removeAttribute('aria-hidden'); } else { node.setAttribute('inert', ''); node.setAttribute('aria-hidden', 'true'); } }
    }

    /** Applique la progression p : écrit les variables CSS et active / désactive les éléments qui apparaissent ou disparaissent. */
    function render(p) {
        var v = compute(p), s = ui.stage.style;
        for (var k in v) { if (k.charAt(0) === '-') { s.setProperty(k, v[k]); } }
        ui.stage.style.setProperty('--mx', r4(state.mx));
        ui.stage.style.setProperty('--my', r4(state.my));
        var blurred = v._blur > 0.01 && !html.classList.contains('cin-lite');
        if (state.live.blur !== blurred) { state.live.blur = blurred; ui.depth.classList.toggle('is-blurred', blurred); }
        var dofOn = v._dof > 0.02 && !html.classList.contains('cin-lite');
        if (state.live.dof !== dofOn) { state.live.dof = dofOn; ui.depth.classList.toggle('is-dof', dofOn); }
        setLive('intro', ui.intro, v._intro > 0.02, true);
        setLive('a', ui.panelA, v._a > 0.02, true);
        setLive('b', ui.panelB, v._b > 0.02, true);
        setLive('c', ui.panelC, v._rail > 0.02, true);
        // la borne disparue ne coûte plus rien à peindre
        var cabVisible = v._cab > 0.001;
        if (state.live.cab !== cabVisible) { state.live.cab = cabVisible; ui.cab.style.visibility = cabVisible ? '' : 'hidden'; }
    }

    // #endregion

    // #region 7. Boucle de rendu : arrêt dès convergence, rien hors écran
    /** Une image de la boucle de rendu : rattrape la cible en douceur, puis s'arrête dès qu'elle est atteinte. */
    function frame(now) {
        state.raf = 0;
        var dt = state.last ? Math.min(64, now - state.last) : 16.67;
        state.last = now;
        var k = 1 - Math.pow(1 - CONFIG.smoothing, dt / 16.67);
        var kp = 1 - Math.pow(1 - CONFIG.pointerSmoothing, dt / 16.67);
        state.target = readTarget();
        if (state.snap) { state.play = state.target; state.snap = false; }
        else { state.play += (state.target - state.play) * k; if (Math.abs(state.target - state.play) < 0.0004) { state.play = state.target; } }
        state.mx += (state.tmx - state.mx) * kp; state.my += (state.tmy - state.my) * kp;
        if (Math.abs(state.tmx - state.mx) < 0.002) { state.mx = state.tmx; }
        if (Math.abs(state.tmy - state.my) < 0.002) { state.my = state.tmy; }
        render(state.play);
        if (state.play !== state.target || state.mx !== state.tmx || state.my !== state.tmy) { request(); } else {
            state.last = 0;
        }
    }

    /** Défilement de la page : demande un rendu. */
    function onScroll() { request(); }
    /** Redimensionnement : remesure la scène (ou revient à la page classique si l'écran devient trop court). */
    function onResize() {
        // viewport devenu trop court pour la scène (zoom navigateur) : on rend la main à la page classique
        if (window.innerHeight < CONFIG.minHeight) { window.location.reload(); return; }
        window.requestAnimationFrame(function () { measure(); state.snap = true; request(); });
    }
    /** Mouvement de la souris : décale légèrement les calques (effet de profondeur). */
    function onPointer(e) {
        if (!CONFIG.parallax || e.pointerType !== 'mouse' || !finePointer.matches || geo.isMobile) { return; }
        state.tmx = clamp((e.clientX / window.innerWidth) * 2 - 1, -1, 1);
        state.tmy = clamp((e.clientY / window.innerHeight) * 2 - 1, -1, 1);
        request();
    }

    /** Défile jusqu'à la progression p de la scène (sans animation si instant est vrai). */
    function scrollToP(p, instant) {
        var y = Math.round(geo.top + geo.travel * p);
        if (instant) { state.snap = true; }
        window.scrollTo(0, y);
        request();
    }

    // #endregion

    // #region 8. Catalogue : filtres et recherche (la grille garde les cartes du serveur)
    var filter = 'all';

    /** Affiche seulement les cartes de la catégorie choisie contenant le texte tapé, et met à jour le compteur. */
    function applyFilter() {
        var q = ui.search.value.trim().toLowerCase();
        var n = 0;
        ui.items.forEach(function (li) {
            var ok = (filter === 'all' || li.getAttribute('data-category') === filter) && li._search.indexOf(q) !== -1;
            li.hidden = !ok;
            if (ok) { n++; }
        });
        ui.count.textContent = n + ' r\u00e9sultat' + (n > 1 ? 's' : '');
        var none = n === 0;
        ui.empty.hidden = !none;
        ui.grid.hidden = none;
        if (none) {
            var chip = data.chips.filter(function (c) { return c.filter === filter; })[0];
            ui.empty.firstChild.textContent = q ? 'Rien ne correspond \u00e0 votre recherche.' : 'Aucun r\u00e9sultat dans \u00ab ' + (chip ? chip.label : 'cette cat\u00e9gorie') + ' \u00bb pour le moment.';
            ui.empty.lastChild.hidden = (filter === 'all' && !q);
        }
    }

    /** Choisit une catégorie (bouton actif) puis applique le filtre. */
    function setFilter(value) {
        filter = value;
        ui.chips.forEach(function (c) { var on = c.getAttribute('data-filter') === value; c.classList.toggle('is-active', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        applyFilter();
    }

    /** Branche les boutons de catégorie, la recherche et le bouton « Voir tout ». */
    function bindCatalog() {
        ui.chips.forEach(function (chip) { chip.addEventListener('click', function () { setFilter(chip.getAttribute('data-filter')); }); });
        ui.search.addEventListener('input', applyFilter);
        ui.empty.lastChild.addEventListener('click', function () { ui.search.value = ''; setFilter('all'); });
    }

    // #endregion

    // #region 9. Compteurs animés (sobres), liens d'accès direct, démarrage
    /** Fait compter les statistiques de 0 jusqu'à leur valeur (la valeur du serveur est toujours rétablie à la fin). */
    function animateStats() {
        var t0 = 0, dur = 900;
        var targets = ui.statEls.map(function (s) { var n = s.firstChild; return { n: n, v: parseInt(n.getAttribute('data-final').replace(/\D/g, ''), 10), raw: n.getAttribute('data-final') }; });
        if (targets.some(function (t) { return isNaN(t.v) || String(t.v) !== t.raw.trim(); })) { return; }   // format inattendu : on laisse la valeur serveur
        function tick(now) {
            if (!t0) { t0 = now; }
            var e = clamp((now - t0) / dur, 0, 1), k = 1 - Math.pow(1 - e, 3);
            targets.forEach(function (t) { t.n.textContent = String(Math.round(t.v * k)); });
            if (e < 1) { window.requestAnimationFrame(tick); }
        }
        targets.forEach(function (t) { t.n.textContent = '0'; });
        window.requestAnimationFrame(tick);
        // si le navigateur suspend les frames (onglet en arrière-plan), la valeur serveur finit toujours affichée
        window.setTimeout(function () { targets.forEach(function (t) { t.n.textContent = t.raw; }); }, dur + 250);
    }

    // « Voir les jeux » / « Passer l'animation » : amène directement au catalogue, sous la scène
    function goToRail(instant) {
        var top = ui.catalog.getBoundingClientRect().top + window.pageYOffset - geo.hdr - 12;
        if (instant) { state.snap = true; }
        window.scrollTo(0, Math.max(0, Math.round(top)));
        request();
        window.setTimeout(function () { try { ui.catalog.focus({ preventScroll: true }); } catch (e) { ui.catalog.focus(); } }, 60);
    }

    // Version fixe : la scène du premier écran (décor + titre + « Voir les jeux ») sans animation ni scroll virtuel,
    // puis le catalogue classique tel quel (filtres, recherche, grille, messages du serveur).
    function startStatic() {
        data.srcMain.setAttribute('data-cinema-static', '');
        data.srcMain.parentNode.insertBefore(ui.main, data.srcMain);
        ui.section.classList.add('is-static');
        ui.catalog.parentNode.removeChild(ui.catalog);   // le catalogue classique du serveur suit déjà
        ui.skip.parentNode.removeChild(ui.skip);
        ui.seeGames.setAttribute('href', '#chips');          // ancre native vers filtres + grille, sans défilement animé
        html.classList.add('cinema-ready');
        window.__cinemaReady = true;
        measure();
        render(0);
        window.addEventListener('resize', function () { window.requestAnimationFrame(function () { measure(); render(0); }); }, { passive: true });
        var decodes = ui.images.map(function (img) { return img.decode ? img.decode().catch(function () {}) : Promise.resolve(); });
        Promise.race([Promise.all(decodes), new Promise(function (r) { window.setTimeout(r, 1800); })]).then(function () { html.classList.add('cinema-world-ready'); measure(); render(0); });
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { measure(); render(0); }); }
    }

    /** Démarrage : installe la scène à la place du contenu serveur, branche les événements et attend le décodage des images. */
    function start() {
        if (STATIC) { startStatic(); return; }
        // le contenu serveur reste dans le DOM (SEO, repli) mais n'est plus affiché
        data.srcMain.setAttribute('data-cinema-source', '');
        data.srcMain.setAttribute('aria-hidden', 'true');
        data.srcMain.parentNode.insertBefore(ui.main, data.srcMain);
        html.classList.add('cinema-ready');
        window.__cinemaReady = true;

        bindCatalog();
        applyFilter();
        animateStats();
        measure();
        state.target = state.play = readTarget();
        render(state.play);

        ui.seeGames.addEventListener('click', function (e) { e.preventDefault(); goToRail(false); });
        ui.skip.addEventListener('click', function (e) { e.preventDefault(); goToRail(true); });
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onResize, { passive: true });
        window.addEventListener('pointermove', onPointer, { passive: true });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) { state.visible = entries[0].isIntersecting; ui.section.classList.toggle('is-paused', !state.visible); if (state.visible) { state.last = 0; request(); } }).observe(ui.section);
        }
        // barrière de décodage : on révèle le monde seulement quand ses images sont prêtes (texte déjà lisible)
        var decodes = ui.images.map(function (img) { return img.decode ? img.decode().catch(function () {}) : Promise.resolve(); });
        var timeout = new Promise(function (r) { window.setTimeout(r, 1800); });
        Promise.race([Promise.all(decodes), timeout]).then(function () { html.classList.add('cinema-world-ready'); measure(); render(state.play); });
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { measure(); render(state.play); }); }
        // l'ancre #cin-rail (ou une URL partagée) doit mener au catalogue
        if (location.hash === '#cin-rail') { goToRail(true); }

        // exposé pour la QA : window.cinema.go(0.58)
        window.cinema = { go: function (p) { scrollToP(p, true); }, compute: compute, config: CONFIG, state: state, geo: geo };
    }

    // #endregion

    // Démarrage dès que le document est lisible
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
