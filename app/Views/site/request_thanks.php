<?php
/** @var string $name */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anfrage erhalten – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <h1>Vielen Dank<?= $name !== '' ? ', ' . e($name) : '' ?>!</h1>
            <p>Wir haben Ihre Anfrage erhalten und erstellen Ihnen zeitnah ein persönliches Angebot.
               Sie erhalten es per E-Mail mit einem Link zum Ansehen und Annehmen.</p>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </main>
</body>
</html>
