<?php
use PHPUnit\Framework\TestCase;
use UnityWebPortal\lib\CSRFToken;

class CSRFTokenTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id(getCorrelationID());
        session_start();
        $_SESSION["csrf_tokens"] = [];
    }

    protected function tearDown(): void
    {
        CSRFToken::clear();
        session_write_close();
        session_id(getCorrelationID());
    }

    public function testGenerateCreatesToken(): void
    {
        $token = CSRFToken::generate();
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateStoresTokenInSession(): void
    {
        $token = CSRFToken::generate();
        $this->assertArrayHasKey("csrf_tokens", $_SESSION);
        $this->assertArrayHasKey($token, $_SESSION["csrf_tokens"]);
        $this->assertFalse($_SESSION["csrf_tokens"][$token]);
    }

    public function testValidateWithValidToken(): void
    {
        $token = CSRFToken::generate();
        $this->assertTrue(CSRFToken::validate($token));
        $this->assertTrue($_SESSION["csrf_tokens"][$token]);
    }

    public function testValidateWithInvalidToken(): void
    {
        CSRFToken::generate();
        $this->assertFalse(CSRFToken::validate("invalid_token"));
    }

    public function testValidateWithEmptyToken(): void
    {
        CSRFToken::generate();
        $this->assertFalse(CSRFToken::validate(""));
    }

    public function testValidateWithoutSessionToken(): void
    {
        $this->assertFalse(CSRFToken::validate("any_token"));
    }

    public function testClearRemovesToken(): void
    {
        CSRFToken::generate();
        $this->assertNotEmpty($_SESSION["csrf_tokens"]);
        CSRFToken::clear();
        $this->assertEmpty($_SESSION["csrf_tokens"]);
    }

    public function testMultipleTokenGenerations(): void
    {
        $token1 = CSRFToken::generate();
        $token2 = CSRFToken::generate();
        $this->assertNotEquals($token1, $token2);
    }

    public function testTokenIsSingleUse(): void
    {
        $token = CSRFToken::generate();
        $this->assertTrue(CSRFToken::validate($token));
        $this->assertFalse(CSRFToken::validate($token));
        $this->assertTrue($_SESSION["csrf_tokens"][$token]);
    }
}
