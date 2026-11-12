<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidLinkException;
use App\Services\TokenShortener;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuickLinkController extends Controller
{
    public function __construct(private readonly TokenShortener $tokenShortener) {}

    /**
     * Shorten the URL for the token owner and show the short link as plain text.
     */
    public function __invoke(Request $request): Response
    {
        try {
            $link = $this->tokenShortener->shorten(
                $request->query('api'),
                $request->query('url'),
                $request->query('alias'),
                $request->filled('level') ? (int) $request->query('level') : null,
            );
        } catch (InvalidLinkException $exception) {
            return response($exception->getMessage(), 422)->header('Content-Type', 'text/plain');
        }

        return response(url($link->alias))->header('Content-Type', 'text/plain');
    }
}
