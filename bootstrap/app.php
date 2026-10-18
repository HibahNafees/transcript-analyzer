<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The public URL is a Vercel rewrite that proxies to this server: use the
        // forwarded host/proto for links and the forwarded client IP for rate
        // limits. Only these hosts are accepted, so a spoofed host can't poison URLs.
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts(at: ['hibah-transcripts.vercel.app', 'transcripts.66-135-1-187.sslip.io'], subdomains: false);

        // Stripe can't send a CSRF token; the webhook verifies Stripe's signature instead.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
