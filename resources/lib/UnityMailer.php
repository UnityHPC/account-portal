<?php

namespace UnityWebPortal\lib;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;
use Twig\TwigFunction;

/**
 * This is a class that uses PHPmailer to send emails based on templates
 */
class UnityMailer
{
    private string $MSG_SENDER_EMAIL;
    private string $MSG_SENDER_NAME;
    private string $MSG_SUPPORT_EMAIL;
    private string $MSG_SUPPORT_NAME;
    private string $MSG_ADMIN_EMAIL;
    private string $MSG_ADMIN_NAME;
    private string $MSG_PI_APPROVAL_EMAIL;
    private string $MSG_PI_APPROVAL_NAME;

    public function __construct()
    {
        $this->MSG_SENDER_EMAIL = CONFIG["mail"]["sender"];
        $this->MSG_SENDER_NAME = CONFIG["mail"]["sender_name"];
        $this->MSG_SUPPORT_EMAIL = CONFIG["mail"]["support"];
        $this->MSG_SUPPORT_NAME = CONFIG["mail"]["support_name"];
        $this->MSG_ADMIN_EMAIL = CONFIG["mail"]["admin"];
        $this->MSG_ADMIN_NAME = CONFIG["mail"]["admin_name"];
        $this->MSG_PI_APPROVAL_EMAIL = CONFIG["mail"]["pi_approve"];
        $this->MSG_PI_APPROVAL_NAME = CONFIG["mail"]["pi_approve_name"];
    }

    public function constructPHPMailer(): PHPMailer
    {
        $mailer = new PHPMailer(exceptions: true);
        $mailer->isSMTP();

        if (empty(CONFIG["smtp"]["host"])) {
            throw new Exception("SMTP server hostname not set");
        }
        $mailer->Host = CONFIG["smtp"]["host"];

        if (empty(CONFIG["smtp"]["port"])) {
            throw new Exception("SMTP server port not set");
        }
        $mailer->Port = CONFIG["smtp"]["port"];

        $security = CONFIG["smtp"]["security"];
        $security_conf_valid = empty($security) || $security == "tls" || $security == "ssl";
        if (!$security_conf_valid) {
            throw new Exception(
                "SMTP security is not set correctly, leave empty, use 'tls', or 'ssl'",
            );
        }
        $mailer->SMTPSecure = $security;

        if (!empty(CONFIG["smtp"]["user"])) {
            $mailer->SMTPAuth = true;
            $mailer->Username = CONFIG["smtp"]["user"];
        } else {
            $mailer->SMTPAuth = false;
        }

        if (!empty(CONFIG["smtp"]["pass"])) {
            $mailer->Password = CONFIG["smtp"]["pass"];
        }

        if (CONFIG["smtp"]["ssl_verify"] == "false") {
            $mailer->SMTPOptions = [
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                    "allow_self_signed" => true,
                ],
            ];
        }
        return $mailer;
    }

    public function constructTwigEnvironment(): \Twig\Environment
    {
        $loader = new \Twig\Loader\FilesystemLoader([
            __DIR__ . "/../../deployment/mail_overrides",
            __DIR__ . "/../mail",
        ]);
        $twig = new \Twig\Environment($loader, ["strict_variables" => true]);
        $functions = [
            new TwigFunction("getRelativeHyperlink", getRelativeHyperlink(...)),
            new TwigFunction("formatHyperlink", formatHyperlink(...)),
            new TwigFunction("throw", fn($x) => throw new Exception($x)),
        ];
        foreach ($functions as $function) {
            $twig->addFunction($function);
        }
        $twig->addGlobal("CONFIG", CONFIG);
        return $twig;
    }

    /**
     * @param string|string[] $recipients
     * @param ?mixed[] $data
     */
    public function sendMail(string|array $recipients, string $template, ?array $data = null): void
    {
        $mailer = $this->constructPHPMailer();

        $twig = $this->constructTwigEnvironment();
        $twig->addFunction(new TwigFunction("setSubject", fn($x) => ($mailer->Subject = $x)));

        $data ??= [];
        $mailer->setFrom($this->MSG_SENDER_EMAIL, $this->MSG_SENDER_NAME);
        $mailer->addReplyTo($this->MSG_SUPPORT_EMAIL, $this->MSG_SUPPORT_NAME);

        $mes_html = $twig->render("$template.html.twig", $data);
        $mailer->msgHTML($mes_html);

        if ($recipients == "admin") {
            $mailer->addBCC($this->MSG_ADMIN_EMAIL, $this->MSG_ADMIN_NAME);
        } elseif ($recipients == "pi_approve") {
            $mailer->addBCC($this->MSG_PI_APPROVAL_EMAIL, $this->MSG_PI_APPROVAL_NAME);
        } else {
            if (is_array($recipients)) {
                foreach ($recipients as $addr) {
                    $mailer->addBCC($addr);
                }
            } else {
                $mailer->addAddress($recipients);
            }
        }
        $mailer->send();
    }
}
