<?php
declare(strict_types=1);

/**
 * Upstream base for cargo tracking APIs (no trailing slash).
 * Production default: Railway HTTPS relay (cPanel blocks outbound port 555).
 * Local dev: create upstream-config.local.php with http://217.29.139.44:555 or set SRS_TRACKING_UPSTREAM.
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

    $base = 'https://sr-classic-tracking-relay-production.up.railway.app';
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
