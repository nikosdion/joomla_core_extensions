<?php
/**
 * @package   topostgresql
 * @copyright Copyright (c) 2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License, version 3 or later
 */

namespace Dionysopoulos\JoomlaCoreExtensions;

/**
 * A simple cache controller using callbacks.
 */
class CallbackController
{
	/**
	 * Initialises a new cache instance with the specified settings.
	 *
	 * A cache time of 0, or less, results in the cache being immediately bypassed.
	 *
	 * @param   int     $cacheSeconds  The duration, in seconds, for which the cache remains valid. Default is 3600 seconds.
	 * @param   string  $category      The namespace (and subdirectory) of the cache. The default is 'cache'.
	 *
	 * @return  void
	 */
	public function __construct(private int $cacheSeconds = 3600, private string $category = 'cache') {}

	/**
	 * Fetches data from the cache, or uses the callback to generate it.
	 *
	 * @param   string    $id        The unique identifier for the cache file.
	 * @param   callable  $callback  A callback function to generate the data if not available
	 *                               or expired in the cache.
	 *
	 * @return  string|null
	 */
	public function get(string $id, callable $callback): ?string
	{
		// A cache time of zero, or less, seconds means "bypass cache".
		if ($this->cacheSeconds <= 0)
		{
			return $callback();
		}

		// Do I have a cache file?
		$cachePath   = __DIR__ . '/../tmp/' . $this->category;
		$cacheFile   = $cachePath . '/' . $id;
		$lastModTime = 0;

		if (file_exists($cacheFile))
		{
			$lastModTime = @filemtime($cacheFile) ?: 0;
		}

		if (time() - $lastModTime < $this->cacheSeconds)
		{
			// We have a cache file. Try to return data from it.
			$data = file_get_contents($cacheFile);

			if ($data !== false)
			{
				return $data;
			}
		}

		// Make sure the cache path exists.
		if (!is_dir($cachePath))
		{
			@mkdir($cachePath, 0755, true);
		}

		// Get the data using the callback.
		$data = $callback();

		// Only string data can be cached.
		if (is_string($data))
		{
			file_put_contents($cacheFile, $data);
		}

		// Return the data we just fetched.
		return $data;
	}

}