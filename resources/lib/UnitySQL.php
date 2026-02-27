<?php

namespace UnityWebPortal\lib;

use PDO;

/**
 * @phpstan-type user_last_login array{operator: string, last_login: string}
 * @phpstan-type request array{request_for: string, uid: string, timestamp: string}
 */
class UnitySQL
{
    private const string TABLE_REQS = "requests";
    private const string TABLE_AUDIT_LOG = "audit_log";
    private const string TABLE_USER_LAST_LOGINS = "user_last_logins";
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

    /** @return user_last_login[] */
    public function getAllUserLastLogins(): array
    {
        $stmt = $this->conn->prepare("SELECT * FROM " . self::TABLE_USER_LAST_LOGINS);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function convertLastLoginToDaysIdle(?int $timestamp, ?int $now = null): int
    {
        if ($timestamp === null) {
            return 0;
        }
        $now ??= time();
        $idle_seconds = $now - $timestamp;
        return intdiv($idle_seconds, 60 * 60 * 24);
    }

    /* for testing purposes */
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

    /* for testing purposes */
    private function removeUserLastLogin(string $uid): void
    {
        $table = self::TABLE_USER_LAST_LOGINS;
        $stmt = $this->conn->prepare("DELETE FROM $table WHERE operator=:uid");
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
    }

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
}
