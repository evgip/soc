<?php

declare(strict_types=1);

namespace App\Modules\Mail\Core;

use W3a\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Mailer - отправка email через PHPMailer (SMTP).
 * 
 * ✅ ИЗМЕНЕНО: Класс теперь нестатический, Logger внедряется через конструктор.
 */
class Mailer
{
    private Logger $logger;
    private array $config;

    /**
     * ✅ Конструктор с инъекцией Logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        
        // Загружаем конфиг модуля Mail
        $configFile = dirname(__DIR__) . '/Config/config.php';
        $this->config = file_exists($configFile) ? require $configFile : [];
    }

    /**
     * Отправить HTML-письмо
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        // Диагностика
        if (empty($this->config)) {
            $this->logger->error("Mailer: Config file not found or empty");
            return false;
        }

        // Режим тестирования
        if (!empty($this->config['pretend']) && $this->config['pretend'] === true) {
            $this->logger->info("PRETEND MAIL TO [{$to}] | Subject: {$subject} | Body: " . strip_tags($htmlBody));
            return true;
        }

        // Fallback, если SMTP не настроен
        if (empty($this->config['username']) || $this->config['username'] === 'your-login@yandex.ru') {
            $this->logger->warning("Mailer Warning: Real SMTP not configured. Falling back to local logging simulation loop.");
            $this->logger->info("SIMULATED MAIL TO [{$to}] | Subject: {$subject} | Body: " . strip_tags($htmlBody));
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = $this->config['auth'] ?? true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port       = (int)$this->config['port'];
            $mail->CharSet    = 'utf-8';

            $this->logger->info("Mailer: Attempting to send via {$this->config['host']}:{$this->config['port']} as {$this->config['username']}");

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            $this->logger->error("PHPMailer Critical Delivery Exception: Mail to [{$to}] failed. Error info: " . $mail->ErrorInfo);
            return false;
        }
    }
}