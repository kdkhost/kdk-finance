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

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Gera um CSV compatível com Excel pt-BR:
     * BOM UTF-8, separador ";" e colunas em ordem alfabética (sem acentos).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $labels
     */
    public function download(string $filename, array $rows, array $labels = []): StreamedResponse
    {
        $keys = $rows !== [] ? array_keys($rows[0]) : array_keys($labels);
        usort($keys, fn (string $a, string $b): int => $this->sortKey($labels[$a] ?? $a) <=> $this->sortKey($labels[$b] ?? $b));

        $headers = array_map(fn (string $key): string => $labels[$key] ?? $key, $keys);

        return response()->streamDownload(function () use ($rows, $keys, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map([$this, 'sanitize'], $headers), ';', '"', '\\', "\r\n");

            foreach ($rows as $row) {
                $line = array_map(
                    fn (string $key): string => $this->sanitize((string) ($row[$key] ?? '')),
                    $keys
                );

                fputcsv($handle, $line, ';', '"', '\\', "\r\n");
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function sanitize(string $value): string
    {
        // Neutraliza fórmulas maliciosas em planilhas.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function sortKey(string $label): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);

        return mb_strtolower($ascii !== false ? $ascii : $label);
    }
}
