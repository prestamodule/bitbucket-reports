<?php

namespace Swis\PHPStan\ErrorFormatter;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\ErrorFormatter\TableErrorFormatter;
use PHPStan\Command\Output;
use PHPStan\File\ParentDirectoryRelativePathHelper;

class BitbucketErrorFormatter implements ErrorFormatter
{
    private TableErrorFormatter $tableErrorFormatter;

    private ParentDirectoryRelativePathHelper $relativePathHelper;

    private BitbucketApiClient $apiClient;

    public function __construct(TableErrorFormatter $tableErrorFormatter)
    {
        $this->tableErrorFormatter = $tableErrorFormatter;
        // @phpstan-ignore-next-line
        $this->relativePathHelper = new ParentDirectoryRelativePathHelper(BitbucketConfig::cloneDir());
        $this->apiClient = new BitbucketApiClient();
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $this->tableErrorFormatter->formatErrors($analysisResult, $output);

        $reportUuid = $this->apiClient->createReport($analysisResult->getTotalErrorsCount());

        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            $this->apiClient->addAnnotation(
                $reportUuid,
                $error->getMessage(),
                // @phpstan-ignore-next-line
                $this->relativePathHelper->getRelativePath($error->getFile()),
                $error->getLine()
            );
        }

        foreach ($analysisResult->getNotFileSpecificErrors() as $error) {
            $this->apiClient->addAnnotation(
                $reportUuid,
                $error,
                null,
                null
            );
        }

        return (int) $analysisResult->hasErrors();
    }
}
