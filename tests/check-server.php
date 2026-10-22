<?php
declare(strict_types=1);

/*
 * Manual static project check. Run from the repository root:
 *     php tests/check-server.php
 * No GitHub Actions, credentials, or database connection are required.
 */
$root = dirname(__DIR__);
$errors = [];
$checked = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) continue;
    try {
        token_get_all((string)file_get_contents($path), TOKEN_PARSE);
        $checked++;
    } catch (ParseError $error) {
        $errors[] = $path . ': ' . $error->getMessage();
    }
}

$requiredFiles = [
    'bootstrap.php', 'config.example.php', 'public/index.php', 'public/password-reset.php',
    'database/schema.sql', 'database/migrate.php', 'database/cleanup.php',
    'register/api/Controller.php', 'register/services/RegistrationService.php',
    'register/repositories/RegistrationRepository.php', 'register/validators/RegistrationValidator.php',
    'register/mail/mailconfirm/Mailer.php', 'login/api/Controller.php',
    'login/services/LoginService.php', 'login/repositories/LoginRepository.php',
    'profile/api/Controller.php', 'profile/services/ProfileService.php',
    'users/api/Controller.php', 'chats/api/Controller.php', 'messages/api/Controller.php',
    'messages/services/ReactionService.php', 'groups/api/Controller.php',
    'channels/api/Controller.php', 'topics/api/Controller.php', 'topics/services/TopicService.php',
    'files/api/Controller.php', 'files/services/AttachmentService.php',
    'notifications/api/Controller.php', 'search/api/Controller.php',
    'security/api/Controller.php', 'security/services/DeviceService.php',
    'security/services/TwoFactorService.php', 'admin/api/Controller.php',
    'password/api/Controller.php', 'password/services/PasswordResetService.php',
    'calls/api/Controller.php', 'gifs/api/Controller.php', 'gifs/services/GiphyService.php',
    'tests/check-features.php',
];
foreach ($requiredFiles as $relative) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
        $errors[] = 'Missing required file: ' . $relative;
    }
}
if (is_file($root . '/public/setup.php')) {
    $errors[] = 'Obsolete public installer must not be deployed';
}

$schema = (string)file_get_contents($root . '/database/schema.sql');
foreach ([
    'users', 'email_verification_tokens', 'auth_tokens', 'conversations', 'conversation_members',
    'messages', 'user_settings', 'user_devices', 'user_blocks', 'chat_groups', 'group_members',
    'channels', 'channel_members', 'topics', 'topic_messages', 'message_attachments',
    'notifications', 'reports', 'admin_users', 'user_crypto_keys', 'api_rate_limits',
    'friend_requests', 'friendships', 'saved_messages', 'polls', 'poll_options', 'poll_votes',
    'chat_preferences', 'chat_folders', 'chat_folder_items', 'stories', 'story_views',
    'password_reset_tokens', 'message_hidden', 'user_two_factor', 'two_factor_setups',
    'two_factor_challenges', 'user_avatars', 'user_notification_settings', 'calls', 'call_participants', 'call_signals',
] as $table) {
    if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+' . preg_quote($table, '/') . '\b/i', $schema)) {
        $errors[] = 'Missing schema table: ' . $table;
    }
}

$bootstrap = (string)file_get_contents($root . '/bootstrap.php');
if (!str_contains($bootstrap, 'function pc_encrypted_envelope')) {
    $errors[] = 'Missing encrypted-envelope validation';
}
if (!str_contains($bootstrap, 'legacyConfigFile')) {
    $errors[] = 'Legacy private confirm.php configuration support is missing';
}
if (str_contains($bootstrap, 'AND u.phone IS NOT NULL AND u.phone_verified_at IS NOT NULL')) {
    $errors[] = 'API authentication must not require phone verification';
}

$entrySource = (string)file_get_contents($root . '/public/index.php');
foreach (['pc_register_dispatch', 'pc_login_dispatch', 'pc_calls_dispatch', 'pc_gifs_dispatch', 'pc_notifications_dispatch'] as $dispatch) {
    if (!str_contains($entrySource, $dispatch)) $errors[] = 'Main router is missing: ' . $dispatch;
}
if (!str_contains($entrySource, "'schema' => 'ready'") || !str_contains($entrySource, "'schema' => 'incomplete'")) {
    $errors[] = 'Health endpoint must distinguish a ready schema from an incomplete schema';
}
if (str_contains($entrySource, 'textbee') || str_contains($entrySource, 'sms_provider')) {
    $errors[] = 'Main API must not depend on TextBee or mandatory SMS verification';
}

$registrationValidator = (string)file_get_contents($root . '/register/validators/RegistrationValidator.php');
if (!str_contains($registrationValidator, 'first_name_required')) $errors[] = 'Registration must require first name';
if (str_contains($registrationValidator, 'invalid_phone')) $errors[] = 'Registration must not require a phone number';
$loginValidator = (string)file_get_contents($root . '/login/validators/LoginValidator.php');
if (!str_contains($loginValidator, 'device_binding_required')) $errors[] = 'Login must bind sessions to a device key';

$twoFactor = (string)file_get_contents($root . '/security/services/TwoFactorService.php');
if (!str_contains($twoFactor, "Bearer\\s+")) $errors[] = '2FA setup must accept a normal Bearer authorization header';
if (!str_contains($twoFactor, 'sodium_crypto_secretbox')) $errors[] = '2FA secret encryption is missing';

$giphy = (string)file_get_contents($root . '/gifs/services/GiphyService.php');
if (!str_contains($giphy, "pc_config('giphy_api_key'") || !str_contains($giphy, "'preview_url'")) {
    $errors[] = 'GIPHY search must use a private server key and return preview URLs';
}
$configExample = (string)file_get_contents($root . '/config.example.php');
if (!str_contains($configExample, "'giphy_api_key' => ''") || str_contains($configExample, 'textbee_api_key')) {
    $errors[] = 'Example config must include GIPHY and omit obsolete SMS settings';
}

$callSource = (string)file_get_contents($root . '/calls/api/Controller.php');
if (!str_contains($callSource, 'SELECT id FROM conversations WHERE id = ? FOR UPDATE')) {
    $errors[] = 'Call creation must serialize per conversation';
}
$cleanupSource = (string)file_get_contents($root . '/database/cleanup.php');
foreach (['two_factor_challenges', 'call_signals', 'password_reset_tokens', 'auth_tokens'] as $expiredTable) {
    if (!str_contains($cleanupSource, $expiredTable)) $errors[] = 'Cleanup missing expired records: ' . $expiredTable;
}

foreach ([
    'messages/api/Controller.php', 'groups/api/Controller.php',
    'channels/api/Controller.php', 'topics/validators/TopicValidator.php',
] as $relative) {
    $source = (string)file_get_contents($root . '/' . $relative);
    if (!str_contains($source, 'pc_encrypted_envelope')) {
        $errors[] = 'Encrypted-message enforcement missing in: ' . $relative;
    }
}

$notificationRepository = (string)file_get_contents($root . '/notifications/repositories/NotificationRepository.php');
if (!str_contains($notificationRepository, 'createForUser') || !str_contains($notificationRepository, 'preferences_json')) {
    $errors[] = 'Preference-aware notification event creation is missing';
}
foreach ([
    'messages/api/Controller.php' => 'direct_messages',
    'friends/services/FriendService.php' => 'contact_requests',
    'groups/api/Controller.php' => 'group_messages',
    'channels/api/Controller.php' => 'group_messages',
    'messages/services/ReactionService.php' => 'reactions',
] as $relative => $kind) {
    $source = (string)file_get_contents($root . '/' . $relative);
    if (!str_contains($source, "'" . $kind . "'")) $errors[] = 'Notification event is not wired for: ' . $relative;
}

$rateLimitedWrites = [
    'messages/api/Controller.php' => 'message-send',
    'groups/api/Controller.php' => 'group-message-send',
    'channels/api/Controller.php' => 'channel-post-send',
    'topics/api/Controller.php' => 'topic-message-send',
    'files/api/Controller.php' => 'attachment-upload',
];
foreach ($rateLimitedWrites as $relative => $bucket) {
    $source = (string)file_get_contents($root . '/' . $relative);
    if (!str_contains($source, "pc_rate_limit('" . $bucket . "'")) {
        $errors[] = 'Missing write rate limit in: ' . $relative;
    }
}

if ($errors) {
    fwrite(STDERR, "Pluschat server check FAILED (" . count($errors) . " issue(s)):\n");
    foreach ($errors as $error) fwrite(STDERR, " - " . $error . "\n");
    exit(1);
}

echo "Pluschat server check OK. Parsed PHP files: {$checked}. Required modules, security checks and schema tables: OK.\n";
