<?php
/**
 * @package   topostgresql
 * @copyright Copyright (c) 2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License, version 3 or later
 */

namespace Dionysopoulos\JoomlaCoreExtensions;

use z4kn4fein\SemVer\Version;

class JoomlaVersions
{
	public function __construct(private ?Getter $getter = null, private ?CallbackController $cache = null)
	{
		$this->getter ??= new Getter();
		$this->cache  ??= new CallbackController(3600, 'versions');
	}

	/**
	 * Returns all tags in the Joomla! CMS repository.
	 *
	 * @return  array
	 */
	public function allTags(): array
	{
		$callback = function (): string {
			$page = 0;
			$tags = [];

			while (true)
			{
				$page++;
				$url = 'https://api.github.com/repos/joomla/joomla-cms/tags?per_page=100&page=' . $page;
				try
				{
					$data = $this->getter->getJson($url);
				}
				catch (\Exception $e)
				{
					break;
				}

				if (empty($data))
				{
					break;
				}

				foreach ($data as $tagInfo)
				{
					$tags[] = $tagInfo->name;
				}
			}

			return json_encode($tags);
		};

		return json_decode($this->cache->get('tags', $callback), true);
	}

	/**
	 * Returns all tags in the Joomla! CMS repository which are a valid CMS version.
	 *
	 * @return  array
	 */
	public function validTags(): array
	{
		$callback = function () {
			return json_encode(
				array_filter(
					$this->allTags(),
					function (string $tag): bool {
						/**
						 * We are trying to filter out "invalid" tag names which fall into the following categories:
						 * - Tags which are words/phrases, e.g. `vPBF1`, `searchmerge`, `cypress` etc.
						 * - Tags which don't follow the x.y.z versioning scheme, e.g. `12.0`, `13.1` etc. (Joomla Platform tags)
						 * - Tags which start with a `v`. None are present as of this writing, but who knows what will happen.
						 *
						 * We do include alpha, beta, and RC tags, as long as they follow the SemVer spec.
						 */
						return preg_match('/^\d+\.\d+\.\d+/', $tag)
						       && Version::parseOrNull($tag) !== null;
					}
				)
			);
		};

		return json_decode($this->cache->get('valid_tags', $callback), true);
	}

	public function relevantTags(): array
	{
		$callback = function() {
			$tags = $this->validTags();
			uasort($tags, 'version_compare');

			// Distribute tags into minor releases
			$minors = [];

			foreach ($tags as $tag)
			{
				$version = Version::parseOrNull($tag);

				if (!$version)
				{
					continue;
				}

				$minor = $version->getMajor() . '.' . $version->getMinor();
				$minors[$minor] ??= [];
				$minors[$minor] = $tag;
			}

			return json_encode(array_values($minors));
		};

		return json_decode($this->cache->get('relevant_tags', $callback), true) ?? [];
	}

	public function sqlFileURLs(): array
	{
		$callback = function () {
			$tags = $this->relevantTags();
			$urls = array_map(
				fn(string $tag): string => Version::parseOrNull($tag)?->getMajor() < 4
					? sprintf(
						"https://raw.githubusercontent.com/joomla/joomla-cms/refs/tags/%s/installation/sql/mysql/joomla.sql",
						$tag
					)
					: sprintf(
						"https://raw.githubusercontent.com/joomla/joomla-cms/refs/tags/%s/installation/sql/mysql/base.sql",
						$tag
					),
				$tags
			);
			$tags = array_map(
				function (string $tag): string
				{
					$version = Version::parseOrNull($tag);

					return ($version?->getMajor() ?? '0') . '.' . ($version?->getMinor() ?? '0');
				},
				$tags
			);

			return json_encode(
				array_filter(
					array_combine($tags, $urls),
					fn(string $key) => !empty($key) && $key !== '0.0',
					ARRAY_FILTER_USE_KEY
				)
			);
		};

		return json_decode($this->cache->get('sql_urls', $callback), true) ?? [];
	}
}