<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/iniciar-sesion');
    }

    public function test_login_page_is_reachable(): void
    {
        $this->get('/iniciar-sesion')->assertOk();
    }
}
