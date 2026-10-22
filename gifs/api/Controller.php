<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/GiphyService.php';

function pc_gifs_dispatch(string $path, string $method): bool
{
    if ($path !== '/api/v1/gifs/search') return false;
    if ($method !== 'GET') {
        header('Allow: GET');
        pc_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
    }
    $user = pc_require_verified_user();
    pc_rate_limit('gif-search', 30, 60);
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
    $result = PCGiphyService::search($query, $limit === false ? 20 : $limit, $offset === false ? 0 : $offset);
    pc_json($result['status'], $result['body']);
}
