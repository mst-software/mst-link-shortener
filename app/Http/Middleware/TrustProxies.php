<?php

namespace App\Http\Middleware;

use App\Support\TrustedProxies;
use Closure;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trusts the proxies of TRUSTED_PROXIES (see config/trustedproxy.php) so Request::ip(), isSecure() and the
 * host reflect the visitor. A client that connects directly cannot spoof its address with X-Forwarded-For.
 *
 * For a request that really comes from a trusted Cloudflare edge, the visitor address is CF-Connecting-IP
 * and X-Forwarded-Host is dropped, because Cloudflare passes a client supplied X-Forwarded-Host through.
 */
class TrustProxies extends BaseTrustProxies
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request::setTrustedProxies([], $this->getTrustedHeaderNames());

        if (TrustedProxies::isFromTrustedCloudflare($request)) {
            $this->useCloudflareHeaders($request);
        }

        $proxies = TrustedProxies::proxies();

        if ($proxies === '*') {
            $this->setTrustedProxyIpAddressesToTheCallingIp($request);
        } elseif ($proxies !== []) {
            $this->setTrustedProxyIpAddressesToSpecificIps($request, $proxies);
        }

        return $next($request);
    }

    private function useCloudflareHeaders(Request $request): void
    {
        $request->headers->remove('X-Forwarded-Host');

        $visitorIp = trim((string) $request->headers->get('CF-Connecting-IP'));

        if (filter_var($visitorIp, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $visitorIp);
        }
    }
}
