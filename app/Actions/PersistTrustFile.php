<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\FailureException;
use App\ValueObjects\Project;
use App\Support\Json;

final readonly class PersistTrustFile
{
    public const int SCHEMA = 3;

    private const array ORDER = [
        'schema',
        'require',
        'require-dev',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public string $path,
        private array $data,
    ) {}

    public static function forProject(Project $project): self
    {
        return self::atPath($project->portoFilePath());
    }

    public static function atPath(string $path): self
    {
        if (! is_file($path)) {
            return new self($path, []);
        }

        $data = Json::readFile($path, 'the porto file');

        $schema = $data['schema'] ?? null;

        if ($schema === 1 || $schema === 2) {
            throw new FailureException(sprintf(
                'The porto file [%s] declares schema %d, which recorded permissions. Schema %d records the version and the hash of each package that you trust. Delete the file and run `porto trust` again.',
                $path,
                $schema,
                self::SCHEMA,
            ));
        }

        if ($schema !== self::SCHEMA) {
            throw new FailureException(sprintf(
                'The porto file [%s] declares schema %s; this build of porto reads schema %d.',
                $path,
                is_scalar($schema) ? (string) $schema : 'none',
                self::SCHEMA,
            ));
        }

        return new self($path, $data);
    }

    public function has(string $section): bool
    {
        return isset($this->data[$section]);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function section(string $section): array
    {
        return Json::array($this->data, $section);
    }

    /**
     * @param  array<string, mixed>  $sections
     */
    public function write(array $sections): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new FailureException(sprintf('Could not create the directory [%s].', $directory));
        }

        $merged = [...self::atPath($this->path)->data, ...$sections, 'schema' => self::SCHEMA];

        $ordered = [];

        foreach (self::ORDER as $key) {
            if (array_key_exists($key, $merged)) {
                $ordered[$key] = $merged[$key];
            }
        }

        foreach ($merged as $key => $value) {
            if (! array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        if (@file_put_contents($this->path, Json::encode($ordered)) === false) {
            throw new FailureException(sprintf('Could not write the porto file to [%s].', $this->path));
        }
    }
}
