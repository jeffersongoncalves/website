<?php

declare(strict_types=1);

it('renders the home page', function () {
    $this->get('/pt_BR')->assertOk();
});
