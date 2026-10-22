<?php
declare(strict_types=1);

/* Run from the project root using PHP CLI, e.g. daily via the host scheduler. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/notifications/repositories/NotificationRepository.php';

$pdo = pc_db();

$birthdaySql = "SELECT DISTINCT r.id AS recipient_id, b.id AS birthday_user_id
    FROM users b
    JOIN user_settings bs ON bs.user_id = b.id
    JOIN friendships f ON f.friend_id = b.id
    JOIN users r ON r.id = f.user_id
    WHERE r.id <> b.id
      AND b.email_verified_at IS NOT NULL
      AND b.birth_date IS NOT NULL
      AND DATE_FORMAT(b.birth_date, '%m-%d') = DATE_FORMAT(UTC_DATE(), '%m-%d')
      AND bs.show_birth_date = 1
      AND bs.profile_visibility <> 'nobody'
      AND NOT EXISTS (SELECT 1 FROM user_blocks x WHERE (x.user_id = r.id AND x.blocked_user_id = b.id) OR (x.user_id = b.id AND x.blocked_user_id = r.id))
      AND NOT EXISTS (
          SELECT 1 FROM notifications n
          WHERE n.user_id = r.id AND n.kind = 'birthdays'
            AND JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.birthday_user_id')) = CAST(b.id AS CHAR)
            AND JSON_UNQUOTE(JSON_EXTRACT(n.payload, '$.birthday_date')) = DATE_FORMAT(UTC_DATE(), '%Y-%m-%d')
      )";
$birthdayStmt = $pdo->query($birthdaySql);
$birthdayCount = 0;
foreach ($birthdayStmt->fetchAll() as $birthday) {
    PCNotificationRepository::createForUser($pdo, (int)$birthday['recipient_id'], 'birthdays', [
        'birthday_user_id' => (string)$birthday['birthday_user_id'],
        'birthday_date' => gmdate('Y-m-d'),
    ]);
    $birthdayCount++;
}
echo 'Birthday reminders considered: ' . $birthdayCount . PHP_EOL;
$jobs = [
    'expired stories' => 'DELETE FROM stories WHERE expires_at <= UTC_TIMESTAMP()',
    'expired password reset tokens' => 'DELETE FROM password_reset_tokens WHERE expires_at <= UTC_TIMESTAMP()',
    'expired email verification tokens' => 'DELETE FROM email_verification_tokens WHERE expires_at <= UTC_TIMESTAMP()',
    'expired 2FA setup records' => 'DELETE FROM two_factor_setups WHERE expires_at <= UTC_TIMESTAMP()',
    'expired 2FA login challenges' => 'DELETE FROM two_factor_challenges WHERE expires_at <= UTC_TIMESTAMP()',
    'expired call signaling records' => 'DELETE FROM call_signals WHERE expires_at <= UTC_TIMESTAMP()',
    'expired ringing calls' => "UPDATE calls SET status = 'missed', ended_at = UTC_TIMESTAMP() WHERE status = 'ringing' AND expires_at <= UTC_TIMESTAMP()",
    'expired active calls' => "UPDATE calls SET status = 'ended', ended_at = UTC_TIMESTAMP() WHERE status = 'active' AND expires_at <= UTC_TIMESTAMP()",
    'expired access tokens' => 'DELETE FROM auth_tokens WHERE expires_at <= UTC_TIMESTAMP()',
    'old rate-limit buckets' => 'DELETE FROM api_rate_limits WHERE window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)',
];
$total = 0;
foreach ($jobs as $label => $sql) {
    $count = $pdo->exec($sql);
    $count = $count === false ? 0 : $count;
    $total += $count;
    echo $label . ': ' . $count . PHP_EOL;
}
echo 'Cleanup complete; rows removed: ' . $total . PHP_EOL;
