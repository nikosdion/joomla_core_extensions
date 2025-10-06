<?php

use Dionysopoulos\JoomlaCoreExtensions\Extensions;
use Dionysopoulos\JoomlaCoreExtensions\Getter;
use Dionysopoulos\JoomlaCoreExtensions\JoomlaVersions;
use Dotenv\Dotenv;

require_once 'vendor/autoload.php';

Dotenv::createImmutable(__DIR__)->safeLoad();

$getter     = new Getter(
	headers: ($_SERVER['GITHUB_PAT'] ?? null) ? [
		'X-GitHub-Api-Version' => '2022-11-28',
		'Authorization'        => 'Bearer ' . $_SERVER['GITHUB_PAT'],
	] : []
);
$jVersions  = new JoomlaVersions();
$extensions = (new Extensions(($jVersions)->sqlFileURLs()))->withVersionLimits();

file_put_contents(
	'extensions.json',
	json_encode(array_values($extensions), JSON_PRETTY_PRINT)
);

$markdown = <<< MARKDOWN
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

$allRelevantVersions = $jVersions->relevantTags();
$lastVersion         = array_reduce(
	$allRelevantVersions,
	fn ($carry, $value) => version_compare($carry, $value, 'gt') ? $carry : $value,
	'0.0.0'
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