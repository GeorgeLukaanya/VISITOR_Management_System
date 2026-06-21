<?php

it('redirects the root url to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});
