<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/NotificationService.php';

function pc_notifications_dispatch(string $path, string $method): bool
{
    if ($path === '/api/v1/notification-settings' && ($method === 'GET' || $method === 'POST')) {
        $user = pc_require_verified_user();
        if ($method === 'POST') pc_rate_limit('notification-settings', 30, 3600);

        $defaults = [
            'direct_messages' => true,
            'mentions' => true,
            'replies' => true,
            'birthdays' => true,
            'contact_requests' => true,
            'reactions' => true,
            'group_invites' => true,
            'group_messages' => true,
        ];
        $pdo = pc_db();
        $stmt = $pdo->prepare('SELECT preferences_json, preview_mode FROM user_notification_settings WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $saved = $stmt->fetch() ?: [];
        $stored = json_decode((string)($saved['preferences_json'] ?? ''), true);
        $preferences = $defaults;
        if (is_array($stored)) {
            foreach ($defaults as $category => $default) {
                if (is_bool($stored[$category] ?? null)) $preferences[$category] = $stored[$category];
            }
        }
        $previewMode = (string)($saved['preview_mode'] ?? 'full');

        if ($method === 'POST') {
            $data = pc_input();
            if (array_key_exists('categories', $data)) {
                if (!is_array($data['categories'])) pc_json(422, ['ok' => false, 'error' => 'invalid_notification_categories']);
                foreach ($data['categories'] as $category => $enabled) {
                    if (!is_string($category) || !array_key_exists($category, $defaults) || !is_bool($enabled)) {
                        pc_json(422, ['ok' => false, 'error' => 'invalid_notification_category', 'category' => is_string($category) ? $category : 'unknown']);
                    }
                    $preferences[$category] = $enabled;
                }
            }
            if (array_key_exists('preview_mode', $data)) {
                if (!is_string($data['preview_mode']) || !in_array($data['preview_mode'], ['full', 'sender', 'hidden'], true)) {
                    pc_json(422, ['ok' => false, 'error' => 'invalid_notification_preview_mode']);
                }
                $previewMode = $data['preview_mode'];
            }
            if (!array_key_exists('categories', $data) && !array_key_exists('preview_mode', $data)) {
                pc_json(422, ['ok' => false, 'error' => 'notification_settings_required']);
            }
            $json = json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $pdo->prepare(
                'INSERT INTO user_notification_settings (user_id, preferences_json, preview_mode) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE preferences_json = VALUES(preferences_json), preview_mode = VALUES(preview_mode)'
            )->execute([$user['id'], $json, $previewMode]);
        }

        pc_json(200, [
            'ok' => true,
            'categories' => $preferences,
            'preview_mode' => $previewMode,
        ]);
    }

    if ($path === '/api/v1/notifications' && $method === 'GET') {
        $user = pc_require_verified_user();
        pc_json(200, ['ok' => true, 'notifications' => PCNotificationService::list((int)$user['id'], $_GET)]);
    }

    if ($path === '/api/v1/notifications/read-all' && $method === 'POST') {
        $user = pc_require_verified_user();
        $count = PCNotificationRepository::markAllRead(pc_db(), (int)$user['id']);
        pc_json(200, ['ok' => true, 'marked_read' => $count]);
    }

    if (preg_match('#^/api/v1/notifications/([0-9]+)/read$#', $path, $matches) && $method === 'POST') {
        $user = pc_require_verified_user();
        $notificationId = (int)$matches[1];
        if ($notificationId < 1 || !PCNotificationRepository::markRead(pc_db(), (int)$user['id'], $notificationId)) {
            pc_json(404, ['ok' => false, 'error' => 'notification_not_found']);
        }
        pc_json(200, ['ok' => true, 'read' => true]);
    }

    return false;
}
