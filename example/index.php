<?php

namespace Raptor\Example;

/* DEV: v2.2021.08.20
 * 
 * This is an example script!
 */

\define('CODESAUR_DEVELOPMENT', true);

\ini_set('display_errors', 'On');
\error_reporting(\E_ALL);

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel;

use codesaur\Http\Message\ServerRequest;
use codesaur\Http\Client\Mail;

use Raptor\Application;
use Indoraptor\IndoApplication;
use Indoraptor\Logger\LoggerModel;

$autoload = require_once '../vendor/autoload.php';

$server = (new ServerRequest())->initFromGlobal();

$indo = new IndoApplication();
$indo->INTERNAL('/send/mail', function (ServerRequestInterface $request)
{
    $is_development = \defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT;
    try {
        $context = [];
        $payload = $request->getParsedBody();
        if (!isset($payload['subject'])
            || !isset($payload['message'])
            || (!isset($payload['to']) && !isset($payload['recipients']))
        ) {
            throw new \Exception('Invalid Request');
        }
        
        $mail = new Mail();
        $mail->setSubject($payload['subject']);
        $mail->setMessage($payload['message']);        
        if (isset($payload['to'])) {
            $mail->targetTo($payload['to'], $payload['name'] ?? '');
        }
        if (isset($payload['recipients'])
            && \is_array($payload['recipients'])
        ) {
            foreach ($payload['recipients'] as $type => $recipients) {
                if (\is_array($recipients)) {
                    foreach ($recipients as $recipient) {
                        try {
                            switch ($type) {
                                case 'To': $mail->addRecipient($recipient['email'] ?? 'null', $recipient['name'] ?? '');
                                    break;
                                case 'Cc': $mail->addCCRecipient($recipient['email'] ?? 'null', $recipient['name'] ?? '');
                                    break;
                                case 'Bcc': $mail->addBCCRecipient($recipient['email'] ?? 'null', $recipient['name'] ?? '');
                                    break;
                            }
                        } catch (\Throwable $e) {
                            if ($is_development) {
                                \error_log($e->getMessage());
                            }
                        }
                    }
                }
            }
        }
        if (isset($payload['attachments'])
            && \is_array($payload['attachments'])
        ) {
            foreach ($payload['attachments'] as $attachment) {
                $mail->addAttachment($attachment);
            }
        }        
        if (empty($payload['from'])) {
            $mail->setFrom('codesaur+noreply@gmail.com'); // THIS IS SENDER EMAIL!
        } else {
            $mail->setFrom($payload['from'], $payload['from_name'] ?? '');
        }
        if (!empty($payload['reply_to'])) {
            $mail->setReplyTo($payload['reply_to'], $payload['reply_to_name'] ?? '');
        }
        
        if (!$mail->send()) {
            throw new \RuntimeException('Email sending failed!');
        }
        
        $level = LogLevel::NOTICE;
        $context['status']  = 'success';
        $context['message'] = 'Email successfully sent to destination';
    } catch (\Throwable $e) {
        if ($is_development) {
            \error_log($e->getMessage());
        }
        
        $level = LogLevel::ERROR;
        $context['status']  = 'error';
        $context['code'] = $e->getCode();
        $context['message'] = $e->getMessage();
    } finally {
        echo \json_encode($context) ?: '{}';
        
        $pdo = $request->getAttribute('pdo');
        if (!$pdo instanceof \PDO) {
            return;
        }
        $logger = new LoggerModel($pdo);
        $logger->setTable('mailer', $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
        $to = $payload['to'] ?? '';
        $name = $payload['name'] ?? '';
        $subject = $payload['subject'] ?? 'Unknown message';
        $logger->log(
            $level,
            "$name - [$to] - $subject",
            $context + ['remote_addr' => $request->getServerParams()['REMOTE_ADDR'] ?? null]
        );
    }
});

(new Application())->handle($server->withAttribute('indo', $indo));
