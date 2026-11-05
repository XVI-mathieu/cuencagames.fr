<?php
/**
 * Élément réutilisable : encadré rouge listant des messages d'erreur (rien n'est affiché si la liste est vide).
 * Inclus par les formulaires (connexion, installation, compte, ajout de projet).
 *
 * Variable attendue : $errors (liste de textes).
 */
if (!empty($errors)): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $erreur): ?>
                <li><?= e($erreur) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
