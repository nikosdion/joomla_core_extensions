<?php

use Dionysopoulos\JoomlaCoreExtensions\Extensions;
use Dionysopoulos\JoomlaCoreExtensions\Getter;
use Dionysopoulos\JoomlaCoreExtensions\JoomlaVersions;
use Dionysopoulos\JoomlaCoreExtensions\Tables;
use Dotenv\Dotenv;

require_once 'vendor/autoload.php';

Dotenv::createImmutable(__DIR__)->safeLoad();

$headers    = ($_SERVER['GITHUB_PAT'] ?? null) ? [
	'X-GitHub-Api-Version' => '2022-11-28',
	'Authorization'        => 'Bearer ' . $_SERVER['GITHUB_PAT'],
] : [];
$getter     = new Getter(headers: $headers);
// The installation SQL files and folder listings of tagged versions never change; cache them for a long time.
$longGetter = new Getter(headers: $headers, cacheSeconds: 31536000);
$jVersions  = new JoomlaVersions($getter);
$extensions = (new Extensions($jVersions->sqlFileURLs(), $longGetter))->withVersionLimits();
$tables     = (new Tables($jVersions->sqlFolderURLs(), $longGetter))->withVersionLimits();
$allRelevantVersions = $jVersions->relevantTags();
$lastVersion         = array_reduce(
	$allRelevantVersions,
	fn ($carry, $value) => version_compare($carry, $value, 'gt') ? $carry : $value,
	'0.0.0'
);
$generationDate      = gmdate('Y-m-d');

file_put_contents(
	'extensions.json',
	json_encode(array_values($extensions), JSON_PRETTY_PRINT)
);

$markdown = <<< MARKDOWN
Generated on $generationDate for Joomla versions up to $lastVersion

| Type | Element | Folder | Client ID | Min. Version | Max. Version |
|------|------|------|------|------|------|

MARKDOWN;

uasort(
	$extensions,
	fn($a, $b) => $a['type'] <=> $b['type']
);

foreach ($extensions as $extension)
{
	$type     = ucfirst($extension['type']);
	$client   = $extension['client_id'] ? 'Administrator' : 'Site';
	$markdown .= <<< MARKDOWN
| $type | {$extension['element']} | {$extension['folder']} | {$client} | {$extension['min']} | {$extension['max']} |
 
MARKDOWN;

}

file_put_contents(
	'extensions.md',
	$markdown
);

file_put_contents(
	'tables.json',
	json_encode(array_values($tables), JSON_PRETTY_PRINT)
);

$markdown = <<< MARKDOWN
Generated on $generationDate for Joomla versions up to $lastVersion

| Table | Min. Version | Max. Version |
|------|------|------|

MARKDOWN;

foreach ($tables as $table)
{
	$markdown .= <<< MARKDOWN
| {$table['table']} | {$table['min']} | {$table['max']} |

MARKDOWN;
}

file_put_contents(
	'tables.md',
	$markdown
);

$lastUpdate          = 'Last Updated: ' . gmdate('D, d M Y H:i:s O') . ' with Joomla! ' . $lastVersion;

$contents = implode(
	"\n",
	array_map(
		fn($line) => str_starts_with($line, 'Last Updated:') ? $lastUpdate : $line,
		explode("\n", file_get_contents('README.md'))
	)
);

file_put_contents('README.md', $contents);
