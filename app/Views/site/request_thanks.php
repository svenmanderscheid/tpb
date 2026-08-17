<?php
/** @var string $name */
?>
<section class="section">
    <div class="wrap">
        <div class="card narrow center">
            <h1>Vielen Dank<?= $name !== '' ? ', ' . e($name) : '' ?>!</h1>
            <p>Wir haben Ihre Anfrage erhalten und erstellen Ihnen zeitnah ein persönliches Angebot.
               Sie erhalten es per E-Mail mit einem Link zum Ansehen und Annehmen.</p>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </div>
</section>
