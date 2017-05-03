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

/**
 * Class OptionManager
 *
 * @package Glavweb\CmsContentBlock
 * @author Andrey Nilov <nilov@glavweb.ru>
 */
class OptionManager extends AbstractContentManager
{
    /**
     * @var array
     */
    private static $optionsCache = null;

    /**
     * @param string $category
     * @param string $optionName
     * @param string $default
     * @return string
     */
    public function getOption($category, $optionName, $default = null)
    {
        $options = $this->getOptionListByCategory($category);
        $option  = isset($options[$optionName]) ? $options[$optionName] : null;

        // Create new option
        if ($option === null) {
            $default = (string)$default;
            $this->createOption($category, $optionName, $default);

            $optionValue = $default;

        } else {
            $optionValue = isset($option['value']) ? $option['value'] : '';
        }

        return $optionValue;
    }

    /**
     * Editable option
     *
     * @param string $category
     * @param string $name
     * @return string
     */
    public function editable($category, $name)
    {
        $attributes['data-option']          = 'true';
        $attributes['data-option-category'] = $category;
        $attributes['data-option-name']     = $name;

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
    public function getOptions()
    {
        if (self::$optionsCache === null) {
            self::$optionsCache = $this->doGetOptions();
        }

        return self::$optionsCache;
    }

    /**
     * @param array $options
     * @param int   $offset
     * @return array
     * @throws \Exception
     */
    private function doGetOptions($options = [], $offset = 0)
    {
        $limit = 1000;

        $response = $this->restClient->get('options', [
            'query' => [
                '_offset' => $offset,
                '_limit'  => $limit
            ]
        ]);
        $responseStatusCode = $response->getStatusCode();

        if ($responseStatusCode != self::HTTP_OK && $responseStatusCode != self::HTTP_PARTIAL_CONTENT) {
            throw new \Exception('Can not get options.');
        }

        $listArray = json_decode($response->getBody(), true);

        foreach ($listArray as $item) {
            $category = $item['category'];
            $name     =  $item['name'];

            $options[$category][$name] = $item;
        }

        $needAdditionalLoad =
            $responseStatusCode == self::HTTP_PARTIAL_CONTENT &&
            !empty($listArray) &&
            $this->getMaxResultByResponse($response) > $offset + $limit
        ;

        if ($needAdditionalLoad) {
            $options = $this->doGetOptions($options, $offset + $limit);
        }

        return $options;
    }

    /**
     * @param string $category
     * @return array
     * @throws \Exception
     */
    public function getOptionListByCategory($category)
    {
        $options = $this->getOptions();

        return isset($options[$category]) ? $options[$category] : [];
    }

    /**
     * @param string $category
     * @param string $name
     * @param string $value
     * @return string
     * @throws \Exception
     */
    private function createOption($category, $name, $value)
    {
        $response = $this->restClient->post('options', [
            'form_params' => [
                'category' => $category,
                'name'     => $name,
                'value'    => $value
            ]
        ], true);

        if (!$response->getStatusCode() == self::HTTP_CREATED) {
            throw new \Exception('Can not save option.');
        }

        $locationResponse = $response->getHeader('location');
        if (!isset($locationResponse[0])) {
            throw new \Exception('Location is not returned from API.');
        }

        // Set option in cache
        $location = $locationResponse[0];
        $option = $this->getOptionByLocation($location);

        self::$optionsCache[$category][$name] = $option;
    }

    /**
     * @param string $location
     * @return array
     * @throws \Exception
     */
    private function getOptionByLocation($location)
    {
        $response = $this->restClient->get($location);

        if (!$response->getStatusCode() == self::HTTP_OK) {
            throw new \Exception('Can not get option by "' . $location . '".');
        }

        $optionData = json_decode($response->getBody(), true);

        return $optionData;
    }
}
