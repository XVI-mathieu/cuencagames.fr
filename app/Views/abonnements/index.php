<?php
/**
 * Abonnements : les formules proposées (avec les jeux qu'elles débloquent), l'abonnement en cours du joueur et son historique.
 * Boutique de démonstration : les prix sont fictifs et aucun paiement n'est demandé.
 *
 * Variables : $plans (formules, chacune avec 'games'), $niveau (niveau du visiteur), $actuel (abonnement en cours ou null),
 * $historique (liste), $gratuits (jeux gratuits pour tous), $complet (compte de gestion : accès à tout) ;
 * données communes : $site, $session.
 */
$etats = ['active' => 'Actif', 'resiliee' => 'Résilié (actif jusqu\'à la fin)', 'terminee' => 'Terminé']; // libellés des états
?>
<a class="back" href="<?= url('/') ?>">← Tous les projets</a>

<h1>Abonnements</h1>
<p class="muted">
    Choisissez une formule pour débloquer plus de jeux. Boutique de démonstration (projet BTS SIO) :
    les prix sont fictifs, aucun paiement n'est demandé. Un abonnement dure un mois, sans renouvellement automatique.
</p>

<?php // region Abonnement en cours ?>
<?php if ($complet): ?>
    <p class="alert alert-success">🛡 Votre compte de gestion donne accès à <strong>tous les jeux</strong>, sans abonnement ni limite de temps.</p>
<?php endif; ?>
<?php if ($session['connecte']): ?>
    <section class="abo-courant">
        <?php if ($actuel): ?>
            <p>
                Votre abonnement : <strong>⭐ <?= e($actuel['name']) ?></strong> — valable jusqu'au
                <strong><?= e(date('d/m/Y', strtotime($actuel['end']))) ?></strong>
                <?php if ($actuel['status'] === 'resiliee'): ?>
                    <span class="tag tag-maint">Résilié : ne sera pas renouvelé</span>
                <?php endif; ?>
            </p>
            <?php if ($actuel['status'] === 'active'): ?>
                <form method="post" action="<?= e(url('/abonnements/resilier')) ?>" class="inline"
                      onsubmit="return confirm('Résilier votre abonnement ? Il reste actif jusqu\'à sa date de fin.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Résilier</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Vous êtes sur la formule <strong>Gratuit</strong> : <?= count($gratuits) ?> jeu(x) accessible(s).</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php // endregion ?>

<?php // region Formules proposées : une carte par formule, la formule gratuite en premier ?>
<div class="plans">
    <article class="plan <?= $niveau === 0 ? 'is-current' : '' ?>">
        <h2>Gratuit</h2>
        <p class="plan-prix">0 €<span>pour tous</span></p>
        <p class="muted">Sans compte ni abonnement.</p>
        <details>
            <summary><?= count($gratuits) ?> jeu(x) inclus</summary>
            <ul><?php foreach ($gratuits as $jeu): ?><li><?= e($jeu['name']) ?></li><?php endforeach; ?></ul>
        </details>
    </article>

    <?php foreach ($plans as $plan): ?>
        <article class="plan niveau-<?= (int) $plan['level'] ?> <?= $niveau === $plan['level'] ? 'is-current' : '' ?>">
            <h2>⭐ <?= e($plan['name']) ?></h2>
            <p class="plan-prix"><?= e(format_price($plan['price'])) ?><span>par mois</span></p>
            <p class="muted"><?= e($plan['description']) ?></p>
            <details>
                <summary><?= count($plan['games']) ?> jeu(x) inclus</summary>
                <ul><?php foreach ($plan['games'] as $jeu): ?><li><?= e($jeu['name']) ?></li><?php endforeach; ?></ul>
            </details>
            <?php if ($actuel && $actuel['level'] === $plan['level'] && $actuel['status'] === 'active'): ?>
                <span class="btn btn-block is-disabled" aria-disabled="true">Formule actuelle</span>
            <?php else: ?>
                <form method="post" action="<?= e(url('/abonnements/souscrire/' . $plan['id'])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $session['connecte'] ? 'Choisir cette formule' : 'Se connecter et choisir' ?>
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php // endregion ?>

<?php // region Historique des abonnements du joueur ?>
<?php if ($historique): ?>
    <h2>Mon historique</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Formule</th><th>Début</th><th>Fin</th><th class="num">Prix</th><th>État</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $s): ?>
                    <tr>
                        <td><?= e($s['name']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($s['start']))) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($s['end']))) ?></td>
                        <td class="num"><?= e(format_price($s['price'])) ?></td>
                        <td><?= e($etats[$s['status']] ?? $s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php // endregion ?>
