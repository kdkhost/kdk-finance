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
 * Gera o pacote de implantação para hospedagem compartilhada (cPanel).
 *
 * Uso:
 *   php tools/empacotar-cpanel.php
 *
 * O pacote é gravado em dist/ e já leva o vendor/ e o build do Vite, de modo
 * que a hospedagem não precisa de Composer nem de Node. Uma senha de
 * instalação aleatória é criada dentro do ZIP em storage/app/instalar.senha.
 * Nenhum .env, banco SQLite, log ou credencial local entra no pacote.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$excludedFromPackage = ['tools', 'tests', 'dist', 'node_modules', '.git', '.qoder', '.idea', '.vscode', '.fleet', '.nova', '.zed'];

function fail(string $message): never
{
    fwrite(STDERR, "\n[ERRO] {$message}\n");
    exit(1);
}

function removeTree(string $path): void
{
    if (! file_exists($path)) {
        return;
    }

    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        removeTree($path.DIRECTORY_SEPARATOR.$entry);
    }

    @rmdir($path);
}

function relative(string $path, string $base): string
{
    $path = str_replace('\\', '/', $path);
    $base = rtrim(str_replace('\\', '/', $base), '/');

    return ltrim(substr($path, strlen($base)), '/');
}

function shouldSkip(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', $relativePath);
    $basename = basename($relativePath);

    $skipped = [
        '.env',
        '.env.example',
        '.phpunit.result.cache',
        '.phpunit.cache',
        '.DS_Store',
        'Thumbs.db',
        'instalador.php.instalado',
        'credentials.local.json',
        'installed.lock',
        'instalar.senha',
        'hot',
    ];

    if (in_array($basename, $skipped, true)) {
        return true;
    }

    if (preg_match('#^\.env\.#', $relativePath) === 1) {
        return true;
    }

    if (preg_match('#^(public/)?storage(/|$)#', $relativePath) === 1 && ! str_starts_with($relativePath, 'storage/app/instalar')) {
        // O conteúdo de storage não vai; a estrutura é recriada vazia abaixo.
        return true;
    }

    if (preg_match('#^storage/(logs|framework|cache)/#', $relativePath) === 1) {
        return true;
    }

    if (preg_match('#\.(sqlite|log)$#i', $relativePath) === 1) {
        return true;
    }

    if (preg_match('#^database/.*\.sqlite$#i', $relativePath) === 1) {
        return true;
    }

    return false;
}

if (! class_exists('ZipArchive')) {
    fail('A extensão ZIP do PHP não está habilitada nesta máquina.');
}

if (! is_file($root.'/vendor/autoload.php')) {
    fail('A pasta vendor/ não existe. Rode "composer install" antes de empacotar.');
}

if (! is_file($root.'/public/build/manifest.json')) {
    fail('O build do Vite não existe. Rode "npm run build" antes de empacotar.');
}

if (! is_file($root.'/public/instalador.php')) {
    fail('O instalador public/instalador.php não foi encontrado.');
}

$changelog = (string) file_get_contents($root.'/CHANGELOG.md');
if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $matches) !== 1) {
    fail('Não foi possível ler a versão atual no CHANGELOG.md.');
}
$version = $matches[1];

$dist = $root.'/dist';
if (! is_dir($dist) && ! mkdir($dist, 0755, true)) {
    fail('Não foi possível criar a pasta dist/.');
}

$target = $dist.'/kdk-finance-cpanel-'.$version.'.zip';
$staging = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kdk-package-'.bin2hex(random_bytes(4));

echo "KDK Finance — pacote cPanel v{$version}\n";
echo "Preparando arquivos...\n";

if (! mkdir($staging, 0755, true)) {
    fail('Não foi possível criar a pasta temporária de empacotamento.');
}

$copied = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $relativePath = relative($item->getPathname(), $root);
    $top = explode('/', $relativePath)[0];

    if (in_array($top, $excludedFromPackage, true)) {
        continue;
    }

    if (shouldSkip($relativePath)) {
        continue;
    }

    // Nunca segue links simbólicos (public/storage aponta para fora do pacote).
    if ($item->isLink()) {
        continue;
    }

    $destination = $staging.DIRECTORY_SEPARATOR.$relativePath;

    if ($item->isDir()) {
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        continue;
    }

    $directory = dirname($destination);
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (! copy($item->getPathname(), $destination)) {
        removeTree($staging);
        fail('Falha ao copiar '.$relativePath);
    }

    $copied++;
}

// Estrutura de storage vazia e gravável (o ZIP não preserva permissões).
$storageDirectories = [
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

foreach ($storageDirectories as $directory) {
    $path = $staging.DIRECTORY_SEPARATOR.$directory;
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }

    if (! is_file($path.'/.gitignore')) {
        file_put_contents($path.'/.gitignore', "*\n!.gitignore\n");
    }
}

// Senha aleatória de instalação (sem caracteres ambíguos).
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
$installPassword = '';
for ($index = 0; $index < 20; $index++) {
    $installPassword .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}
file_put_contents($staging.'/storage/app/instalar.senha', $installPassword."\n");

@unlink($target);

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    removeTree($staging);
    fail('Não foi possível criar o arquivo ZIP.');
}

$zipIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($zipIterator as $item) {
    /** @var SplFileInfo $item */
    if ($item->isLink()) {
        continue;
    }

    $localName = relative($item->getPathname(), $staging);

    if ($item->isDir()) {
        $zip->addEmptyDir($localName);

        continue;
    }

    $zip->addFile($item->getPathname(), $localName);
}

$zip->close();
removeTree($staging);

if (! is_file($target)) {
    fail('O pacote não foi gerado.');
}

$size = filesize($target);
$hash = hash_file('sha256', $target);

echo "\nPacote gerado com sucesso.\n";
echo 'Arquivo : '.$target."\n";
echo 'Tamanho : '.number_format($size / 1048576, 2, ',', '.')." MB\n";
echo 'Arquivos: '.$copied."\n";
echo 'SHA-256 : '.$hash."\n";
echo "\nSenha de instalação (também em storage/app/instalar.senha dentro do ZIP):\n";
echo '  '.$installPassword."\n";
echo "\nEnvie o conteúdo do ZIP para a hospedagem e acesse /instalador.php.\n";
