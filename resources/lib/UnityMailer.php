<?php

namespace UnityWebPortal\lib;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;

class UnityMailerException extends Exception {}

/**
 * This is a class that uses PHPmailer to send emails based on templates
 */
class UnityMailer extends PHPMailer
{
    private string $content_dir = __DIR__ . "/../mail"; // location of all email templates
    private string $override_dir = __DIR__ . "/../../deployment/mail";

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
    }

    /**
     * @param string|string[] $recipients
     * @param ?mixed[] $data
     */
    public function sendMail(string|array $recipients, string $template, ?array $data = null): void
    {
        $this->setFrom($this->MSG_SENDER_EMAIL, $this->MSG_SENDER_NAME);
        $this->addReplyTo($this->MSG_SUPPORT_EMAIL, $this->MSG_SUPPORT_NAME);

        $template_filename = $template . ".php";
        if (file_exists($this->override_dir . "/" . $template_filename)) {
            $template_path = $this->override_dir . "/" . $template_filename;
        } else {
            $template_path = $this->content_dir . "/" . $template_filename;
        }

        if (file_exists($this->override_dir . "/footer.php")) {
            $footer_template_path = $this->override_dir . "/footer.php";
        } else {
            $footer_template_path = $this->content_dir . "/footer.php";
        }

        ob_start();
        include $template_path;
        include $footer_template_path;
        $mes_html = _ob_get_clean();
        $this->msgHTML($mes_html);

        if ($recipients == "admin") {
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

        $output = parent::send();
        if ($output === false) {
            throw new UnityMailerException($this->ErrorInfo);
        }
        $this->clearAllRecipients();
    }
}
