<?php
/**
 * Abonnements : les formules proposées (avec les jeux qu'elles débloquent), l'abonnement en cours du joueur et son historique.
 * Boutique de démonstration : les prix sont fictifs et aucun paiement n'est demandé.
 *
 * Variables : $plans (formules, chacune avec 'games' et 'offers' : une offre par durée), $niveau (niveau du visiteur),
 * $actuel (abonnement en cours ou null), $historique et $commandes (listes), $gratuits (jeux gratuits pour tous),
 * $complet (compte de gestion : accès à tout), $quantiteMax (exemplaires maximum d'une durée) ;
 * données communes : $site, $session.
 */
$etats = ['active' => 'Actif', 'resiliee' => 'Résilié (actif jusqu\'à la fin)', 'terminee' => 'Terminé']; // libellés des états
?>
<a class="back" href="<?= url('/') ?>">← Tous les projets</a>

<h1>Abonnements</h1>
<p class="muted">
    Choisissez une formule pour débloquer plus de jeux. Boutique de démonstration (projet BTS SIO) :
    les prix sont fictifs, aucun paiement n'est demandé. Choisissez une durée (1, 3, 6 ou 12 mois : plus c'est long, moins
    c'est cher), ajoutez l'offre à votre <a href="<?= e(url_reduit('panier', 'index')) ?>">panier</a>, puis validez la commande.
    Pas de renouvellement automatique.
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
                <?php // region Ajout au panier : durée + quantité ?>
                <form method="post" action="<?= e(url_reduit('panier', 'ajouter')) ?>" class="plan-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="formule" value="<?= (int) $plan['id'] ?>">
                    <label class="field">
                        <span>Durée</span>
                        <select name="duree">
                            <?php foreach ($plan['offers'] as $offre): ?>
                                <option value="<?= (int) $offre['months'] ?>">
                                    <?= e($offre['label']) ?> — <?= e(format_price($offre['price'])) ?><?= $offre['discount'] > 0 ? ' (−' . (int) $offre['discount'] . ' %)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Quantité</span>
                        <input type="number" name="quantite" value="1" min="1" max="<?= (int) $quantiteMax ?>">
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">🛒 Ajouter au panier</button>
                </form>
                <?php // endregion ?>
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
                <tr><th>Formule</th><th>Durée</th><th>Début</th><th>Fin</th><th class="num">Prix</th><th>État</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historique as $s): ?>
                    <tr>
                        <td><?= e($s['name']) ?></td>
                        <td><?= (int) $s['months'] ?> mois</td>
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

<?php // region Commandes du joueur ?>
<?php if ($commandes): ?>
    <h2>Mes commandes</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>N°</th><th>Date</th><th>Formule</th><th>Durée</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $c): ?>
                    <tr>
                        <td>#<?= (int) $c['id'] ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($c['date']))) ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td><?= (int) $c['months'] ?> mois</td>
                        <td class="num"><?= e(format_price($c['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php // endregion ?>
