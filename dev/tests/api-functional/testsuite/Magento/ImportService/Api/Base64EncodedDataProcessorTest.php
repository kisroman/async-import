<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\ImportService\Api;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\ImportService\Api\Data\SourceInterface;
use Magento\ImportService\Model\Import\Processor\Base64EncodedDataProcessor;
use Magento\ImportService\Model\Import\Type\SourceTypeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;

class Base64EncodedDataProcessorTest extends WebapiAbstract
{
    const SERVICE_NAME = 'sourceRepositoryV1';
    const SERVICE_VERSION = 'V1';
    const RESOURCE_PATH = '/V1/import/source';

    const FILE_TYPE = 'csv';

    /**
     * @var \Magento\Framework\Filesystem
     */
    private $fileSystem;

    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Bootstrap::getObjectManager()->create(\Magento\Framework\Filesystem::class);
    }

    public function testImportDataNotSet()
    {
        $result = $this->_webApiCall(
            $this->makeServiceInfo(),
            $this->makeRequestData(null)
        );

        $this->assertEquals(SourceInterface::STATUS_FAILED, $result['status']);
        $this->assertRegExp('/Invalid request/', $result['error']);
        $this->assertNull($result['source']);
    }

    public function testWrongData()
    {
        $result = $this->_webApiCall(
            $this->makeServiceInfo(),
            $this->makeRequestData('Some simple text.')
        );

        $this->assertEquals(SourceInterface::STATUS_FAILED, $result['status']);
        $this->assertEquals('Invalid request: Base64 import data string is invalid.', $result['error']);
        $this->assertNull($result['source']);
    }

    public function testCorrectData()
    {
        $data = 'QUJDREVGR0hhYmNkZWZnaDAxMjM0NTY3ODk=';

        $result = $this->_webApiCall(
            $this->makeServiceInfo(),
            $this->makeRequestData($data)
        );

        /** Assert the response status and the source_id */
        $this->assertEquals(SourceInterface::STATUS_UPLOADED, $result['status']);
        $this->assertNotNull($result['source']['import_data']);

        if (isset($result['source']['import_data'])) {
            $contentFilePath = SourceTypeInterface::IMPORT_SOURCE_FILE_PATH . $result['source']['import_data'];

            $varWriter =  $this->fileSystem->getDirectoryWrite(DirectoryList::VAR_DIR);

            $pathCopiedFile = $varWriter->getAbsolutePath($contentFilePath);

            $this->assertEquals(
                base64_decode($data),
                $varWriter->readFile($pathCopiedFile)
            );

            /** Remove the file from the working directory */
            $varWriter->getDriver()->deleteFile($pathCopiedFile);
        }
    }

    /**
     * Sets up the service info.
     *
     * @return array
     */
    private function makeServiceInfo()
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'Save',
            ],
        ];

        return $serviceInfo;
    }

    /**
     * Sets up the request array.
     *
     * @param string $importData
     *
     * @return array
     */
    private function makeRequestData($importData)
    {
        return [
            'source' => [
                SourceInterface::SOURCE_TYPE => self::FILE_TYPE,
                SourceInterface::IMPORT_TYPE => Base64EncodedDataProcessor::IMPORT_TYPE,
                SourceInterface::IMPORT_DATA => $importData
            ]
        ];
    }
}
