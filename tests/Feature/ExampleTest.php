<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_root_to_the_trivia_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/trivia');
    }
}
