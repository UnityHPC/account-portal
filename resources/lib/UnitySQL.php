<?php

namespace UnityWebPortal\lib;

use PDO;
use PDOException;

/**
 * @phpstan-type user_last_login array{operator: string, last_login: int}
 * @phpstan-type pi_group_expiration_date array{gid: string, expiration_date: int}
 * @phpstan-type request array{request_for: string, uid: string, timestamp: string}
 */
class UnitySQL
{
    private const string TABLE_REQS = "requests";
    private const string TABLE_AUDIT_LOG = "audit_log";
    private const string TABLE_USER_LAST_LOGINS = "user_last_logins";
    private const string TABLE_PI_GROUP_EXPIRATION_DATES = "pi_group_expiration_dates";
    // FIXME this string should be changed to something more intuitive, requires production change
    public const string REQUEST_BECOME_PI = "admin";
    private const int TABLE_AUDIT_LOG_RECIPIENT_MAX_MB_STR_LEN = 768;

    private PDO $conn;

    /** @throws PDOException */
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

    /** @throws PDOException */
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

    /** @throws PDOException */
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

    /** @throws PDOException */
    public function removeRequests(string $dest): void
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM " . self::TABLE_REQS . " WHERE request_for=:request_for",
        );
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
    }

    /**
     * @throws PDOException
     * @throws \Exception if the request is not found or multiple requests are found (FIXME)
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

    /** @throws PDOException */
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

    /**
     * @return request[]
     * @throws PDOException
     */
    public function getAllRequests(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_REQS);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return request[]
     * @throws PDOException
     */
    public function getRequests(string $dest): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . self::TABLE_REQS . " WHERE request_for=:request_for",
        );
        $stmt->bindParam(":request_for", $dest);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return request[]
     * @throws PDOException
     */
    public function getRequestsByUser(string $user): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_REQS . " WHERE uid=:uid");
        $stmt->bindParam(":uid", $user);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @throws PDOException */
    public function deleteRequestsByUser(string $user): void
    {
        $stmt = $this->conn->prepare("DELETE FROM " . self::TABLE_REQS . " WHERE uid=:uid");
        $stmt->bindParam(":uid", $user);
        $stmt->execute();
    }

    /** @throws PDOException */
    public function addLog(string $action_type, string $recipient): void
    {
        if (mb_strlen($recipient, "UTF-8") > self::TABLE_AUDIT_LOG_RECIPIENT_MAX_MB_STR_LEN) {
            UnityHTTPD::errorLog("warning", "audit log recipient truncated", data: $recipient);
            $recipient = mb_substr(
                $recipient,
                0,
                self::TABLE_AUDIT_LOG_RECIPIENT_MAX_MB_STR_LEN,
                "UTF-8",
            );
        }
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

    /** @throws PDOException */
    public function updateUserLastLogin(string $uid): void
    {
        $table = self::TABLE_USER_LAST_LOGINS;
        $stmt = $this->conn->prepare("
            INSERT INTO $table
            (operator, last_login)
            VALUES(:uid, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
            last_login=CURRENT_TIMESTAMP
        ");
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

    /**
     * @return user_last_login[]
     * @throws PDOException
     */
    public function getAllUserLastLogins(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_USER_LAST_LOGINS);
        $stmt->execute();
        $records = $stmt->fetchAll();
        $output = [];
        foreach ($records as $record) {
            array_push($output, [
                "operator" => $record["operator"],
                "last_login" => strtotime($record["last_login"]),
            ]);
        }
        return $output;
    }

    /** @throws PDOException */
    public function convertLastLoginToDaysIdle(?int $timestamp, ?int $now = null): int
    {
        if ($timestamp === null) {
            return 0;
        }
        $now ??= time();
        $idle_seconds = $now - $timestamp;
        return intdiv($idle_seconds, 60 * 60 * 24);
    }

    /**
     * for testing purposes
     * @throws PDOException
     */
    private function setUserLastLogin(string $uid, int $timestamp): void
    {
        $datetime = date("Y-m-d H:i:s", $timestamp);
        $table = self::TABLE_USER_LAST_LOGINS;
        $stmt = $this->conn->prepare("
            INSERT INTO $table
            VALUES (:uid, :datetime)
            ON DUPLICATE KEY
            UPDATE last_login=:datetime;
        ");
        $stmt->bindParam(":uid", $uid);
        $stmt->bindParam(":datetime", $datetime);
        $stmt->execute();
    }

    /**
     * for testing purposes
     * @throws PDOException
     */
    private function removeUserLastLogin(string $uid): void
    {
        $table = self::TABLE_USER_LAST_LOGINS;
        $stmt = $this->conn->prepare("DELETE FROM $table WHERE operator=:uid");
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

    /**
     * @throws PDOException
     * @throws \Exception if multiple records are found (this should never happen)
     */
    public function getUserLastLogin(string $uid): ?int
    {
        $table = self::TABLE_USER_LAST_LOGINS;
        $stmt = $this->conn->prepare("SELECT * FROM $table WHERE operator=:uid");
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            return null;
        }
        if (count($result) > 1) {
            throw new \Exception("multiple records found with operator '$uid'");
        }
        $timestamp_str = $result[0]["last_login"];
        return strtotime($timestamp_str);
    }

    /**
     * @throws PDOException
     * @throws \Exception if multiple records are found (this should never happen)
     */
    public function getPIGroupExpirationDate(string $gid): int|null
    {
        $table = self::TABLE_PI_GROUP_EXPIRATION_DATES;
        $stmt = $this->conn->prepare("SELECT * FROM $table WHERE gid=:gid");
        $stmt->bindParam(":gid", $gid);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (count($result) == 0) {
            return null;
        }
        if (count($result) > 1) {
            throw new \Exception("multiple records found with gid '$gid'");
        }
        $timestamp_str = $result[0]["expiration_date"];
        return strtotime($timestamp_str);
    }

    /** @throws PDOException */
    public function setPIGroupExpirationDate(string $gid, int $expiration_date): void
    {
        $table = self::TABLE_PI_GROUP_EXPIRATION_DATES;
        $stmt = $this->conn->prepare("
            INSERT INTO $table
            VALUES (:gid, :expiration_date)
            ON DUPLICATE KEY
            UPDATE expiration_date=:expiration_date
        ");
        $stmt->bindParam(":gid", $gid);
        $expiration_date_str = date("Y-m-d H:i:s", $expiration_date);
        $stmt->bindParam(":expiration_date", $expiration_date_str);
        $stmt->execute();
    }

    /** @throws PDOException */
    public function removePIGroupExpirationDate(string $gid): void
    {
        $table = self::TABLE_PI_GROUP_EXPIRATION_DATES;
        $stmt = $this->conn->prepare("DELETE FROM $table WHERE gid=:gid");
        $stmt->bindParam(":gid", $gid);
        $stmt->execute();
    }

    /**
     * @throws PDOException
     * @return pi_group_expiration_date[]
     */
    public function getAllPIGroupExpirationDates(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_PI_GROUP_EXPIRATION_DATES);
        $stmt->execute();
        $records = $stmt->fetchAll();
        $output = [];
        foreach ($records as $record) {
            array_push($output, [
                "gid" => $record["gid"],
                "expiration_date" => strtotime($record["expiration_date"]),
            ]);
        }
        return $output;
    }
}
