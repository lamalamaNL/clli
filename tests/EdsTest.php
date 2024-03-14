<?php

use LamaLama\Clli\Console\Services\Figma\FigmaClient;

test('it can connect to figma', function () {
    $f = new FigmaClient();
    $f->get('/v1/teams/892656262375942883/projects');
});
