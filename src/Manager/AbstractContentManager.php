<?php

/*
 * This file is part of the GLAVWEB.cms Content Block package.
 *
 * (c) Andrey Nilov <nilov@glavweb.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Glavweb\CmsContentBlock\Manager;

use Psr\Http\Message\ResponseInterface;
use Glavweb\CmsRestClient\CmsRestClient;

/**
 * Class AbstractContentManager
 *
 * @package Glavweb\CmsContentBlock
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
abstract class AbstractContentManager
{
    /**
     * Http status constants
     */
    const HTTP_OK              = 200;
    const HTTP_CREATED         = 201;
    const HTTP_PARTIAL_CONTENT = 206;

    /**
     * @var CmsRestClient
     */
    protected $restClient;

    /**
     * ContentBlockManager constructor.
     *
     * @param CmsRestClient $restClient
     */
    public function __construct(CmsRestClient $restClient)
    {
        $this->restClient = $restClient;
    }

    /**
     * @param ResponseInterface $response
     * @return int
     * @throws \Exception
     */
    protected function getMaxResultByResponse(ResponseInterface $response)
    {
        $contentRange = $response->getHeader('Content-Range');

        if (!isset($contentRange[0])) {
            throw new \Exception('Header "Content-Range" is not returned from API.');
        }

        $maxResult = explode('/', $contentRange[0])[1];

        return (int)$maxResult;
    }
}