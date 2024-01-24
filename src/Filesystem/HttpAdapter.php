<?php

namespace Flowan\LaravelFilesystemHttp\Filesystem;

use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

class HttpAdapter implements FilesystemAdapter
{
    protected $client;

    protected string $bucket = 'public';

    public function __construct(
        protected array $config
    ) {
        $this->client = Http::withBasicAuth(
            $this->config['username'],
            $this->config['password']
        )->baseUrl($this->config['url'].'/api/');

        if (! empty($this->config['bucket'])) {
            $this->setBucket($this->config['bucket']);
        }
    }

    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->client->post('file/exists', [
                'bucket' => $this->bucket,
                'path' => $path,
            ])->object()->exists;
        } catch (\Throwable $exception) {
            throw UnableToCheckFileExistence::forLocation($path, $exception);
        }
    }

    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        try {
            return $this->client->post('directory/exists', [
                'bucket' => $this->bucket,
                'path' => $path,
            ])->object()->exists;
        } catch (\Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    /**
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $response = $this->client->post('file', [
                'bucket' => $this->bucket,
                'path' => $path,
                'contents' => $contents,
            ]);

            if ($response->status() !== 200) {
                throw UnableToWriteFile::atLocation($path, $response->body());
            }
        } catch (\Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage());
        }
    }

    /**
     * @param  resource  $contents
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        // TODO implement
    }

    /**
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read(string $path): string
    {
        try {
            $response = $this->client->get('file', [
                'bucket' => $this->bucket,
                'path' => $path,
            ]);

            if ($response->status() !== 200) {
                throw UnableToReadFile::fromLocation($path, 'File not found');
            }

            return $response->body();
        } catch (\Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, '', $exception);
        }
    }

    /**
     * @return resource
     *
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function readStream(string $path)
    {
        // TODO implement

        return fopen('php://temp', 'r+');
    }

    /**
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $path): void
    {
        try {
            $response = $this->client->delete('file', [
                'bucket' => $this->bucket,
                'path' => $path,
            ]);

            if ($response->status() !== 200) {
                throw UnableToDeleteFile::atLocation($path, 'File could not be deleted');
            }
        } catch (\Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, '', $exception);
        }
    }

    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $response = $this->client->delete('directory', [
                'bucket' => $this->bucket,
                'path' => $path,
            ]);

            if ($response->status() !== 200) {
                throw UnableToDeleteDirectory::atLocation($path, 'Directory could not be deleted');
            }
        } catch (\Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, '', $exception);
        }
    }

    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $response = $this->client->post('directory', [
                'bucket' => $this->bucket,
                'path' => $path,
            ]);

            if ($response->status() !== 200) {
                throw UnableToCreateDirectory::atLocation($path, $response->body());
            }
        } catch (\Throwable $exception) {
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage());
        }
    }

    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        // TODO implement
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            $meta = $this->getMeta($path);

            if ($meta instanceof \Throwable) {
                throw $meta;
            }

            return new FileAttributes(
                $path,
                null,
                $meta->visibility,
                null,
                null,
                [],
            );
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::visibility($path);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $meta = $this->getMeta($path);

            if ($meta instanceof \Throwable) {
                throw $meta;
            }

            return new FileAttributes(
                $path,
                null,
                null,
                null,
                $meta->mime_type,
                [],
            );
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $meta = $this->getMeta($path);

            if ($meta instanceof \Throwable) {
                throw $meta;
            }

            return new FileAttributes(
                $path,
                null,
                null,
                $meta->last_modified,
                null,
                [],
            );
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::lastModified($path);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $meta = $this->getMeta($path);

            if ($meta instanceof \Throwable) {
                throw $meta;
            }

            return new FileAttributes(
                $path,
                $meta->size,
                null,
                null,
                null,
                [],
            );
        } catch (\Throwable $exception) {
            throw UnableToRetrieveMetadata::fileSize($path);
        }
    }

    /**
     * @return iterable<StorageAttributes>
     *
     * @throws FilesystemException
     */
    public function listContents(string $path, bool $deep): iterable
    {
        // TODO implement

        return [];
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        // TODO implement
    }

    /**
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        // TODO implement
    }

    public function setBucket(string $bucket)
    {
        $this->bucket = $bucket;
    }

    protected function getMeta(string $path)
    {
        try {
            $response = $this->client->post('file/meta', [
                'bucket' => $this->bucket,
                'path' => $path,
            ]);

            return $response->object()->meta;
        } catch (\Throwable $exception) {
            return $exception;
        }
    }
}
