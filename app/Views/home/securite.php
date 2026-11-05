<?php
/**
 * Section pédagogique « Sécurité » : simulation de sensibilisation à l'arnaque du faux site de jeux gratuits.
 * IMPORTANT : rien n'est réellement collecté, envoyé ni téléchargé : les informations affichées sont lues côté
 * visiteur (comme tout site le fait) et restent sur son écran. L'IP et le GPS ne fonctionnent qu'en HTTPS.
 *
 * Styles : assets/css/securite.css. Comportement : assets/js/securite.js. Aucune variable.
 */
?>
<link rel="stylesheet" href="<?= url('/assets/css/securite.css') ?>?v=<?= (int) @filemtime(ROOT . '/assets/css/securite.css') ?>">
<a class="back" href="<?= url('/') ?>">← Retour aux projets</a>

<section class="hero sec-intro" id="secIntro">
    <span class="eyebrow">Sensibilisation // Sécurité</span>
    <h1>Teste tes réflexes</h1>
    <p>Sauras-tu repérer l'arnaque ? Cette simulation te montre ce qu'un faux site de « jeux gratuits » peut voir et te faire faire — sans aucun piratage.</p>

    <div class="note" style="max-width:720px">
        ⚠️ <strong>Simulation pédagogique.</strong> Rien n'est réellement collecté, envoyé ni téléchargé.
        Toutes les infos affichées sont lues sur ton propre appareil (comme le fait n'importe quel site) et restent sur ton écran.
    </div>

    <div style="margin-top:26px">
        <button type="button" class="btn btn-primary btn-lg" id="secStart">▶ Lancer la simulation</button>
    </div>
</section>

<?php // region Scène de simulation (5 étapes affichées l'une après l'autre par securite.js) ?>
<div class="sec-stage" id="secStage" hidden>
    <div class="sec-pad" id="secStagePad">

        <?php /* Étape 1 : l'appât */ ?>
        <div id="secBait">
            <div class="sec-fakebrand"><div class="m">🎮</div><div class="n">Game<span>Zone</span> Rewards</div></div>
            <h2 style="color:#fff;font-size:1.4rem">Récupère tes récompenses gratuites 🎁</h2>
            <p class="muted">Skins, V-Bucks et packs exclusifs t'attendent. Télécharge le jeu pour débloquer ton coffre du jour.</p>
            <button type="button" class="btn btn-primary btn-lg" id="secDownload" disabled style="margin-top:10px">⬇ Télécharger le jeu gratuit</button>
            <p class="muted" id="secDlHint" style="margin-top:12px;font-size:.9rem">Accepte les cookies pour continuer…</p>

            <div class="sec-cookie" id="secCookie">
                <p>🍪 <strong style="color:#fff">On utilise des cookies</strong> pour personnaliser ton expérience et celle de nos 87 partenaires publicitaires.</p>
                <div class="btns">
                    <button type="button" class="btn btn-sm" id="secCookieRef">Refuser</button>
                    <button type="button" class="btn btn-primary btn-sm" id="secCookieAcc">Tout accepter</button>
                </div>
            </div>
        </div>

        <?php /* Étape 2 : faux formulaire de connexion (phishing) */ ?>
        <div id="secLoginStep" hidden>
            <div class="sec-fakebrand"><div class="m">🎮</div><div class="n">Game<span>Zone</span></div></div>
            <h2 style="color:#fff;font-size:1.2rem">Connecte-toi pour télécharger ton jeu</h2>
            <form class="sec-login" id="secLogin" autocomplete="off">
                <label for="secEmail">E-mail ou pseudo</label>
                <input type="text" id="secEmail" placeholder="ton@email.com" required>
                <label for="secPass">Mot de passe</label>
                <input type="password" id="secPass" placeholder="••••••••" required>
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:18px">Se connecter et télécharger</button>
                <p class="muted" style="text-align:center;margin-top:12px;font-size:.85rem">🔒 Connexion « sécurisée »</p>
            </form>
        </div>

        <?php /* Étape 3 : fausse installation */ ?>
        <div id="secDlStep" hidden style="text-align:center">
            <div class="sec-fakebrand" style="justify-content:center"><div class="m">🎮</div><div class="n">Game<span>Zone</span></div></div>
            <p style="color:#fff">Téléchargement de <strong>Coup-d-Envoi-3D.exe</strong>…</p>
            <div class="sec-bar"><i id="secDlBar"></i></div>
            <div class="sec-pct" id="secDlPct">0%</div>
            <p class="muted" style="font-size:.85rem">Fichier .exe · 48,2 Mo · source : gamezone-rewards.net</p>
        </div>

        <?php /* Étape 4 : le scan des données */ ?>
        <div id="secScanStep" hidden>
            <h2 style="color:var(--danger);font-size:1.3rem" id="secScanTitle">⚠ ACCÈS EN COURS…</h2>
            <p class="muted" id="secScanSub">extraction des données de l'appareil</p>
            <div class="sec-bar"><i id="secScanBar"></i></div>
            <div class="sec-pct" id="secScanPct">0%</div>
            <div class="sec-legend">
                <span><i class="sec-dot s1"></i>peu sensible</span>
                <span><i class="sec-dot s2"></i>identifiant</span>
                <span><i class="sec-dot s3"></i>très sensible</span>
            </div>
            <div class="sec-term" id="secTerm"></div>
            <div class="sec-countdown" id="secCountdown" hidden></div>
        </div>

        <?php /* Étape 5 : la révélation et les leçons */ ?>
        <div id="secReveal" hidden>
            <div class="sec-lesson warn">
                <h3>✋ Stop — c'était une simulation</h3>
                <p><strong>Rien n'a été piraté, rien n'a été téléchargé.</strong> Voici pourtant tout ce qu'un vrai faux-site aurait obtenu de toi en quelques secondes.</p>
            </div>

            <div class="sec-lesson warn">
                <h3>1 · Tes identifiants (phishing)</h3>
                <p>Tu les as tapés <strong>toi-même</strong> sur un faux formulaire. Aucun piratage : il a suffi de te les demander.</p>
                <div class="sec-stolen" id="secStolen"></div>
                <p class="muted" style="font-size:.9rem">Réflexe : ne tape jamais ton mot de passe sur un site atteint par un lien. Vérifie l'adresse. N'utilise pas le même mot de passe partout.</p>
            </div>

            <div class="sec-lesson warn">
                <h3>2 · Le fichier .exe que tu allais installer</h3>
                <p>Un <strong>.exe</strong> est un <strong>programme</strong> : une fois lancé, il peut faire tout ce que toi tu peux faire sur l'ordi — lire tes fichiers, voler tes mots de passe enregistrés, installer un virus, espionner ta webcam. Un jeu piégé ressemble à s'y méprendre à un vrai jeu.</p>
                <p><strong>La règle :</strong> ne télécharge un .exe que depuis le <strong>site officiel</strong> du logiciel (ou un store : Steam, Microsoft Store, Play Store). Jamais depuis une pub, un lien reçu, ou un site de « jeux gratuits ». En cas de doute, passe le fichier sur <strong>virustotal.com</strong> avant de l'ouvrir.</p>
            </div>

            <div class="sec-lesson info">
                <h3>3 · Ce que le site voit sans rien demander</h3>
                <p>Toutes les lignes rouges au-dessus (IP, localisation, empreinte…) ont été lues automatiquement, juste parce que tu as ouvert la page. C'est le fonctionnement normal du web.</p>
            </div>

            <div class="sec-lesson info">
                <h3>4 · Et ton adresse ? Ton école ?</h3>
                <p>Ça, un site ne peut <strong>pas</strong> le voler. Mais on peut le <strong>deviner</strong> à partir de ce que tu publies :</p>
                <ul>
                    <li>📸 une photo devant ton collège avec le panneau visible → ton école ;</li>
                    <li>🎂 un « joyeux anniversaire » → ta date de naissance ;</li>
                    <li>👤 le même pseudo sur Insta, TikTok, Discord → tous tes comptes reliés ;</li>
                    <li>📍 une photo brute (non nettoyée) → parfois les coordonnées GPS cachées dedans.</li>
                </ul>
                <p class="muted" style="font-size:.9rem">Avant de poster : « qu'est-ce que ça révèle sur moi ? »</p>
            </div>

            <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap">
                <button type="button" class="btn btn-primary" id="secRestart">↻ Recommencer</button>
                <a class="btn" href="<?= url('/') ?>">Voir les vrais projets</a>
            </div>
        </div>

    </div>
</div>
<?php // endregion ?>

<script src="<?= url('/assets/js/securite.js') ?>?v=<?= (int) @filemtime(ROOT . '/assets/js/securite.js') ?>"></script>