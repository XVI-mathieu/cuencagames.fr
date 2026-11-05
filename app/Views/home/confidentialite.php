<?php
/**
 * Politique de confidentialité (RGPD / CNIL) et cookies. À relire si le site collecte de nouvelles données.
 *
 * Variables : données communes $site (éditeur, contact, hébergeur : voir app/Config/config.php).
 */
$contact = $site['contact'];
$contactLabel = preg_replace('#^https?://#', '', $contact);
?>
<article class="legal">
    <?php // region Introduction ?>
    <h1>Politique de confidentialité</h1>
    <p class="muted">
        Ce site respecte le Règlement général sur la protection des données (RGPD, règlement UE 2016/679)
        et la loi française « Informatique et Libertés » du 6 janvier 1978 modifiée.
    </p>

    <h2>1. Responsable du traitement</h2>
    <p>
        <strong><?= e($site['auteur']) ?></strong> (<?= e($site['statut']) ?>).
        Contact pour toute question ou demande : <a href="<?= e($contact) ?>" rel="noopener"><?= e($contactLabel) ?></a>.
    </p>
    <?php // endregion ?>

    <?php // region 2. Données collectées, finalités et bases légales ?>
    <h2>2. Données collectées, finalités et bases légales</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Traitement</th><th>Données</th><th>Finalité</th><th>Base légale</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Compte joueur (facultatif)</td>
                    <td>Nom, prénom, adresse e-mail, téléphone (facultatif), nom d'utilisateur, mot de passe <em>haché</em> (jamais conservé en clair), rôle, date de création, date de dernière connexion</td>
                    <td>Créer et gérer votre compte, vous connecter (par nom d'utilisateur ou e-mail), vous identifier</td>
                    <td>Votre consentement (case cochée à l'inscription)</td>
                </tr>
                <tr>
                    <td>Abonnements (boutique de démonstration)</td>
                    <td>Formule choisie, prix fictif, dates de début et de fin, état de l'abonnement, rattachés à votre compte — <strong>aucun paiement ni coordonnée bancaire</strong></td>
                    <td>Débloquer les jeux de votre formule et vous montrer votre historique</td>
                    <td>Votre consentement (abonnement choisi volontairement depuis votre compte)</td>
                </tr>
                <tr>
                    <td>Sécurité du site</td>
                    <td>Adresse IP et journaux techniques conservés par l'hébergeur</td>
                    <td>Assurer la sécurité et le bon fonctionnement du site</td>
                    <td>Intérêt légitime</td>
                </tr>
                <tr>
                    <td>Temps de jeu gratuit (visiteurs sans compte)</td>
                    <td>Compteur de secondes de jeu, stocké dans votre session (cookie technique) — <strong>aucune donnée personnelle</strong></td>
                    <td>Limiter à 10 minutes la version navigateur d'un jeu sans compte</td>
                    <td>Intérêt légitime (données non personnelles)</td>
                </tr>
                <tr>
                    <td>Statistiques de téléchargement</td>
                    <td>Simple compteur par jeu — <strong>aucune donnée personnelle enregistrée</strong></td>
                    <td>Savoir quels jeux sont les plus téléchargés</td>
                    <td>Intérêt légitime (données non personnelles)</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p>
        Vous pouvez parcourir le site, télécharger et jouer
        <strong>sans créer de compte</strong>. Vos données ne sont ni vendues, ni louées, ni utilisées à des fins
        publicitaires ou de profilage.
    </p>
    <?php // endregion ?>

    <?php // region 3. Destinataires et hébergement, 4. Durée de conservation ?>
    <h2>3. Destinataires et hébergement</h2>
    <p>
        Les données du compte ne sont accessibles qu'à l'éditeur (administrateur du site). Elles sont hébergées chez
        <?= e($site['hebergeurNom']) ?> (sous-traitant technique), dont les serveurs peuvent se situer
        dans l'Union européenne. Aucune autre transmission n'est effectuée.
    </p>

    <h2>4. Durée de conservation</h2>
    <ul>
        <li>Compte et abonnements : conservés tant que le compte n'est pas supprimé par vous (bouton « Supprimer mon compte »,
            qui efface aussi vos abonnements) ; un compte sans connexion pendant 3 ans est supprimé.</li>
        <li>Journaux techniques de l'hébergeur : durée définie par l'hébergeur (en général quelques mois).</li>
    </ul>
    <?php // endregion ?>

    <?php // region 5. Vos droits, 6. Mineurs ?>
    <h2>5. Vos droits</h2>
    <p>
        Vous disposez des droits d'<strong>accès</strong>, de <strong>rectification</strong>, d'<strong>effacement</strong>,
        de <strong>limitation</strong>, d'<strong>opposition</strong> et de <strong>portabilité</strong> de vos données,
        ainsi que du droit de retirer votre consentement à tout moment et de définir des directives sur le sort de vos
        données après votre décès.
    </p>
    <ul>
        <li>Connecté, rendez-vous dans <a href="<?= url('/mon-compte') ?>">Mon compte</a> : vous pouvez
            <strong>télécharger vos données</strong> et <strong>supprimer votre compte</strong> immédiatement.</li>
        <li>Pour toute autre demande, contactez l'éditeur via <a href="<?= e($contact) ?>" rel="noopener"><?= e($contactLabel) ?></a>.
            Une réponse vous est apportée dans un délai d'un mois.</li>
        <li>Si vous estimez, après nous avoir contactés, que vos droits ne sont pas respectés, vous pouvez adresser une
            réclamation à la <strong>CNIL</strong> :
            <a href="https://www.cnil.fr/fr/plaintes" rel="noopener">www.cnil.fr/fr/plaintes</a> —
            3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07.</li>
    </ul>

    <h2>6. Mineurs</h2>
    <p>
        La création d'un compte par un mineur de moins de 15 ans nécessite l'accord de ses parents ou représentants légaux
        (article 45 de la loi Informatique et Libertés).
    </p>
    <?php // endregion ?>

    <?php // region 7. Cookies et traceurs ?>
    <h2 id="cookies">7. Cookies et traceurs</h2>
    <p>
        Un cookie est un petit fichier enregistré dans votre navigateur. Ce site <strong>n'utilise que des cookies
        strictement nécessaires</strong> à son fonctionnement ; selon les lignes directrices de la CNIL, ils sont
        <strong>exemptés de consentement</strong>. Il n'y a <strong>aucun</strong> cookie publicitaire, de mesure d'audience,
        de réseau social ni de suivi.
    </p>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Nom</th><th>Finalité</th><th>Durée</th><th>Type</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>PHPSESSID</code></td>
                    <td>Maintenir votre connexion, protéger les formulaires contre les requêtes frauduleuses (jeton CSRF) et décompter le temps de jeu gratuit sans compte</td>
                    <td>Session (supprimé à la fermeture du navigateur)</td>
                    <td>Technique, indispensable</td>
                </tr>
                <tr>
                    <td><code>cookies_info_ok</code> (stockage local du navigateur)</td>
                    <td>Mémoriser que vous avez lu le bandeau d'information, pour ne plus l'afficher</td>
                    <td>12 mois</td>
                    <td>Technique, indispensable</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p>
        Vous pouvez configurer votre navigateur pour bloquer les cookies, mais la connexion à votre compte ne
        fonctionnera alors plus. Le lien « Gérer les cookies » en bas de page réaffiche le bandeau d'information.
    </p>
    <?php // endregion ?>

    <?php // region 8. Services tiers, 9. Sécurité, 10. Modifications ?>
    <h2>8. Services tiers</h2>
    <ul>
        <li><strong>Polices de caractères :</strong> hébergées sur ce site, sans appel à Google Fonts ni à un autre
            service externe.</li>
        <li><strong>Page « Sécurité » (simulation pédagogique) :</strong> si vous cliquez sur « Démarrer », la page
            affiche ce que n'importe quel site peut voir de votre appareil. Pour montrer votre adresse IP et votre ville,
            votre navigateur interroge directement des services de géolocalisation d'IP (ipapi.co, ipwho.is, geojs.io,
            ipify.org), qui voient alors votre adresse IP. Ces informations restent affichées à l'écran et ne sont ni
            enregistrées ni envoyées à ce site. La position GPS n'est transmise que si vous l'autorisez dans votre
            navigateur et reste sur votre appareil. Ne cliquez pas sur « Démarrer » si vous ne souhaitez pas ces échanges.</li>
        <li><strong>Liens externes :</strong> les liens vers d'autres sites (par exemple OpenStreetMap) relèvent de leur
            propre politique de confidentialité.</li>
    </ul>

    <h2>9. Sécurité</h2>
    <p>
        Les mots de passe sont stockés sous forme chiffrée (hachage), l'accès au site se fait en HTTPS, les formulaires sont
        protégés contre les requêtes frauduleuses et les tentatives de connexion répétées sont ralenties.
    </p>

    <h2>10. Modifications</h2>
    <p>Cette politique peut évoluer ; la date de dernière mise à jour figure ci-dessous.</p>
    <p class="muted">Dernière mise à jour : <?= date('d/m/Y') ?></p>
    <?php // endregion ?>
</article>
