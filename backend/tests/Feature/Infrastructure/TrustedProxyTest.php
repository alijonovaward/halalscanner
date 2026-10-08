<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_internal_proxy_headers_preserve_https_urls_and_the_client_address(): void
    {
        Route::get('/_proxy-probe', function (Request $request) {
            return response()->json([
                'secure' => $request->secure(),
                'client_ip' => $request->ip(),
                'analysis_url' => route('api.ingredient-analyses.store'),
            ]);
        });

        $this->withServerVariables([
            'REMOTE_ADDR' => '172.18.0.12',
        ])->withHeaders([
            'Host' => 'web',
            'X-Forwarded-For' => '203.0.113.41',
            'X-Forwarded-Host' => 'staging.example.com',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
        ])->get('/_proxy-probe')
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'client_ip' => '203.0.113.41',
                'analysis_url' => 'https://staging.example.com/api/ingredient-analyses',
            ]);
    }
}
