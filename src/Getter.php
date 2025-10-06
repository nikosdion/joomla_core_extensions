<?php
/**
 * @package   topostgresql
 * @copyright Copyright (c) 2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License, version 3 or later
 */

namespace Dionysopoulos\JoomlaCoreExtensions;

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;

/**
 * A simple, caching HTTP getter.
 */
class Getter
{
	private CallbackController $cache;

	/**
	 * Constructor for initialising the HTTP client and cache duration.
	 *
	 * A cache time of 0, or less, results in the cache being immediately bypassed.
	 *
	 * @param   Http|null  $http          An optional HTTP client instance.
	 * @param   int        $cacheSeconds  The duration, in seconds, for cached responses. Defaults to 3600 seconds.
	 *
	 * @return  void
	 */
	public function __construct(private ?Http $http = null, private array $headers = [], int $cacheSeconds = 3600)
	{
		$this->cache = new CallbackController($cacheSeconds);
		$this->http = $http ?? (new HttpFactory())->getHttp(
			[
				'userAgent' => 'JoomlaCoreExtensions/1.0',
			]
		);
	}

	/**
	 * Retrieves the contents of a URL if it is successfully fetched with a status code of 200.
	 *
	 * @param   string  $url  The URL to be fetched.
	 *
	 * @return  string|null  The contents of the URL or null if the request is unsuccessful.
	 */
	public function get(string $url): ?string
	{
		return $this->cache->get(
			sha1($url),
			function () use ($url) {
				$response = $this->http->get($url, $this->headers);

				if ($response->getStatusCode() !== 200)
				{
					return null;
				}

				$body = $response->getBody();
				$body->rewind();

				return $body->getContents();
			}
		);
	}

	/**
	 * Fetches the contents of a URL and decodes the JSON response.
	 *
	 * @param   string  $url  The URL to fetch the JSON from.
	 *
	 * @return  mixed  The decoded JSON data, or null if the request fails, or the JSON is invalid.
	 */
	public function getJson(string $url): mixed
	{
		$json = $this->get($url);

		if ($json === null)
		{
			return null;
		}

		try
		{
			return json_decode($json, false, flags: JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $e)
		{
			return null;
		}
	}
}