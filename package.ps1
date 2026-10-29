$ErrorActionPreference = 'Stop'
$source = Join-Path $PSScriptRoot 'calico-gf-spam-guard'
$main = Get-Content -LiteralPath (Join-Path $source 'calico-gf-spam-guard.php') -Raw
if ($main -notmatch '(?m)^ \* Version: (\d+\.\d+\.\d+)\s*$') { throw 'Plugin version header missing' }
$pluginVersion = $Matches[1]
$target = Join-Path $PSScriptRoot "calico-gf-spam-guard-$pluginVersion.zip"
Compress-Archive -LiteralPath $source -DestinationPath $target -CompressionLevel Optimal -Force
$digest = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash.ToLowerInvariant()
$stable = Join-Path $PSScriptRoot 'calico-gf-spam-guard.zip'
Copy-Item -LiteralPath $target -Destination $stable -Force
Set-Content -LiteralPath (Join-Path $PSScriptRoot 'SHA256SUMS.txt') -Value "$digest  calico-gf-spam-guard-$pluginVersion.zip`n$digest  calico-gf-spam-guard.zip" -Encoding utf8
Write-Output "Built $target"
Write-Output "SHA256 $digest"
