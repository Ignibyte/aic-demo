<?php /** @var \App\Site\Component\BootstrapIntro $bootstrapIntro */ ?>
<section class="intro" aria-labelledby="lotline-heading">
    <p class="eyebrow"><?= $bootstrapIntro->dealership ?></p>
    <h1 id="lotline-heading"><?= $bootstrapIntro->title ?></h1>
    <p class="description"><?= $bootstrapIntro->description ?></p>
    <p class="status"><span class="status-dot" aria-hidden="true"></span>Foundation in progress</p>
    <a class="health-link" href="<?= $bootstrapIntro->healthPath ?>" data-enhance="off">Check application health <span aria-hidden="true">↗</span></a>
</section>
