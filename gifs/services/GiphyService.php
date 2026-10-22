<?php
declare(strict_types=1);

final class PCGiphyService
{
    public static function search(string $query, int $limit, int $offset): array
    {
        $apiKey = trim((string)pc_config('giphy_api_key', ''));
        if ($apiKey === '') {
            return ['status' => 503, 'body' => ['ok' => false, 'error' => 'gif_search_not_configured']];
        }
        $query = trim($query);
        if ($query === '' || pc_len($query) > 80) {
            return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_search_query']];
        }
        $limit = max(1, min(25, $limit));
        $offset = max(0, min(499, $offset));
        $url = 'https://api.giphy.com/v1/gifs/search?' . http_build_query([
            'api_key' => $apiKey,
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
            'rating' => 'pg-13',
            'lang' => 'ru',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = false;
        $status = 0;
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'Pluschat/1.0',
            ]);
            $response = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: Pluschat/1.0\r\n",
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $response = @file_get_contents($url, false, $context);
            foreach (($http_response_header ?? []) as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int)$match[1];
                    break;
                }
            }
        }

        if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
            error_log('[Pluschat GIPHY] Provider request failed with HTTP ' . $status);
            return ['status' => 502, 'body' => ['ok' => false, 'error' => 'gif_provider_unavailable']];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
            return ['status' => 502, 'body' => ['ok' => false, 'error' => 'gif_provider_invalid_response']];
        }

        $items = [];
        foreach ($decoded['data'] as $gif) {
            if (!is_array($gif)) continue;
            $images = is_array($gif['images'] ?? null) ? $gif['images'] : [];
            $preview = $images['fixed_width_small']['url'] ?? $images['preview_gif']['url'] ?? null;
            $original = $images['fixed_width']['url'] ?? $images['original']['url'] ?? null;
            if (!is_string($preview) || !is_string($original)
                || !str_starts_with($preview, 'https://') || !str_starts_with($original, 'https://')) {
                continue;
            }
            $fixed = is_array($images['fixed_width'] ?? null) ? $images['fixed_width'] : [];
            $items[] = [
                'id' => (string)($gif['id'] ?? ''),
                'title' => is_string($gif['title'] ?? null) ? mb_substr_safe($gif['title'], 120) : '',
                'url' => $original,
                'preview_url' => $preview,
                'width' => (int)($fixed['width'] ?? 0),
                'height' => (int)($fixed['height'] ?? 0),
            ];
        }
        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'query' => $query,
                'results' => $items,
                'count' => count($items),
                'next_offset' => $offset + count($items),
            ],
        ];
    }
}

function mb_substr_safe(string $value, int $length): string
{
    if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
    return substr($value, 0, $length);
}
