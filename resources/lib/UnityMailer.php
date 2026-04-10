<?php

namespace UnityWebPortal\lib;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;
use Twig\TwigFunction;

class UnityMailerException extends Exception {}

/**
 * This is a class that uses PHPmailer to send emails based on templates
 */
class UnityMailer extends PHPMailer
{
    private string $MSG_SENDER_EMAIL;
    private string $MSG_SENDER_NAME;
    private string $MSG_SUPPORT_EMAIL;
    private string $MSG_SUPPORT_NAME;
    private string $MSG_ADMIN_EMAIL;
    private string $MSG_ADMIN_NAME;
    private string $MSG_PI_APPROVAL_EMAIL;
    private string $MSG_PI_APPROVAL_NAME;
    private string $MSG_RECIPIENT_OVERRIDE;
    private string $MSG_RECIPIENT_OVERRIDE_NAME;

    private \Twig\Loader\FilesystemLoader $loader;
    private \Twig\Environment $twig;

    public function __construct()
    {
        parent::__construct();
        $this->isSMTP();

        $this->MSG_SENDER_EMAIL = CONFIG["mail"]["sender"];
        $this->MSG_SENDER_NAME = CONFIG["mail"]["sender_name"];
        $this->MSG_SUPPORT_EMAIL = CONFIG["mail"]["support"];
        $this->MSG_SUPPORT_NAME = CONFIG["mail"]["support_name"];
        $this->MSG_ADMIN_EMAIL = CONFIG["mail"]["admin"];
        $this->MSG_ADMIN_NAME = CONFIG["mail"]["admin_name"];
        $this->MSG_PI_APPROVAL_EMAIL = CONFIG["mail"]["pi_approve"];
        $this->MSG_PI_APPROVAL_NAME = CONFIG["mail"]["pi_approve_name"];
        $this->MSG_RECIPIENT_OVERRIDE = CONFIG["mail"]["recipient_override"] ?? "";
        $this->MSG_RECIPIENT_OVERRIDE_NAME = CONFIG["mail"]["recipient_override_name"] ?? "";
        if (empty(CONFIG["smtp"]["host"])) {
            throw new Exception("SMTP server hostname not set");
        }
        $this->Host = CONFIG["smtp"]["host"];

        if (empty(CONFIG["smtp"]["port"])) {
            throw new Exception("SMTP server port not set");
        }
        $this->Port = CONFIG["smtp"]["port"];

        $security = CONFIG["smtp"]["security"];
        $security_conf_valid = empty($security) || $security == "tls" || $security == "ssl";
        if (!$security_conf_valid) {
            throw new Exception(
                "SMTP security is not set correctly, leave empty, use 'tls', or 'ssl'",
            );
        }
        $this->SMTPSecure = $security;

        if (!empty(CONFIG["smtp"]["user"])) {
            $this->SMTPAuth = true;
            $this->Username = CONFIG["smtp"]["user"];
        } else {
            $this->SMTPAuth = false;
        }

        if (!empty(CONFIG["smtp"]["pass"])) {
            $this->Password = CONFIG["smtp"]["pass"];
        }

        if (CONFIG["smtp"]["ssl_verify"] == "false") {
            $this->SMTPOptions = [
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                    "allow_self_signed" => true,
                ],
            ];
        }

        $this->loader = new \Twig\Loader\FilesystemLoader(UnityDeployment::getMailDirs());
        $this->twig = new \Twig\Environment($this->loader, ["strict_variables" => true]);
        $functions = [
            new TwigFunction("setSubject", fn($x) => ($this->Subject = $x)),
            new TwigFunction("getRelativeHyperlink", getRelativeHyperlink(...)),
            new TwigFunction("formatHyperlink", formatHyperlink(...)),
            new TwigFunction("uniqid", uniqid(...)),
            new TwigFunction("errorLog", UnityHTTPD::errorLog(...)),
        ];
        foreach ($functions as $function) {
            $this->twig->addFunction($function);
        }
        $this->twig->addGlobal("CONFIG", CONFIG);
    }

    /**
     * @param string|string[] $recipients
     * @param ?mixed[] $data
     */
    public function sendMail(string|array $recipients, string $template, ?array $data = null): void
    {
        $data ??= [];
        $this->setFrom($this->MSG_SENDER_EMAIL, $this->MSG_SENDER_NAME);
        $this->addReplyTo($this->MSG_SUPPORT_EMAIL, $this->MSG_SUPPORT_NAME);

        $mes_html = "";

        if ($this->MSG_RECIPIENT_OVERRIDE !== "") {
            $this->addBCC($this->MSG_RECIPIENT_OVERRIDE, $this->MSG_RECIPIENT_OVERRIDE_NAME);
            $recipients_str = is_array($recipients) ? _json_encode($recipients) : $recipients;
            $mes_html .= implode("\n", [
                "<p>",
                "This message has been diverted away from its original recipient(s):",
                htmlspecialchars($recipients_str),
                "</p>",
            ]);
        } elseif ($recipients == "admin") {
            $this->addBCC($this->MSG_ADMIN_EMAIL, $this->MSG_ADMIN_NAME);
        } elseif ($recipients == "pi_approve") {
            $this->addBCC($this->MSG_PI_APPROVAL_EMAIL, $this->MSG_PI_APPROVAL_NAME);
        } else {
            if (is_array($recipients)) {
                foreach ($recipients as $addr) {
                    $this->addBCC($addr);
                }
            } else {
                $this->addAddress($recipients);
            }
        }

        $mes_html .= $this->twig->render("$template.html.twig", $data);
        $this->msgHTML($mes_html);

        $output = parent::send();
        if ($output === false) {
            throw new UnityMailerException($this->ErrorInfo);
        }
        $this->clearAllRecipients();
    }
}
