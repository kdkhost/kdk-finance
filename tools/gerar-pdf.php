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
 *
 * Converte um documento Markdown em PDF com a identidade visual do sistema.
 *
 * Uso:
 *   php tools/gerar-pdf.php docs/INSTALACAO-CPANEL.md
 *   php tools/gerar-pdf.php docs/INSTALACAO-CPANEL.md docs/INSTALACAO-CPANEL.pdf
 *
 * Requer o Google Chrome (ou Edge) instalado para a impressão em PDF.
 * O HTML intermediário é autocontido: nenhuma fonte ou imagem externa.
 */

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

function fail(string $message): never
{
    fwrite(STDERR, "\n[ERRO] {$message}\n");
    exit(1);
}

$root = dirname(__DIR__);
$source = $argv[1] ?? 'docs/INSTALACAO-CPANEL.md';
$sourcePath = str_starts_with($source, '/') || preg_match('#^[A-Za-z]:#', $source) === 1
    ? $source
    : $root.'/'.ltrim($source, '/');

if (! is_file($sourcePath)) {
    fail('Markdown não encontrado: '.$sourcePath);
}

$target = $argv[2] ?? preg_replace('/\.md$/i', '.pdf', $sourcePath);
$targetPath = str_starts_with((string) $target, '/') || preg_match('#^[A-Za-z]:#', (string) $target) === 1
    ? (string) $target
    : $root.'/'.ltrim((string) $target, '/');

$markdown = (string) file_get_contents($sourcePath);
$converter = new GithubFlavoredMarkdownConverter([
    'html_input' => 'strip',
    'allow_unsafe_links' => false,
]);
$body = (string) $converter->convert($markdown);

$changelog = (string) @file_get_contents($root.'/CHANGELOG.md');
preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $versionMatch);
$version = $versionMatch[1] ?? '—';
$date = date('d/m/Y');

$escapedBody = $body;
// O título já aparece na capa; evita repeti-lo no início do conteúdo.
$escapedBody = preg_replace('#^\s*<h1>.*?</h1>\s*#s', '', $escapedBody) ?? $escapedBody;
// Abre links externos em nova aba quando o PDF for lido no navegador.
$escapedBody = preg_replace('/<a href="(https?:[^"]+)"/', '<a href="$1" target="_blank" rel="noopener"', $escapedBody) ?? $escapedBody;

$html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Instalação no cPanel — KDK Finance</title>
<style>
    :root {
        --navy: #0a2540;
        --navy-800: #0e3154;
        --navy-900: #071a2e;
        --teal: #0f9d76;
        --blue: #1d6fb8;
        --amber: #b45309;
        --red: #c0392b;
        --text: #0f1c2e;
        --muted: #64748b;
        --border: #dfe7f0;
        --surface: #f2f6fb;
        --card: #ffffff;
    }

    @page {
        size: A4;
        margin: 20mm 17mm 22mm 17mm;
    }

    * { box-sizing: border-box; }

    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    body {
        margin: 0;
        font-family: "Segoe UI", "Inter", "Helvetica Neue", Arial, sans-serif;
        font-size: 10.5pt;
        line-height: 1.55;
        color: var(--text);
        background: #fff;
    }

    .cover {
        page-break-after: always;
        margin: -20mm -17mm 0;
        padding: 32mm 20mm 20mm;
        min-height: 297mm;
        background:
            radial-gradient(circle at 88% 8%, rgba(15, 157, 118, .28), transparent 42%),
            linear-gradient(160deg, var(--navy-900) 0%, var(--navy) 55%, var(--navy-800) 100%);
        color: #fff;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 10pt;
        letter-spacing: .22em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .72);
    }

    .brand-mark {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: linear-gradient(145deg, #12b98c, #0b8262);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 11pt;
        letter-spacing: 0;
        color: #fff;
    }

    .cover h1 {
        margin: 46mm 0 0;
        font-size: 30pt;
        line-height: 1.15;
        font-weight: 750;
        letter-spacing: -.02em;
        max-width: 150mm;
        color: #fff;
    }

    .cover .subtitle {
        margin: 10mm 0 0;
        font-size: 13pt;
        line-height: 1.6;
        color: rgba(255, 255, 255, .82);
        max-width: 140mm;
    }

    .cover .rule {
        width: 46mm;
        height: 4px;
        margin: 14mm 0;
        border-radius: 99px;
        background: var(--teal);
    }

    .cover .meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7mm 12mm;
        margin-top: 24mm;
        font-size: 9.5pt;
    }

    .cover .meta div span {
        display: block;
        color: rgba(255, 255, 255, .58);
        font-size: 7.6pt;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 2px;
    }

    .cover .meta div strong { font-weight: 600; color: #fff; }

    .cover .foot {
        margin-top: 30mm;
        padding-top: 5mm;
        border-top: 1px solid rgba(255, 255, 255, .18);
        font-size: 8.6pt;
        color: rgba(255, 255, 255, .7);
        line-height: 1.7;
    }

    main { padding: 0 0 6mm; }

    h1, h2, h3, h4 {
        color: var(--navy);
        line-height: 1.25;
        letter-spacing: -.01em;
    }

    h2 {
        margin: 9mm 0 3mm;
        padding-bottom: 2.4mm;
        border-bottom: 2px solid var(--border);
        font-size: 16.5pt;
        font-weight: 700;
        page-break-after: avoid;
    }

    h2::before {
        content: "";
        display: inline-block;
        width: 4px;
        height: 15pt;
        margin-right: 7px;
        border-radius: 99px;
        background: var(--teal);
        vertical-align: -2pt;
    }

    h3 {
        margin: 6.5mm 0 2mm;
        font-size: 12.5pt;
        font-weight: 650;
        page-break-after: avoid;
    }

    h4 { margin: 5mm 0 1.5mm; font-size: 11pt; }

    p { margin: 0 0 2.6mm; }

    a { color: var(--blue); text-decoration: none; }

    strong { font-weight: 650; }

    ul, ol { margin: 0 0 3mm; padding-left: 6mm; }

    li { margin-bottom: 1.1mm; }

    li::marker { color: var(--teal); }

    hr {
        border: 0;
        border-top: 1px solid var(--border);
        margin: 7mm 0;
    }

    blockquote {
        margin: 4mm 0;
        padding: 3.2mm 4.5mm;
        border-left: 3px solid var(--amber);
        border-radius: 0 6px 6px 0;
        background: #fdf7ec;
        color: #5a3a08;
    }

    blockquote p { margin: 0; }

    code {
        font-family: "Cascadia Mono", "Consolas", "Courier New", monospace;
        font-size: 9pt;
        background: #eef3f9;
        border: 1px solid #dbe5f0;
        border-radius: 4px;
        padding: .5px 4px;
        color: #0b3a66;
    }

    pre {
        margin: 3mm 0 4mm;
        padding: 3.6mm 4.2mm;
        background: var(--navy);
        border-radius: 8px;
        overflow: hidden;
        page-break-inside: avoid;
    }

    pre code {
        background: transparent;
        border: 0;
        padding: 0;
        color: #e8f1fb;
        font-size: 8.8pt;
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-word;
    }

    table {
        width: 100%;
        margin: 3mm 0 5mm;
        border-collapse: collapse;
        font-size: 9.3pt;
        page-break-inside: avoid;
    }

    thead th {
        background: var(--navy);
        color: #fff;
        text-align: left;
        font-weight: 600;
        padding: 2.4mm 3mm;
        border: 1px solid var(--navy);
    }

    tbody td {
        padding: 2.2mm 3mm;
        border: 1px solid var(--border);
        vertical-align: top;
    }

    tbody tr:nth-child(even) td { background: #f7fafd; }

    input[type="checkbox"] {
        appearance: none;
        width: 8px;
        height: 8px;
        border: 1.4px solid var(--teal-600, #0b8262);
        border-radius: 2px;
        display: inline-block;
        margin-right: 3px;
        vertical-align: -1px;
    }

    .footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: -16mm;
        display: flex;
        justify-content: space-between;
        font-size: 7.6pt;
        letter-spacing: .04em;
        color: var(--muted);
        border-top: 1px solid var(--border);
        padding-top: 2.4mm;
    }

    .footer strong { color: var(--navy); font-weight: 600; }

    h2, h3 { break-after: avoid-page; }

    tr, pre, blockquote, table { break-inside: avoid; }
</style>
</head>
<body>
<div class="cover">
    <div class="brand"><span class="brand-mark">KDK</span> KDK Finance</div>
    <h1>Instalação em hospedagem compartilhada cPanel</h1>
    <p class="subtitle">Guia completo de implantação, segurança e acesso somente leitura do sistema financeiro multimoedas.</p>
    <div class="rule"></div>
    <div class="meta">
        <div><span>Versão do sistema</span><strong>{$version}</strong></div>
        <div><span>Data do documento</span><strong>{$date}</strong></div>
        <div><span>Ambiente</span><strong>cPanel · PHP 8.2+ · MySQL/MariaDB</strong></div>
        <div><span>Empresa</span><strong>KDKHost Soluções</strong></div>
    </div>
    <div class="foot">
        Marcelo Brad - RJ · Responsável Técnico / Engenheiro de Software Full-Stack<br>
        CNPJ 33.986.975/0001-90 · contato@kdkhost.com.br · https://kdkhost.com.br
    </div>
</div>

<main>
{$escapedBody}
</main>

<div class="footer">
    <span><strong>KDK Finance</strong> · Instalação no cPanel · v{$version}</span>
    <span>KDKHost Soluções · contato@kdkhost.com.br</span>
</div>
</body>
</html>
HTML;

$htmlPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kdk-doc-'.bin2hex(random_bytes(4)).'.html';
file_put_contents($htmlPath, $html);

// Facilita inspeção visual do layout: KDK_DOC_HTML=caminho php tools/gerar-pdf.php ...
$htmlCopy = getenv('KDK_DOC_HTML') ?: null;
if ($htmlCopy) {
    copy($htmlPath, $htmlCopy);
    echo "HTML de inspeção: {$htmlCopy}\n";
}

$candidates = array_filter([
    getenv('CHROME_PATH') ?: null,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
]);

$browser = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $browser = $candidate;
        break;
    }
}

if ($browser === null) {
    @unlink($htmlPath);
    fail('Chrome/Edge não encontrado. Defina CHROME_PATH com o caminho do executável.');
}

if (! is_dir(dirname($targetPath))) {
    mkdir(dirname($targetPath), 0755, true);
}

@unlink($targetPath);

$fileUrl = 'file:///'.str_replace('\\', '/', $htmlPath);
$command = escapeshellarg($browser)
    .' --headless=new --disable-gpu --no-first-run --no-default-browser-check'
    .' --no-pdf-header-footer --print-to-pdf-no-header'
    .' --print-to-pdf='.escapeshellarg($targetPath)
    .' '.escapeshellarg($fileUrl).' 2>&1';

exec($command, $output, $status);

@unlink($htmlPath);

if (! is_file($targetPath) || filesize($targetPath) < 5000) {
    fail("A impressão do PDF falhou (código {$status}).\n".implode("\n", $output));
}

echo "PDF gerado: {$targetPath}\n";
echo 'Tamanho: '.number_format(filesize($targetPath) / 1024, 1, ',', '.')." KB\n";
