$ErrorActionPreference = 'Stop'
$source = Join-Path $PSScriptRoot 'calico-gf-spam-guard'
$target = Join-Path $PSScriptRoot 'calico-gf-spam-guard-0.2.0.zip'
Compress-Archive -LiteralPath $source -DestinationPath $target -CompressionLevel Optimal -Force
$digest = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath (Join-Path $PSScriptRoot 'SHA256SUMS.txt') -Value "$digest  calico-gf-spam-guard-0.2.0.zip" -Encoding utf8
Write-Output "Built $target"
Write-Output "SHA256 $digest"
