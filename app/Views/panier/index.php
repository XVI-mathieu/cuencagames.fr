<?php
/**
 * Panier : une ligne par offre (formule + durée), quantité modifiable, suppression d'une ligne, vidage du panier
 * et validation de la commande. Boutique de démonstration : aucun paiement réel, prix fictifs.
 *
 * Variables : $lignes (key, name, months, label, quantity, price, subtotal), $plan (formule du panier ou null),
 * $total (montant), $mois (durée totale), $quantiteMax, $moisMax ; données communes : $site, $session.
 */
?>
<a class="back" href="<?= e(url_reduit('abonnement', 'index')) ?>">← Les formules</a>

<div class="page-head">
    <h1>🛒 Mon panier</h1>
</div>

<?php if (!$lignes): ?>
    <?php // region Panier vide ?>
    <div class="panel panier-vide">
        <p>Votre panier est vide.</p>
        <p class="muted">Choisissez une formule et une durée sur la page des abonnements.</p>
        <a class="btn btn-primary" href="<?= e(url_reduit('abonnement', 'index')) ?>">Voir les abonnements</a>
    </div>
    <?php // endregion ?>
<?php else: ?>
    <?php // region Lignes du panier : modification des quantités ?>
    <form method="post" action="<?= e(url_reduit('panier', 'modifier')) ?>" id="form-panier">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table panier-table">
                <thead>
                    <tr>
                        <th>Formule</th>
                        <th>Durée</th>
                        <th class="num">Prix</th>
                        <th>Quantité</th>
                        <th class="num">Sous-total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr>
                            <?php /* data-label : sur smartphone, chaque ligne devient une fiche et le libellé s'affiche devant la valeur */ ?>
                            <td data-label="Formule">⭐ <?= e($ligne['name']) ?></td>
                            <td data-label="Durée"><?= e($ligne['label']) ?></td>
                            <td class="num" data-label="Prix"><?= e(format_price($ligne['price'])) ?></td>
                            <td data-label="Quantité">
                                <input type="number" class="qte" name="quantites[<?= e($ligne['key']) ?>]"
                                       value="<?= (int) $ligne['quantity'] ?>" min="0" max="<?= (int) $quantiteMax ?>"
                                       aria-label="Quantité : <?= e($ligne['name'] . ' ' . $ligne['label']) ?>">
                            </td>
                            <td class="num" data-label="Sous-total"><?= e(format_price($ligne['subtotal'])) ?></td>
                            <td class="actions">
                                <?php /* formaction : ce bouton envoie le formulaire du panier vers « supprimer » avec la clé de sa ligne */ ?>
                                <button type="submit" class="btn btn-sm btn-danger" name="cle" value="<?= e($ligne['key']) ?>"
                                        formaction="<?= e(url_reduit('panier', 'supprimer')) ?>">Retirer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted panier-aide">
            Quantité : de 1 à <?= (int) $quantiteMax ?> par durée (0 retire la ligne) ; une commande ne peut pas dépasser
            <?= (int) $moisMax ?> mois. Un abonnement porte sur une seule formule.
        </p>
        <div class="panier-actions">
            <button type="submit" class="btn">Mettre à jour les quantités</button>
        </div>
    </form>
    <?php // endregion ?>

    <?php // region Récapitulatif, vidage et validation ?>
    <section class="panel panier-recap">
        <h2>Récapitulatif</h2>
        <dl class="profile">
            <dt>Formule</dt><dd><?= e($plan['name'] ?? '') ?></dd>
            <dt>Durée totale</dt><dd><?= (int) $mois ?> mois</dd>
            <dt>Total</dt><dd class="panier-total"><?= e(format_price($total)) ?></dd>
        </dl>
        <p class="muted">Boutique de démonstration : aucun paiement n'est demandé. L'abonnement remplace celui en cours dès la validation.</p>

        <div class="panier-actions">
            <form method="post" action="<?= e(url_reduit('panier', 'valider')) ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">
                    <?= $session['connecte'] ? 'Valider la commande' : 'Se connecter et valider' ?>
                </button>
            </form>
            <form method="post" action="<?= e(url_reduit('panier', 'vider')) ?>" class="inline" id="form-vider">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">Vider le panier</button>
            </form>
        </div>
    </section>
    <?php // endregion ?>

    <script src="<?= url('/assets/js/panier.js') ?>?v=<?= (int) @filemtime(ROOT . '/assets/js/panier.js') ?>"></script>
<?php endif; ?>
