<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Emulasi siklus hidup per-request pada test HTTP.
     *
     * Guard auth (RequestGuard Sanctum) menyimpan user pada instance guard
     * selama proses test; pada produksi setiap request memakai aplikasi baru.
     * `forgetGuards()` memastikan request berikutnya membaca Bearer token lagi,
     * sehingga test multi-user / token-revoked berperilaku seperti produksi.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app !== null) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
