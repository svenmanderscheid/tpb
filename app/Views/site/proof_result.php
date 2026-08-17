<?php
/**
 * @var string $kind  approved|changes|error
 * @var string|null $message
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card narrow center">
            <?php if ($kind === 'approved'): ?>
                <h1>Vielen Dank – Freigabe erteilt!</h1>
                <div class="alert ok">Ihr Motiv ist freigegeben. Der Auftrag geht nun in die Produktion.</div>
            <?php elseif ($kind === 'changes'): ?>
                <h1>Änderung angefordert</h1>
                <p>Danke für Ihre Rückmeldung. Wir überarbeiten den Proof und senden Ihnen eine neue Version zur Freigabe.</p>
            <?php else: ?>
                <h1>Aktion nicht möglich</h1>
                <div class="alert error"><?= e((string) ($message ?? 'Diese Aktion ist nicht mehr möglich.')) ?></div>
            <?php endif; ?>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </div>
</section>
