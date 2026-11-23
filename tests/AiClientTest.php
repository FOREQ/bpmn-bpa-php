<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/ai_client.php';

final class AiClientTest extends TestCase
{
    private static $server;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = random_int(20000, 40000);
        foreach (glob(sys_get_temp_dir() . '/ai_mock_*.cnt') ?: [] as $f) {
            unlink($f);
        }
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/fixtures/ai_mock_server.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['PHP_CLI_SERVER_WORKERS' => '4']
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', self::$port); $i++) {
            usleep(100_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
    }

    private function useMode(string $mode): void
    {
        putenv('AI_BASE_URL=http://127.0.0.1:' . self::$port . '/' . $mode);
        putenv('AI_MODEL=/model');
        putenv('AI_SLOTS_DIR=' . sys_get_temp_dir() . '/ai_test_slots_' . getmypid());
        putenv('AI_GENERATOR_MAX_TOKENS');
    }

    public function testThinkingOffAndGeneratorLimitByDefault(): void
    {
        $this->useMode('echo');
        $json = json_decode(aiChat([['role' => 'user', 'content' => 'x']], 'generator'), true);
        $this->assertSame(['enable_thinking' => false], $json['kwargs']);
        $this->assertSame(16000, $json['max_tokens']);
        $this->assertSame(8000, aiConfig('grader')['max_tokens']);
    }

    public function testMaxTokensCapped(): void
    {
        $this->useMode('echo');
        putenv('AI_GENERATOR_MAX_TOKENS=50000');
        $this->assertSame(AI_MAX_TOKENS_CAP, aiConfig('generator')['max_tokens']);
        putenv('AI_GENERATOR_MAX_TOKENS');
    }

    public function testLengthWithEmptyContentGivesClearError(): void
    {
        $this->useMode('length');
        $this->expectExceptionMessageMatches('/не уложился в лимит/u');
        aiChat([['role' => 'user', 'content' => 'x']], 'generator');
    }

    public function testRetriesOn429(): void
    {
        $this->useMode('busy');
        putenv('AI_RETRY_429=3');
        $this->assertSame('{"ok":true}', aiChat([['role' => 'user', 'content' => 'x']]));
    }

    public function testBadGatewayIsTemporarilyUnavailable(): void
    {
        $this->useMode('down');
        $this->expectExceptionMessageMatches('/временно недоступен/u');
        aiChat([['role' => 'user', 'content' => 'x']]);
    }

    public function testSemaphoreGivesUpWhenAllSlotsBusy(): void
    {
        $this->useMode('echo');
        putenv('AI_MAX_PARALLEL=2');
        putenv('AI_QUEUE_WAIT=1');
        $a = aiAcquireSlot();
        $b = aiAcquireSlot();
        try {
            $this->expectExceptionMessageMatches('/ИИ занят/u');
            aiAcquireSlot();
        } finally {
            aiReleaseSlot($a);
            aiReleaseSlot($b);
            putenv('AI_QUEUE_WAIT');
        }
    }
}
