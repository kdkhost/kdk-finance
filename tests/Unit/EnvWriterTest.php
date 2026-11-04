<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

namespace Tests\Unit;

use App\Support\Install\EnvWriter;
use PHPUnit\Framework\TestCase;

class EnvWriterTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            foreach (glob($this->directory.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_app_key_is_a_valid_laravel_key(): void
    {
        $key = EnvWriter::appKey();

        $this->assertStringStartsWith('base64:', $key);
        $decoded = base64_decode(substr($key, 7), true);

        $this->assertNotFalse($decoded);
        $this->assertSame(32, strlen((string) $decoded));
        $this->assertNotSame($key, EnvWriter::appKey());
    }

    public function test_quote_neutralizes_dangerous_characters(): void
    {
        $this->assertSame('"abc"', EnvWriter::quote('abc'));
        $this->assertSame('"a\\"b"', EnvWriter::quote('a"b'));
        $this->assertSame('"C:\\\\dados"', EnvWriter::quote('C:\\dados'));
        $this->assertSame('"p\\$ss"', EnvWriter::quote('p$ss'));
        $this->assertSame('"linha"', EnvWriter::quote("linha\r\n"));
        $this->assertSame('"café#1"', EnvWriter::quote('café#1'));
    }

    public function test_render_skips_empty_values_and_formats_booleans(): void
    {
        $rendered = EnvWriter::render([
            'APP_NAME' => 'KDK Finance',
            'APP_KEY' => null,
            'APP_DEBUG' => false,
            'FORCE_HTTPS' => true,
            'DB_PORT' => 3306,
        ]);

        $this->assertStringContainsString('APP_NAME="KDK Finance"', $rendered);
        $this->assertStringContainsString('APP_DEBUG="false"', $rendered);
        $this->assertStringContainsString('FORCE_HTTPS="true"', $rendered);
        $this->assertStringContainsString('DB_PORT="3306"', $rendered);
        $this->assertStringNotContainsString('APP_KEY', $rendered);
        $this->assertStringEndsWith(PHP_EOL, $rendered);
    }

    public function test_write_creates_a_restricted_file_and_read_key_reads_it_back(): void
    {
        $this->directory = sys_get_temp_dir().'/kdk-env-'.bin2hex(random_bytes(4));
        mkdir($this->directory);

        $path = $this->directory.'/.env';
        $key = EnvWriter::appKey();

        EnvWriter::write($path, [
            'APP_KEY' => $key,
            'DB_PASSWORD' => 'senha $complica"da',
        ]);

        $this->assertFileExists($path);
        $this->assertSame($key, EnvWriter::readKey($path, 'APP_KEY'));
        $this->assertSame('senha $complica"da', EnvWriter::readKey($path, 'DB_PASSWORD'));
        $this->assertNull(EnvWriter::readKey($path, 'NAO_EXISTE'));

        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame('640', substr(sprintf('%o', fileperms($path)), -3));
        }

        $this->assertSame([], glob($this->directory.'/*.tmp-*'));
    }

    public function test_write_refuses_a_missing_directory(): void
    {
        $this->expectException(\RuntimeException::class);

        EnvWriter::write(sys_get_temp_dir().'/kdk-inexistente-'.bin2hex(random_bytes(4)).'/.env', [
            'APP_KEY' => EnvWriter::appKey(),
        ]);
    }
}
