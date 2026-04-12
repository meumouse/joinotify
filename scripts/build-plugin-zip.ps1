$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginRoot = Split-Path -Parent $PSScriptRoot
$pluginSlug = 'joinotify'
$releaseRoot = Join-Path $pluginRoot 'release'
$stagingRoot = Join-Path $releaseRoot $pluginSlug
$zipPath = Join-Path $releaseRoot "$pluginSlug.zip"
$pluginFile = Join-Path $pluginRoot "$pluginSlug.php"

function New-CleanDirectory {
	param (
		[string] $Path
	)

	if (Test-Path $Path) {
		Remove-Item -Recurse -Force $Path
	}

	New-Item -ItemType Directory -Path $Path | Out-Null
}

function Copy-IfExists {
	param (
		[string] $Source,
		[string] $Destination
	)

	if (-not (Test-Path $Source)) {
		return
	}

	$destinationParent = Split-Path -Parent $Destination

	if ($destinationParent -and -not (Test-Path $destinationParent)) {
		New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
	}

	Copy-Item -Path $Source -Destination $Destination -Recurse -Force
}

function Copy-LanguageFiles {
	$languagesRoot = Join-Path $pluginRoot 'languages'
	$targetRoot = Join-Path $stagingRoot 'languages'
	$extensions = @('.json', '.mo', '.po', '.pot')

	if (-not (Test-Path $languagesRoot)) {
		return
	}

	New-Item -ItemType Directory -Path $targetRoot -Force | Out-Null

	Get-ChildItem -Path $languagesRoot -File | Where-Object {
		$extensions -contains $_.Extension.ToLowerInvariant() -and
		$_.Name -notin @('package.json', 'package-lock.json')
	} | ForEach-Object {
		Copy-Item -Path $_.FullName -Destination (Join-Path $targetRoot $_.Name) -Force
	}
}

function Get-PluginVersion {
	$versionLine = Get-Content $pluginFile | Where-Object { $_ -match '^\s*\*\s*Version:\s*' } | Select-Object -First 1

	if (-not $versionLine) {
		return '0.0.0'
	}

	return ($versionLine -replace '^\s*\*\s*Version:\s*', '').Trim()
}

function New-ZipFromDirectory {
	param (
		[string] $SourceDirectory,
		[string] $DestinationZip
	)

	if (Test-Path $DestinationZip) {
		Remove-Item -Force $DestinationZip
	}

	$zipStream = [System.IO.File]::Open($DestinationZip, [System.IO.FileMode]::CreateNew)

	try {
		$zipArchive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)

		try {
			$sourceRoot = (Resolve-Path $SourceDirectory).Path
			$sourceParent = Split-Path -Parent $sourceRoot
			$entries = Get-ChildItem -Path $SourceDirectory -Recurse -Force

			foreach ($entry in $entries) {
				$relativePath = $entry.FullName.Substring($sourceParent.Length + 1).Replace('\', '/')

				if ($entry.PSIsContainer) {
					if (-not $relativePath.EndsWith('/')) {
						$relativePath = "$relativePath/"
					}

					$null = $zipArchive.CreateEntry($relativePath)
					continue
				}

				[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
					$zipArchive,
					$entry.FullName,
					$relativePath,
					[System.IO.Compression.CompressionLevel]::Optimal
				) | Out-Null
			}
		}
		finally {
			$zipArchive.Dispose()
		}
	}
	finally {
		$zipStream.Dispose()
	}
}

New-CleanDirectory -Path $releaseRoot
New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null

$topLevelFiles = @(
	'joinotify.php',
	'README.md',
	'LICENSE.md',
	'changelogs.md'
)

foreach ($file in $topLevelFiles) {
	Copy-IfExists -Source (Join-Path $pluginRoot $file) -Destination (Join-Path $stagingRoot $file)
}

$directoryMappings = @(
	@{ Source = 'assets'; Destination = 'assets' },
	@{ Source = 'inc'; Destination = 'inc' },
	@{ Source = 'templates'; Destination = 'templates' },
	@{ Source = 'vendor'; Destination = 'vendor' },
	@{ Source = 'languages'; Destination = 'languages' }
)

foreach ($mapping in $directoryMappings) {
	Copy-IfExists `
		-Source (Join-Path $pluginRoot $mapping.Source) `
		-Destination (Join-Path $stagingRoot $mapping.Destination)
}

Copy-LanguageFiles

$manifest = [ordered]@{
	name = $pluginSlug
	version = Get-PluginVersion
	generatedAt = (Get-Date).ToUniversalTime().ToString('o')
	zipFile = Split-Path -Leaf $zipPath
}

$manifest | ConvertTo-Json | Set-Content -Path (Join-Path $releaseRoot 'manifest.json')

New-ZipFromDirectory -SourceDirectory $stagingRoot -DestinationZip $zipPath

Write-Host "ZIP created: $zipPath"
