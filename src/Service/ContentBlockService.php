<?php

/*
 * This file is part of the GLAVWEB.cms Content Block package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\CmsContentBlock\Service;

use Psr\Http\Message\ResponseInterface;
use Glavweb\CmsRestClient\CmsRestClient;

/**
 * Class ContentBlockService
 *
 * @package Glavweb\CmsContentBlock
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class ContentBlockService
{
    /**
     * Http status constants
     */
    const HTTP_OK              = 200;
    const HTTP_CREATED         = 201;
    const HTTP_PARTIAL_CONTENT = 206;

    /**
     * @var array
     */
    private static $contentBlocksCache = null;

    /**
     * @var CmsRestClient
     */
    private $restClient;

    /**
     * ContentBlockService constructor.
     *
     * @param CmsRestClient $restClient
     */
    public function __construct(CmsRestClient $restClient)
    {
        $this->restClient = $restClient;
    }

    /**
     * @param string $category
     * @param string $blockName
     * @param string $defaultContent
     * @return string
     */
    public function getContentBlock($category, $blockName, $defaultContent = null)
    {
        $contentBlocks = $this->getContentBlockListByCategory($category);
        $contentBlock  = isset($contentBlocks[$blockName]) ? $contentBlocks[$blockName] : null;

        // Create new block
        if ($contentBlock === null) {
            if ($defaultContent !== null) {
                $this->createContentBlock($category, $blockName, $defaultContent);
            }

            $contentBlockBody = (string)$defaultContent;

        } else {
            $contentBlockBody = $contentBlock['body'];
        }

        return $contentBlockBody;
    }

    /**
     * @param string $category
     * @param string $blockName
     * @return array
     */
    public function getContentBlockAttributes($category, $blockName)
    {
        $contentBlocks = $this->getContentBlockListByCategory($category);
        $contentBlock  = isset($contentBlocks[$blockName]) ? $contentBlocks[$blockName] : null;

        $attributes = [];
        if ($contentBlock && isset($contentBlock['attributes'])) {
            foreach ($contentBlock['attributes'] as $attributeItem) {
                $attributes[$attributeItem['name']] = $attributeItem['body'];
            }
        }

        return $attributes;
    }

    /**
     * Editable block
     *
     * @param string $category
     * @param string $blockName
     * @return string
     */
    public function editable($category, $blockName)
    {
        $attributes = $this->getContentBlockAttributes($category, $blockName);

        $attributes['data-content-block']          = 'true';
        $attributes['data-content-block-category'] = $category;
        $attributes['data-content-block-name']     = $blockName;

        $attrParts = array();
        foreach ($attributes as $attrName => $attrValue) {
            $attrParts[] = sprintf('%s="%s"', $attrName, $attrValue);
        }

        return ' ' . implode(' ', $attrParts);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getContentBlocks()
    {
        if (self::$contentBlocksCache === null) {
            self::$contentBlocksCache = $this->doGetContentBlocks();
        }

        return self::$contentBlocksCache;
    }

    /**
     * @param array $contentBlocks
     * @param int   $offset
     * @return array
     * @throws \Exception
     */
    private function doGetContentBlocks($contentBlocks = [], $offset = 0)
    {
        $limit = 1000;

        $response = $this->restClient->get('content-blocks', [
            'query' => [
                '_offset' => $offset,
                '_limit'  => $limit
            ]
        ]);
        $responseStatusCode = $response->getStatusCode();

        if ($responseStatusCode != self::HTTP_OK && $responseStatusCode != self::HTTP_PARTIAL_CONTENT) {
            throw new \Exception('Can not get content blocks.');
        }

        $listArray = json_decode($response->getBody(), true);


        foreach ($listArray as $item) {
            $category = $item['category'];
            $name     =  $item['name'];

            $contentBlocks[$category][$name] = $item;
        }

        $needAdditionalLoad =
            $responseStatusCode == self::HTTP_PARTIAL_CONTENT &&
            !empty($listArray) &&
            $this->getMaxResultByResponse($response) > $offset + $limit
        ;

        if ($needAdditionalLoad) {
            $contentBlocks = $this->doGetContentBlocks($contentBlocks, $offset + $limit);
        }

        return $contentBlocks;
    }

    /**
     * @param string $category
     * @return array
     * @throws \Exception
     */
    public function getContentBlockListByCategory($category)
    {
        $contentBlocks = $this->getContentBlocks();

        return isset($contentBlocks[$category]) ? $contentBlocks[$category] : [];
    }

    /**
     * @param string $category
     * @param string $blockName
     * @param string $defaultContent
     * @return string
     * @throws \Exception
     */
    private function createContentBlock($category, $blockName, $defaultContent)
    {
        $response = $this->restClient->post('content-blocks', [
            'form_params' => [
                'category' => $category,
                'name'     => $blockName,
                'body'     => $defaultContent
            ]
        ], true);

        if (!$response->getStatusCode() == self::HTTP_CREATED) {
            throw new \Exception('Can not save content block.');
        }

        $locationResponse = $response->getHeader('location');
        if (!isset($locationResponse[0])) {
            throw new \Exception('Location is not returned from API.');
        }

        // Set content block in cache
        $location = $locationResponse[0];
        $contentBlockItem = $this->getContentBlockByLocation($location);

        self::$contentBlocksCache[$category][$blockName] = $contentBlockItem;
    }

    /**
     * @param ResponseInterface $response
     * @return int
     * @throws \Exception
     */
    private function getMaxResultByResponse(ResponseInterface $response)
    {
        $contentRange = $response->getHeader('Content-Range');

        if (!isset($contentRange[0])) {
            throw new \Exception('Header "Content-Range" is not returned from API.');
        }

        $maxResult = explode('/', $contentRange[0])[1];

        return (int)$maxResult;
    }

    /**
     * @param string $location
     * @return array
     * @throws \Exception
     */
    private function getContentBlockByLocation($location)
    {
        $contentBlockResponse = $this->restClient->get($location);

        if (!$contentBlockResponse->getStatusCode() == self::HTTP_OK) {
            throw new \Exception('Can not get content block by "' . $location . '".');
        }

        $contentBlockItem = json_decode($contentBlockResponse->getBody(), true);

        return $contentBlockItem;
    }
}