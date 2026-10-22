<?php
declare(strict_types=1);

require_once __DIR__ . '/../validators/ReactionValidator.php';
require_once __DIR__ . '/../repositories/ReactionRepository.php';
require_once dirname(__DIR__, 2) . '/notifications/repositories/NotificationRepository.php';

final class PCReactionService
{
    public static function list(int $userId, int $messageId): array
    {
        if ($messageId < 1) return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_message_id']];
        $pdo = pc_db();
        if (!PCReactionRepository::canReact($pdo, $userId, $messageId)) {
            return ['status' => 404, 'body' => ['ok' => false, 'error' => 'message_not_found']];
        }
        return ['status' => 200, 'body' => [
            'ok' => true,
            'message_id' => (string)$messageId,
            'reactions' => PCReactionRepository::listForMessage($pdo, $userId, $messageId),
        ]];
    }

    public static function add(int $userId, int $messageId, string $reaction): array
    {
        if ($messageId < 1 || !PCReactionValidator::validate($reaction)) {
            return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_reaction']];
        }
        $pdo = pc_db();
        if (!PCReactionRepository::canReact($pdo, $userId, $messageId)) {
            return ['status' => 404, 'body' => ['ok' => false, 'error' => 'message_not_found']];
        }
        $senderStmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = ? LIMIT 1');
        $senderStmt->execute([$messageId]);
        $senderId = (int)$senderStmt->fetchColumn();
        PCReactionRepository::add($pdo, $userId, $messageId, $reaction);
        if ($senderId > 0 && $senderId !== $userId) {
            PCNotificationRepository::createForUser($pdo, $senderId, 'reactions', [
                'message_id' => (string)$messageId,
                'user_id' => (string)$userId,
                'reaction' => $reaction,
            ]);
        }
        return ['status' => 200, 'body' => ['ok' => true, 'reaction' => $reaction, 'active' => true]];
    }

    public static function remove(int $userId, int $messageId, string $reaction): array
    {
        if ($messageId < 1 || !PCReactionValidator::validate($reaction)) {
            return ['status' => 422, 'body' => ['ok' => false, 'error' => 'invalid_reaction']];
        }
        $pdo = pc_db();
        if (!PCReactionRepository::canReact($pdo, $userId, $messageId)) {
            return ['status' => 404, 'body' => ['ok' => false, 'error' => 'message_not_found']];
        }
        PCReactionRepository::remove($pdo, $userId, $messageId, $reaction);
        return ['status' => 200, 'body' => ['ok' => true, 'reaction' => $reaction, 'active' => false]];
    }
}
