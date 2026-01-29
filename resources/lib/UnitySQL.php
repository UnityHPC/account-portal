<?php

namespace UnityWebPortal\lib;

use PDO;

enum UserExpiryWarningType: string
{
    case IDLELOCK = "idlelock";
    case DISABLE = "disable";
}

/**
 * @phpstan-type account_deletion_request array{timestamp: string, uid: string}
 * @phpstan-type user_last_login array{operator: string, last_login: string}
 * @phpstan-type request array{request_for: string, uid: string, timestamp: string}
 */
class UnitySQL
{
    private const string TABLE_REQS = "requests";
    private const string TABLE_AUDIT_LOG = "audit_log";
    private const string TABLE_ACCOUNT_DELETION_REQUESTS = "account_deletion_requests";
    private const string TABLE_USER_LAST_LOGINS = "user_last_logins";
    private const string TABLE_USER_EXPIRY = "user_expiry";
    // FIXME this string should be changed to something more intuitive, requires production change
    public const string REQUEST_BECOME_PI = "admin";

    private PDO $conn;

    public function __construct()
    {
        $this->conn = new PDO(
            "mysql:host=" . CONFIG["sql"]["host"] . ";dbname=" . CONFIG["sql"]["dbname"],
            CONFIG["sql"]["user"],
            CONFIG["sql"]["pass"],
        );
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getConn(): PDO
    {
        return $this->conn;
    }

    //
    // requests table methods
    //
    public function addRequest(string $requestor, string $dest): void
    {
        if ($this->requestExists($requestor, $dest)) {
            return;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO " . self::TABLE_REQS . " (uid, request_for) VALUES (:uid, :request_for)",
        );
        $stmt->bindParam(":uid", $requestor);
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
    }

    public function removeRequest(string $requestor, string $dest): void
    {
        if (!$this->requestExists($requestor, $dest)) {
            return;
        }

        $stmt = $this->conn->prepare(
            "DELETE FROM " . self::TABLE_REQS . " WHERE uid=:uid and request_for=:request_for",
        );
        $stmt->bindParam(":uid", $requestor);
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
    }

    public function removeRequests(string $dest): void
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM " . self::TABLE_REQS . " WHERE request_for=:request_for",
        );
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
    }

    /**
     * @throws \Exception
     * @return request
     */
    public function getRequest(string $user, string $dest): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . self::TABLE_REQS . " WHERE uid=:uid and request_for=:request_for",
        );
        $stmt->bindParam(":uid", $user);
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            throw new \Exception("no such request: uid='$user' request_for='$dest'");
        }
        if (count($result) > 1) {
            throw new \Exception("multiple requests for uid='$user' request_for='$dest'");
        }
        return $result[0];
    }

    public function requestExists(string $requestor, string $dest): bool
    {
        try {
            $this->getRequest($requestor, $dest);
            return true;
            // FIXME use a specific exception
        } catch (\Exception) {
            return false;
        }
    }

    /** @return request[] */
    public function getAllRequests(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_REQS);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return request[] */
    public function getRequests(string $dest): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . self::TABLE_REQS . " WHERE request_for=:request_for",
        );
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return request[] */
    public function getRequestsByUser(string $user): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_REQS . " WHERE uid=:uid");
        $stmt->bindParam(":uid", $user);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function deleteRequestsByUser(string $user): void
    {
        $stmt = $this->conn->prepare("DELETE FROM " . self::TABLE_REQS . " WHERE uid=:uid");
        $stmt->bindParam(":uid", $user);
        $stmt->execute();
    }

    public function addLog(string $action_type, string $recipient): void
    {
        $table = self::TABLE_AUDIT_LOG;
        $stmt = $this->conn->prepare(
            "INSERT INTO $table (operator, operator_ip, action_type, recipient)
            VALUE (:operator, :operator_ip, :action_type, :recipient)",
        );
        $stmt->bindValue(":operator", $_SESSION["OPERATOR"] ?? "");
        $stmt->bindValue(":operator_ip", $_SESSION["OPERATOR_IP"] ?? "");
        $stmt->bindParam(":action_type", $action_type);
        $stmt->bindParam(":recipient", $recipient);
        $stmt->execute();
    }

    public function addAccountDeletionRequest(string $uid): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO " . self::TABLE_ACCOUNT_DELETION_REQUESTS . " (uid) VALUE (:uid)",
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

    public function accDeletionRequestExists(string $uid): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . self::TABLE_ACCOUNT_DELETION_REQUESTS . " WHERE uid=:uid",
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
        return count($stmt->fetchAll()) > 0;
    }

    public function deleteAccountDeletionRequest(string $uid): void
    {
        if (!$this->accDeletionRequestExists($uid)) {
            return;
        }
        $stmt = $this->conn->prepare(
            "DELETE FROM " . self::TABLE_ACCOUNT_DELETION_REQUESTS . " WHERE uid=:uid",
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

    /** @return account_deletion_request[] */
    public function getAllAccountDeletionRequests(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_ACCOUNT_DELETION_REQUESTS);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * returns an array where each key is a UID and each value is another array
     * where each key is a warning type and each value is a sorted list of
     * day numbers when a warning was sent
     *
     * day numbers count up from the last day that the user logged in,
     * so on day 5 the user has been idle for 5 days
     * for example, a user might get idlelock warnings on day numbers 180, 200, 219
     * before actually getting idlelocked on day 220
     * in this case, the output would be:
     * ['username_here' => ['idlelock' => [180, 200, 219], 'disable' => []], ...]
     * @return array<string, array<string, int[]>>
     */
    public function getAllUsersExpirationWarningDaysSent(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_USER_EXPIRY);
        $stmt->execute();
        $records = $stmt->fetchAll();
        $output = [];
        foreach ($records as $record) {
            $uid = $record["uid"];
            $idlelock = _json_decode($record["idlelock_warning_days_sent"]);
            $disable = _json_decode($record["disable_warning_days_sent"]);
            $output[$uid] = ["idlelock" => $idlelock, "disable" => $disable];
        }
        return $output;
    }

    /**
     * example output: ['idlelock' => [1,2,3], 'disable' => [4,5,6]]
     * @return array<string, int[]>
     */
    public function getUserExpirationWarningDaysSent(string $uid): array
    {
        $stmt = $this->conn->prepare(
            sprintf("SELECT * FROM %s WHERE uid=:uid", self::TABLE_USER_EXPIRY),
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
        $records = $stmt->fetchAll();
        switch (count($records)) {
            case 0:
                return ["idlelock" => [], "disable" => []];
            case 1:
                $record = $records[0];
                $uid = $record["uid"];
                $idlelock = _json_decode($record["idlelock_warning_days_sent"]);
                $disable = _json_decode($record["disable_warning_days_sent"]);
                return ["idlelock" => $idlelock, "disable" => $disable];
            default:
                throw new \Exception("multiple records found with uid='$uid'");
        }
    }

    public function recordUserExpirationWarningDaySent(
        string $uid,
        UserExpiryWarningType $warning_type,
        int $day,
    ): void {
        $warning_type_to_days_sent = $this->getUserExpirationWarningDaysSent($uid);
        array_push($warning_type_to_days_sent[$warning_type->value], $day);
        sort($warning_type_to_days_sent[$warning_type->value]);
        $idlelock_days_sent_str = _json_encode($warning_type_to_days_sent["idlelock"]);
        $disable_days_sent_str = _json_encode($warning_type_to_days_sent["disable"]);
        $stmt = $this->conn->prepare(
            sprintf(
                "INSERT INTO %s (uid, %s, %s) VALUES (:uid, :idlelock_days, :disable_days) ON DUPLICATE KEY UPDATE %s=:idlelock_days, %s=:disable_days",
                self::TABLE_USER_EXPIRY,
                "idlelock_warning_days_sent",
                "disable_warning_days_sent",
                "idlelock_warning_days_sent",
                "disable_warning_days_sent",
            ),
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->bindParam(":idlelock_days", $idlelock_days_sent_str);
        $stmt->bindParam(":disable_days", $disable_days_sent_str);
        $stmt->execute();
    }

    public function resetUserExpirationWarningDaysSent(string $uid): void
    {
        $stmt = $this->conn->prepare(
            sprintf(
                "INSERT INTO %s (uid, %s, %s) VALUES (:uid, '[]', '[]') ON DUPLICATE KEY UPDATE %s='[]', %s='[]'",
                self::TABLE_USER_EXPIRY,
                "idlelock_warning_days_sent",
                "disable_warning_days_sent",
                "idlelock_warning_days_sent",
                "disable_warning_days_sent",
            ),
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

    /** @return string[] */
    public function getAllUserLastLogins(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_USER_LAST_LOGINS);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* for testing purposes */
    private function setUserLastLoginDaysAgo(string $uid, int $days): void
    {
        $datetime = date("Y-m-d H:i:s", time() - $days * 24 * 60 * 60);
        $stmt = $this->conn->prepare(
            sprintf(
                "UPDATE %s SET last_login=:datetime WHERE operator=:uid",
                self::TABLE_USER_LAST_LOGINS,
            ),
        );
        $stmt->bindParam(":uid", $uid);
        $stmt->bindParam(":datetime", $datetime);
        $stmt->execute();
    }
}
