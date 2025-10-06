<?php
/**
 * @package   topostgresql
 * @copyright Copyright (c) 2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License, version 3 or later
 */

namespace Dionysopoulos\JoomlaCoreExtensions;

use Kodus\SQLSplit\Splitter;
use PHPSQLParser\PHPSQLParser;

class Extensions
{
	public function __construct(
		private array $sqlFiles,
		private ?Getter $getter = null,
		private ?CallbackController $cache = null
	)
	{
		$this->getter ??= new Getter(cacheSeconds: 31536000);
		$this->cache  ??= new CallbackController(3600, 'extensions');
	}

	public function byVersion(): array
	{
		return json_decode(
			$this->cache->get('by_version', fn() => json_encode($this->allExtensions_real())),
			true
		);
	}

	public function withVersionLimits(): array
	{
		$byVersion = $this->byVersion();
		$ret       = [];

		foreach ($byVersion as $version => $extensions)
		{
			foreach ($extensions as $shortKey => $extension)
			{
				$ret[$shortKey] ??= array_merge(
					$extension,
					[
						'min' => $version,
						'max' => $version,
					]
				);

				if (version_compare($version, $ret[$shortKey]['min'], 'lt'))
				{
					$ret[$shortKey]['min'] = $version;
				}

				if (version_compare($version, $ret[$shortKey]['max'], 'gt'))
				{
					$ret[$shortKey]['max'] = $version;
				}
			}
		}

		return $ret;
	}


	private function snipComments(string $query): string
	{
		return implode(
			"\n",
			array_filter(
				explode("\n", $query),
				fn($part) => !str_starts_with($part, '#') && !str_starts_with($part, '--')
			)
		);
	}

	private function allExtensions_real(): array
	{
		$return = [];
		$parser = new PHPSQLParser();

		foreach ($this->sqlFiles as $version => $url)
		{
			$contents = $this->getter->get($url);

			if (empty($contents))
			{
				continue;
			}

			// Older Joomla versions have `$Id$` in a comment which confuses the splitter.
			$contents = str_replace('$Id$', '', $contents);

			// Split the queries, remove comments, and keep only queries inserting into extensions.
			$queries = Splitter::split($contents, true);
			$queries = array_map([$this, 'snipComments'], $queries);
			$queries = array_filter(
				$queries,
				fn($x) => str_starts_with($x, 'INSERT INTO') && str_contains($x, '`#__extensions`')
			);

			$extensions = [];

			foreach ($queries as $query)
			{
				// Parse the query
				$parsed = $parser->parse($query);

				// Make sure it's an insert query that has a usable VALUES clause.
				if (!isset($parsed['INSERT']) || !isset($parsed['VALUES']))
				{
					continue;
				}

				// Make sure it's an INSERT INTO `#__extensions`.
				$tableInfo = $this->findExpressionType($parsed['INSERT'], 'table');

				if (empty($tableInfo) || ($tableInfo['no_quotes']['parts'][0] ?? '') !== '#__extensions')
				{
					continue;
				}

				// Get the column list.
				$colListInfo = $this->findExpressionType($parsed['INSERT'], 'column-list');

				if (empty($colListInfo) || !isset($colListInfo['sub_tree']))
				{
					continue;
				}

				$columns = $this->parseColumnReferences($this->findExpressionTypes($colListInfo['sub_tree'], 'colref'));

				// Get all the rows of values.
				$rows = [];

				foreach ($parsed['VALUES'] as $record)
				{
					if ($record['expr_type'] !== 'record')
					{
						continue;
					}

					$row = [];

					foreach ($record['data'] as $datum)
					{
						$row[] = $datum['base_expr'];
					}

					$rows[] = $row;
				}

				// Map each row so that it is associative to the column names.
				$rows = array_map(
					fn($row) => array_combine($columns, $row),
					$rows
				);

				$extensions = array_merge(
					$extensions,
					array_combine(
						array_map([$this, 'extensionShortCode'], $rows),
						array_map(
							fn($row) => [
								'type'      => $this->unquote($row['type']),
								'element'   => $this->unquote($row['element']),
								'folder'    => $this->unquote($row['folder']),
								'client_id' => $this->unquote($row['client_id']),
							],
							$rows
						)
					)
				);
			}

			if (empty($extensions))
			{
				continue;
			}

			$return[$version] = $extensions;
		}

		return $return;
	}

	private function findExpressionTypes(array $parsed, string $type): array
	{
		$ret = [];

		foreach ($parsed as $part)
		{
			if ($part['expr_type'] === $type)
			{
				$ret[] = $part;
			}
		}

		return $ret;
	}

	private function findExpressionType(array $parsed, string $type): ?array
	{
		return $this->findExpressionTypes($parsed, $type)[0] ?? null;
	}

	private function parseColumnReferences(array $findExpressionTypes)
	{
		$ret = [];

		foreach ($findExpressionTypes as $info)
		{
			$colName = $info['no_quotes']['parts'][0] ?? '';

			if (!$colName)
			{
				continue;
			}

			$ret[] = $colName;
		}

		return $ret;
	}

	private function extensionShortCode(array $record): string
	{
		$type      = $this->unquote($record['type']);
		$element   = $this->unquote($record['element']);
		$folder    = $this->unquote($record['folder']);
		$client_id = $this->unquote($record['client_id']);

		return match ($type)
		{
			'component', 'package' => $element,
			'file', 'files', 'library' => str_starts_with($element, $type) ? $element : ($type . '_' . $element),
			'plugin' => 'plg_' . ($folder ?? 'unknown') . ($element === null ? '' : '_') . ($element ?? ''),
			'module' => ($client_id === 0 ? 'a' : '') . $element,
			'language' => ($client_id === 0 ? 'a' : '') . 'lang_' . $element,
			'template' => ($client_id === 0 ? 'atpl_' : 'tpl_') . $element,
			default => null,
		};
	}

	private function unquote(string $string): string
	{
		if (str_starts_with($string, "'") && str_ends_with($string, "'"))
		{
			return substr($string, 1, -1);
		}

		return $string;
	}
}