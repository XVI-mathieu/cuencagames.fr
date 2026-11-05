/**
 * Page « Sécurité » (app/Views/home/securite.php) : simulation pédagogique d'un faux site de « jeux gratuits ».
 * IMPORTANT : rien n'est réellement collecté, envoyé ni téléchargé. Les informations affichées sont lues côté visiteur
 * (comme tout site peut le faire) et restent sur son écran.
 *
 * Déroulement : appât (cookies, téléchargement) -> faux formulaire de connexion -> fausse installation
 * -> « scan » des données de l'appareil -> explications.
 */
(function () {
    'use strict';

    // region Outils et état

    /** Raccourci : élément de la page par son identifiant. */
    var $ = function (id) { return document.getElementById(id); };
    var nav = navigator;
    var ua = nav.userAgent;
    var cookiesOk = false;   // le visiteur a-t-il cliqué sur « Tout accepter » ?
    var cookieTime = null;   // et à quelle heure

    /** Protège un texte saisi avant de l'écrire dans le HTML (le visiteur ne doit pas pouvoir injecter de code). */
    function echapper(texte) {
        return String(texte).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function afficher(id) { $(id).hidden = false; }
    function cacher(id) { $(id).hidden = true; }

    // endregion

    // region Collecte réelle des informations de l'appareil (côté visiteur)

    /** Devine le système d'exploitation à partir du « user agent » du navigateur. */
    function systeme() {
        if (/Windows NT 10/.test(ua)) { return 'Windows 10/11'; }
        if (/Windows/.test(ua)) { return 'Windows'; }
        if (/iPhone|iPad|iPod/.test(ua)) { return 'iOS'; }
        if (/Android[ /]([\d.]+)/.test(ua)) { return 'Android ' + RegExp.$1; }
        if (/Mac OS X ([\d_]+)/.test(ua)) { return 'macOS ' + RegExp.$1.replace(/_/g, '.'); }
        if (/Linux/.test(ua)) { return 'Linux'; }
        return 'inconnu';
    }

    /** Devine le navigateur et sa version. */
    function navigateur() {
        if (/Edg\/([\d]+)/.test(ua)) { return 'Edge ' + RegExp.$1; }
        if (/OPR\//.test(ua)) { return 'Opera'; }
        if (/Chrome\/([\d]+)/.test(ua)) { return 'Chrome ' + RegExp.$1; }
        if (/Firefox\/([\d]+)/.test(ua)) { return 'Firefox ' + RegExp.$1; }
        if (/Version\/([\d]+).*Safari/.test(ua)) { return 'Safari ' + RegExp.$1; }
        return 'inconnu';
    }

    // Fuseau horaire (et décalage UTC) : donne déjà une idée de la région du visiteur
    var fuseau = 'inconnu';
    var decalage = '';
    try {
        fuseau = Intl.DateTimeFormat().resolvedOptions().timeZone;
        var heures = -new Date().getTimezoneOffset() / 60;
        decalage = ' (UTC' + (heures >= 0 ? '+' : '') + heures + ')';
    } catch (erreur) { /* fuseau non disponible */ }

    var connexion = nav.connection || nav.mozConnection || nav.webkitConnection;

    /** Nom de la carte graphique, si le navigateur le permet (WebGL). */
    function carteGraphique() {
        try {
            var gl = document.createElement('canvas').getContext('webgl');
            if (!gl) { return null; }
            var info = gl.getExtension('WEBGL_debug_renderer_info');
            var nom = info ? gl.getParameter(info.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
            return nom ? String(nom).slice(0, 42) : null;
        } catch (erreur) { return null; }
    }

    /** « Empreinte » : code court calculé à partir des caractéristiques de l'appareil, presque unique à chaque visiteur. */
    function empreinte() {
        var brut = [ua, nav.language, (nav.languages || []).join(','), screen.width + 'x' + screen.height,
            screen.colorDepth, window.devicePixelRatio, fuseau, nav.hardwareConcurrency, nav.deviceMemory, nav.maxTouchPoints].join('|');
        var hachage = 0;
        for (var i = 0; i < brut.length; i++) { hachage = ((hachage << 5) - hachage + brut.charCodeAt(i)) | 0; }
        return 'GZ-' + (hachage >>> 0).toString(16).toUpperCase().padStart(8, '0');
    }

    /** Ville approximative déduite du fuseau horaire (« Europe/Paris » -> « ≈ Paris »). */
    function lieuApproximatif() {
        if (!fuseau || fuseau === 'inconnu') { return null; }
        var morceaux = fuseau.split('/');
        return '≈ ' + (morceaux[1] || morceaux[0]).replace(/_/g, ' ');
    }

    /**
     * Liste des informations affichées : [libellé, valeur, sensibilité].
     * Sensibilité : 1 = peu sensible, 2 = identifiant, 3 = très sensible.
     */
    function lignesDonnees() {
        var l = [];
        l.push(['Profondeur couleur', screen.colorDepth + ' bits', 1]);
        l.push(['Orientation', (screen.orientation && screen.orientation.type) || 'n/c', 1]);
        l.push(['Tactile', (nav.maxTouchPoints > 0 || 'ontouchstart' in window) ? 'oui, ' + (nav.maxTouchPoints || '?') + ' pts' : 'non', 1]);
        l.push(['Cœurs CPU', (nav.hardwareConcurrency || '?') + '', 1]);
        l.push(['Mémoire', nav.deviceMemory ? nav.deviceMemory + ' Go' : 'n/c', 1]);
        l.push(['Modules (plugins)', (nav.plugins ? nav.plugins.length : '?') + '', 1]);
        l.push(['Cookies activés', nav.cookieEnabled ? 'oui' : 'non', 1]);
        l.push(['Ne pas suivre', nav.doNotTrack === '1' ? 'activé' : 'désactivé', 1]);
        l.push(['Système', systeme(), 2]);
        l.push(['Appareil', (nav.userAgentData && nav.userAgentData.platform) || nav.platform || 'inconnu', 2]);
        l.push(['Navigateur', navigateur(), 2]);
        l.push(['Carte graphique', carteGraphique(), 2]);
        l.push(['Résolution', screen.width + '×' + screen.height + ' (' + (window.devicePixelRatio || 1) + '×)', 2]);
        l.push(['Langue', nav.language || '?', 2]);
        l.push(['Langues préférées', (nav.languages || []).join(', ') || 'n/c', 2]);
        l.push(['Connexion', connexion && connexion.effectiveType ? connexion.effectiveType.toUpperCase() : 'n/c', 2]);
        l.push(['Débit estimé', connexion && connexion.downlink ? connexion.downlink + ' Mb/s' : 'n/c', 2]);
        l.push(['Provenance', document.referrer ? document.referrer.slice(0, 40) : 'accès direct', 2]);
        l.push(['Heure locale', new Date().toLocaleTimeString('fr-FR'), 2]);
        l.push(['Fuseau horaire', fuseau + decalage, 3]);
        l.push(['Localisation ≈', lieuApproximatif(), 3]);
        l.push(['Cookies', cookiesOk ? 'ACCEPTÉS à ' + cookieTime.toLocaleTimeString('fr-FR') : 'refusés', 3]);
        l.sort(function (a, b) { return a[2] - b[2]; });
        return l;
    }

    // endregion

    // region Affichage du « terminal » et services de géolocalisation

    /** Zone d'affichage des lignes. */
    function terminal() { return $('secTerm'); }

    /** Ajoute une ligne « libellé : valeur » au terminal et retourne l'élément de la valeur (pour la compléter plus tard). */
    function ajouterLigne(cle, valeur, sensibilite) {
        var absent = (valeur === null || valeur === undefined || valeur === '');
        var ligne = document.createElement('div');
        ligne.className = 'ln';
        ligne.innerHTML = '<span class="k"><i class="sec-dot s' + (sensibilite || 1) + '"></i>' + cle + '</span>'
            + '<span class="v' + (sensibilite >= 3 ? ' hot' : '') + '">' + (absent ? 'non disponible' : valeur) + '</span>';
        terminal().appendChild(ligne);
        return ligne.querySelector('.v');
    }

    /**
     * Demande l'adresse IP publique et la ville à des services externes (essayés l'un après l'autre jusqu'au premier qui répond).
     * Le visiteur n'envoie rien à ce site : c'est son navigateur qui interroge ces services.
     */
    function recupererIP(elIp, elVille, elFai) {
        var formater = function (d, pays) {
            return (d.city || '?') + (d.region ? ', ' + d.region : '') + (pays ? ' (' + pays + ')' : '');
        };
        var services = [
            function () {
                return fetch('https://ipapi.co/json/').then(function (r) { if (!r.ok) { throw 0; } return r.json(); })
                    .then(function (d) { return { ip: d.ip, ville: formater(d, d.country_name), fai: d.org }; });
            },
            function () {
                return fetch('https://ipwho.is/').then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.success === false) { throw 0; }
                        return { ip: d.ip, ville: formater(d, d.country), fai: d.connection && (d.connection.isp || d.connection.org) };
                    });
            },
            function () {
                return fetch('https://get.geojs.io/v1/ip/geo.json').then(function (r) { return r.json(); })
                    .then(function (d) { return { ip: d.ip, ville: formater(d, d.country), fai: d.organization_name }; });
            },
            function () {
                return fetch('https://api.ipify.org?format=json').then(function (r) { return r.json(); })
                    .then(function (d) { return { ip: d.ip, ville: 'n/c (IP seule)', fai: 'n/c' }; });
            }
        ];
        var essayer = function (i) {
            if (i >= services.length) {
                elIp.textContent = 'bloquée (HTTP local ?)';
                elVille.textContent = '—';
                elFai.textContent = '—';
                return;
            }
            services[i]().then(function (d) {
                elIp.textContent = d.ip || 'n/c';
                elVille.textContent = d.ville || 'n/c';
                elFai.textContent = d.fai || 'n/c';
            }).catch(function () { essayer(i + 1); });
        };
        essayer(0);
    }

    /** Demande la position GPS (seulement si le visiteur l'autorise dans son navigateur) et affiche un lien vers la carte. */
    function recupererGPS(elCoord, elCarte) {
        if (!nav.geolocation) {
            elCoord.textContent = 'non supporté';
            elCarte.textContent = '—';
            return;
        }
        nav.geolocation.getCurrentPosition(
            function (pos) {
                var la = pos.coords.latitude.toFixed(5);
                var lo = pos.coords.longitude.toFixed(5);
                elCoord.textContent = la + ', ' + lo + ' (±' + Math.round(pos.coords.accuracy) + ' m)';
                elCarte.innerHTML = '<a href="https://www.openstreetmap.org/?mlat=' + la + '&mlon=' + lo + '#map=17/' + la + '/' + lo
                    + '" target="_blank" rel="noopener" style="color:var(--danger);text-decoration:underline">carte →</a>';
            },
            function () {
                elCoord.textContent = 'refusée / bloquée';
                elCarte.textContent = '— (autorise la localisation)';
            },
            { enableHighAccuracy: true, timeout: 9000, maximumAge: 0 }
        );
    }

    // endregion

    // region Effets (alarme sonore)

    /** Fait retentir une courte alarme (5 bips) avec l'API audio du navigateur ; silencieux si elle est indisponible. */
    function alarme() {
        try {
            var AC = window.AudioContext || window.webkitAudioContext;
            var ctx = new AC();
            var t = ctx.currentTime;
            for (var i = 0; i < 5; i++) {
                var oscillateur = ctx.createOscillator();
                var gain = ctx.createGain();
                oscillateur.type = 'square';
                oscillateur.frequency.value = i % 2 ? 900 : 640;
                oscillateur.connect(gain);
                gain.connect(ctx.destination);
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.16, t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
                oscillateur.start(t);
                oscillateur.stop(t + 0.24);
                t += 0.28;
            }
            setTimeout(function () { try { ctx.close(); } catch (erreur) { /* déjà fermé */ } }, 2200);
        } catch (erreur) { /* audio indisponible */ }
    }

    // endregion

    // region Enchaînement des étapes

    /** Barre de progression factice : avance par à-coups jusqu'à 100 %, puis appelle $fin. */
    function progression(idBarre, idPourcent, fin) {
        var p = 0;
        var minuteur = setInterval(function () {
            p += Math.random() * 16 + 7;
            if (p >= 100) {
                p = 100;
                clearInterval(minuteur);
                $(idBarre).style.width = '100%';
                $(idPourcent).textContent = '100%';
                setTimeout(fin, 500);
            } else {
                $(idBarre).style.width = p + '%';
                $(idPourcent).textContent = Math.floor(p) + '%';
            }
        }, 190);
    }

    /** Étape 4 : lance le « scan » (alerte rouge, vibration, alarme, barre), puis affiche les données. */
    function lancerScan() {
        afficher('secScanStep');
        $('secStage').classList.add('alert');
        try { if (nav.vibrate) { nav.vibrate([250, 90, 250, 90, 500]); } } catch (erreur) { /* pas de vibreur */ }
        alarme();
        progression('secScanBar', 'secScanPct', afficherDonnees);
    }

    /** Affiche toutes les informations lues sur l'appareil, puis déclenche le compte à rebours. */
    function afficherDonnees() {
        var lignes = lignesDonnees();
        terminal().innerHTML = '';
        lignes.forEach(function (l, i) {
            var el = ajouterLigne(l[0], l[1], l[2]);
            el.parentElement.style.animationDelay = (i * 0.05) + 's'; // apparition l'une après l'autre
        });
        if (nav.getBattery) {
            nav.getBattery().then(function (b) {
                ajouterLigne('Batterie', Math.round(b.level * 100) + '%' + (b.charging ? ' ⚡' : ''), 2);
            }).catch(function () { /* batterie indisponible */ });
        }
        ajouterLigne('EMPREINTE UNIQUE', empreinte(), 3);
        var elIp = ajouterLigne('Adresse IP publique', 'récupération…', 3);
        var elVille = ajouterLigne('Ville (via IP)', '…', 3);
        var elFai = ajouterLigne('Opérateur / FAI', '…', 3);
        recupererIP(elIp, elVille, elFai);
        var elGps = ajouterLigne('Position GPS', "demande d'autorisation…", 3);
        var elCarte = ajouterLigne('Carte', '…', 3);
        recupererGPS(elGps, elCarte);

        $('secScanTitle').textContent = '✓ ACCÈS TOTAL — DONNÉES RÉCUPÉRÉES';
        $('secScanTitle').style.color = 'var(--lime)';
        $('secScanSub').textContent = 'en clair, sans aucun piratage';

        setTimeout(compteARebours, lignes.length * 55 + 1200);
    }

    /** Compte à rebours théâtral, puis affichage des explications (étape 5). */
    function compteARebours() {
        var zone = $('secCountdown');
        zone.hidden = false;
        var n = 5;
        zone.textContent = '⚠ Autodestruction des données dans ' + n + '…';
        var minuteur = setInterval(function () {
            n--;
            if (n <= 0) {
                clearInterval(minuteur);
                zone.hidden = true;
                $('secStage').classList.remove('alert');
                afficher('secReveal');
                $('secReveal').scrollIntoView({ behavior: 'smooth' });
            } else {
                zone.textContent = '⚠ Autodestruction des données dans ' + n + '…';
            }
        }, 900);
    }

    // endregion

    // region Boutons de la simulation

    $('secStart').addEventListener('click', function () {
        cacher('secIntro');
        afficher('secStage');
        $('secStage').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /** Étape 1 : le visiteur accepte ou refuse les faux cookies ; dans les deux cas le téléchargement se débloque. */
    function reponseCookies(accepte) {
        cookiesOk = accepte;
        cookieTime = accepte ? new Date() : null;
        cacher('secCookie');
        $('secDownload').disabled = false;
        $('secDlHint').textContent = accepte
            ? 'Ton coffre est prêt ! Clique pour télécharger 🎁'
            : 'Sans cookies, impossible de débloquer les récompenses.';
    }
    $('secCookieAcc').addEventListener('click', function () { reponseCookies(true); });
    $('secCookieRef').addEventListener('click', function () { reponseCookies(false); });

    $('secDownload').addEventListener('click', function () {
        cacher('secBait');
        afficher('secLoginStep');
    });

    /** Étape 2 : le faux formulaire « vole » ce qui est tapé (affiché plus tard comme leçon), puis passe à la fausse installation. */
    $('secLogin').addEventListener('submit', function (evenement) {
        evenement.preventDefault();
        var email = $('secEmail').value || '(vide)';
        var motDePasse = $('secPass').value || '(vide)';
        $('secStolen').innerHTML =
            '<div><span class="lbl">e-mail / pseudo : </span><span class="val">' + echapper(email) + '</span></div>' +
            '<div><span class="lbl">mot de passe : </span><span class="val">' + echapper(motDePasse) + '</span></div>';
        $('secEmail').value = '';
        $('secPass').value = '';
        cacher('secLoginStep');
        afficher('secDlStep');
        progression('secDlBar', 'secDlPct', function () { cacher('secDlStep'); lancerScan(); });
    });

    $('secRestart').addEventListener('click', function () { location.reload(); });

    // endregion
})();
