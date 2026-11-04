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

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_encrypted_at_rest_and_decrypted_on_read(): void
    {
        AppSetting::set('ovh_application_key', 'minha-chave-super-secreta', 'ovh', true);

        $raw = DB::table('app_settings')->where('key', 'ovh_application_key')->value('value');

        $this->assertNotSame('minha-chave-super-secreta', $raw);
        $this->assertTrue(str_starts_with((string) $raw, 'eyJpdiI6'), 'O valor deve ser um payload criptografado do Laravel.');
        $this->assertSame('minha-chave-super-secreta', AppSetting::get('ovh_application_key'));
    }

    public function test_public_values_are_stored_verbatim(): void
    {
        AppSetting::set('spread_percent', '2.00', 'taxes');

        $raw = DB::table('app_settings')->where('key', 'spread_percent')->value('value');

        $this->assertSame('2.00', $raw);
        $this->assertFalse((bool) DB::table('app_settings')->where('key', 'spread_percent')->value('is_secret'));
    }

    public function test_cache_is_invalidated_when_value_changes(): void
    {
        AppSetting::set('iof_percent', '3.38', 'taxes');
        $this->assertSame('3.38', AppSetting::get('iof_percent'));

        AppSetting::set('iof_percent', '4.10', 'taxes');

        $this->assertSame('4.10', AppSetting::get('iof_percent'));
    }

    public function test_default_is_returned_for_missing_keys(): void
    {
        $this->assertSame('padrao', AppSetting::get('chave_inexistente', 'padrao'));
    }
}
