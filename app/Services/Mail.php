<?php
declare(strict_types=1);
namespace App\Services;

final class Mail
{
    public static function passwordReset(string $email, string $name, string $url): void
    {
        $source = ROOT . '/User Interface/Emails/ui-typography/compiled.html';
        if (!is_file($source)) {
            throw new \RuntimeException('The uploaded Tabler email template is missing.');
        }
        $template = file_get_contents($source);
        preg_match_all('#<style\b[^>]*>(.*?)</style>#si', $template, $matches);
        $styles = implode("\n", $matches[1]);
        $styles = preg_replace('/@font-face\s*\{[^}]*\}|@import[^;]*;/i', '', $styles);
        $styles = preg_replace('/url\([^)]*\)/i', 'none', $styles);
        $brand = Settings::get('brand_name', Config::get('name'));
        ob_start();
        require ROOT . '/app/Views/emails/password-reset.php';
        $body = ob_get_clean();
        $from = Config::get('mail_from');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid mail configuration.');
        }
        $sent = mail($email, 'Reset your Theatre Shop password', $body, [
            'From' => $from, 'MIME-Version' => '1.0', 'Content-Type' => 'text/html; charset=UTF-8',
        ]);
        if (!$sent) {
            throw new \RuntimeException('Password reset email could not be sent. Check cPanel mail delivery.');
        }
    }
}
