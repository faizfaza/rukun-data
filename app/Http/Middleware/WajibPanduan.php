<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Panduan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WajibPanduan
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->panduan_selesai_at === null
            && ! $request->routeIs(Panduan::getRouteName(), 'filament.admin.auth.logout')) {
            return redirect()->to(Panduan::getUrl());
        }

        return $next($request);
    }
}
