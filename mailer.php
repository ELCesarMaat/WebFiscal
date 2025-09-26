<?php
// mailer.php - Capa de abstracción para enviar correos usando PHPMailer si está disponible
// Requiere: composer require phpmailer/phpmailer  (o incluir manualmente la librería)

// Uso:
// require 'mailer.php';
// send_app_mail([
//   'to' => 'destinatario@dominio.com',
//   'subject' => 'Asunto',
//   'text' => 'Texto plano',
//   'html' => '<b>HTML</b>',
//   'reply_to' => 'alguien@dominio.com'
// ]);

function send_app_mail(array $opts) {
    $config = require __DIR__ . '/config.php';
    $smtp = $config['smtp'] ?? ['enabled' => false];
    $allowFallback = $smtp['allow_fallback'] ?? false; // si false y falla SMTP => no usar mail()
    $GLOBALS['MAIL_LAST_ERROR'] = null;

    $to        = $opts['to']        ?? null;
    $subject   = $opts['subject']   ?? '';
    $text      = $opts['text']      ?? '';
    $html      = $opts['html']      ?? '';
    $replyTo   = $opts['reply_to']  ?? null;
    $fromAddr  = $smtp['from_address'] ?? ('no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $fromName  = $smtp['from_name'] ?? 'MEDLEX';

    if (!$to) return false;

    // Sanitizar
    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    // Fallback a correo simple si no hay PHPMailer o SMTP deshabilitado
    $useSmtp = !empty($smtp['enabled']);

    // Intentar cargar PHPMailer
    $phpmailerAvailable = false;
    if ($useSmtp) {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $phpmailerAvailable = true;
        } else {
            // intentar autoload de composer
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) $phpmailerAvailable = true;
            }
        }
    }

    if ($phpmailerAvailable) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($to);
            if ($replyTo) $mail->addReplyTo($replyTo);

            if ($useSmtp) {
                $mail->isSMTP();
                $mail->Host       = $smtp['host'];
                $mail->SMTPAuth   = $smtp['auth'];
                $mail->Username   = $smtp['user'];
                $mail->Password   = $smtp['pass'];
                if (!empty($smtp['secure'])) $mail->SMTPSecure = $smtp['secure'];
                $mail->Port       = $smtp['port'];
            }

            if ($html) {
                $mail->isHTML(true);
                $mail->Subject = $subject;
                // Embebidos CID (imágenes) si se suministra 'embed'
                $embeds = $opts['embed'] ?? [];
                if (is_array($embeds) && !empty($embeds)) {
                    foreach ($embeds as $emb) {
                        if (!empty($emb['path']) && !empty($emb['cid']) && file_exists($emb['path'])) {
                            try {
                                $mail->addEmbeddedImage($emb['path'], $emb['cid'], basename($emb['path']));
                            } catch (Throwable $ie) {
                                // registrar pero continuar
                                @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tEMBED_FAIL\t".$emb['path']."\t".$ie->getMessage()."\n", FILE_APPEND);
                            }
                        } else {
                            @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tEMBED_SKIP\t".($emb['path']??'')."\n", FILE_APPEND);
                        }
                    }
                }
                $mail->Body    = $html;
                $mail->AltBody = $text ?: strip_tags($html);
            } else {
                $mail->Subject = $subject;
                $mail->Body    = $text;
            }

            $ok = $mail->send();
            @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tPHPMailer\tTO={$to}\tRESULT=".($ok?'OK':'FAIL')."\n", FILE_APPEND);
            if (!$ok) {
                $GLOBALS['MAIL_LAST_ERROR'] = 'PHPMailer devolvió FAIL sin excepción.';
            }
            return $ok;
        } catch (Throwable $e) {
            $GLOBALS['MAIL_LAST_ERROR'] = 'Excepción PHPMailer: '.$e->getMessage();
            @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tPHPMailer_EXCEPTION\t".$e->getMessage()."\n", FILE_APPEND);
            if (!$allowFallback) return false; // no continuar a mail() si SMTP debía usarse
        }
    } elseif ($useSmtp) {
        // SMTP habilitado pero no disponible PHPMailer
        $msg = 'SMTP habilitado pero PHPMailer no está instalado (composer require phpmailer/phpmailer).';
        $GLOBALS['MAIL_LAST_ERROR'] = $msg;
        @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tPHPMailer_MISSING\t{$msg}\n", FILE_APPEND);
        if (!$allowFallback) return false;
    }

    // Llegamos aquí si: a) SMTP deshabilitado o b) permitido fallback explícitamente
    $boundary = 'bndry_' . bin2hex(random_bytes(6));
    $headers  = "From: {$fromName} <{$fromAddr}>\r\n";
    if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if ($html) {
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $parts = [];
        $parts[] = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . ($text ?: strip_tags($html)) . "\r\n";
        $parts[] = "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n";
        $parts[] = "--{$boundary}--\r\n";
        $body = implode('', $parts);
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body = $text;
    }

    $result = mail($to, $subject, $body, $headers);
    if (!$result) {
        $GLOBALS['MAIL_LAST_ERROR'] = $GLOBALS['MAIL_LAST_ERROR'] ?: 'mail() devolvió FALSE (sin servidor local).';
    }
    @file_put_contents(__DIR__.'/email_log.txt', date('Y-m-d H:i:s')."\tMAIL()_FALLBACK\tTO={$to}\tRESULT=".($result?'OK':'FAIL')."\n", FILE_APPEND);
    return $result;
}
