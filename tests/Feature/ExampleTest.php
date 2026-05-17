<?php

it('renders the home page', function () {
    $this->get('/')->assertOk();
});
