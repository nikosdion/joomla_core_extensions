<?php
/**
 * @package   nikosdion/joomla_core_extensions
 * @license   AGPL-3.0-or-later
 * @copyright Copyright (c) 2025-2026 Nicholas K. Dionysopoulos
 */

namespace Dionysopoulos\JoomlaCoreExtensions;

use Kodus\SQLSplit\Splitter;

/**
 * Finds the core database tables created by each Joomla! version family.
 */
class Tables
{
	/**
	 * Constructor.
	 *
	 * @param   array                    $sqlFolders  Version family => GitHub Contents API URL of installation/sql/mysql
	 * @param   Getter|null              $getter      The HTTP getter to use.
	 * @param   CallbackController|null  $cache       The cache controller to use.
	 */
	public function __construct(
		private array $sqlFolders,
		private ?Getter $getter = null,
		private ?CallbackController $cache = null
	)
	{
		$this->getter ??= new Getter(cacheSeconds: 31536000);
		$this->cache  ??= new CallbackController(3600, 'tables');
	}

	/**
	 * Returns the tables created by each version family, keyed by version family.
	 *
	 * @return  array
	 */
	public function byVersion(): array
	{
		return json_decode(
			$this->cache->get('by_version', fn() => json_encode($this->allTables_real())),
			true
		);
	}

	/**
	 * Returns all tables ever created by Joomla!, with the minimum and maximum version family they were found in.
	 *
	 * @return  array
	 */
	public function withVersionLimits(): array
	{
		$ret = [];

		foreach ($this->byVersion() as $version => $tables)
		{
			foreach ($tables as $table)
			{
				$ret[$table] ??= [
					'table' => $table,
					'min'   => $version,
					'max'   => $version,
				];

				if (version_compare($version, $ret[$table]['min'], 'lt'))
				{
					$ret[$table]['min'] = $version;
				}

				if (version_compare($version, $ret[$table]['max'], 'gt'))
				{
					$ret[$table]['max'] = $version;
				}
			}
		}

		ksort($ret);

		return $ret;
	}

	private function allTables_real(): array
	{
		$return = [];

		foreach ($this->sqlFolders as $version => $folderUrl)
		{
			$files = $this->getter->getJson($folderUrl);

			if (!is_array($files))
			{
				continue;
			}

			$tables = [];

			foreach ($files as $fileInfo)
			{
				if (($fileInfo->type ?? '') !== 'file'
				    || !str_ends_with(strtolower($fileInfo->name ?? ''), '.sql')
				    || empty($fileInfo->download_url))
				{
					continue;
				}

				$contents = $this->getter->get($fileInfo->download_url);

				if (empty($contents))
				{
					continue;
				}

				$tables = array_merge($tables, $this->tablesInSql($contents));
			}

			if (empty($tables))
			{
				continue;
			}

			$tables = array_values(array_unique($tables));
			sort($tables);

			$return[$version] = $tables;
		}

		return $return;
	}

	/**
	 * Returns the names of the tables created by the CREATE TABLE statements in an SQL file.
	 *
	 * @param   string  $contents  The SQL file contents.
	 *
	 * @return  array
	 */
	private function tablesInSql(string $contents): array
	{
		// Older Joomla versions have `$Id$` in a comment which confuses the splitter.
		$contents = str_replace('$Id$', '', $contents);
		$tables   = [];

		foreach (Splitter::split($contents, true) as $query)
		{
			$query = trim($this->snipComments($query));

			if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(#__\w+)[`"]?/i', $query, $matches))
			{
				$tables[] = $matches[1];
			}
		}

		return $tables;
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
}
