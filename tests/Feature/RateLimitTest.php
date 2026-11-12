<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\Link;

test('visit routes are limited to 60 requests per minute per ip', function () {
    $link = Link::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->get('/'.$link->alias)->assertRedirect();
    }

    $this->get('/'.$link->alias)->assertStatus(429);
});

test('registration is limited to 5 attempts per minute per ip', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/auth/signup', [])->assertSessionHasErrors();
    }

    $this->post('/auth/signup', [])->assertStatus(429);
});
