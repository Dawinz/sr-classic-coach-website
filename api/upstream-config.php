<?php
declare(strict_types=1);

/**
 * Upstream base for cargo tracking APIs (no trailing slash).
 * Default: direct API server. Override on cPanel if port 555 is blocked — see upstream-config.local.php.example
 */
function trackingUpstreamBase(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $localFile = __DIR__ . '/upstream-config.local.php';
    if (is_readable($localFile)) {
        $override = include $localFile;
        if (is_string($override) && $override !== '') {
            $base = rtrim($override, '/');
            return $base;
        }
    }

    $env = getenv('SRS_TRACKING_UPSTREAM');
    if (is_string($env) && $env !== '') {
        $base = rtrim($env, '/');
        return $base;
    }

    $base = 'http://217.29.139.44:555';
    return $base;
}

function trackingMobileAppBase(): string
{
    return trackingUpstreamBase() . '/mobile_app';
}

function trackingPosAppBase(): string
{
    return trackingUpstreamBase() . '/pos_phone_app';
}
