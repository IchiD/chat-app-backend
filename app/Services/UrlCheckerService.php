<?php

namespace App\Services;

class UrlCheckerService
{
  /**
   * Check if a URL is safe to request.
   */
  public function isAllowed(string $url): bool
  {
    // Deny file scheme
    if (preg_match('/^file:\/\//i', $url)) {
      return false;
    }

    // Disallow URLs containing user info which may bypass proxies
    if (str_contains($url, '@')) {
      return false;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
      return false;
    }

    $host = trim($parts['host'], '[]');
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

    if (!$ip) {
      return false;
    }

    // Block metadata IP explicitly
    if ($ip === '169.254.169.254') {
      return false;
    }

    // Filter out private and reserved ranges
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
      return false;
    }

    return true;
  }
}
