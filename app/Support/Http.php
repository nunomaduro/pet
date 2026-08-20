<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\FetchFailed;

final readonly class Http
{
    private const int TIMEOUT = 60;

    public function __construct(
        private string $userAgent,
        private ?string $githubToken = null,
    ) {}

    public static function default(): self
    {
        return new self('porto (+https://github.com/nunomaduro/porto)', self::discoverGithubToken());
    }

    public function get(string $url): string
    {
        $body = extension_loaded('curl')
            ? $this->getWithCurl($url)
            : $this->getWithStreams($url);

        if (trim($body) === '') {
            throw FetchFailed::empty($url);
        }

        return $body;
    }

    public function download(string $url, string $destination): void
    {
        $directory = dirname($destination);

        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw FetchFailed::transport($url, sprintf('could not create the directory [%s].', $directory));
        }

        $temporary = $destination.'.'.bin2hex(random_bytes(6)).'.part';

        try {
            $body = $this->get($url);

            if (file_put_contents($temporary, $body) === false) {
                throw FetchFailed::transport($url, sprintf('could not write to [%s].', $temporary));
            }

            if ((int) filesize($temporary) === 0) {
                throw FetchFailed::empty($url);
            }

            if (! rename($temporary, $destination)) {
                throw FetchFailed::transport($url, sprintf('could not move the download into [%s].', $destination));
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function discoverGithubToken(): ?string
    {
        foreach (['PORTO_GITHUB_TOKEN', 'GITHUB_TOKEN', 'GH_TOKEN'] as $variable) {
            $value = getenv($variable);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach (self::authFilePaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            $contents = @file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            $oauth = $decoded['github-oauth'] ?? null;

            if (is_array($oauth) && isset($oauth['github.com']) && is_string($oauth['github.com'])) {
                return $oauth['github.com'];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function authFilePaths(): array
    {
        $paths = [];

        $composerAuth = getenv('COMPOSER_AUTH_FILE');

        if (is_string($composerAuth) && $composerAuth !== '') {
            $paths[] = $composerAuth;
        }

        $composerHome = getenv('COMPOSER_HOME');
        $home = getenv('HOME');

        if (is_string($composerHome) && $composerHome !== '') {
            $paths[] = Path::join($composerHome, 'auth.json');
        } elseif (is_string($home) && $home !== '') {
            $paths[] = Path::join($home, '.composer/auth.json');
            $paths[] = Path::join($home, '.config/composer/auth.json');
        }

        return $paths;
    }

    /**
     * @return array<int, string>
     */
    private function headers(string $url): array
    {
        $headers = [
            'User-Agent: '.$this->userAgent,
            'Accept: application/json, application/zip;q=0.9, */*;q=0.8',
        ];

        $host = parse_url($url, PHP_URL_HOST);

        if ($this->githubToken !== null && is_string($host) && str_ends_with($host, 'github.com')) {
            $headers[] = 'Authorization: Bearer '.$this->githubToken;
        }

        return $headers;
    }

    private function getWithCurl(string $url): string
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw FetchFailed::transport($url, 'could not initialise curl.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => $this->headers($url),
            CURLOPT_ENCODING => '',
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        curl_close($handle);

        if ($body === false || $error !== '') {
            throw FetchFailed::transport($url, $error === '' ? 'the transfer failed.' : $error);
        }

        if ($status < 200 || $status >= 300) {
            throw FetchFailed::status($url, $status, is_string($body) ? $body : '');
        }

        return (string) $body;
    }

    private function getWithStreams(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->headers($url)),
                'timeout' => self::TIMEOUT,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $http_response_header = [];

        $body = @file_get_contents($url, false, $context);

        $status = 0;

        foreach ($http_response_header as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        if ($body === false) {
            throw FetchFailed::transport($url, 'the transfer failed.');
        }

        if ($status < 200 || $status >= 300) {
            throw FetchFailed::status($url, $status, $body);
        }

        return $body;
    }
}
