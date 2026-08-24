# Builds installable Joomla packages into dist/
#   dist/com_clubleaddir.zip   - component
#   dist/mod_clubleaddir.zip   - module
#   dist/pkg_clubleaddir.zip   - package containing both
# Requires PowerShell 7+ (pwsh).
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
New-Item -ItemType Directory -Path $dist | Out-Null

function Get-ManifestVersion($path) {
    [xml]$xml = Get-Content $path -Raw
    return $xml.SelectSingleNode('(/extension/version)[1]').InnerText
}

$comVersion = Get-ManifestVersion (Join-Path $root 'com_clubleaddir/com_clubleaddir.xml')

Write-Host "Building Club Leadership Directory v$comVersion"

# Component zip (manifest must sit at the archive root)
Compress-Archive -Path (Join-Path $root 'com_clubleaddir/*') -DestinationPath (Join-Path $dist 'com_clubleaddir.zip') -CompressionLevel Optimal

# Module zip
Compress-Archive -Path (Join-Path $root 'mod_clubleaddir/*') -DestinationPath (Join-Path $dist 'mod_clubleaddir.zip') -CompressionLevel Optimal

# Package zip: package manifest + both extension zips, flat
$staging = Join-Path $env:TEMP ("pkg_clubleaddir_" + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $staging | Out-Null
try {
    Copy-Item (Join-Path $root 'pkg/pkg_clubleaddir.xml') $staging
    Copy-Item (Join-Path $dist 'com_clubleaddir.zip') $staging
    Copy-Item (Join-Path $dist 'mod_clubleaddir.zip') $staging
    Compress-Archive -Path (Join-Path $staging '*') -DestinationPath (Join-Path $dist 'pkg_clubleaddir.zip') -CompressionLevel Optimal
}
finally {
    Remove-Item $staging -Recurse -Force
}

Get-ChildItem $dist | Format-Table Name, Length -AutoSize
