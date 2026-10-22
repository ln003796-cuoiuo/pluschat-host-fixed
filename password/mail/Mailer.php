<?php
declare(strict_types=1);

final class PCPasswordResetMailer
{
    public static function send(string $email, string $username, string $link): bool
    {
        $parts = parse_url($link);
        if (!is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['https', 'http'], true)
            || empty($parts['host'])) {
            return false;
        }

        $from = trim((string)pc_config('mail_from', 'register@xn--80avljg2a1c.xn--p1ai'));
        $from = preg_replace('/[\\r\\n<>\\s]+/', '', $from) ?? '';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $from = 'register@xn--80avljg2a1c.xn--p1ai';
        }

        $safeName = trim(preg_replace('/[\\r\\n]+/', '', $username) ?? '');
        if ($safeName === '') $safeName = 'друг';
        $safeNameHtml = htmlspecialchars($safeName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLinkHtml = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Восстановление доступа к Pluschat';
        $plain = "Здравствуйте, {$safeName}!\n\nМы получили запрос на восстановление пароля Pluschat. Откройте ссылку, чтобы задать новый пароль:\n{$link}\n\nСсылка действует один час и может быть использована один раз. Если вы не запрашивали восстановление, просто проигнорируйте это письмо.";
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#171923;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f5f8;padding:32px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#fff;border:1px solid #e4e7ed;border-radius:20px;overflow:hidden;">'
            . '<tr><td style="padding:26px 30px;background:#111318;color:#fff;font-size:22px;font-weight:700;">+&nbsp; Pluschat</td></tr>'
            . '<tr><td style="padding:30px 30px 14px;"><p style="margin:0 0 12px;font-size:12px;letter-spacing:1.2px;color:#697080;">ВОССТАНОВЛЕНИЕ ДОСТУПА</p>'
            . '<h1 style="margin:0 0 14px;font-size:26px;line-height:1.25;">Привет, ' . $safeNameHtml . '!</h1>'
            . '<p style="margin:0;font-size:16px;line-height:1.65;color:#4d5361;">Нажми на кнопку ниже, чтобы создать новый пароль для своего аккаунта.</p></td></tr>'
            . '<tr><td style="padding:16px 30px 26px;"><a href="' . $safeLinkHtml . '" style="display:inline-block;padding:14px 22px;border-radius:12px;background:#171923;color:#fff;text-decoration:none;font-weight:700;">Восстановить пароль</a>'
            . '<p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#626978;">Ссылка действует <strong>1 час</strong> и используется один раз. Если кнопка не открывается, скопируй эту ссылку в браузер:<br><span style="overflow-wrap:anywhere;">' . $safeLinkHtml . '</span></p></td></tr>'
            . '<tr><td style="padding:0 30px 28px;"><div style="height:1px;background:#eceef2;"></div><p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#858b98;">Если ты не запрашивал восстановление пароля, ничего делать не нужно. Старый пароль останется действовать, пока ты не завершишь восстановление.</p></td></tr>'
            . '</table><p style="margin:18px 0 0;font-size:11px;color:#969ba6;">Pluschat · Личное общение. Больше контроля.</p>'
            . '</td></tr></table></body></html>';

        $boundary = 'pc_' . bin2hex(random_bytes(16));
        $headers = [
            'From: Pluschat <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Pluschat PHP',
        ];
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plain . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . '--' . $boundary . "--\r\n";

        return @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }
}
