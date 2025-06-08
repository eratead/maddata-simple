<?php

it('returns a successful response', function () {
    $response = $this->get('/dashboard');

    $response->assertStatus(302);
});
