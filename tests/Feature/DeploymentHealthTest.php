<?php

it('returns API status at the root without a web session', function () {
    $this->getJson('/')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok');
});

it('exposes an API health route without a database dependency', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
