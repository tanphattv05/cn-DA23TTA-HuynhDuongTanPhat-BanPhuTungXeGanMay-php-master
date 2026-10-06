param([string]$Php = 'D:/xampp/php/php.exe')
$ErrorActionPreference = 'Stop'
$repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
Set-Location -LiteralPath $repo
function Run-Php([string[]]$Arguments) {
    & $Php @Arguments
    if ($LASTEXITCODE -ne 0) { throw "PHP check failed: $($Arguments[0])" }
}
# No bypass switch: verify the working application before packaging.
Run-Php @('tests/product-management.php','--isolated')
Run-Php @('tests/final-handover.php','--isolated')
$phpFiles = @(Get-ChildItem -LiteralPath (Join-Path $repo 'scr'),(Join-Path $repo 'tests') -Recurse -File -Filter '*.php')
$phpFiles += Get-Item -LiteralPath (Join-Path $repo 'index.php')
foreach ($file in $phpFiles) { Run-Php @('-l',$file.FullName) }
Write-Output "Lint passed: $($phpFiles.Count) PHP files"
& git -c core.safecrlf=false diff --check
if ($LASTEXITCODE -ne 0) { throw 'git diff --check failed' }
& git -c core.safecrlf=false diff --cached --check
if ($LASTEXITCODE -ne 0) { throw 'git diff --cached --check failed' }

# Use tracked sources plus explicitly reviewed phase-15 additions. Arbitrary
# untracked files, local secrets, upload images and repository metadata never enter.
$tracked = @(& git -c core.quotepath=false ls-files)
if ($LASTEXITCODE -ne 0) { throw 'Source inventory requires Git' }
$additions = @('.gitignore','scr/backend/config/database.example.php',
    'tests/final-handover.php','tools/build-handover.ps1','docs/FINAL-HANDOVER.md')
$selected = @($tracked + $additions | Sort-Object -Unique | Where-Object {
    ($_ -match '^(scr/|docs/|tests/|tools/|README\.md$|index\.php$|\.htaccess$|\.gitignore$)') -and
    ($_ -notmatch '(?i)(^|/)(\.git|dist|logs?|cache|sessions?|\.env[^/]*|sess_[^/]*|motoparts_(test|schema)[^/]*)(/|$)|\.(log|tmp|bak|zip|sqlite|db)$') -and
    ($_ -notmatch '^scr/backend/config/(?!database\.example\.php$)') -and
    ($_ -notmatch '^scr/assets/images/products/(?!\.htaccess$)') -and
    ($_ -notmatch '\.sql$' -or $_ -eq 'docs/database-schema.sql') -and
    (Test-Path -LiteralPath (Join-Path $repo $_) -PathType Leaf)
})
$stage = Join-Path ([IO.Path]::GetTempPath()) ('motoparts_handover_' + [Guid]::NewGuid().ToString('N'))
$zipTemporary = Join-Path $stage 'MotoParts-MVC-Final.zip'
$payload = Join-Path $stage 'payload'
$extract = Join-Path $stage 'verified'
New-Item -ItemType Directory -Path $payload | Out-Null
try {
    foreach ($path in $selected) {
        $source = Join-Path $repo $path
        # Reject symlink/junction traversal, even for tracked paths.
        $part = Get-Item -LiteralPath $source
        while ($part.FullName -ne $repo) {
            if ($part.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Reparse point rejected: $path" }
            $part = Get-Item -LiteralPath (Split-Path $part.FullName -Parent)
        }
        $target = Join-Path $payload $path
        New-Item -ItemType Directory -Path (Split-Path $target -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $source -Destination $target
    }
    Run-Php @('tests/final-handover.php','--isolated',('--package-root='+$payload))
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [IO.Compression.ZipFile]::CreateFromDirectory($payload,$zipTemporary)
    # Re-open the actual archive, extract only into our fresh temp directory and
    # verify both policy and byte-for-byte equality before publishing to dist.
    [IO.Compression.ZipFile]::ExtractToDirectory($zipTemporary,$extract)
    Run-Php @('tests/final-handover.php','--isolated',('--package-root='+$extract))
    foreach ($path in $selected) {
        if ((Get-FileHash -LiteralPath (Join-Path $payload $path)).Hash -ne
            (Get-FileHash -LiteralPath (Join-Path $extract $path)).Hash) { throw "Archive mismatch: $path" }
    }
    $dist = Join-Path $repo 'dist'
    if (!(Test-Path -LiteralPath $dist)) { New-Item -ItemType Directory -Path $dist | Out-Null }
    if ((Get-Item -LiteralPath $dist).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'dist must not be a link' }
    $destination = Join-Path $dist 'MotoParts-MVC-Final.zip'
    Copy-Item -LiteralPath $zipTemporary -Destination $destination -Force
    Write-Output "ZIP: $destination"
    Write-Output "Files: $($selected.Count); bytes: $((Get-Item -LiteralPath $destination).Length)"
    Write-Output "SHA256: $((Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash)"
} finally {
    # Only our random temporary tree, never a source directory or real upload.
    $resolved = [IO.Path]::GetFullPath($stage)
    $tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\') + '\'
    if (!$resolved.StartsWith($tempRoot,[StringComparison]::OrdinalIgnoreCase) -or
        (Split-Path $resolved -Leaf) -notmatch '^motoparts_handover_[a-f0-9]{32}$') { throw 'Unsafe cleanup path' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
