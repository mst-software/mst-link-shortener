<?php

namespace App\Http\Controllers;

use App\Contracts\CountryResolver;
use App\Http\Requests\Widget\CodeRequest;
use App\Http\Requests\Widget\ProgressRequest;
use App\Http\Requests\Widget\StartRequest;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Services\Views\VisitorContext;
use App\Services\Visits\CodeIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    public function __construct(
        private readonly CodeIssuer $issuer,
        private readonly CountryResolver $countries,
    ) {}

    public function start(StartRequest $request): JsonResponse
    {
        $campaign = $this->campaign($request);
        $context = VisitorContext::fromRequest($request, $this->countries);
        $existing = null;

        if ($request->filled('attempt_token')) {
            $existing = $this->issuer->attemptFromToken($request->string('attempt_token')->toString());

            if (! $this->ownsAttempt($existing, $campaign, $context)) {
                return response()->json(['error' => 'bad_token'], 400);
            }
        }

        return $this->respond($this->issuer->start(
            $campaign,
            $context,
            $request->string('page_load_id')->toString(),
            $request->filled('referrer') ? $request->string('referrer')->toString() : null,
            $request->filled('fingerprint') ? $request->string('fingerprint')->toString() : null,
            $existing,
        ));
    }

    public function progress(ProgressRequest $request): JsonResponse
    {
        $attempt = $this->verifiedAttempt($request);

        if ($attempt === null) {
            return response()->json(['error' => 'bad_token'], 400);
        }

        return $this->respond($this->issuer->progress(
            $attempt,
            $this->campaign($request),
            $request->string('page_load_id')->toString(),
        ));
    }

    public function code(CodeRequest $request): JsonResponse
    {
        $attempt = $this->verifiedAttempt($request);

        if ($attempt === null) {
            return response()->json(['error' => 'bad_token'], 400);
        }

        return $this->respond($this->issuer->issue($attempt, $this->campaign($request)));
    }

    private function campaign(Request $request): Campaign
    {
        /** @var Campaign */
        return $request->attributes->get('widget_campaign');
    }

    /**
     * The attempt named by the token, only when it belongs to this campaign and this browser.
     */
    private function verifiedAttempt(Request $request): ?CodeAttempt
    {
        $attempt = $this->issuer->attemptFromToken($request->string('attempt_token')->toString());
        $context = VisitorContext::fromRequest($request, $this->countries);

        return $this->ownsAttempt($attempt, $this->campaign($request), $context) ? $attempt : null;
    }

    private function ownsAttempt(?CodeAttempt $attempt, Campaign $campaign, VisitorContext $context): bool
    {
        return $attempt !== null
            && $attempt->campaign_id === $campaign->id
            && hash_equals($attempt->ua_hash, $context->uaHash);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function respond(array $result): JsonResponse
    {
        return match ($result['error'] ?? null) {
            null => response()->json($result),
            'too_early' => response()->json($result, 422),
            'campaign_unavailable' => response()->json($result, 403),
            default => response()->json($result, 400),
        };
    }
}
