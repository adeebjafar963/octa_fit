# Validates local bodybuilding exercise animations against the live catalog.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$php = Join-Path $root "tools\php83\php.exe"
$mobile = Join-Path $root "mobile"
$inventoryScript = Join-Path $root "scripts\list_bb_exercises.php"

$raw = & $php $inventoryScript
if ($LASTEXITCODE -ne 0) { throw "Could not read bodybuilding exercises." }
$exercises = $raw | ConvertFrom-Json

$groups = @(
    "full-body", "hamstrings", "shoulders", "forearms", "triceps", "biceps",
    "glutes", "calves", "chest", "back", "quads", "core"
)

function Resolve-Asset([string]$slug) {
    if (-not $slug.StartsWith("bb-")) { return $null }
    $rest = $slug.Substring(3)
    foreach ($group in $script:groups) {
        if ($rest -eq $group -or $rest.StartsWith("$group-")) {
            $folder = $group.Replace("-", "_")
            $file = ($slug.Replace("-", "_") + ".gif")
            return Join-Path $mobile "assets\exercises\bodybuilding\$folder\$file"
        }
    }
    return $null
}

$total = 0
$withAnimation = 0
$missing = 0
$broken = 0
$missingFiles = 0
$paths = @{}
$duplicateMappings = 0
$maxBytes = 600KB

foreach ($exercise in $exercises) {
    $total++
    $path = Resolve-Asset $exercise.slug
    if (-not $path) {
        $missing++
        continue
    }
    if ($paths.ContainsKey($path)) {
        $duplicateMappings++
    } else {
        $paths[$path] = $exercise.slug
    }
    if (-not (Test-Path -LiteralPath $path)) {
        $missingFiles++
        $missing++
        continue
    }
    $item = Get-Item -LiteralPath $path
    $withAnimation++
    if ($item.Length -le 0 -or $item.Length -gt $maxBytes) {
        $broken++
        continue
    }
    $bytes = [System.IO.File]::ReadAllBytes($path)
    $header = [System.Text.Encoding]::ASCII.GetString($bytes, 0, [Math]::Min(6, $bytes.Length))
    if ($header -ne "GIF89a" -and $header -ne "GIF87a") {
        $broken++
    }
}

Write-Host ""
Write-Host "BODYBUILDING MEDIA VALIDATION"
Write-Host ""
Write-Host "Total exercises: $total"
Write-Host "With animation: $withAnimation"
Write-Host "Missing animation: $missing"
Write-Host "Broken assets: $broken"
Write-Host "Missing files: $missingFiles"
Write-Host "Duplicates: $duplicateMappings"
Write-Host ""
if ($missing -eq 0 -and $broken -eq 0 -and $missingFiles -eq 0 -and $duplicateMappings -eq 0 -and $withAnimation -eq $total) {
    Write-Host "STATUS: PASS"
    exit 0
}
Write-Host "STATUS: FAIL"
exit 1
