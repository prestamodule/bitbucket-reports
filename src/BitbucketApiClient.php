<?php

declare(strict_types=1);

namespace Swis\Bitbucket\Reports;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BitbucketApiClient
{
    /**
     * Please note this should be http instead of https because of the proxy.
     */
    private const BASE_URL = 'http://api.bitbucket.org/2.0/';

    private const PROXY_URL = 'http://localhost:29418';

    private Client $httpClient;

    private ParentDirectoryRelativePathHelper $relativePathHelper;

    public function __construct(string $baseUrl = self::BASE_URL, string $proxyUrl = self::PROXY_URL)
    {
        $this->httpClient = new Client([
            'base_uri' => $baseUrl,
            RequestOptions::PROXY => $proxyUrl,
        ]);
        $this->relativePathHelper = new ParentDirectoryRelativePathHelper(BitbucketConfig::cloneDir());
    }

    public function createReport(string $title, int $numberOfIssues = 0): UuidInterface
    {
        $payload = $numberOfIssues > 0
            ? [
                'title' => $title,
                'details' => sprintf('This PR introduces %d new issue(s).', $numberOfIssues),
                'report_type' => 'BUG',
                'result' => 'FAILED',
            ]
            : [
                'title' => $title,
                'details' => 'This PR introduces no new issues.',
                'report_type' => 'BUG',
                'result' => 'PASSED',
            ];

        $result = $this->httpClient->put($this->buildReportUrl(), [
            RequestOptions::JSON => $payload,
        ]);

        $resultBody = json_decode((string) $result->getBody(), true);

        return Uuid::fromString($resultBody['uuid']);
    }

    public function addAnnotationsBulk(
        UuidInterface $reportUuid,
        array $annotations
    ): array {
        $payload = [];

        foreach ($annotations as $annotation) {
            $annotationPayload = [
                'annotation_type' => 'BUG',
                'summary' => $annotation['summary'],
            ];

            if (isset($annotation['path'])) {
                $annotationPayload['path'] = $this->relativePathHelper->getRelativePath($annotation['path']);
            }

            if (isset($annotation['line'])) {
                $annotationPayload['line'] = $annotation['line'];
            }

            $payload[] = $annotationPayload;
        }

        $response = $this->httpClient->post($this->buildAnnotationsBulkUrl($reportUuid), [
            RequestOptions::JSON => $payload,
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function addAnnotation(
        UuidInterface $reportUuid,
        string $summary,
        ?string $filePath,
        ?int $line
    ): UuidInterface {
        $payload = [
            'annotation_type' => 'BUG',
            'summary' => $summary,
        ];

        if ($filePath !== null) {
            $payload['path'] = $this->relativePathHelper->getRelativePath($filePath);
        }

        if ($line !== null) {
            $payload['line'] = $line;
        }

        $response = $this->httpClient->put($this->buildAnnotationUrl($reportUuid), [
            RequestOptions::JSON => $payload,
        ]);

        $responseBody = json_decode((string) $response->getBody(), true);

        return Uuid::fromString($responseBody['uuid']);
    }

    private function buildReportUrl(?UuidInterface $uuid = null): string
    {
        return sprintf(
            'repositories/%s/%s/commit/%s/reports/%s',
            BitbucketConfig::repoOwner(),
            BitbucketConfig::repoSlug(),
            BitbucketConfig::commit(),
            $uuid !== null ? '{'.$uuid->toString().'}' : $this->buildReportName()
        );
    }

    private function buildAnnotationsBulkUrl(UuidInterface $reportUuid): string
    {
        return sprintf(
            '%s/annotations',
            $this->buildReportUrl($reportUuid),
        );
    }

    private function buildAnnotationUrl(UuidInterface $reportUuid): string
    {
        return sprintf(
            '%s/annotations/%s',
            $this->buildReportUrl($reportUuid),
            $this->buildAnnotationName()
        );
    }

    private function buildReportName(): string
    {
        return BitbucketConfig::repoSlug().'-'.Uuid::uuid4()->toString();
    }

    private function buildAnnotationName(): string
    {
        return BitbucketConfig::repoSlug().'-annotation-'.Uuid::uuid4()->toString();
    }
}
