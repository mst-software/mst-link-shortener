<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Services\Visits\CodeIssuer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Guards every /widget/* request: decodes the JSON body (the widget posts it as text/plain to avoid
 * a preflight), finds the campaign, accepts only an Origin that belongs to the campaign's domain and
 * only while the campaign is available, then adds the CORS headers for that exact origin.
 */
class WidgetCors
{
    public function __construct(private readonly CodeIssuer $issuer) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->isMethod('OPTIONS')) {
            return $this->preflight($request);
        }

        $this->decodeBody($request);

        $key = $request->input('campaign');
        $attempt = null;

        if (is_string($key) && $key !== '') {
            $campaign = Campaign::query()->where('widget_key', $key)->first();
        } else {
            $token = $request->input('attempt_token');
            $attempt = $this->issuer->attemptFromToken(is_string($token) ? $token : null);

            if ($attempt === null) {
                return $this->open(response()->json(['error' => 'bad_token'], 400));
            }

            $campaign = $attempt->campaign;
        }

        if ($campaign === null) {
            return $this->open(response()->json(['error' => 'campaign_unavailable'], 404));
        }

        $origin = (string) $request->headers->get('Origin', '');

        if (! $this->originAllowed($origin, $campaign->domain)) {
            return response()->json(['error' => 'bad_origin'], 403);
        }

        $request->attributes->set('widget_campaign', $campaign);
        $request->attributes->set('widget_attempt', $attempt);

        $available = Campaign::query()->available()->whereKey($campaign->id)->exists();

        $response = $available
            ? $next($request)
            : response()->json(['error' => 'campaign_unavailable'], 403);

        return $this->withCors($response, $origin);
    }

    /**
     * Preflight answers carry no body, so the campaign comes from the optional `campaign` query value.
     */
    private function preflight(Request $request): SymfonyResponse
    {
        $response = response()->noContent();
        $origin = (string) $request->headers->get('Origin', '');
        $key = $request->query('campaign');
        $campaign = is_string($key) ? Campaign::query()->where('widget_key', $key)->first() : null;

        if ($campaign === null || ! $this->originAllowed($origin, $campaign->domain)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Max-Age', '600');

        return $this->withCors($response, $origin);
    }

    private function decodeBody(Request $request): void
    {
        $data = json_decode((string) $request->getContent(), true);

        if (is_array($data)) {
            $request->request->replace($data);
        }
    }

    /**
     * Accept the campaign host and its subdomains over http(s); on a local app also allow
     * 127.0.0.1 when the campaign domain is `localhost`.
     */
    private function originAllowed(string $origin, string $domain): bool
    {
        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        $domain = strtolower($domain);

        if ($host === $domain || str_ends_with($host, '.'.$domain)) {
            return true;
        }

        return app()->isLocal() && $domain === 'localhost' && $host === '127.0.0.1';
    }

    private function withCors(SymfonyResponse $response, string $origin): SymfonyResponse
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');

        return $response;
    }

    /**
     * Answers that reveal nothing (unknown campaign or token) may be read by any origin.
     */
    private function open(JsonResponse $response): SymfonyResponse
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
