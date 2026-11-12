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
                $this->stringInput($request, 'api'),
                $this->stringInput($request, 'url'),
                $this->stringInput($request, 'alias'),
                $request->integer('level') ?: null,
            );
        } catch (InvalidLinkException $exception) {
            return response($exception->getMessage(), 422)->header('Content-Type', 'text/plain');
        }

        return response(url($link->alias))->header('Content-Type', 'text/plain');
    }

    /**
     * Read a query value only when it is a non-empty string (arrays become null).
     */
    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
